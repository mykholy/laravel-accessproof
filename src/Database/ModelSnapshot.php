<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Mykholy\AccessProof\Exceptions\InvalidProbe;

/**
 * Reads persisted records without applying Eloquent global scopes.
 */
final class ModelSnapshot
{
    /**
     * @param  array<string, mixed>  $before
     * @param  list<string>  $columns
     */
    private function __construct(
        private readonly Connection $connection,
        private readonly string $table,
        private readonly string $keyName,
        private readonly int|string $key,
        private readonly array $before,
        private readonly array $columns,
    ) {}

    /** @param list<string> $columns */
    public static function capture(Model $model, array $columns = []): self
    {
        $key = $model->getKey();

        if (! is_int($key) && (! is_string($key) || $key === '')) {
            throw new InvalidProbe('A watched model must have a persisted scalar primary key.');
        }

        $connection = $model->getConnection();
        $table = $model->getTable();
        $keyName = $model->getKeyName();
        $row = $connection->table($table)->where($keyName, $key)->first();

        if ($row === null) {
            throw new InvalidProbe('A watched model must exist before the request.');
        }

        $before = get_object_vars($row);
        $columns = $columns === [] ? array_keys($before) : array_values(array_unique($columns));

        foreach ($columns as $column) {
            if (! is_string($column) || $column === '' || ! array_key_exists($column, $before)) {
                throw new InvalidArgumentException('Unknown watched database column.');
            }
        }

        return new self($connection, $table, $keyName, $key, $before, $columns);
    }

    /** @return list<string> */
    public function changedColumns(): array
    {
        $row = $this->connection->table($this->table)
            ->where($this->keyName, $this->key)->first();

        if ($row === null) {
            return ['[row deleted]'];
        }

        $after = get_object_vars($row);
        $changed = [];

        foreach ($this->columns as $column) {
            if (! array_key_exists($column, $after) || $this->before[$column] !== $after[$column]) {
                $changed[] = $column;
            }
        }

        return $changed;
    }

    public function label(): string
    {
        return $this->table.'('.$this->keyName.'='.$this->key.')';
    }
}
