<?php

declare(strict_types=1);

namespace Catalog\Catalog;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * The local product cache. Every query is parameterised; no value from a request
 * is ever concatenated into SQL.
 */
final class MysqlProductRepository implements ProductStore
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function findBySourceId(string $sourceId): ?Product
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM products WHERE source_id = ?',
            [$sourceId]
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findBySku(string $sku): ?Product
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM products WHERE sku = ?',
            [$sku]
        );

        return $row === false ? null : $this->hydrate($row);
    }

    /** @return list<Product> */
    public function searchByTitle(string $keyword, int $limit, int $offset): array
    {
        // LIKE wildcards in the keyword are escaped so a caller cannot turn a search
        // into a full table scan with "%".
        $pattern = '%' . addcslashes($keyword, '%_\\') . '%';

        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM products WHERE title LIKE ? ORDER BY title LIMIT ? OFFSET ?',
            [$pattern, $limit, $offset],
            [ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER]
        );

        return array_map(fn (array $row): Product => $this->hydrate($row), $rows);
    }

    /** Insert or refresh a cached product. */
    public function save(Product $product): void
    {
        $fetchedAt = ($product->fetchedAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        $this->connection->executeStatement(
            'INSERT INTO products '
            . '(source_id, sku, title, brand, category, price_amount, price_currency, attributes, fetched_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'sku = VALUES(sku), title = VALUES(title), brand = VALUES(brand), category = VALUES(category), '
            . 'price_amount = VALUES(price_amount), price_currency = VALUES(price_currency), '
            . 'attributes = VALUES(attributes), fetched_at = VALUES(fetched_at)',
            [
                $product->sourceId,
                $product->sku,
                $product->title,
                $product->brand,
                $product->category,
                $product->price?->toDecimalString(),
                $product->price?->currency,
                json_encode($product->attributes, JSON_THROW_ON_ERROR),
                $fetchedAt,
            ]
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Product
    {
        $attributes = [];
        if (is_string($row['attributes']) && $row['attributes'] !== '') {
            $decoded = json_decode($row['attributes'], true);
            if (is_array($decoded)) {
                /** @var array<string, scalar> $attributes */
                $attributes = $decoded;
            }
        }

        $price = null;
        if ($row['price_amount'] !== null && $row['price_currency'] !== null) {
            $price = Money::fromDecimalString((string) $row['price_amount'], (string) $row['price_currency']);
        }

        return new Product(
            sourceId: (string) $row['source_id'],
            title: (string) $row['title'],
            sku: $row['sku'] === null ? null : (string) $row['sku'],
            brand: $row['brand'] === null ? null : (string) $row['brand'],
            category: $row['category'] === null ? null : (string) $row['category'],
            price: $price,
            attributes: $attributes,
            fetchedAt: new DateTimeImmutable((string) $row['fetched_at'], new DateTimeZone('UTC')),
        );
    }
}
