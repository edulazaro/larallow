<?php

namespace EduLazaro\Larallow;

use BackedEnum;

class Permissions
{
    protected array $permissions = [];
    protected $scopable = null;
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
     * Set an optional scopable (morph) model to scope permissions.
     *
     * @param mixed $scopable
     * @return static
     */
    public function on($scopable): static
    {
        $this->scopable = $scopable;
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
                    ->when($this->scopable, fn($q) => $q->whereMorphedTo('scopable', $this->scopable))
                    ->pluck('permission')
                    ->unique()
                    ->all();
        }

        $rolePermissions = [];
        if (method_exists($actor, 'hasRolePermissions')) {
            $query = $actor->roles()->with('permissions');

            if ($this->scopable) {
                $query->wherePivot('scopable_type', $this->scopable->getMorphClass())
                    ->wherePivot('scopable_id', $this->scopable->getKey());
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
                'scopable_type' => $this->scopable?->getMorphClass(),
                'scopable_id' => $this->scopable?->getKey(),
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

        if ($this->scopable) {
            $query->whereMorphedTo('scopable', $this->scopable);
        }

        $query->delete();

        return true;
    }
}
