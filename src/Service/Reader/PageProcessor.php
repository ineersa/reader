<?php

declare(strict_types=1);

namespace App\Service\Reader;

use Ineersa\Html2text\Config;
use Ineersa\Html2text\HTML2Markdown;

final class PageProcessor
{
    private const HTML_SUP_RE = '/<sup( [^>]*)?>([\w\-]+)<\/sup>/u';
    private const HTML_SUB_RE = '/<sub( [^>]*)?>([\w\-]+)<\/sub>/u';
    private const HTML_TAGS_SEQUENCE_RE = '/(?<=\w)((<[^>]*>)+)(?=\w)/u';
    private const MIN_CONTENT_TEXT_LEN = 120;
    private const POSITIVE_HINT_RE = '/\b(article|content|entry|main|post|markdown|doc|documentation|readme|page-body|prose)\b/i';
    private const NEGATIVE_HINT_RE = '/\b(nav|navbar|menu|footer|header|sidebar|aside|related|share|social|cookie|banner|popup|modal|breadcrumb|pagination|ads?|advert|promo|subscribe)\b/i';

    /** @var string[] */
    private const DEFAULT_NOISE_CLASS_TOKENS = ['codeblock-lines', 'linenos', 'line-numbers', 'gutter'];

    /**
     * @param string[] $noiseClassTokens
     */
    public static function processHtml(string $html, string $url, ?string $title, bool $displayUrls = false, array $noiseClassTokens = []): string
    {
        $html = self::removeUnicodeSmp($html);
        $html = self::replaceSpecialChars($html);
        $html = (string) preg_replace(self::HTML_SUP_RE, '^{\\2}', $html);
        $html = (string) preg_replace(self::HTML_SUB_RE, '_{\\2}', $html);
        $html = (string) preg_replace(self::HTML_TAGS_SEQUENCE_RE, ' \1', $html);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.ReaderUtils::ensureUtf8($html));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            $text = self::normalizeText(self::htmlToText($html));

            return ($displayUrls ? "\nURL: $url\n" : '').$text;
        }

        $xpath = new \DOMXPath($dom);
        $root = self::pickContentRoot($xpath);
        if ($root instanceof \DOMElement) {
            self::removeBoilerplate($xpath, $root);
            self::removeKnownNoise($xpath, $root, $noiseClassTokens);
            self::absolutizeLinks($xpath, $root, $url);
        }

        self::replaceImages($dom, $xpath);
        self::removeMath($dom, $xpath);

        if ($root instanceof \DOMElement) {
            $rootHtml = $dom->saveHTML($root);
            $cleanHtml = false === $rootHtml ? '' : $rootHtml;
        } else {
            $documentHtml = $dom->saveHTML();
            $cleanHtml = false === $documentHtml ? '' : $documentHtml;
        }

        $text = self::normalizeText(self::htmlToText($cleanHtml));

        return ($displayUrls ? "\nURL: $url\n" : '').$text;
    }

    private static function pickContentRoot(\DOMXPath $xpath): ?\DOMElement
    {
        $body = $xpath->query('//body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $candidates = $xpath->query('//main | //article | //section | //div');
        if (!$candidates) {
            return $body;
        }

        $bestNode = null;
        $bestScore = 0.0;
        foreach ($candidates as $candidate) {
            if (!$candidate instanceof \DOMElement) {
                continue;
            }

            $score = self::scoreCandidate($xpath, $candidate);
            if ($score > $bestScore) {
                $bestNode = $candidate;
                $bestScore = $score;
            }
        }

        return $bestNode ?? $body;
    }

    private static function replaceImages(\DOMDocument $dom, \DOMXPath $xpath): void
    {
        $nodes = $xpath->query('//img');
        if (!$nodes) {
            return;
        }

        foreach ($nodes as $img) {
            if (!$img instanceof \DOMElement) {
                continue;
            }

            $name = $img->getAttribute('alt');
            if ('' === $name) {
                $name = $img->getAttribute('title');
            }

            $replacement = '' !== $name ? \sprintf('[Image: %s]', $name) : '[Image]';
            self::replaceNodeWithText($dom, $img, $replacement);
        }
    }

    private static function removeMath(\DOMDocument $dom, \DOMXPath $xpath): void
    {
        $nodes = $xpath->query('//*[local-name()="math"]');
        if (!$nodes) {
            return;
        }

        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private static function removeBoilerplate(\DOMXPath $xpath, \DOMElement $root): void
    {
        $nodes = $xpath->query('.//*', $root);
        if (!$nodes) {
            return;
        }

        $toRemove = [];
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            if (self::isBoilerplateNode($xpath, $node)) {
                $toRemove[] = $node;
            }
        }

        foreach ($toRemove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * @param string[] $noiseClassTokens
     */
    private static function removeKnownNoise(\DOMXPath $xpath, \DOMElement $root, array $noiseClassTokens): void
    {
        $tokens = self::normalizeNoiseClassTokens($noiseClassTokens);
        if ([] === $tokens) {
            return;
        }

        $conditions = array_map(
            static fn (string $token): string => \sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $token),
            $tokens,
        );
        $query = './/*[self::pre or self::div or self::span]['.implode(' or ', $conditions).']';
        $nodes = $xpath->query($query, $root);
        if (!$nodes) {
            return;
        }

        $toRemove = [];
        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $toRemove[] = $node;
            }
        }

        foreach ($toRemove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * @param string[] $noiseClassTokens
     *
     * @return string[]
     */
    private static function normalizeNoiseClassTokens(array $noiseClassTokens): array
    {
        $tokens = [];
        foreach (array_merge(self::DEFAULT_NOISE_CLASS_TOKENS, $noiseClassTokens) as $token) {
            $normalized = strtolower(trim($token));
            if ('' === $normalized) {
                continue;
            }

            $tokens[] = $normalized;
        }

        return array_values(array_unique($tokens));
    }

    private static function isBoilerplateNode(\DOMXPath $xpath, \DOMElement $node): bool
    {
        $tagName = strtolower($node->tagName);
        if (\in_array($tagName, ['script', 'style', 'noscript', 'template', 'iframe', 'svg', 'canvas', 'nav', 'footer', 'header', 'aside', 'form', 'button'], true)) {
            return true;
        }

        $className = strtolower(trim($node->getAttribute('class')));
        $id = strtolower(trim($node->getAttribute('id')));
        $role = strtolower(trim($node->getAttribute('role')));
        $ariaLabel = strtolower(trim($node->getAttribute('aria-label')));
        $hints = trim(implode(' ', array_filter([$className, $id, $role, $ariaLabel], static fn (string $value): bool => '' !== $value)));
        if ('' === $hints || 1 !== preg_match(self::NEGATIVE_HINT_RE, $hints)) {
            return false;
        }

        if (!\in_array($tagName, ['div', 'section', 'ul', 'ol', 'li'], true)) {
            return false;
        }

        if (self::containsLikelyMainContent($xpath, $node)) {
            return false;
        }

        return true;
    }

    private static function containsLikelyMainContent(\DOMXPath $xpath, \DOMElement $node): bool
    {
        $mainNodes = $xpath->query('.//main | .//article', $node);
        if ($mainNodes && $mainNodes->length > 0) {
            return true;
        }

        $textLength = mb_strlen(self::mergeWhitespace($node->textContent ?? ''));
        if ($textLength < 600) {
            return false;
        }

        $paragraphs = $xpath->query('.//p', $node);
        $headings = $xpath->query('.//h1 | .//h2 | .//h3', $node);

        $paragraphCount = $paragraphs instanceof \DOMNodeList ? $paragraphs->length : 0;
        $headingCount = $headings instanceof \DOMNodeList ? $headings->length : 0;

        return $paragraphCount >= 3 || $headingCount >= 1;
    }

    private static function scoreCandidate(\DOMXPath $xpath, \DOMElement $candidate): float
    {
        $text = self::mergeWhitespace($candidate->textContent ?? '');
        $textLength = mb_strlen($text);
        if ($textLength < self::MIN_CONTENT_TEXT_LEN) {
            return 0.0;
        }

        $tagName = strtolower($candidate->tagName);
        $score = (float) $textLength;
        if ('main' === $tagName || 'article' === $tagName) {
            $score += 450.0;
        }

        $className = strtolower(trim($candidate->getAttribute('class')));
        $id = strtolower(trim($candidate->getAttribute('id')));
        $hints = trim($className.' '.$id);
        if ('' !== $hints && 1 === preg_match(self::POSITIVE_HINT_RE, $hints)) {
            $score += 300.0;
        }
        if ('' !== $hints && 1 === preg_match(self::NEGATIVE_HINT_RE, $hints)) {
            $score -= 400.0;
        }

        $links = $xpath->query('.//a', $candidate);
        $linkTextLength = 0;
        if ($links) {
            foreach ($links as $link) {
                if (!$link instanceof \DOMElement) {
                    continue;
                }

                $linkTextLength += mb_strlen(self::mergeWhitespace($link->textContent ?? ''));
            }
        }

        $linkDensity = $linkTextLength / $textLength;
        $score -= min(0.9, $linkDensity) * 500.0;

        return $score;
    }

    private static function absolutizeLinks(\DOMXPath $xpath, \DOMElement $root, string $baseUrl): void
    {
        $nodes = $xpath->query('.//a[@href]', $root);
        if (!$nodes) {
            return;
        }

        foreach ($nodes as $anchor) {
            if (!$anchor instanceof \DOMElement || !$anchor->hasAttribute('href')) {
                continue;
            }

            $href = trim($anchor->getAttribute('href'));
            if (
                '' === $href
                || str_starts_with($href, '#')
                || str_starts_with($href, 'mailto:')
                || str_starts_with($href, 'javascript:')
                || str_starts_with($href, 'tel:')
                || str_starts_with($href, 'data:')
            ) {
                continue;
            }

            $anchor->setAttribute('href', self::urlJoin($baseUrl, $href));
        }
    }

    private static function replaceNodeWithText(\DOMDocument $dom, \DOMNode $node, string $text): void
    {
        $textNode = $dom->createTextNode($text);
        $node->parentNode?->insertBefore($textNode, $node);
        $node->parentNode?->removeChild($node);
    }

    private static function htmlToText(string $html): string
    {
        $config = new Config(
            bodyWidth: 0,
            unicodeSnob: true,
            inlineLinks: true,
            skipInternalLinks: true,
            wrapLinks: false,
            wrapListItems: false,
            wrapTables: false,
            padTables: false,
            useAutomaticLinks: true,
            backquoteCodeStyle: true,
            imagesToAlt: true,
            ulItemMark: '-',
            emphasisMark: '*',
        );

        try {
            $converter = new HTML2Markdown($config);
            $text = $converter->convert(ReaderUtils::ensureUtf8($html));
        } catch (\Throwable) {
            $text = strip_tags($html);
        }

        return trim($text);
    }

    private static function removeEmptyLines(string $text): string
    {
        return (string) preg_replace('/^\s+$/m', '', $text);
    }

    private static function collapseExtraNewlines(string $text): string
    {
        return (string) preg_replace("/\n(\s*\n)+/", "\n\n", $text);
    }

    private static function normalizeText(string $text): string
    {
        $text = self::removeEmptyLines($text);
        $text = self::collapseExtraNewlines($text);
        $text = self::normalizeTrailingWhitespace($text);
        $text = self::unescapeMarkdownArtifacts($text);

        return trim($text);
    }

    private static function normalizeTrailingWhitespace(string $text): string
    {
        $lines = explode("\n", $text);
        $lastIndex = \count($lines) - 1;
        for ($i = 0; $i <= $lastIndex; ++$i) {
            if (!preg_match('/[ \t]+$/', $lines[$i])) {
                continue;
            }

            $trimmed = rtrim($lines[$i], " \t");
            if (str_ends_with($trimmed, '>')) {
                continue;
            }

            $currentIndent = strspn($lines[$i], " \t");
            $nextIndent = null;
            for ($j = $i + 1; $j <= $lastIndex; ++$j) {
                if ('' === $lines[$j]) {
                    continue;
                }

                $nextIndent = strspn($lines[$j], " \t");
                break;
            }

            if (null !== $nextIndent && $nextIndent > $currentIndent) {
                continue;
            }

            $lines[$i] = rtrim($lines[$i], " \t");
        }

        return implode("\n", $lines);
    }

    private static function unescapeMarkdownArtifacts(string $text): string
    {
        return (string) preg_replace('/(?<=\d)\\\./', '.', $text);
    }

    private static function replaceSpecialChars(string $text): string
    {
        $replacements = [
            '◼' => '◾',
            "\u{200B}" => '',
            "\u{00A0}" => ' ',
        ];

        return strtr($text, $replacements);
    }

    private static function mergeWhitespace(string $text): string
    {
        $text = str_replace("\n", ' ', $text);
        $text = (string) preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    private static function removeUnicodeSmp(string $text): string
    {
        return (string) preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);
    }

    private static function urlJoin(string $base, string $relative): string
    {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\\//', $relative)) {
            return $relative;
        }

        if ('' === $base) {
            return $relative;
        }

        $parts = parse_url($base);
        if (!\is_array($parts)) {
            return $relative;
        }

        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';

        if (str_starts_with($relative, '/')) {
            $newPath = self::normalizePath($relative);
        } else {
            $baseDirectory = rtrim(substr($path, 0, strrpos($path.'/', '/') + 1), '/');
            $newPath = self::normalizePath($baseDirectory.'/'.$relative);
        }

        return $scheme.'://'.$host.$port.$newPath;
    }

    private static function normalizePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                array_pop($parts);
                continue;
            }

            $parts[] = $segment;
        }

        return '/'.implode('/', $parts);
    }
}
