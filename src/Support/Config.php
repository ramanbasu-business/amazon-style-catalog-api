<?php

declare(strict_types=1);

namespace Catalog\Support;

use RuntimeException;

/**
 * Typed access to configuration. Values come from the environment only, so no
 * credential ever needs to live in a tracked file.
 */
final class Config
{
    /** @param array<string, string> $values */
    public function __construct(private readonly array $values)
    {
    }

    /** @param array<string, string> $env */
    public static function fromEnvironment(array $env): self
    {
        $values = [];
        foreach ($env as $key => $value) {
            if (is_string($value)) {
                $values[$key] = $value;
            }
        }

        return new self($values);
    }

    public function string(string $key, ?string $default = null): string
    {
        $raw = $this->values[$key] ?? null;
        if ($raw === null || $raw === '') {
            if ($default === null || $default === '') {
                throw new RuntimeException("Missing required configuration value: {$key}");
            }

            return $default;
        }

        return $raw;
    }

    public function int(string $key, ?int $default = null): int
    {
        $raw = $this->values[$key] ?? null;
        if ($raw === null || $raw === '') {
            if ($default === null) {
                throw new RuntimeException("Missing required configuration value: {$key}");
            }

            return $default;
        }

        if (!preg_match('/^-?\d+$/', $raw)) {
            throw new RuntimeException("Configuration value {$key} must be an integer, got: {$raw}");
        }

        return (int) $raw;
    }

    public function float(string $key, ?float $default = null): float
    {
        $raw = $this->values[$key] ?? null;
        if ($raw === null || $raw === '') {
            if ($default === null) {
                throw new RuntimeException("Missing required configuration value: {$key}");
            }

            return $default;
        }

        if (!is_numeric($raw)) {
            throw new RuntimeException("Configuration value {$key} must be numeric, got: {$raw}");
        }

        return (float) $raw;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $raw = $this->values[$key] ?? null;
        if ($raw === null || $raw === '') {
            return $default;
        }

        return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
    }
}
