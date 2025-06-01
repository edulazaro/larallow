
# Larallow for Laravel

<p align="center">
    <a href="https://packagist.org/packages/edulazaro/larallow"><img src="https://img.shields.io/packagist/dt/edulazaro/larallow" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/edulazaro/larallow"><img src="https://img.shields.io/packagist/v/edulazaro/larallow" alt="Latest Stable Version"></a>
</p>


**Larallow** is a flexible Laravel package for managing roles and permissions with advanced features including scoped roles and permissions, polymorphic relations, translation support, and seamless integration with PHP enums for permissions. Zero configuration required.

¿Why this package when Spatie Permissions exist?

Spatie Permissions is a great package. However it stores permissions in the database by default and does not handle well scopes or permissions for different actors, also requiring to specify the guard for each permission. It's all about your preferences and project requirements.

## Features

- Manage roles and permissions for any actor model (User, Client, etc.)
- Support for scoped roles via polymorphic roleable models (e.g., specific projects, teams)
- Support for scoped permissions via polymorphic permissionable models (e.g., specific resources)
- Use PHP enums for defining and restricting permissions in a type-safe manner
- Fluent querying and checking with Permissions and Roles helper classes
- Built-in translation support for role names without external packages

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

## Setup and Configuration

### Adding Traits to Your Models

Add the `HasPermissions` and `HasRoles` traits to your actor models, e.g., User or Client:

```php
use EduLazaro\Larallow\Concerns\HasPermissions;
use EduLazaro\Larallow\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasPermissions, HasRoles;
}
```

You can skip any of the traits if you do not need them, as both permissions and roles can be handled separately.

### Defining Allowed Permissions with Enums

Define permissions as PHP 8.1+ backed enums:

```php
enum UserPermissions: string
{
    case EditPost = 'edit-post';
    case DeletePost = 'delete-post';
    case ViewDashboard = 'view-dashboard';
}
```

Register allowed permissions on the actor model in the boot method of a service provider:

```php
User::allowed(UserPermissions::class);
```

This provides an extra layer of security during development, so only allowed permissions can be added.

### Managing Roles and Permissions

**Assigning Roles to Actors**

You can assing a role to a user:

```php
$user->assignRole($role);
```

You can also specify a scope or context model, which can be an organisaton, an office, a department... etc.

```php
$user->assignRole($role, $roleableModel);
```

**Removing Roles**

You can remove a role from a user using the method `removeRole`:

```php
$user->removeRole($role);
```

Or if using an scope:

```php
$user->removeRole($role, $roleableModel);
```

**Checking Roles**

This will check if a role exists:

```php
$user->hasRole('admin');
```

If using scopes:

```php
$user->hasRole('admin', $roleableModel);
```

**Assigning Permissions Directly**

You can directly asign permissions:

```php
$user->allow(UserPermissions::EditPost);
```

Or you can just use the value mathed in the enum:

```php
$user->allow('edit_post');
```

Or if using scopes:

```php
$user->allow(UserPermissions::EditPost, $permissionableModel);
```

### Fluent Permission Queries Using `Permissions` Class

```php
$result = Permissions::query()
    ->permissions([UserPermissions::EditPost, UserPermissions::ViewDashboard])
    ->for($user)
    ->on($permissionableModel) // optional
    ->check();
```

If using scopes:

```php
$result = Permissions::query()
    ->permissions(['edit_post', 'create_post'])
    ->for($user)
    ->on($permissionableModel) // optional
    ->check();
```

Or using the permissions helper:

```php
$result = permissions(['edit_post', 'create_post'])
    ->for($user)
    ->on($permissionableModel) // optional
    ->check();
```

### Permission Checks on Actor Models

- `hasPermission($permission, $permissionable = null): bool`
- `hasPermissions($permissions, $permissionable = null): bool`
- `hasRolePermission($permission, $permissionable = null): bool`
- `hasRolePermissions($permissions, $permissionable = null): bool`
- `hasAnyRolePermission($permission, $permissionable = null): bool`

Example:

```php
if ($user->hasPermission(UserPermissions::EditPost)) {
    // ...
}

if ($user->hasRolePermission('edit-post', $projectModel)) {
    // permission 'edit-post' via a role scoped to $projectModel
}
```

### Role Names and Translation Support

```php
echo $role->name;
$nameEs = $role->getTranslation('name', 'es', 'Default Name');
$role->setTranslation('name', 'fr', 'Nom en Français');
```

## Database Schema Overview

### Roles Table

- `id`
- `roleable_types` (JSON)
- `actor_types` (JSON)
- `handle` (string, unique)
- `name` (string, nullable)
- `translations` (JSON, nullable)
- timestamps

### Actor_Role Pivot

- `id`
- `actor_type`, `actor_id`
- `role_id`
- `roleable_type`, `roleable_id`
- timestamps

### Role_Permissions Table

- `id`
- `role_id`
- `permission`
- timestamps

### Actor_Permissions Table

- `id`
- `actor_type`, `actor_id`
- `permissionable_type`, `permissionable_id`
- `permission`
- timestamps

## Usage Examples

```php
$user->assignRole($role, $team);
$user->allow(UserPermissions::EditPost, $document);

$hasAll = Permissions::query()
    ->permissions([UserPermissions::EditPost, UserPermissions::DeletePost])
    ->for($user)
    ->on($document)
    ->check();

if ($hasAll) {
    // User can edit and delete on $document
}
```

## Testing

Run tests with:

```
./vendor/bin/phpunit
```

## Contributing

Contributions are welcome! Please fork the repo, add tests, and submit a PR.

## License

Larakeep is open-sourced software licensed under the [MIT license](LICENSE.md).
