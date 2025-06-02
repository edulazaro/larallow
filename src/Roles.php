<?php

namespace EduLazaro\Larallow;

use EduLazaro\Larallow\Models\Role;
use InvalidArgumentException;

class Roles
{
    protected array $roles = [];
    protected $actor = null;
    protected $roleable = null;
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
     * Optionally set the polymorphic roleable model for scoping the assignment/removal/check.
     *
     * @param mixed|null $roleable
     * @return $this
     */
    public function on($roleable = null): static
    {
        $this->roleable = $roleable;
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
     * Assign the roles to the actor with optional roleable scope.
     *
     * @return bool True if assigned, false if no actor.
     */
    public function assign(): bool
    {
        if (!$this->actor) {
            return false;
        }

        $actorClass = $this->actor->getMorphClass();

        $roleableClass = null;
        if ($this->roleable) {
            $roleableClass = $this->roleable->getMorphClass();
        }

        $roles = Role::whereIn('id', $this->roles)->get();

        foreach ($roles as $role) {
            $actorTypes = $role->actor_types;
            if (is_string($actorTypes)) {
                $actorTypes = json_decode($actorTypes, true) ?: [];
            }

            $roleableTypes = $role->roleable_types;
            if (is_string($roleableTypes)) {
                $roleableTypes = json_decode($roleableTypes, true) ?: [];
            }

            if ($actorTypes !== null && !empty($actorTypes) && !in_array($actorClass, $actorTypes, true)) {
                throw new InvalidArgumentException(
                    "Actor type '{$actorClass}' is not allowed by the actor_types of role '{$role->name}'."
                );
            }

            if ($roleableClass !== null && $roleableTypes !== null && !empty($roleableTypes) && !in_array($roleableClass, $roleableTypes, true)) {
                throw new InvalidArgumentException(
                    "Roleable type '{$roleableClass}' is not allowed by the roleable_types of role '{$role->name}'."
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
        if ($roleableClass !== null) {
            $pivotData = [
                'roleable_type' => $roleableClass,
                'roleable_id' => $this->roleable->getKey(),
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
     * Remove the roles from the actor, optionally scoped by roleable.
     *
     * @return bool True if removed, false if no actor.
     */
    public function remove(): bool
    {
        if (!$this->actor) {
            return false;
        }

        if ($this->roleable) {
            // Remove roles with matching roleable pivot values
            foreach ($this->roles as $roleId) {
                $this->actor->roles()
                    ->wherePivot('role_id', $roleId)
                    ->wherePivot('roleable_type', $this->roleable->getMorphClass())
                    ->wherePivot('roleable_id', $this->roleable->getKey())
                    ->detach();
            }
        } else {
            // Remove roles without roleable scope
            $this->actor->roles()
                ->wherePivotIn('role_id', $this->roles)
                ->wherePivotNull('roleable_type')
                ->wherePivotNull('roleable_id')
                ->detach();
        }

        return true;
    }

    /**
     * Comprueba si el actor tiene alguno de los roles indicados en $this->roles, filtrando por scope roleable si está definido.
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
                if ($this->roleable === null) {
                    return $role->pivot->roleable_type === null && $role->pivot->roleable_id === null;
                }

                return $role->pivot->roleable_type === $this->roleable->getMorphClass()
                    && $role->pivot->roleable_id === $this->roleable->getKey();
            })
            ->contains(function ($role) {
                return in_array($role->id, $this->roles, true) || in_array($role->name, $this->roles, true);
            });
    }
}
