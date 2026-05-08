<?php

namespace App\Security;

use function strlen;

/**
 * Validates user-supplied paths before using them as redirect targets.
 *
 * Centralized here so every redirect that consumes an untrusted value
 * (query strings, session-stored "return-to" URIs, form fields) goes
 * through the same checks. Preventing open-redirects is the goal.
 *
 * Returns the input path if it passes, or null if it fails — never
 * mutates / sanitises silently.
 *
 * Accepted: any non-empty relative path (e.g. "/web/home", "/x?y=1").
 * Rejected: absolute URLs, protocol-relative URLs, paths containing
 * "..", paths over MAX_URL_LENGTH, and login/logout paths (which
 * would defeat the redirect's intent).
 *
 */
final class WebTargetPath
{
    private const MAX_URL_LENGTH = 2048;

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

        if (strlen($path) > self::MAX_URL_LENGTH) {
            return null;
        }

        $parsedUrl = parse_url($path);

        if ($parsedUrl === false || isset($parsedUrl['scheme']) || isset($parsedUrl['host'])) {
            return null;
        }

        $urlPath = $parsedUrl['path'] ?? '';

        if ($urlPath === '' || $urlPath === '/') {
            return null;
        }

        if (str_contains($path, '..')) {
            return null;
        }

        if (str_contains($path, '//')) {
            return null;
        }

        if (preg_match('#https?://#i', $path)) {
            return null;
        }

        foreach (self::EXCLUDED_PATH_PREFIXES as $excluded) {
            if ($urlPath === $excluded || str_starts_with($urlPath, $excluded . '/')) {
                return null;
            }
        }

        return $path;
    }
}
