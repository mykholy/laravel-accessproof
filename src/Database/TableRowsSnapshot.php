<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Database;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Mykholy\AccessProof\Exceptions\InvalidProbe;

/**
 * Observes a bounded, explicitly scoped set of persisted rows, including identity.
 *
 * A replacement cannot pass just because the row count remains unchanged.
 */
final class TableRowsSnapshot
{
    private const MAX_ROWS = 500;

    /**
     * @param  array<string, int|float|string|bool|null>  $where
     * @param  list<string>  $columns
     * @param  array<string, array<string, mixed>>  $before
     */
    private function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table,
        private readonly array $where,
        private readonly array $columns,
        private readonly string $keyColumn,
        private readonly array $before,
    ) {}

    /**
     * @param  array<string, int|float|string|bool|null>  $where
     * @param  list<string>  $columns
     */
    public static function capture(
        ConnectionInterface $connection,
        string $table,
        array $where,
        array $columns = [],
        string $keyColumn = 'id',
    ): self {
        if ($where === []) {
            throw new InvalidArgumentException('Row-set watches require at least one equality predicate.');
        }

        self::validateIdentifier($table);
        self::validateIdentifier($keyColumn);

        foreach (array_keys($where) as $column) {
            self::validateIdentifier($column);
        }

        foreach ($columns as $column) {
            self::validateIdentifier($column);
        }

        $columns = array_values(array_unique($columns));

        return new self(
            $connection,
            $table,
            $where,
            $columns,
            $keyColumn,
            self::rows($connection, $table, $where, $columns, $keyColumn),
        );
    }

    public function changed(): bool
    {
        return $this->before !== self::rows(
            $this->connection,
            $this->table,
            $this->where,
            $this->columns,
            $this->keyColumn,
        );
    }

    public function label(): string
    {
        return $this->table.' (scoped row set)';
    }

    private static function validateIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $identifier) !== 1) {
            throw new InvalidArgumentException('Row watches accept only simple, unqualified SQL identifiers.');
        }
    }

    /**
     * @param  array<string, int|float|string|bool|null>  $where
     * @param  list<string>  $columns
     * @return array<string, array<string, mixed>>
     */
    private static function rows(
        ConnectionInterface $connection,
        string $table,
        array $where,
        array $columns,
        string $keyColumn,
    ): array {
        $query = $connection->table($table);

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        $selected = $columns === [] ? ['*'] : array_values(array_unique([$keyColumn, ...$columns]));
        $records = $query->limit(self::MAX_ROWS + 1)->get($selected);

        if ($records->count() > self::MAX_ROWS) {
            throw new InvalidProbe('Row-set watch exceeds the 500-row safety limit; narrow the predicate.');
        }

        $rows = [];

        foreach ($records as $record) {
            $attributes = get_object_vars($record);
            $key = $attributes[$keyColumn] ?? null;

            if (! is_int($key) && ! is_string($key)) {
                throw new InvalidProbe('Row-set watches require a non-null integer or string key.');
            }

            $identity = 'key:'.(string) $key;

            if (isset($rows[$identity])) {
                throw new InvalidProbe('Row-set watch keys must be unique within the predicate.');
            }

            ksort($attributes);
            $rows[$identity] = $attributes;
        }

        ksort($rows);

        return $rows;
    }
}
