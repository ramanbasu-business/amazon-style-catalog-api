<?php

declare(strict_types=1);

namespace Catalog\Http\Handler;

use Catalog\Catalog\CatalogService;
use Catalog\Http\ApiProblem;
use Catalog\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductHandler
{
    private const MAX_LIMIT = 50;

    public function __construct(private readonly CatalogService $catalog)
    {
    }

    /** @param array<string, string> $args */
    public function byId(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'] ?? '';
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id)) {
            throw ApiProblem::validation(['id' => 'Must be 1-64 characters of letters, digits, hyphen or underscore.']);
        }

        $product = $this->catalog->findById($id);
        if ($product === null) {
            throw ApiProblem::notFound("No product found for id {$id}.");
        }

        return Json::write($response, $product->toArray());
    }

    /** @param array<string, string> $args */
    public function bySku(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $sku = $args['sku'] ?? '';
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $sku)) {
            throw ApiProblem::validation(
                ['sku' => 'Must be 1-64 characters of letters, digits, hyphen or underscore.']
            );
        }

        $product = $this->catalog->findBySku($sku);
        if ($product === null) {
            throw ApiProblem::notFound("No product found for SKU {$sku}.");
        }

        return Json::write($response, $product->toArray());
    }

    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $errors = [];

        $keyword = is_string($params['q'] ?? null) ? trim($params['q']) : '';
        if ($keyword === '' || mb_strlen($keyword) > 200) {
            $errors['q'] = 'Required, 1-200 characters.';
        }

        $limit = $this->positiveInt($params['limit'] ?? null, 20);
        if ($limit === null || $limit > self::MAX_LIMIT) {
            $errors['limit'] = 'Must be an integer between 1 and ' . self::MAX_LIMIT . '.';
        }

        $offset = $this->nonNegativeInt($params['offset'] ?? null, 0);
        if ($offset === null) {
            $errors['offset'] = 'Must be an integer of 0 or more.';
        }

        if ($errors !== []) {
            throw ApiProblem::validation($errors);
        }

        /** @var int $limit */
        /** @var int $offset */
        $products = $this->catalog->search($keyword, $limit, $offset);

        return Json::write($response, [
            'data' => array_map(fn ($p): array => $p->toArray(), $products),
            'meta' => ['count' => count($products), 'limit' => $limit, 'offset' => $offset],
        ]);
    }

    private function positiveInt(mixed $raw, int $default): ?int
    {
        $value = $this->nonNegativeInt($raw, $default);

        return $value === null || $value < 1 ? null : $value;
    }

    private function nonNegativeInt(mixed $raw, int $default): ?int
    {
        if ($raw === null || $raw === '') {
            return $default;
        }

        if (!is_string($raw) || !preg_match('/^\d+$/', $raw)) {
            return null;
        }

        return (int) $raw;
    }
}
