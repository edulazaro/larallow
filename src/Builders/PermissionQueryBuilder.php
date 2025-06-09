<?php

namespace EduLazaro\Larallow\Builders;

use EduLazaro\Larallow\Permission;

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
}
