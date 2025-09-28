<?php

namespace App\Resources;

use App\Enums\FilterType;

class Filters
{
    private array $filters = [];

    public function __construct() {}

    public function __get($name)
    {
        if ($name === 'all') {
            return $this->filters;
        } else if (array_key_exists($name, $this->filters)) {
            return $this->filters[$name];
        } else if (FilterType::isInvalidType($name)) {
            throw new \Exception("Property '$name' does not exist.");
        } else return null;
    }

    public function set(FilterType $type, $value): void
    {
        $this->filters[$type->value] = $value;
    }

    public function get(FilterType $type): mixed
    {
        return $this->filters[$type->value] ?? null;
    }

    public function all(): mixed
    {
        return $this->filters;
    }

    public function byResource(): array
    {
        return array_map(fn($f) => $this->$f, FilterType::CLIENT_FILTERS);
    }
}
