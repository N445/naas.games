<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\UX\Icons\Icon;
use Symfony\UX\Icons\Iconify;

#[AdminRoute('/icons', name: 'icon_picker')]
final class IconPickerController extends AbstractController
{
    private const int CACHE_SECONDS = 604800;
    private const int MAX_RESULTS = 96;
    private const string ICON_PATTERN = '/^([a-z0-9-]+):([a-z0-9-]+)$/';

    public const array SUGGESTIONS = [
        'fa7-brands:discord', 'fa7-brands:twitch', 'fa7-brands:youtube', 'fa7-brands:x-twitter', 'fa7-brands:bluesky',
        'fa7-brands:instagram', 'fa7-brands:tiktok', 'fa7-brands:threads', 'fa7-brands:mastodon', 'fa7-brands:facebook',
        'fa7-brands:reddit', 'fa7-brands:steam', 'fa7-brands:flickr', 'fa7-brands:artstation', 'fa7-brands:deviantart',
        'fa7-brands:patreon', 'fa7-brands:paypal', 'fa7-brands:spotify', 'fa7-brands:github', 'fa7-brands:linkedin',
        'simple-icons:kick', 'simple-icons:kofi', 'fa7-solid:globe',
        'fa7-solid:link', 'fa7-solid:envelope', 'fa7-solid:rocket', 'fa7-solid:camera', 'fa7-solid:images',
        'fa7-solid:gamepad', 'fa7-solid:video', 'fa7-solid:users', 'fa7-solid:star', 'fa7-solid:heart',
    ];

    public function __construct(
        #[Autowire(service: '.ux_icons.iconify')]
        private readonly Iconify $iconify,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface&CacheInterface $cache,
    ) {
    }

    #[AdminRoute('/search', name: 'search', options: ['methods' => ['GET']])]
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->query->getString('q'));
        $prefixes = array_filter(explode(',', $request->query->getString('prefixes')));
        $icons = mb_strlen($query) < 2
            ? $this->suggestionsFor($prefixes)
            : array_slice(array_merge([], ...array_map(fn (string $prefix): array => $this->searchIn($prefix, $query), $prefixes)), 0, self::MAX_RESULTS);
        $this->warmSvgCache($icons);

        return $this->json(['icons' => $icons]);
    }

    #[AdminRoute('/svg', name: 'svg', options: ['methods' => ['GET']])]
    public function svg(Request $request): Response
    {
        if (!preg_match(self::ICON_PATTERN, $request->query->getString('name'), $parts)) {
            throw $this->createNotFoundException();
        }

        $svg = $this->cache->get(self::svgCacheKey($request->query->getString('name')), function (ItemInterface $item) use ($parts): string {
            $item->expiresAfter(self::CACHE_SECONDS);

            return self::standalone($this->iconify->fetchIcon($parts[1], $parts[2]));
        });

        return new Response($svg, Response::HTTP_OK, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age='.self::CACHE_SECONDS]);
    }

    /**
     * @param list<string> $icons
     */
    private function warmSvgCache(array $icons): void
    {
        $missing = array_filter($icons, fn (string $icon): bool => !$this->cache->hasItem(self::svgCacheKey($icon)));
        $byPrefix = [];
        foreach ($missing as $icon) {
            [$prefix, $name] = explode(':', $icon, 2);
            $byPrefix[$prefix][] = $name;
        }

        foreach ($byPrefix as $prefix => $names) {
            $this->cacheBatch($prefix, $names);
        }
    }

    /**
     * @param list<string> $names
     */
    private function cacheBatch(string $prefix, array $names): void
    {
        try {
            $fetched = $this->iconify->fetchIcons($prefix, $names);
        } catch (\Throwable) {
            return;
        }

        foreach ($fetched as $name => $icon) {
            $item = $this->cache->getItem(self::svgCacheKey($prefix.':'.$name));
            $this->cache->save($item->set(self::standalone($icon))->expiresAfter(self::CACHE_SECONDS));
        }
    }

    private static function svgCacheKey(string $icon): string
    {
        return 'icon_picker_svg_'.str_replace(':', '_', $icon);
    }

    private static function standalone(Icon $icon): string
    {
        return $icon->withAttributes(['xmlns' => 'http://www.w3.org/2000/svg'])->toHtml();
    }

    /**
     * @param list<string> $prefixes
     *
     * @return list<string>
     */
    private function suggestionsFor(array $prefixes): array
    {
        return array_values(array_filter(self::SUGGESTIONS, static fn (string $icon): bool => [] === $prefixes || in_array(explode(':', $icon)[0], $prefixes, true)));
    }

    /**
     * @return list<string>
     */
    private function searchIn(string $prefix, string $query): array
    {
        return $this->cache->get('icon_picker_search_'.md5($prefix.'|'.mb_strtolower($query)), function (ItemInterface $item) use ($prefix, $query): array {
            $item->expiresAfter(self::CACHE_SECONDS);

            return array_values($this->iconify->searchIcons($prefix, $query)['icons'] ?? []);
        });
    }
}
