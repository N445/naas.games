<?php

namespace App\Seo;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps non-HTML-page routes (fragments, login, admin, downloads) out of search indexes.
 * Pages with a <head> declare their robots policy in templates/base.html.twig instead.
 */
#[AsEventListener(KernelEvents::RESPONSE)]
final class RobotsTagListener
{
    private const array NOINDEX_ROUTES = [
        'APP_LOGIN',
        'APP_LOGOUT',
        'APP_GALLERY_RESULTS',
        'APP_GALLERY_MORE',
        'APP_GALLERY_SCREENSHOT',
        'APP_SCREENSHOT_DOWNLOAD',
        'ux_live_component',
    ];

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $route = (string) $event->getRequest()->attributes->get('_route');
        $base = preg_replace('/\.(en|fr)$/', '', $route);
        if (in_array($base, self::NOINDEX_ROUTES, true) || str_starts_with($base, 'APP_ADMIN')) {
            $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
    }
}
