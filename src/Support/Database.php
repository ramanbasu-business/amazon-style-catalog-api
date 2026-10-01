<?php

declare(strict_types=1);

namespace Catalog\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Throwable;

final class Database
{
    public static function connect(Config $config): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $config->string('DB_HOST'),
            'port' => $config->int('DB_PORT', 3306),
            'dbname' => $config->string('DB_NAME'),
            'user' => $config->string('DB_USER'),
            'password' => $config->string('DB_PASSWORD'),
            'charset' => 'utf8mb4',
        ]);
    }

    public static function isReachable(Connection $connection): bool
    {
        try {
            $connection->executeQuery('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
