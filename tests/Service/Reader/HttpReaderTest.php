<?php

declare(strict_types=1);

namespace App\Tests\Service\Reader;

use App\Service\Reader\HttpReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpReaderTest extends TestCase
{
    public function testReadMapsDocumentFromUrl(): void
    {
        $url = 'https://example.com/article';
        $html = '<html><head><title>Article</title></head><body><main><p>Hello</p></main></body></html>';
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse($html));

        $reader = new HttpReader($httpClient);
        $result = $reader->read($url);

        self::assertSame($url, $result->url);
        self::assertSame($url, $result->canonicalUrl);
        self::assertSame('Article', $result->title);
        self::assertStringContainsString('Hello', $result->markdown);
    }

    public function testReadUsesGithubRawForBlobUrls(): void
    {
        $requested = [];
        $rawContent = "<?php\necho 'hello';\n";

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requested, $rawContent): MockResponse {
            $requested[] = $method.' '.$url;

            return new MockResponse($rawContent);
        });

        $reader = new HttpReader($httpClient);
        $document = $reader->read('https://github.com/foo/bar/blob/main/src/File.php');

        self::assertSame('https://github.com/foo/bar/blob/main/src/File.php', $document->url);
        self::assertSame('foo/bar/main/src/File.php', $document->title);
        self::assertStringContainsString('<?php', $document->markdown);
        self::assertStringContainsString("echo 'hello';", $document->markdown);
        self::assertStringContainsString('```php', $document->markdown);
        self::assertStringContainsString('URL: https://github.com/foo/bar/blob/main/src/File.php', $document->markdown);
        self::assertSame(['GET https://raw.githubusercontent.com/foo/bar/main/src/File.php'], $requested);
    }

    public function testReadUsesConfiguredHttpTimeoutAndRetries(): void
    {
        $url = 'https://example.com/article';
        $requestSeen = [];
        $optionsSeen = [];
        $httpClient = new MockHttpClient(static function (string $method, string $requestUrl, array $options) use (&$requestSeen, &$optionsSeen): MockResponse {
            $requestSeen = [$method, $requestUrl];
            $optionsSeen = $options;

            return new MockResponse('<html><body><main><p>ok</p></main></body></html>');
        });

        $reader = new HttpReader($httpClient);
        $reader->read($url);

        self::assertSame(['GET', $url], $requestSeen);
        self::assertArrayNotHasKey('retry_failed', $optionsSeen);
        self::assertArrayNotHasKey('max_retries', $optionsSeen);
    }
}
