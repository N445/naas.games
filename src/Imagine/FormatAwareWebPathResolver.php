<?php

namespace App\Imagine;

use Liip\ImagineBundle\Imagine\Cache\Resolver\WebPathResolver;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RequestContext;

/**
 * Liip keeps the source extension on cached files, so a WebP rendered from a PNG is stored as
 * ".png" and the web server announces image/png. Appending the output format fixes the Content-Type
 * and keeps "a.png" and "a.jpg" from colliding in the cache.
 */
#[AutoconfigureTag('liip_imagine.cache.resolver', ['resolver' => self::NAME])]
final class FormatAwareWebPathResolver extends WebPathResolver
{
    public const string NAME = 'format_aware';

    public function __construct(
        Filesystem $filesystem,
        #[Autowire(service: 'router.request_context')] RequestContext $requestContext,
        #[Autowire(service: 'liip_imagine.filter.configuration')] private readonly FilterConfiguration $filters,
        #[Autowire('%kernel.project_dir%/public')] string $webRootDir,
    ) {
        parent::__construct($filesystem, $requestContext, $webRootDir, 'media/cache');
    }

    protected function getFilePath($path, $filter)
    {
        return parent::getFilePath($this->withOutputExtension($path, $filter), $filter);
    }

    protected function getFileUrl($path, $filter)
    {
        return parent::getFileUrl($this->withOutputExtension($path, $filter), $filter);
    }

    private function withOutputExtension(string $path, string $filter): string
    {
        $format = strtolower((string) ($this->filters->get($filter)['format'] ?? ''));
        $format = 'jpeg' === $format ? 'jpg' : $format;
        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
        $extension = 'jpeg' === $extension ? 'jpg' : $extension;

        return '' === $format || $format === $extension ? $path : $path.'.'.$format;
    }
}
