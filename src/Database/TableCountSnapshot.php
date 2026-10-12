<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Database;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

/**
 * Detects row-count changes for an explicit table predicate.
 *
 * Updates and simultaneous inserts/deletes with the same net count are outside its scope.
 */
final class TableCountSnapshot
{
    /** @param array<string, int|float|string|bool|null> $where */
    private function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table,
        private readonly array $where,
        private readonly int $before,
    ) {
    }

    /** @param array<string, int|float|string|bool|null> $where */
    public static function capture(ConnectionInterface $connection, string $table, array $where = []): self
    {
        if ($table === '' || str_contains($table, '.')) {
            throw new InvalidArgumentException('Provide an unqualified table name.');
        }

        foreach ($where as $column => $value) {
            if (! is_string($column) || $column === '' || str_contains($column, '.')) {
                throw new InvalidArgumentException('Provide unqualified where column names.');
            }
        }

        return new self($connection, $table, $where, self::count($connection, $table, $where));
    }

    public function change(): ?string
    {
        $after = self::count($this->connection, $this->table, $this->where);

        if ($this->before === $after) {
            return null;
        }

        return sprintf('Row count in %s changed from %d to %d', $this->table, $this->before, $after);
    }

    /** @param array<string, int|float|string|bool|null> $where */
    private static function count(ConnectionInterface $connection, string $table, array $where): int
    {
        $query = $connection->table($table);

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        return $query->count();
    }
}
