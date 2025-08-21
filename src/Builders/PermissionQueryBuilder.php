<?php

namespace EduLazaro\Larallow\Builders;

use EduLazaro\Larallow\Permission;
use Illuminate\Support\Collection;

class PermissionQueryBuilder
{
    /**
     * Filters to apply in query.
     *
     * Supported fields: 'actor_type', 'scope_type', 'handle'
     *
     * @var array<string, string|array<string>>
     */
    protected array $filters = [];

    /**
     * Add a where clause to filter permissions.
     *
     * @param string $field The field to filter on ('actor_type', 'scope_type', 'handle').
     * @param string|array<string> $value The value(s) to match.
     * @return $this
     */
    public function where(string $field, string|array $value): static
    {
        $this->filters[$field] = $value;
        return $this;
    }

    /**
     * Filter permissions by actor type.
     *
     * @param string $actorType
     * @return $this
     */
    public function whereActorType(string $actorType): static
    {
        return $this->where('actor_type', $actorType);
    }

    /**
     * Filter permissions by scope type.
     *
     * @param string $scopeType
     * @return $this
     */
    public function whereScopeType(string $scopeType): static
    {
        return $this->where('scope_type', $scopeType);
    }

    /**
     * Get the first permission matching the filters.
     *
     * @return Permission|null
     */
    public function first(): ?Permission
    {
        $results = $this->get();;
        return count($results) ? reset($results) : null;
    }

    /**
     * Get all permissions matching the filters.
     *
     * @return Permission[]
     */
    public function get(): array
    {
        return array_filter(Permission::$registry, function (Permission $permission) {
            foreach ($this->filters as $field => $value) {
                $prop = match ($field) {
                    'actor_type' => 'actorTypes',
                    'scope_type' => 'scopeTypes',
                    'handle' => 'handle',
                    default => null,
                };

                if (!$prop) {
                    continue;
                }

                $values = is_array($value) ? $value : [$value];

                $found = false;

                if ($prop === 'handle') {
                    foreach ($values as $val) {
                        if ($permission->$prop === $val) {
                            $found = true;
                            break;
                        }
                    }
                } else {
                    foreach ($values as $val) {
                        if (in_array($val, $permission->$prop, true)) {
                            $found = true;
                            break;
                        }
                    }
                }

                if (!$found) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Pluck specific fields from the matched permissions as a Collection.
     *
     * @param string $valueField The property to use as the value.
     * @param string|null $keyField The property to use as the key (optional).
     * @return Collection<string|int, mixed> A Laravel Collection of the plucked values.
     */
    public function pluck(string $valueField, ?string $keyField = null): Collection
    {
        $results = [];
        foreach ($this->get() as $permission) {
            $value = $permission->{$valueField} ?? null;
            $key = $keyField ? ($permission->{$keyField} ?? null) : null;

            if ($key !== null) {
                $results[$key] = $value;
            } else {
                $results[] = $value;
            }
        }

        return collect($results);
    }

    /**
     * Get all permissions matching the filters in array format.
     *
     * @return array[]
     */
    public function toArray(): array
    {
        $permissions = $this->get();
        return array_map(fn(Permission $permission) => $permission->toArray(), $permissions);
    }

    /**
     * Get permissions as an array of handle => label for select options.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->get() as $permission) {
            $options[$permission->handle] = $permission->label ?? $permission->handle;
        }

        return $options;
    }
}
