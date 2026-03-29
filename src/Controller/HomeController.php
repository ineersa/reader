<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Reader\Exception\BackendError;
use App\Service\Reader\Exception\ToolUsageError;
use App\Service\Reader\HttpReader;
use App\Service\Reader\ReaderUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'document' => null,
            'error' => null,
            'hint' => null,
            'requested_url' => '',
        ]);
    }

    #[Route('/read', name: 'app_reader_fetch', methods: ['GET'])]
    public function read(
        Request $request,
        HttpReader $reader,
        ValidatorInterface $validator,
        #[Autowire(service: 'limiter.app_submit')] RateLimiterFactory $readerLimiter,
    ): Response {
        $requestedUrl = trim((string) $request->query->get('url', ''));

        try {
            $canonicalUrl = $this->validateAndNormalizeUrl($requestedUrl, $validator);
            $this->consumeRateLimit($request, $readerLimiter);
            $document = $reader->read($canonicalUrl);

            return $this->renderReadResponse($request, [
                'document' => $document,
                'error' => null,
                'hint' => null,
                'requested_url' => $requestedUrl,
            ]);
        } catch (ToolUsageError $e) {
            return $this->renderReadResponse($request, [
                'document' => null,
                'error' => $e->getMessage(),
                'hint' => $e->getHint(),
                'requested_url' => $requestedUrl,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (TooManyRequestsHttpException) {
            return $this->renderReadResponse($request, [
                'document' => null,
                'error' => 'Rate limit exceeded.',
                'hint' => 'You can make up to 10 requests every 10 minutes from the same IP address.',
                'requested_url' => $requestedUrl,
            ], Response::HTTP_TOO_MANY_REQUESTS);
        } catch (BackendError $e) {
            return $this->renderReadResponse($request, [
                'document' => null,
                'error' => $e->getMessage(),
                'hint' => $e->getHint(),
                'requested_url' => $requestedUrl,
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/r/{url}', name: 'app_reader_raw', methods: ['GET'], requirements: ['url' => '.+'])]
    public function raw(
        string $url,
        Request $request,
        HttpReader $reader,
        ValidatorInterface $validator,
        #[Autowire(service: 'limiter.app_submit')] RateLimiterFactory $readerLimiter,
    ): Response {
        try {
            $canonicalUrl = $this->validateAndNormalizeUrl($url, $validator);
            $this->consumeRateLimit($request, $readerLimiter);
            $document = $reader->read($canonicalUrl);

            return new Response($document->markdown, Response::HTTP_OK, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        } catch (ToolUsageError $e) {
            return new Response($e->getMessage()."\n\n".$e->getHint(), Response::HTTP_BAD_REQUEST, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        } catch (TooManyRequestsHttpException) {
            return new Response('Rate limit exceeded. You can make up to 10 requests every 10 minutes from the same IP address.', Response::HTTP_TOO_MANY_REQUESTS, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        } catch (BackendError $e) {
            return new Response($e->getMessage()."\n\n".$e->getHint(), Response::HTTP_BAD_GATEWAY, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
    }

    /**
     * @param array{document:mixed,error:?string,hint:?string,requested_url:string} $context
     */
    private function renderReadResponse(Request $request, array $context, int $status = Response::HTTP_OK): Response
    {
        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('home/_result_frame.html.twig', $context, new Response(status: $status));
        }

        return $this->render('home/index.html.twig', $context, new Response(status: $status));
    }

    private function consumeRateLimit(Request $request, RateLimiterFactory $readerLimiter): void
    {
        $limiter = $readerLimiter->create($request->getClientIp() ?? 'unknown');
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }
    }

    /**
     * @throws ToolUsageError
     */
    private function validateAndNormalizeUrl(string $requestedUrl, ValidatorInterface $validator): string
    {
        $normalizedUrl = ReaderUtils::canonicalizeUrl($requestedUrl);
        if ('' === $normalizedUrl) {
            throw (new ToolUsageError('Invalid URL provided.'))->setHint('Provide an absolute public URL with http or https, e.g. `https://example.com/article`.');
        }

        $violations = $validator->validate($normalizedUrl, new Assert\Url(
            protocols: ['http', 'https'],
            requireTld: false,
            message: 'Invalid URL provided.',
            tldMessage: 'Invalid URL provided.',
        ));

        if (
            0 !== $violations->count()
            || false === parse_url($normalizedUrl)
        ) {
            throw (new ToolUsageError('Invalid URL provided.'))->setHint('Provide an absolute public URL with http or https, e.g. `https://example.com/article`.');
        }

        $host = strtolower((string) parse_url($normalizedUrl, \PHP_URL_HOST));

        if ('' === $host) {
            throw (new ToolUsageError('Invalid URL provided.'))->setHint('Provide an absolute public URL with http or https, e.g. `https://example.com/article`.');
        }

        $isIp = false !== filter_var($host, \FILTER_VALIDATE_IP);
        if (!$isIp && !str_contains($host, '.')) {
            if ('localhost' === $host) {
                throw (new ToolUsageError('Internal URLs are not allowed.'))->setHint('Use a public URL that resolves to a public IP address.');
            }

            throw (new ToolUsageError('Invalid URL provided.'))->setHint('Provide an absolute public URL with http or https, e.g. `https://example.com/article`.');
        }

        return $normalizedUrl;
    }
}
