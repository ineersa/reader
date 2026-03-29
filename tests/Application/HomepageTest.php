<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Service\Reader\Exception\BackendError;
use App\Service\Reader\HttpReader;
use App\Service\Reader\ReadDocument;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomepageTest extends WebTestCase
{
    public function testHomepageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'reader');
        self::assertPageTitleContains('reader');
        self::assertStringContainsString('Turn any web page into LLM-ready markdown.', $client->getResponse()->getContent() ?? '');
    }

    public function testReadRouteReturnsTurboFrameWithMarkdownPayload(): void
    {
        $client = $this->createClientWithReader(
            $this->createSuccessfulReader(markdown: "# Hello\n\n- item one\n- item two"),
            '10.0.0.2',
            ['HTTP_TURBO_FRAME' => 'reader-result'],
        );

        $client->request('GET', '/read?url=https://8.8.8.8');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<turbo-frame id="reader-result">', $client->getResponse()->getContent() ?? '');
        self::assertStringContainsString('Test page', $client->getResponse()->getContent() ?? '');
        self::assertStringContainsString('# Hello', $client->getResponse()->getContent() ?? '');
        self::assertStringContainsString('data-controller="markdown-view"', $client->getResponse()->getContent() ?? '');
    }

    public function testRawRouteReturnsMarkdown(): void
    {
        $client = $this->createClientWithReader(
            $this->createSuccessfulReader(markdown: "# Hello\n\nBody"),
            '10.0.0.3',
        );

        $client->request('GET', '/r/https://8.8.8.8');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/plain; charset=UTF-8');
        self::assertSame("# Hello\n\nBody", $client->getResponse()->getContent());
    }

    public function testInvalidUrlShowsValidationError(): void
    {
        $client = static::createClient(server: ['REMOTE_ADDR' => '10.0.0.4']);
        static::getContainer()->get('cache.app')->clear();

        $client->request('GET', '/read?url=not-a-valid-url', server: ['HTTP_TURBO_FRAME' => 'reader-result']);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Invalid URL provided.', $client->getResponse()->getContent() ?? '');
    }

    public function testUnsupportedUrlSchemeShowsValidationError(): void
    {
        $client = static::createClient(server: ['REMOTE_ADDR' => '10.0.0.7']);
        static::getContainer()->get('cache.app')->clear();

        $client->request('GET', '/read?url=ftp://example.com/file.txt', server: ['HTTP_TURBO_FRAME' => 'reader-result']);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Invalid URL provided.', $client->getResponse()->getContent() ?? '');
    }

    public function testInternalUrlShowsValidationError(): void
    {
        $client = static::createClient(server: ['REMOTE_ADDR' => '10.0.0.8']);
        static::getContainer()->get('cache.app')->clear();

        $client->request('GET', '/read?url=http://127.0.0.1/private', server: ['HTTP_TURBO_FRAME' => 'reader-result']);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Internal URLs are not allowed.', $client->getResponse()->getContent() ?? '');
    }

    public function testInvalidRequestsDoNotConsumeRateLimit(): void
    {
        $client = static::createClient(server: ['REMOTE_ADDR' => '10.0.0.9']);
        static::getContainer()->get('cache.app')->clear();

        for ($i = 0; $i < 12; ++$i) {
            $client->request('GET', '/read?url=not-a-valid-url', server: ['HTTP_TURBO_FRAME' => 'reader-result']);
            self::assertResponseStatusCodeSame(422);
        }
    }

    public function testReaderRateLimitTriggersAfterTenRequests(): void
    {
        $client = $this->createClientWithReader(
            $this->createSuccessfulReader(),
            '10.0.0.5',
        );

        for ($i = 0; $i < 10; ++$i) {
            $client->request('GET', '/r/https://8.8.8.8');
            self::assertResponseIsSuccessful();
        }

        $client->request('GET', '/r/https://8.8.8.8');

        self::assertResponseStatusCodeSame(429);
        self::assertStringContainsString('Rate limit exceeded.', $client->getResponse()->getContent() ?? '');
    }

    public function testBackendErrorsRenderHelpfulMessage(): void
    {
        $client = $this->createClientWithReader(
            $this->createFailingReader(),
            '10.0.0.6',
            ['HTTP_TURBO_FRAME' => 'reader-result'],
        );

        $client->request('GET', '/read?url=https://8.8.8.8');

        self::assertResponseStatusCodeSame(502);
        self::assertStringContainsString('Upstream fetch failed.', $client->getResponse()->getContent() ?? '');
        self::assertStringContainsString('Try a different URL or retry later.', $client->getResponse()->getContent() ?? '');
    }

    public function testBackendErrorsConsumeRateLimitAfterValidation(): void
    {
        $client = $this->createClientWithReader(
            $this->createFailingReader(),
            '10.0.0.10',
            ['HTTP_TURBO_FRAME' => 'reader-result'],
        );

        for ($i = 0; $i < 10; ++$i) {
            $client->request('GET', '/read?url=https://8.8.8.8');

            self::assertResponseStatusCodeSame(502);
            self::assertStringContainsString('Upstream fetch failed.', $client->getResponse()->getContent() ?? '');
        }

        $client->request('GET', '/read?url=https://8.8.8.8');
        self::assertResponseStatusCodeSame(429);
        self::assertStringContainsString('Rate limit exceeded.', $client->getResponse()->getContent() ?? '');
    }

    /**
     * @param array<string, string> $server
     */
    private function createClientWithReader(HttpReader $reader, string $ip, array $server = []): KernelBrowser
    {
        $client = static::createClient(server: array_merge(['REMOTE_ADDR' => $ip], $server));
        $client->disableReboot();
        static::getContainer()->get('cache.app')->clear();
        static::getContainer()->set(HttpReader::class, $reader);

        return $client;
    }

    private function createSuccessfulReader(string $title = 'Test page', string $markdown = 'Body'): HttpReader
    {
        $reader = $this->createMock(HttpReader::class);
        $reader->method('read')->willReturnCallback(static fn (string $url): ReadDocument => new ReadDocument(
            url: $url,
            canonicalUrl: $url,
            title: $title,
            markdown: $markdown,
        ));

        return $reader;
    }

    private function createFailingReader(): HttpReader
    {
        $reader = $this->createMock(HttpReader::class);
        $reader->method('read')->willReturnCallback(static function (): never {
            throw (new BackendError('Upstream fetch failed.'))->setHint('Try a different URL or retry later.');
        });

        return $reader;
    }
}
