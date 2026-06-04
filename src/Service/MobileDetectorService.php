<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Best-effort User-Agent sniff to detect mobile clients.
 *
 * Intended for *coarse* routing decisions like "redirect mobile users
 * to the app download page", NOT for layout. Use CSS media queries
 * for that.
 */
readonly class MobileDetectorService
{
    public const array MOBILE_KEYWORDS = [
        'Mobile',
        'Android',
        'iPhone',
        'iPad',
        'iPod',
        'webOS',
        'BlackBerry',
        'Opera Mini',
        'IEMobile',
    ];

    public function isMobile(Request $request): bool
    {
        $userAgent = $request->headers->get('User-Agent', '');

        foreach (self::MOBILE_KEYWORDS as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
