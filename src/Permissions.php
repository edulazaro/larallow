<?php

namespace EduLazaro\Larallow;

use BackedEnum;
use EduLazaro\Larallow\Permission;

class Permissions
{
    protected array $permissions = [];
    protected $scope = null;
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
     * Set an optional scope (morph) model to scope permissions.
     *
     * @param mixed $scope
     * @return static
     */
    public function on($scope): static
    {
        $this->scope = $scope;
        return $this;
    }

    protected function getGrantedPermissions(): array
    {
        $actor = $this->actor ?? auth()->user();

        $permissions = [];
        if (method_exists($actor, 'hasPermissions')) {
            $permissions = $actor->permissions()
                ->when($this->scope, fn($q) => $q->whereMorphedTo('scope', $this->scope))
                ->pluck('permission')
                ->unique()
                ->all();
        }

        $rolePermissions = [];
        if (method_exists($actor, 'hasRolePermissions')) {
            $query = $actor->roles()->with('permissions');
            if ($this->scope) {
                $query->wherePivot('scope_type', $this->scope->getMorphClass())
                    ->wherePivot('scope_id', $this->scope->getKey());
            }
            $rolePermissions = $query->get()
                ->pluck('permissions.*.permission')
                ->flatten()
                ->unique()
                ->all();
        }

        return array_unique(array_merge($permissions, $rolePermissions));
    }


    /**
     * Check if the actor has at least one of the specified permissions, considering both direct and role-based permissions.
     *
     * @return bool True if actor has any of the permissions, false otherwise.
     */
    public function check(): bool
    {
        $actor = $this->actor ?? auth()->user();
        $granted = $this->getGrantedPermissions();

        foreach ($this->permissions as $required) {
            $valid = [$required];

            foreach ($granted as $grantedPermission) {
                $implied = Permission::$impliedPermissions[$grantedPermission] ?? [];

                if (in_array($required, $implied, true)) {
                    $valid[] = $grantedPermission;
                }
            }

            if (count(array_intersect($valid, $granted)) > 0) {
                return true;
            }
        }

        return false;
    }


    /**
     * Check if the actor has all of the specified permissions, taking into account direct permissions and permissions via roles.
     *
     * @return bool True if actor has all permissions, false otherwise.
     */
    public function checkAll(): bool
    {
        $actor = $this->actor ?? auth()->user();
        $granted = $this->getGrantedPermissions();

        foreach ($this->permissions as $required) {
            $valid = [$required];

            foreach ($granted as $grantedPermission) {

                $implied = Permission::$impliedPermissions[$grantedPermission] ?? [];

                if (in_array($required, $implied, true)) {
                    $valid[] = $grantedPermission;
                }
            }

            if (count(array_intersect($valid, $granted)) === 0) {
                return false;
            }
        }

        return true;
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
                'scope_type' => $this->scope?->getMorphClass(),
                'scope_id' => $this->scope?->getKey(),
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

        if ($this->scope) {
            $query->whereMorphedTo('scope', $this->scope);
        }

        $query->delete();

        return true;
    }
}
