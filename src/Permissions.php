<?php

namespace EduLazaro\Larallow;

use BackedEnum;

class Permissions
{
    protected array $permissions = [];
    protected $permissionable = null;
    protected $actor = null;

    /**
     * Create a new instance for chaining.
     *
     * @return static
     */
    public static function query(): static
    {
        return new static;
    }

    /**
     * Set the permissions to check or assign.
     *
     * @param string|BackedEnum|array<string|BackedEnum> $permissions
     * @return static
     */
    public function permissions(string|BackedEnum|array $permissions): static
    {
        $instance = new static;
        $permissions = is_array($permissions)
            ? array_map(fn ($perm) => $perm instanceof BackedEnum ? $perm->value : $perm, $permissions)
            : [(($permissions instanceof BackedEnum) ? $permissions->value : $permissions)];

        $instance->permissions = array_unique($permissions);

        return $instance;
    }

    /**
     * Set the actor (user or model) for whom permissions apply.
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
     * Set an optional permissionable (morph) model to scope permissions.
     *
     * @param mixed $permissionable
     * @return static
     */
    public function on($permissionable): static
    {
        $this->permissionable = $permissionable;
        return $this;
    }
    /**
     * Check if the actor has all of the specified permissions, taking into account direct permissions and permissions via roles.
     *
     * @return bool True if actor has all permissions, false otherwise.
     */
    public function check(): bool
    {
        $actor = $this->actor ?? auth()->user();

        if (!$actor) {
            return false;
        }
  
        $permissions = [];
        if (method_exists($actor, 'hasPermissions')) {
            $permissions = $actor->permissions()
                    ->when($this->permissionable, fn($q) => $q->whereMorphedTo('permissionable', $this->permissionable))
                    ->pluck('permission')
                    ->unique()
                    ->all();
        }

        $rolePermissions = [];
        if (method_exists($actor, 'hasRolePermissions')) {
            $query = $actor->roles()->with('permissions');

            if ($this->permissionable) {
                $query->wherePivot('roleable_type', $this->permissionable->getMorphClass())
                    ->wherePivot('roleable_id', $this->permissionable->getKey());
            }

            $rolePermissions = $query->get()
                ->pluck('permissions.*.permission')
                ->flatten()
                ->unique()
                ->all();
        }

        $permissions = array_unique(array_merge($permissions, $rolePermissions));

        $missing = array_diff($this->permissions, $permissions);

        return count($missing) === 0;
    }

    /**
     * Assign the given permissions to the actor.
     *
     * @return bool True if assigned, false if no actor set.
     */
    public function allow(): bool
    {
        if (!$this->actor) {
            return false;
        }

        foreach ($this->permissions as $permission) {
            $this->actor->permissions()->firstOrCreate([
                'permission' => $permission,
                'permissionable_type' => $this->permissionable?->getMorphClass(),
                'permissionable_id' => $this->permissionable?->getKey(),
            ]);
        }

        return true;
    }

    /**
     * Remove the given permissions from the actor.
     *
     * @return bool True if deleted, false if no actor set.
     */
    public function deny(): bool
    {
        if (!$this->actor) {
            return false;
        }

        $query = $this->actor->permissions()
            ->whereIn('permission', $this->permissions);

        if ($this->permissionable) {
            $query->whereMorphedTo('permissionable', $this->permissionable);
        }

        $query->delete();

        return true;
    }
}
