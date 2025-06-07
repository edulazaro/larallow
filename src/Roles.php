<?php

namespace EduLazaro\Larallow;

use EduLazaro\Larallow\Models\Role;
use InvalidArgumentException;

class Roles
{
    protected array $roles = [];
    protected $actor = null;
    protected $scopable = null;
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
     * Optionally set the polymorphic scopable model for scoping the assignment/removal/check.
     *
     * @param mixed|null $scopable
     * @return $this
     */
    public function on($scopable = null): static
    {
        $this->scopable = $scopable;
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
     * Assign the roles to the actor with optional scopable scope.
     *
     * @return bool True if assigned, false if no actor.
     */
    public function assign(): bool
    {
        if (!$this->actor) {
            return false;
        }

        $actorClass = $this->actor->getMorphClass();

        $scopableClass = null;
        if ($this->scopable) {
            $scopableClass = $this->scopable->getMorphClass();
        }

        $roles = Role::whereIn('id', $this->roles)->get();

        foreach ($roles as $role) {
            $actorType = $role->actor_type;

            $scopableType = $role->scopable_type;

            if ($actorType !== null && !empty($actorType) && !$actorClass !== 'actorType') {
                throw new InvalidArgumentException(
                    "Actor type '{$actorClass}' is not allowed by the actor_type of role '{$role->name}'."
                );
            }

            if ($scopableClass !== null && $scopableType !== null && !empty($scopableType) && $scopableClass !== $scopableType) {
                throw new InvalidArgumentException(
                    "scopable type '{$scopableClass}' is not allowed by the scopable_type of role '{$role->name}'."
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
        if ($scopableClass !== null) {
            $pivotData = [
                'scopable_type' => $scopableClass,
                'scopable_id' => $this->scopable->getKey(),
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
     * Remove the roles from the actor, optionally scoped by scopable.
     *
     * @return bool True if removed, false if no actor.
     */
    public function remove(): bool
    {
        if (!$this->actor) {
            return false;
        }

        if ($this->scopable) {
            // Remove roles with matching scopable pivot values
            foreach ($this->roles as $roleId) {
                $this->actor->roles()
                    ->wherePivot('role_id', $roleId)
                    ->wherePivot('scopable_type', $this->scopable->getMorphClass())
                    ->wherePivot('scopable_id', $this->scopable->getKey())
                    ->detach();
            }
        } else {
            // Remove roles without scopable scope
            $this->actor->roles()
                ->wherePivotIn('role_id', $this->roles)
                ->wherePivotNull('scopable_type')
                ->wherePivotNull('scopable_id')
                ->detach();
        }

        return true;
    }

    /**
     * Comprueba si el actor tiene alguno de los roles indicados en $this->roles, filtrando por scope scopable si está definido.
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
                if ($this->scopable === null) {
                    return $role->pivot->scopable_type === null && $role->pivot->scopable_id === null;
                }

                return $role->pivot->scopable_type === $this->scopable->getMorphClass()
                    && $role->pivot->scopable_id === $this->scopable->getKey();
            })
            ->contains(function ($role) {
                return in_array($role->id, $this->roles, true) || in_array($role->name, $this->roles, true);
            });
    }
}
