<?php

namespace EduLazaro\Larallow;

use EduLazaro\Larallow\Models\Role;
use InvalidArgumentException;

class Roles
{
    protected array $roles = [];
    protected $actor = null;
    protected $scope = null;
    protected $tenant = null;

    /**
     * Create a new instance for fluent interface.
     *
     * @return static
     */
    public static function query(): static
    {
        return new static;
    }

    /**
     * Set roles by Role instance, id, or array of these.
     *
     * @param Role|int|array<Role|int> $roles
     * @return static
     */
    public function roles(Role|int|array $roles): static
    {
        $roles = is_array($roles) ? $roles : [$roles];

        $this->roles = array_map(function ($role) {
            return $role instanceof Role ? $role->id : $role;
        }, $roles);

        return $this;
    }

    /**
     * Set the actor for whom roles are assigned or checked.
     *
     * @param mixed $actor
     * @return static
     */
    public function for($actor): static
    {
        $this->actor = $actor;
        return $this;
    }

    /**
     * Optionally set the polymorphic scope model for scoping the assignment/removal/check.
     *
     * @param mixed|null $scope
     * @return $this
     */
    public function on($scope = null): static
    {
        $this->scope = $scope;
        return $this;
    }

    /**
     * Optionally set the polymorphic tenant model for multi tenantcy apps
     *
     * @param mixed|null $tenat
     * @return $this
     */
    public function tenant($tenant = null): static
    {
        $this->tenant = $tenant;
        return $this;
    }

    /**
     * Assign the roles to the actor with optional scope scope.
     *
     * @return bool True if assigned, false if no actor.
     */
    public function assign(): bool
    {
        if (!$this->actor) {
            return false;
        }

        $actorClass = $this->actor->getMorphClass();

        $scopeClass = null;
        if ($this->scope) {
            $scopeClass = $this->scope->getMorphClass();
        }

        $roles = Role::whereIn('id', $this->roles)->get();

        foreach ($roles as $role) {
            $actorType = $role->actor_type;

            $scopeType = $role->scope_type;

            if ($actorType !== null && !empty($actorType) && !$actorClass !== 'actorType') {
                throw new InvalidArgumentException(
                    "Actor type '{$actorClass}' is not allowed by the actor_type of role '{$role->name}'."
                );
            }

            if ($scopeClass !== null && $scopeType !== null && !empty($scopeType) && $scopeClass !== $scopeType) {
                throw new InvalidArgumentException(
                    "scope type '{$scopeClass}' is not allowed by the scope_type of role '{$role->name}'."
                );
            }

            if ($this->tenant) {
                if ($role->tenant_type !== $this->tenant->getMorphClass() || $role->tenant_id !== $this->tenant->getKey()) {
                    throw new InvalidArgumentException(
                        "Role '{$role->name}' does not belong to tenant '{$this->tenant->getMorphClass()}#{$this->tenant->getKey()}'."
                    );
                }
            }
        }

        $pivotData = [];
        if ($scopeClass !== null) {
            $pivotData = [
                'scope_type' => $scopeClass,
                'scope_id' => $this->scope->getKey(),
            ];
        }

        $syncData = [];
        foreach ($this->roles as $roleId) {
            $syncData[$roleId] = $pivotData;
        }

        $this->actor->roles()->syncWithoutDetaching($syncData);

        return true;
    }

    /**
     * Remove the roles from the actor, optionally scoped by scope.
     *
     * @return bool True if removed, false if no actor.
     */
    public function remove(): bool
    {
        if (!$this->actor) {
            return false;
        }

        if ($this->scope) {
            // Remove roles with matching scope pivot values
            foreach ($this->roles as $roleId) {
                $this->actor->roles()
                    ->wherePivot('role_id', $roleId)
                    ->wherePivot('scope_type', $this->scope->getMorphClass())
                    ->wherePivot('scope_id', $this->scope->getKey())
                    ->detach();
            }
        } else {
            // Remove roles without scope scope
            $this->actor->roles()
                ->wherePivotIn('role_id', $this->roles)
                ->wherePivotNull('scope_type')
                ->wherePivotNull('scope_id')
                ->detach();
        }

        return true;
    }

    /**
     * Check if the actor has any of the roles assgined, fitler by scope, optionally
     *
     * @return bool
     */
    public function check(): bool
    {
        if (!$this->actor) {
            return false;
        }

        if (empty($this->roles)) {
            return false;
        }

        return $this->actor->roles
            ->filter(function ($role) {
                if ($this->scope === null) {
                    return $role->pivot->scope_type === null && $role->pivot->scope_id === null;
                }

                return $role->pivot->scope_type === $this->scope->getMorphClass()
                    && $role->pivot->scope_id === $this->scope->getKey();
            })
            ->contains(function ($role) {
                return in_array($role->id, $this->roles, true) || in_array($role->name, $this->roles, true);
            });
    }

    /**
     * Check if the actor has all of the roles in $this->roles, filtered by scope if defined.
     *
     * @return bool
     */
    public function checkAll(): bool
    {
        if (!$this->actor || empty($this->roles)) {
            return false;
        }

        $scopedRoles = $this->actor->roles->filter(function ($role) {
            if ($this->scope === null) {
                return $role->pivot->scope_type === null && $role->pivot->scope_id === null;
            }

            return $role->pivot->scope_type === $this->scope->getMorphClass()
                && $role->pivot->scope_id === $this->scope->getKey();
        });

        $actorRoleIdentifiers = $scopedRoles->flatMap(fn ($role) => [$role->id, $role->name])->all();

        foreach ($this->roles as $required) {
            if (!in_array($required, $actorRoleIdentifiers, true)) {
                return false;
            }
        }

        return true;
    }
}
