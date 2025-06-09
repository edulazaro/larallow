<?php

namespace EduLazaro\Larallow;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use EduLazaro\Larallow\Builders\PermissionQueryBuilder;

class Permission
{
    public string $handle;
    public ?string $label = null;
    public array $actorTypes = [];
    public array $scopeTypes = [];

    public static array $registry = [];
    public static array $impliedPermissions = [];

    protected array $queryFilters = [];
    
    /**
     * For batch creation: map handles => Permission instances.
     *
     * @var Permission[]
     */
    protected array $batchInstances = [];

    public static function query(): PermissionQueryBuilder
    {
        return new PermissionQueryBuilder();
    }

    public static function where(string $field, string|array $value): PermissionQueryBuilder
    {
        return (new PermissionQueryBuilder())->where($field, $value);
    }

    /**
     * Get a permission by handle from the registry.
     *
     * @param string $handle
     * @return self|null
     */
    public static function get(string $handle): ?Permission
    {
        return static::$registry[$handle] ?? null;
    }

    public static function first(): ?Permission
    {
        return count(static::$registry) ? reset(static::$registry) : null;
    }

    /**
     * Get all permissions defined.
     *
     * @return self[]
     */
    public static function all(): array
    {
        return static::$registry;
    }

    /**
     * Create one or many permissions.
     *
     * @param string|BackedEnum|mixed[] $handles Permission handle or array of handles.
     * @return static
     */
    public static function create(string|array|BackedEnum $handles): static
    {
        $instance = new static;

        if (is_array($handles) && Arr::isAssoc($handles)) {
            foreach ($handles as $handle => $label) {
                $handleValue = $handle instanceof BackedEnum ? $handle->value : $handle;

                $permInstance = new static;
                $permInstance->handle = $handleValue;
                $permInstance->label = $label;
                static::$registry[$handleValue] = $permInstance;
                $instance->batchInstances[$handleValue] = $permInstance;
            }
        } else {
            $handlesArray = is_array($handles) ? $handles : [$handles];

            foreach ($handlesArray as $handle) {
                $handleValue = $handle instanceof BackedEnum ? $handle->value : $handle;

                $permInstance = new static;
                $permInstance->handle = $handleValue;
                static::$registry[$handleValue] = $permInstance;
                $instance->batchInstances[$handleValue] = $permInstance;
            }
        }

        if (count($instance->batchInstances) === 1) {
            return reset($instance->batchInstances);
        }

        return $instance;
    }

    /**
     * Check if a permission handle is registered in the permission registry.
     *
     * @param string $handle Permission handle to check.
     * @return bool True if the permission exists, false otherwise.
     */
    public static function exists(string $handle): bool
    {
        return isset(static::$registry[$handle]);
    }

    /**
     * Check if a permission handle is allowed for a given actor type and optional scope type.
     *
     * @param string $handle The permission handle to check.
     * @param string|null $actorType Fully qualified model class or morph alias of the actor.
     * @param string|null $scopeType Fully qualified model class or morph alias of the scope.
     * @return bool True if the permission is allowed for the given actor and scope, false otherwise.
     */
    public static function isAllowedFor(string $handle, ?string $actorType = null, ?string $scopeType = null): bool
    {
        $permission = static::get($handle);

        if (!$permission) {
            return false;
        }

        $actorTypeNormalized = $actorType ? static::getMorphAliasFromMap($actorType) : null;
        $scopeTypeNormalized = $scopeType ? static::getMorphAliasFromMap($scopeType) : null;

        if ($actorTypeNormalized !== null && !empty($permission->actorTypes) && !in_array($actorTypeNormalized, $permission->actorTypes, true)) {
            return false;
        }

        if ($scopeTypeNormalized !== null && !empty($permission->scopeTypes) && !in_array($scopeTypeNormalized, $permission->scopeTypes, true)) {
            return false;
        }

        return true;
    }

    
    /**
     * Optional helper to get the morph alias for a given class based on Laravel's morph map configuration.
     *
     * @param string $class Fully qualified class name to look up.
     * @return string|null Returns the morph alias if found; otherwise, returns the original class name.
     */
    protected static function getMorphAliasFromMap(string $class): ?string
    {
        foreach (Relation::morphMap() as $alias => $morphClass) {
            if ($morphClass === $class) {
                return $alias;
            }
        }

        return $class;
    }

    /**
     * Set label(s) for permission(s).
     *
     * If batch, labels should be array keyed by permission handles.
     * If single, label for the current handle.
     *
     * @param string|array<string, string> $labelOrLabels
     * @return $this
     */
    public function labels(string|array $labelOrLabels): static
    {
        if ($this->batchInstances) {
            if (is_array($labelOrLabels)) {
                foreach ($labelOrLabels as $handle => $label) {
                    if (isset($this->batchInstances[$handle])) {
                        $this->batchInstances[$handle]->label = $label;
                    }
                }
            }
        } else {
            if (is_string($labelOrLabels)) {
                $this->label = $labelOrLabels;
            }
        }
        return $this;
    }

    /**
     * Set label for permission.
     *
     * @param string $label
     * @return $this
     */
    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Set actor types this permission applies to.
     *
     * @param string|array<string> $actorTypes
     * @return $this
     */
    public function for(string|array $actorTypes): static
    {
        $actorTypesArr = is_array($actorTypes) ? $actorTypes : [$actorTypes];

        $normalized = array_map(function ($type) {
            if (class_exists($type) && is_subclass_of($type, Model::class)) {
                return Relation::getMorphedModel($type) ?? $type;
            }

            return $type;
        }, $actorTypesArr);

        if ($this->batchInstances) {
            foreach ($this->batchInstances as $instance) {
                $instance->actorTypes = $normalized;
            }
        } else {
            $this->actorTypes = $normalized;
        }

        return $this;
    }

    /**
     * Set scope types this permission applies to.
     *
     * @param string|array<string> $scopeTypes
     * @return $this
     */
    public function on(string|array $scopeTypes): static
    {
        $scopeTypesArr = is_array($scopeTypes) ? $scopeTypes : [$scopeTypes];

        if ($this->batchInstances) {
            foreach ($this->batchInstances as $instance) {
                $instance->scopeTypes = $scopeTypesArr;
            }
        } else {
            $this->scopeTypes = $scopeTypesArr;
        }

        return $this;
    }

    /**
     * Define permissions implied by this permission.
     *
     * @param string|array<string> $permissions
     * @return $this
     */
    public function implies(string|BackedEnum|array $permissions): static
    {
        $perms = is_array($permissions) ? $permissions : [$permissions];

        foreach ($perms as $perm) {
            $permKey = $perm instanceof BackedEnum ? $perm->value : $perm;

            if (!isset(static::$impliedPermissions[$this->handle])) {
                static::$impliedPermissions[$this->handle] = [];
            }

            static::$impliedPermissions[$this->handle][] = $permKey;

            if (isset(static::$impliedPermissions[$permKey])) {
                static::$impliedPermissions[$this->handle] = array_merge(
                    static::$impliedPermissions[$this->handle],
                    static::$impliedPermissions[$permKey]
                );
            }
        }

        static::$impliedPermissions[$this->handle] = array_unique(static::$impliedPermissions[$this->handle]);

        return $this;
    }

    /**
     * Get batch instances
     *
     * @return $this
     */
    public function getBatchInstances(): array
    {
        return $this->batchInstances;
    }
}
