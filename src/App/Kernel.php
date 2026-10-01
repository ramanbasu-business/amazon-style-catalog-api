<?php

declare(strict_types=1);

namespace Catalog\App;

use Catalog\Http\Handler\HealthHandler;
use Catalog\Http\Middleware\ApiKeyMiddleware;
use Catalog\Http\Middleware\SecurityHeadersMiddleware;
use Catalog\Http\ProblemDetailsErrorHandler;
use Catalog\Support\Clock;
use Catalog\Support\Config;
use Catalog\Support\Database;
use Catalog\Support\SystemClock;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

/**
 * Wires the application. Kept as one readable file rather than spread across
 * provider classes: at this size a reviewer can see the whole object graph here.
 */
final class Kernel
{
    public static function createApp(Config $config, ?ContainerInterface $container = null): App
    {
        $container ??= self::createContainer($config);

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        self::registerRoutes($app);

        // Slim wraps middleware inside-out: the first added is innermost. The order
        // below gives, from the outside in: security headers, error handling, API
        // key, routing, body parsing. Headers are outermost so they are present on
        // error responses too, and the key is checked before routing so an invalid
        // key gets 401 rather than leaking whether a path exists.
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->add(new ApiKeyMiddleware($config->string('API_KEY')));

        $errorMiddleware = $app->addErrorMiddleware(
            displayErrorDetails: $config->bool('APP_DEBUG'),
            logErrors: true,
            logErrorDetails: true,
        );
        $errorMiddleware->setDefaultErrorHandler(new ProblemDetailsErrorHandler(
            $app->getResponseFactory(),
            $container->get(LoggerInterface::class),
        ));

        $app->add(new SecurityHeadersMiddleware());

        return $app;
    }

    public static function createContainer(Config $config): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions([
            Config::class => $config,
            Clock::class => fn (): Clock => new SystemClock(),
            Connection::class => fn (Config $c): Connection => Database::connect($c),
            LoggerInterface::class => function (Config $c): LoggerInterface {
                // Structured JSON to stdout: the container runtime owns log shipping,
                // and no request payloads are logged, only identifiers.
                $handler = new StreamHandler('php://stdout');
                $handler->setFormatter(new JsonFormatter());

                return new Logger($c->string('APP_ENV', 'local'), [$handler]);
            },
        ]);

        return $builder->build();
    }

    private static function registerRoutes(App $app): void
    {
        $app->get('/health', HealthHandler::class);
    }
}
