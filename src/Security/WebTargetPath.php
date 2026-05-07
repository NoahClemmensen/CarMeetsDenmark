<?php

namespace App\Security;

use function strlen;

/**
 * Utility class for validating and sanitizing target paths for web platform redirects.
 * Centralizes security validation to prevent open redirect vulnerabilities.
 */
final class WebTargetPath
{
    /**
     * Maximum allowed URL length to prevent DoS via large URLs.
     */
    private const MAX_URL_LENGTH = 2048;

    /**
     * Paths that should never be used as redirect targets.
     */
    private const EXCLUDED_PATH_PREFIXES = [
        '/login',
        '/logout',
    ];

    /**
     * Validate that a target path is safe for redirect.
     * Returns the validated path or null if invalid.
     *
     * Security checks:
     * - Must be a non-empty relative path (not just '/')
     * - Must not exceed maximum URL length
     * - Must not contain path traversal sequences (..)
     * - Must not contain protocol patterns that could lead to open redirects
     * - Must not be a login/logout path
     *
     * @param ?string $path
     */
    public static function validate(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        // Check URL length to prevent DoS
        if (strlen($path) > self::MAX_URL_LENGTH) {
            return null;
        }

        // Parse the URL to separate path from query string for validation
        $parsedUrl = parse_url($path);

        // If parsing fails or we have a scheme/host, reject it
        if ($parsedUrl === false || isset($parsedUrl['scheme']) || isset($parsedUrl['host'])) {
            return null;
        }

        $urlPath = $parsedUrl['path'] ?? '';

        // Must be a non-empty absolute path
        if ($urlPath === '' || $urlPath === '/') {
            return null;
        }

        // Reject path traversal attempts anywhere in the path or query string
        if (str_contains($path, '..')) {
            return null;
        }

        // Reject protocol-relative URLs (//example.com) anywhere in the string
        if (str_contains($path, '//')) {
            return null;
        }

        // Reject paths that could contain protocol patterns anywhere
        if (preg_match('#https?://#i', $path)) {
            return null;
        }

        // Don't allow login/logout paths as redirect targets
        foreach (self::EXCLUDED_PATH_PREFIXES as $excluded) {
            if ($urlPath === $excluded || str_starts_with($urlPath, $excluded . '/')) {
                return null;
            }
        }

        return $path;
    }
}
