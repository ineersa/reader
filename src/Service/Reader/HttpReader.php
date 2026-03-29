<?php

declare(strict_types=1);

namespace App\Service\Reader;

use App\Service\Reader\Exception\BackendError;
use App\Service\Reader\Exception\ToolUsageError;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpReader
{
    /**
     * @param string[] $noiseClassTokens
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly array $noiseClassTokens = [],
        private readonly ?CacheInterface $cache = null,
        private readonly int $cacheTtlSeconds = 600,
    ) {
    }

    /**
     * @throws BackendError
     * @throws ToolUsageError
     */
    public function read(string $url): ReadDocument
    {
        $requestedUrl = trim($url);
        $canonicalUrl = ReaderUtils::canonicalizeUrl($requestedUrl);

        if (null === $this->cache) {
            return $this->doRead($requestedUrl, $canonicalUrl);
        }

        $cacheKey = 'read_document.'.hash('sha256', $canonicalUrl);

        try {
            /** @var ReadDocument $document */
            $document = $this->cache->get($cacheKey, function (ItemInterface $item) use ($requestedUrl, $canonicalUrl): ReadDocument {
                $item->expiresAfter($this->cacheTtlSeconds);

                return $this->doRead($requestedUrl, $canonicalUrl);
            });
        } catch (ToolUsageError|BackendError $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw (new BackendError(\sprintf('Error fetching URL `%s`: %s', ReaderUtils::maybeTruncate($canonicalUrl, 256), ReaderUtils::maybeTruncate($e->getMessage()))))->setHint('This may be a network timeout, server error, or the URL may be inaccessible. Try retrying the request or check if the URL is valid and accessible.');
        }

        return $document;
    }

    /**
     * @return array{title:string,markdown:string}
     *
     * @throws BackendError
     * @throws ToolUsageError
     */
    private function fetch(string $url, string $requestedUrl): array
    {
        $githubPage = $this->tryFetchGithubContent($url, $requestedUrl);
        if (null !== $githubPage) {
            return $githubPage;
        }

        $html = $this->httpGet($url);

        return [
            'title' => $this->extractTitle($html, $url),
            'markdown' => PageProcessor::processHtml(
                html: $html,
                url: $url,
                title: null,
                displayUrls: true,
                noiseClassTokens: $this->noiseClassTokens,
            ),
        ];
    }

    /**
     * @throws BackendError
     * @throws ToolUsageError
     */
    private function doRead(string $url, string $canonicalUrl): ReadDocument
    {
        $page = $this->fetch($canonicalUrl, $url);

        return new ReadDocument(
            url: $url,
            canonicalUrl: $canonicalUrl,
            title: $page['title'],
            markdown: $page['markdown'],
        );
    }

    /**
     * @throws BackendError
     * @throws ToolUsageError
     */
    private function httpGet(string $url): string
    {
        try {
            $response = $this->client->request('GET', $url);

            return $response->getContent();
        } catch (TransportExceptionInterface $e) {
            if ($this->isBlockedPrivateNetworkException($e)) {
                throw (new ToolUsageError('Internal URLs are not allowed.'))->setHint('Use a public URL that resolves to a public IP address.');
            }

            throw (new BackendError(\sprintf('HTTP error for %s: %s', $url, ReaderUtils::maybeTruncate($e->getMessage(), 500)), previous: $e))->setHint('This may be a network timeout, server error, or the URL may be inaccessible. Try retrying the request or check if the URL is valid and the server is responding.');
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            throw (new BackendError(\sprintf('HTTP error for %s: %s', $url, ReaderUtils::maybeTruncate($e->getMessage(), 500)), previous: $e))->setHint('This may be a network timeout, server error, or the URL may be inaccessible. Try retrying the request or check if the URL is valid and the server is responding.');
        }
    }

    /**
     * @return array{title:string,markdown:string}|null
     *
     * @throws BackendError
     * @throws ToolUsageError
     */
    private function tryFetchGithubContent(string $url, string $requestedUrl): ?array
    {
        $host = strtolower((string) parse_url($url, \PHP_URL_HOST));
        if ('github.com' === $host) {
            return $this->fetchGithubBlobAsRaw($url, $requestedUrl);
        }

        if ('raw.githubusercontent.com' === $host) {
            $rawContent = $this->httpGet($url);

            return $this->makePlainTextPage($rawContent, $requestedUrl, $this->makeGithubTitle($url));
        }

        return null;
    }

    /**
     * @return array{title:string,markdown:string}|null
     */
    private function fetchGithubBlobAsRaw(string $url, string $requestedUrl): ?array
    {
        $rawInfo = $this->makeGithubRawUrl($url);
        if (null === $rawInfo) {
            return null;
        }

        try {
            $rawContent = $this->httpGet($rawInfo['raw_url']);
        } catch (BackendError) {
            return null;
        }

        return $this->makePlainTextPage($rawContent, $requestedUrl, $this->makeGithubTitle($rawInfo['raw_url']));
    }

    /**
     * @return array{title:string,markdown:string}
     */
    private function makePlainTextPage(string $content, string $url, ?string $title): array
    {
        $text = ReaderUtils::ensureUtf8($content);
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = rtrim($normalized, "\n");

        $fileName = $this->getFileNameFromUrl($url);
        $isMarkdown = $this->isMarkdownFile($fileName);
        $language = $isMarkdown ? null : $this->guessLanguageFromFileName($fileName);

        $header = "\nURL: $url\n\n";
        if ($isMarkdown) {
            $body = $normalized;
        } else {
            $fence = '```'.($language ?? '');
            $body = $fence."\n".$normalized."\n```";
        }

        return [
            'title' => $title ?? $url,
            'markdown' => $header.$body,
        ];
    }

    /**
     * @return array{raw_url:string,file_name:string}|null
     */
    private function makeGithubRawUrl(string $url): ?array
    {
        $parts = parse_url($url);
        if (!\is_array($parts)) {
            return null;
        }

        $path = $parts['path'] ?? '';
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => '' !== $segment));
        if (\count($segments) < 4) {
            return null;
        }

        $type = strtolower($segments[2]);
        if (!\in_array($type, ['blob', 'raw'], true)) {
            return null;
        }

        $owner = rawurlencode($segments[0]);
        $repo = rawurlencode($segments[1]);
        $tailSegments = \array_slice($segments, 3);
        $encodedTail = array_map(static fn (string $segment): string => rawurlencode($segment), $tailSegments);

        return [
            'raw_url' => 'https://raw.githubusercontent.com/'.$owner.'/'.$repo.'/'.implode('/', $encodedTail),
            'file_name' => $tailSegments[\count($tailSegments) - 1] ?? '',
        ];
    }

    private function makeGithubTitle(string $url): ?string
    {
        $parts = parse_url($url);
        if (!\is_array($parts)) {
            return null;
        }

        $path = trim($parts['path'] ?? '', '/');
        if ('' === $path) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => '' !== $segment));
        if ('github.com' === $host && isset($segments[2]) && \in_array(strtolower($segments[2]), ['blob', 'raw'], true)) {
            $segments = array_merge([$segments[0], $segments[1]], \array_slice($segments, 3));
        }

        $decoded = array_map(static fn (string $segment): string => urldecode($segment), $segments);
        $filtered = array_filter($decoded, static fn (string $segment): bool => '' !== trim($segment));

        return [] === $filtered ? null : implode('/', $filtered);
    }

    private function getFileNameFromUrl(string $url): string
    {
        $parsedPath = parse_url($url, \PHP_URL_PATH);
        $path = \is_string($parsedPath) ? $parsedPath : '';

        return '' === $path ? '' : basename($path);
    }

    private function isMarkdownFile(string $fileName): bool
    {
        $lower = strtolower($fileName);
        if (\in_array($lower, ['readme', 'readme.md', 'readme.markdown'], true)) {
            return true;
        }

        $extension = strtolower(pathinfo($lower, \PATHINFO_EXTENSION));

        return '' !== $extension && \in_array($extension, ['md', 'markdown', 'mdown', 'mkd', 'mkdn'], true);
    }

    private function guessLanguageFromFileName(string $fileName): ?string
    {
        $lower = strtolower($fileName);
        $special = [
            'dockerfile' => 'dockerfile',
            'makefile' => 'makefile',
            'cmakelists.txt' => 'cmake',
        ];
        if (isset($special[$lower])) {
            return $special[$lower];
        }

        $extension = strtolower(pathinfo($lower, \PATHINFO_EXTENSION));
        if ('' === $extension) {
            return null;
        }

        $map = [
            'php' => 'php',
            'ts' => 'ts',
            'tsx' => 'tsx',
            'js' => 'js',
            'jsx' => 'jsx',
            'json' => 'json',
            'py' => 'python',
            'rb' => 'ruby',
            'go' => 'go',
            'rs' => 'rust',
            'java' => 'java',
            'c' => 'c',
            'h' => 'c',
            'hpp' => 'cpp',
            'hh' => 'cpp',
            'cpp' => 'cpp',
            'cc' => 'cpp',
            'cxx' => 'cpp',
            'cs' => 'csharp',
            'swift' => 'swift',
            'kt' => 'kotlin',
            'kts' => 'kotlin',
            'sh' => 'bash',
            'bash' => 'bash',
            'zsh' => 'bash',
            'ps1' => 'powershell',
            'psm1' => 'powershell',
            'sql' => 'sql',
            'yaml' => 'yaml',
            'yml' => 'yaml',
            'toml' => 'toml',
            'ini' => 'ini',
            'vue' => 'vue',
            'svelte' => 'svelte',
            'xml' => 'xml',
            'html' => 'html',
            'htm' => 'html',
            'css' => 'css',
            'scss' => 'scss',
            'less' => 'less',
            'lua' => 'lua',
            'r' => 'r',
            'pl' => 'perl',
            'pm' => 'perl',
            'bat' => 'batch',
            'groovy' => 'groovy',
            'gradle' => 'gradle',
            'graphql' => 'graphql',
            'lock' => 'json',
        ];

        return $map[$extension] ?? null;
    }

    private function isBlockedPrivateNetworkException(TransportExceptionInterface $e): bool
    {
        return 1 === preg_match('/^(Host|IP) ".+" is blocked for ".+"\.$/', $e->getMessage());
    }

    private function extractTitle(string $html, string $url): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.ReaderUtils::ensureUtf8($html));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return ReaderUtils::getDomain($url);
        }

        $xpath = new \DOMXPath($dom);
        $titleNode = $xpath->query('//title')->item(0);

        $title = $titleNode?->textContent;
        if (null === $title) {
            return ReaderUtils::getDomain($url);
        }

        $title = trim($title);

        return '' !== $title ? $title : ReaderUtils::getDomain($url);
    }
}
