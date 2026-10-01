<?php

declare(strict_types=1);

namespace Catalog\Tests\Support;

use Catalog\App\Kernel;
use Catalog\Support\Clock;
use Catalog\Support\Config;
use Catalog\Support\FrozenClock;
use DateTimeImmutable;
use DateTimeZone;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Drives the real Slim application in-process. Requests go through the actual
 * middleware stack, so auth, security headers and error rendering are covered
 * rather than stubbed.
 */
abstract class AppTestCase extends TestCase
{
    protected const API_KEY = 'test-key';

    protected FrozenClock $clock;

    /** @var array<string, mixed> */
    private array $overrides = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-10-01 12:00:00', new DateTimeZone('UTC')));
    }

    /** Replace a container entry for one test, e.g. a fake source adapter. */
    protected function override(string $id, mixed $value): void
    {
        $this->overrides[$id] = $value;
    }

    /** @param array<string, string> $extraConfig */
    protected function container(array $extraConfig = []): ContainerInterface
    {
        $config = new Config($extraConfig + [
            'APP_ENV' => 'test',
            'APP_DEBUG' => 'true',
            'API_KEY' => self::API_KEY,
            'DB_HOST' => 'unused-in-unit-tests',
            'DB_NAME' => 'catalog',
            'DB_USER' => 'catalog',
            'DB_PASSWORD' => 'catalog',
        ]);

        $builder = new ContainerBuilder();
        // Overrides go on the left: with PHP's "+" the left operand's keys win.
        $builder->addDefinitions($this->overrides + [
            Config::class => $config,
            Clock::class => fn (): Clock => $this->clock,
            LoggerInterface::class => fn (): LoggerInterface => new Logger('test', [new NullHandler()]),
            Connection::class => fn (): Connection => throw new \LogicException(
                'This test must override Connection::class or use the integration suite.'
            ),
        ]);

        return $builder->build();
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     */
    protected function request(
        string $method,
        string $path,
        ?array $body = null,
        array $headers = ['X-Api-Key' => self::API_KEY],
        ?ContainerInterface $container = null,
    ): ResponseInterface {
        $container ??= $this->container();
        $config = $container->get(Config::class);

        $request = (new ServerRequestFactory())->createServerRequest($method, $path);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $json = json_encode($body, JSON_THROW_ON_ERROR);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream($json));
        }

        return Kernel::createApp($config, $container)->handle($request);
    }

    /** @return array<string, mixed> */
    protected function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded, "Response body was not a JSON object: {$body}");

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
