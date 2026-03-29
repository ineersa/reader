<?php

declare(strict_types=1);

namespace App\Service\Reader;

final readonly class ReaderUtils
{
    public static function canonicalizeUrl(string $url): string
    {
        $trimmed = trim($url);
        if ('' === $trimmed) {
            return '';
        }

        $decoded = html_entity_decode($trimmed, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        $decoded = trim($decoded);

        if (!str_contains($decoded, '://')) {
            $decoded = 'https://'.$decoded;
        }

        $parts = parse_url($decoded);
        if (false === $parts || !isset($parts['host']) || '' === $parts['host']) {
            return $decoded;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) && '' !== $parts['query'] ? '?'.$parts['query'] : '';

        if ('' === $path) {
            $path = '/';
        } else {
            $path = self::normalizeUrlPath($path);
        }

        return $scheme.'://'.$host.$port.$path.$query;
    }

    public static function maybeTruncate(string $text, int $numChars = 1024): string
    {
        if (mb_strlen($text) > $numChars) {
            return mb_substr($text, 0, $numChars - 3).'...';
        }

        return $text;
    }

    public static function getDomain(string $url): string
    {
        if ('' === $url) {
            return '';
        }

        if (!str_contains($url, 'http')) {
            $url = 'http://'.$url;
        }

        $parts = parse_url($url);

        return \is_array($parts) ? ($parts['host'] ?? '') : '';
    }

    public static function ensureUtf8(string $html): string
    {
        if (!mb_detect_encoding($html, 'UTF-8', true)) {
            $html = mb_convert_encoding($html, 'UTF-8');
        }

        return $html;
    }

    private static function normalizeUrlPath(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return '/'.implode('/', $segments);
    }
}
