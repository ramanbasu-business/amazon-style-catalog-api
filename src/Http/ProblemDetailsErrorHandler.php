<?php

declare(strict_types=1);

namespace Catalog\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\ErrorHandlerInterface;
use Throwable;

/**
 * Turns every uncaught throwable into an RFC 9457 problem document.
 *
 * An ApiProblem carries a message meant for the client. Anything else is logged
 * in full and reported as a bare 500, so stack traces and SQL never reach a
 * caller even if APP_DEBUG is wrong in production.
 */
final class ProblemDetailsErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        [$status, $title, $detail, $extra] = $this->describe($exception);

        if ($status >= 500) {
            $this->logger->error('Unhandled error serving request', [
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        $body = ['type' => 'about:blank', 'title' => $title, 'status' => $status];
        if ($detail !== '') {
            $body['detail'] = $detail;
        }

        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/problem+json');
        $response->getBody()->write(
            json_encode($body + $extra, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        return $response;
    }

    /** @return array{int, string, string, array<string, mixed>} */
    private function describe(Throwable $exception): array
    {
        return match (true) {
            $exception instanceof ApiProblem => [
                $exception->status(),
                $exception->title(),
                $exception->detail(),
                $exception->extra(),
            ],
            $exception instanceof HttpNotFoundException => [404, 'Not Found', 'No route matches this path.', []],
            $exception instanceof HttpMethodNotAllowedException => [
                405,
                'Method Not Allowed',
                'This path does not accept that method.',
                [],
            ],
            default => [500, 'Internal Server Error', '', []],
        };
    }
}
