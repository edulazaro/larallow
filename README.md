
# Larallow for Laravel: A package to handle roles and permissions

<p align="center">
    <a href="https://packagist.org/packages/edulazaro/larallow"><img src="https://img.shields.io/packagist/dt/edulazaro/larallow" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/edulazaro/larallow"><img src="https://img.shields.io/packagist/v/edulazaro/larallow" alt="Latest Stable Version"></a>
</p>

**Larallow** is a flexible Laravel package for managing roles and permissions with advanced features including scoped roles and permissions, polymorphic relations, translation support, and seamless integration with PHP enums for permissions. Zero configuration required.

Why this package when Spatie Permissions exists?

Spatie Permissions is a great package. However it stores permissions in the database by default and does not handle well scopes or permissions for different actors, also requiring to specify the guard for each permission. It's all about your preferences and project requirements.

## Features

- Manage roles and permissions for any actor model (User, Client, etc.)
- Support for scoped roles via polymorphic roleable models (e.g., specific projects, teams)
- Support for scoped permissions via polymorphic permissionable models (e.g., specific resources)
- Define permissions with a fluent API in a similar way you define Laravel routes
- Fluent querying and checking with Permissions and Roles helper classes
- Built-in translation support for role names without external packages
- Permission hierarchy (implications)

## Installation

Add the package via Composer:

```
composer require edulazaro/larallow
```

Publish migrations and run them:

```
php artisan vendor:publish --tag=larallow
php artisan migrate
```

## Quick Start

### 1. Add traits to your model

```php
use EduLazaro\Larallow\Concerns\HasPermissions;
use EduLazaro\Larallow\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasPermissions, HasRoles;
}
```

### 2. Define permissions (in a service provider)

```php
use EduLazaro\Larallow\Permission;

Permission::create([
    'edit-post' => 'Edit Post',
    'delete-post' => 'Delete Post',
])->for(User::class);
```

### 3. Assign and check permissions

```php
// Assign a permission
$user->allow('edit-post');

// Or using the permissions() method
$user->permissions('edit-post')->allow();

// Check if user has permission (direct or via role)
$user->permissions('edit-post')->check();

// Check direct permission only
$user->hasPermission('edit-post');
```

### 4. Work with roles

```php
use EduLazaro\Larallow\Models\Role;

// Create a role
$role = Role::create(['handle' => 'editor', 'name' => 'Editor']);

// Add permissions to the role
$role->addPermission('edit-post');

// Assign role to user
$user->assignRole($role);

// Check role
$user->hasRole('editor');
```

---

## Setup

### Actor Traits

Add the `HasPermissions` and `HasRoles` traits to your actor models (User, Client, etc.):

```php
use EduLazaro\Larallow\Concerns\HasPermissions;
use EduLazaro\Larallow\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasPermissions, HasRoles;
}
```

You can use only one trait if you don't need both features.

### Role Tenant Trait

For models that own roles (e.g., Account, Group, Organization), add the `IsRoleTenant` trait:

```php
use EduLazaro\Larallow\Concerns\IsRoleTenant;

class Account extends Model
{
    use IsRoleTenant;
}
```

This allows you to retrieve roles belonging to that tenant:

```php
$roles = $account->roles()->get();
```

### Morph Maps (Recommended)

For consistent and secure morph relationships, define all models in your morph map:

```php
use Illuminate\Database\Eloquent\Relations\Relation;

// In AppServiceProvider boot()
Relation::morphMap([
    'user' => User::class,
    'client' => Client::class,
    'office' => Office::class,
]);
```

This is optional but heavily recommended, especially when dealing with multiple actor types or scopes.

---

## Permissions

### Defining Permissions

Register permissions using `Permission::create()` in a service provider:

```php
use EduLazaro\Larallow\Permission;

// Single permission
Permission::create('edit-post')->label('Edit Post');

// Multiple permissions
Permission::create([
    'edit-post' => 'Edit Post',
    'delete-post' => 'Delete Post',
    'view-post' => 'View Post',
]);
```

#### For specific actor types

Use `for()` to restrict permissions to specific actor models:

```php
Permission::create('edit-post')->for(User::class);
Permission::create('manage-account')->for([User::class, Client::class]);

// Using morph map names
Permission::create('edit-post')->for('user');
```

#### For specific scopes

Use `on()` to restrict permissions to specific scope models (e.g., Office, Group):

```php
Permission::create('manage-office')
    ->for(User::class)
    ->on(Office::class);

// Multiple scopes
Permission::create('manage-resources')
    ->for(User::class)
    ->on([Office::class, Group::class]);
```

#### Grouping permissions

Define multiple permissions with shared configuration:

```php
Permission::create([
    'manage-clients' => 'Manage Clients',
    'manage-properties' => 'Manage Properties',
    'manage-appointments' => 'Manage Appointments',
])->for(User::class)
  ->on([Office::class, Group::class]);
```

#### Permission implications (hierarchy)

Define permission hierarchies where higher-level permissions imply lower-level ones:

```php
Permission::create('manage-posts')
    ->for(User::class)
    ->implies('edit-post');

Permission::create('edit-post')->for(User::class);

// Users with 'manage-posts' automatically have 'edit-post'
```

#### Using enums

```php
Permission::create(UserPermission::EditPost->value)->label('Edit Post');

Permission::create([
    UserPermission::EditPost->value => 'Edit Post',
    UserPermission::DeletePost->value => 'Delete Post',
])->for(User::class);
```

#### Translating permission labels

```php
Permission::create([
    'edit-post' => __('Edit Post'),
]);
```

Or for easier text management you can use [Laratext](https://github.com/edulazaro/laratext) package:

```php
Permission::create([
    'edit-post' => text('edit_post', 'Edit Post'),
]);
```

### Assigning Permissions

Assign permissions directly to actors:

```php
// Single permission
$user->allow('edit-post');
$user->allow(UserPermission::EditPost);

// Multiple permissions
$user->allow(['edit-post', 'delete-post']);
```

#### With scope

```php
$office = Office::find(1);
$user->allow('manage-office', $office);
```

#### Alternative syntax

```php
$user->permissions('edit-post')->allow();
$user->permissions(['edit-post', 'delete-post'])->allow();
```

### Removing Permissions

```php
$user->deny('edit-post');

// With scope
$user->deny('manage-office', $office);

// Alternative
$user->permissions('edit-post')->deny();
```

### Syncing Permissions

Sync permissions to match a given list (adds missing, removes others):

```php
$user->syncPermissions(['edit-post', 'view-post']);

// With scope
$user->syncPermissions(['manage-office'], $office);
```

### Checking Permissions

#### Direct permissions only

Check if user has a permission directly assigned (not via roles):

```php
$user->hasPermission('edit-post');
$user->hasPermission('edit-post', $scope);

// Multiple (all required)
$user->hasPermissions(['edit-post', 'delete-post']);
```

You can also retrieve the list of permissions directly assigned to an actor:

```php
$permissions = $user->permissions; // Collection of ActorPermission models

foreach ($permissions as $permission) {
    echo $permission->permission; // string permission name
}

// Query specific permissions
$canEdit = $user->permissions()->where('permission', 'edit-post')->exists();
```

#### Combined (direct + role-based)

Check if user has permission either directly or via a role:

```php
// Model method (simplest)
$user->permissions('edit-post')->check();

// With scope
$user->permissions('edit-post')->on($office)->check();

// Helper function
permissions('edit-post')->for($user)->check();

// Permissions class (verbose)
Permissions::query()
    ->permissions('edit-post')
    ->for($user)
    ->check();
```

#### Check any vs all

```php
// True if user has ANY of these permissions
$user->permissions(['edit-post', 'delete-post'])->check();

// True only if user has ALL permissions
$user->permissions(['edit-post', 'delete-post'])->checkAll();

// With scope
$user->permissions(['edit-post', 'delete-post'])->on($office)->checkAll();
```

#### Blade directives

```php
@permissions('edit-post')
    <button>Edit Post</button>
@endpermissions

@allpermissions(['edit-post', 'delete-post'])
    <button>Manage Post</button>
@endallpermissions

// With scope
@permissions('edit-post', $scope)
    <button>Edit Post</button>
@endpermissions
```

---

## Roles

### Creating Roles

```php
use EduLazaro\Larallow\Models\Role;

$role = Role::create([
    'handle' => 'editor',
    'name' => 'Editor',
]);

// With tenant and scope restrictions
$role = new Role();
$role->handle = 'office_manager';
$role->name = 'Office Manager';
$role->tenant_type = $tenant->getMorphClass();
$role->tenant_id = $tenant->id;
$role->actor_type = 'user';
$role->scope_type = 'office';
$role->save();
```

### Assigning Roles

```php
$user->assignRole($role);
$user->assignRole($roleId);

// Multiple roles
$user->assignRoles([$role1, $role2, $roleId]);

// With scope
$user->assignRole($role, $office);
$user->assignRoles([$role1, $role2], $office);
```

#### With tenant (multi-tenancy)

```php
Roles::query()
    ->roles($roles)
    ->for($user)
    ->on($office)
    ->tenant($group)
    ->assign();
```

### Removing Roles

```php
$user->removeRole($role);
$user->removeRole($role, $scope);
```

### Syncing Roles

Sync roles to match a given list:

```php
$user->syncRoles([$role1, $role2]);
$user->syncRoles([1, 2, $roleInstance]);

// With scope (only affects roles for that scope)
$user->syncRoles([$managerRole], $office);
```

### Checking Roles

```php
$user->hasRole('editor');
$user->hasRole('editor', $scope);
```

You can also retrieve the roles assigned to an actor:

```php
$roles = $user->roles; // Collection of Role models

foreach ($roles as $role) {
    echo $role->handle;
}
```

#### Check any vs all

```php
// Any of these roles
$user->roles(['editor', 'admin'])->check();

// All of these roles
$user->roles(['editor', 'admin'])->checkAll();

// With scope
$user->roles(['editor', 'admin'])->on($scope)->checkAll();

// Using helper
roles(['editor', 'admin'])->for($user)->check();

// Using Roles class (verbose)
Roles::query()
    ->roles(['editor', 'admin'])
    ->for($user)
    ->check();
```

#### Blade directives

```php
@roles(['admin', 'editor'])
    <p>You have elevated access.</p>
@endroles

@allroles(['admin', 'editor'])
    <p>You have full admin/editor access.</p>
@endallroles
```

### Role Permissions

Add permissions to roles:

```php
$role->addPermission('edit-post');
$role->addPermission(['edit-post', 'delete-post', 'view-post']);
```

Remove permissions from roles:

```php
$role->removePermission('edit-post');
$role->removePermission(['edit-post', 'delete-post']);
```

You can also use the relationship directly:

```php
$role->permissions()->create(['permission' => 'edit-post']);
$role->permissions()->where('permission', 'edit-post')->delete();
```

### Checking Role Permissions

Check if user has permissions through their roles (ignoring direct permissions):

```php
// Single permission
$user->hasRolePermission('edit-post');
$user->hasRolePermission('edit-post', $scope);

// All required
$user->hasRolePermissions(['edit-post', 'delete-post']);

// Any of these
$user->hasAnyRolePermission('edit-post');
$user->hasAnyRolePermissions(['edit-post', 'delete-post']);
```

---

## Query Scopes

Filter actors by their permissions:

```php
// Users with a specific permission (direct or via role)
$users = User::withPermission('edit-post')->get();
$users = User::withPermission('edit-post', $office)->get();

// Users with any of these permissions
$users = User::withAnyPermission(['edit-post', 'delete-post'])->get();

// Users with all of these permissions
$users = User::withAllPermissions(['edit-post', 'delete-post'])->get();
```

These scopes automatically include implied permissions.

---

## Advanced

### Permission Query Builder

Query registered permissions:

```php
// Get all permissions
$allPermissions = Permission::all();

// Get by handle
$permission = Permission::get('edit-post');

// Check existence
Permission::exists('edit-post');

// Check if allowed for actor/scope
Permission::isAllowedFor('edit-post', 'user', 'office');
```

#### Filtering permissions

```php
$permissions = Permission::query()
    ->where('actor_type', 'user')
    ->where('scope_type', 'office')
    ->get();

// Alternative methods
$permissions = Permission::query()
    ->whereActorType('user')
    ->whereScopeType('office')
    ->get();

// Get as options array [handle => label]
$options = Permission::query()
    ->whereActorType('user')
    ->options();

// Pluck specific fields
$options = Permission::query()
    ->where('actor_type', 'user')
    ->pluck('label', 'handle')
    ->toArray();

// Filter by multiple handles
$permissions = Permission::query()
    ->where('handle', ['edit-post', 'delete-post'])
    ->get();

// Get first match
$permission = Permission::query()
    ->whereActorType('user')
    ->first();

// Short form syntax
$permissions = Permission::where('actor_type', 'user')
    ->where('scope_type', 'office')
    ->get();

$permission = Permission::where('actor_type', 'user')->first();
```

### Multi-tenancy

The `tenant()` method scopes role operations to a specific tenant:

```php
$tenant = Group::find(1);

Roles::query()
    ->roles($roles)
    ->for($user)
    ->on($office)
    ->tenant($tenant)
    ->assign();
```

During assignment, an `InvalidArgumentException` is thrown if any role does not belong to the specified tenant.

### Deleting Roles

```php
$role = Role::find($roleId);
$role->delete();
```

Make sure to detach any assignments or permissions related to this role before deleting to maintain data integrity.

---

## Translation Support

Roles support multilingual translations without external packages:

```php
// Get translated name (uses current locale)
echo $role->name;

// Get specific locale with fallback
$name = $role->getTranslation('name', 'es', 'Default Name');

// Set translation
$role->setTranslation('name', 'fr', 'Nom en Français');
$role->save();
```

---

## Testing

Run the package tests with:

```
./vendor/bin/phpunit
```

## Contributing

Contributions are welcome! Please fork the repo, add tests, and submit a PR.

## License

Larallow is open-sourced software licensed under the [MIT license](LICENSE.md).
