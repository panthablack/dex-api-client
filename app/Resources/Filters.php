<?php

namespace App\Resources;

use App\Enums\FilterType;
use App\Enums\ResourceType;
use Exception;

class Filters
{
    private array $filters = [];

    public function __construct(array $filters = [])
    {
        foreach ($filters as $key => $value) {
            $resolvedType = FilterType::resolve($key);
            if (!$resolvedType) throw new \Exception("Cannot resolve filter type: $key");
            else $this->set($resolvedType, $value);
        }
    }

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
        $resolved = FilterType::resolve($type);
        $this->filters[$resolved->value] = $value;
    }

    public function get(string | FilterType $type): mixed
    {
        $resolved = FilterType::resolve($type);
        return $this->filters[$resolved->value] ?? null;
    }

    public function all(): mixed
    {
        return $this->filters;
    }

    public function toJson(): string {
        $jsonString = json_encode($this->all);
        if ($jsonString === false) throw new \Exception("Conversion to JSON string failed.");
        else return $jsonString;
    }

    public function byResource(ResourceType $type): array
    {
        $filters = [];
        if ($type === ResourceType::CLIENT || $type === ResourceType::CASE_CLIENT)
            $filters = FilterType::CLIENT_FILTERS;
        else if ($type === ResourceType::CASE || $type === ResourceType::CLOSED_CASE)
            $filters = FilterType::CASE_FILTERS;
        return array_reduce($filters, function (array $acc, FilterType $filter) {
            if (isset($this->filters[$filter->value]))
                $acc[$filter->value] = $this->get($filter);
            return $acc;
        }, []);
    }
}
