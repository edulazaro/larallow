
# Larallow for Laravel: A package to handle roles and permission

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
- Define permissions with a fluent API in a similar way you define Laravel routes.
- Fluent querying and checking with Permissions and Roles helper classes
- Built-in translation support for role names without external packages
- Permission hierarchy

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

Here you can find how to easily edit your models so they accept permissions.


### Actor permissions and roles

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

### Morph Maps for Models (optional)

To ensure consistent and secure morph relationships across your application, we recommend explicitly defining all models used with Larallow in your application's morph map. This improves performance and helps avoid unexpected behavior—especially when dealing with multiple actor types or roleable targets. This is optional, as the class names can be used for the relaions, but it's heavily recommended.

How to define the morph map, use a service provider such as `AppServiceProvider`:

```php
use Illuminate\Database\Eloquent\Relations\Relation;

public function boot(): void
{
    Relation::morphMap([
        'user' => User::class,
        'client' => Client::class,
        'project' => Project::class,
        'account' => Account::class,
    ]);
}
```

Make sure this is called early in the request lifecycle, typically in the `boot()` method of a service provider.

## Managing Permissions

We will start explaining how to create and configure peremissions in your app.

### Creating Permissions

Permissions are registered using the `Permission::create()` method, passing an array where keys are enum values and values are translated labels:


```php
use EduLazaro\Larallow\Permission;

Permission::create('manage_offices')->label('Manage Offices');
```

You can also use enums:

```php
use EduLazaro\Larallow\Permission;

Permission::create(UserPermission::ManageOffices->value)->label('Manage Offices');
```

For a specific user type. `for()` specifies the actor model class(es) (e.g. User::class) the permissions apply to:

```php
use EduLazaro\Larallow\Permission;

Permission::create('manage_offices')->for(User::class)->label('Manage Offices');
Permission::create('manage_offices')->for(Client::class)->label('Manage Offices');
```

Or using the morph map name:

```php
use EduLazaro\Larallow\Permission;

Permission::create('manage_offices')->for('user')->label('Manage Offices');
```

You can define the permission for many user types at a time:

```php
use EduLazaro\Larallow\Permission;

Permission::create('manage_offices')->for([User::class, Client::class])->label('Manage Offices');
```

Or using the morph map name:

```php
use EduLazaro\Larallow\Permission;

Permission::create('manage_offices')->for(['user', 'client'])->label('Manage Offices');
```

For a specific scope. `on()` specifies the scope model class(es) (e.g. Group::class, Office::class) the permissions apply to:

```php
use EduLazaro\Larallow\Permission;

Permission::create('manage_offices')->for(User::class)->on(Group::class)->label('Manage Offices');
Permission::create('manage_clients')->for(User::class)->on(Office::class)->label('Manage Clients');
```

Or using the morph map name:

```php
use EduLazaro\Larallow\Permission;

Permission::create('manage_offices')->for(User::class)->on('group')->label('Manage Offices');
```

For many scopes at a time:

```php
use EduLazaro\Larallow\Permission;

Permission::create('manage_offices')->for(User::class)->on([Group::class, Office::class])->label('Manage Offices');
```

For many user types and scopes at a time:

```php
use EduLazaro\Larallow\Permission;

Permission::create('manage_offices')->for([User::class, Client::class])->on([Group::class, Office::class])->label('Manage Offices');
```

### Grouping permissions

You can group the permissions using the array notation,:

```php
use EduLazaro\Larallow\Permission;

Permission::create([
    UserPermission::ManageOffices->value => 'Manage offices',
])->for(User::class)
  ->on(Group::class);

Permission::create([
    UserPermission::ManageClients->value => 'Manage clients',
    UserPermission::ManageProperties->value => 'Manage properties',
    UserPermission::ManageDevelopments->value => 'Manage developments',
    UserPermission::ManageAppointments->value => 'Manage appointments',
    UserPermission::ManageUsers->value => 'Manage users',
])->for(User::class)
  ->on([
      Office::class,
      Group::class,
  ]);
```

### Permission translation

To translate the permissions you define them like:

```php
use EduLazaro\Larallow\Permission;

Permission::create([
    UserPermission::ManageOffices->value => __('Manage offices'),
])->for(User::class)
  ->on(Group::class);
```

Or for asier text management you can use [Laratext](https://github.com/edulazaro/laratext) package:

```php
use EduLazaro\Larallow\Permission;

Permission::create([
    UserPermission::ManageOffices->value => text('manage_offices', 'Manage offices'),
])->for(User::class)
  ->on(Group::class);
```

### Getting permissions

The `Permission` class holds all registered permissions in a static registry and provides various methods to query and filter these permissions. The `PermissionQueryBuilder` lets you fluently build queries to filter permissions by properties like actor types, scope types, and handles.

This returns all permissions currently registered.

```php
use EduLazaro\Larallow\Permission;

$allPermissions = Permission::all();

foreach ($allPermissions as $permission) {
    echo $permission->handle . ' ' . $permission->label;
}
```

To get a Single Permission by Handle

```php
$permission = Permission::get('edit_post');

if ($permission) {
    echo "Found permission: " . $permission->handle . PHP_EOL;
}
```

### Query builder 

Use `Permission::query()` to start a fluent query and add .where() clauses for filtering. These are the supported filter fields:

- `actor_type`: Filter by actor types (e.g., `App\Models\User`)
- `scope_type`: Filter by scope types (e.g., `App\Models\Group`)
- `handle`: Filter by permission handle(s)

For example, to get all permissions for User actor and Group scope:

```php
$permissions = Permission::query()
    ->where('actor_type', 'App\Models\User') // or 'user' if defined in the morph map
    ->where('scope_type', 'App\Models\Group')  // or 'group' if defined in the morph map
    ->get();

foreach ($permissions as $permission) {
    echo $permission->handle . " (" . ($permission->label ?? 'No label') . ")" . PHP_EOL;
}
```

This returns an array of Permission instances matching both actor and scope.

You can also use the short form:

```php
$permissions = Permission::where('actor_type', 'user')
    ->where('scope_type', 'group')
    ->get();
```

In order to get the first one:

You can also use the short form:

```php
$permissions = Permission::where('actor_type', 'user')
    ->where('scope_type', 'group')
    ->first();
```

You can also filter by multiple values:

```php
$permissions = Permission::query()
    ->where('handle', ['edit_post', 'delete_post'])
    ->get();
```

In order to check If a Permission Exists (Static Check):

```php
if (Permission::exists('edit_post')) {
    echo "Permission 'edit_post' exists." . PHP_EOL;
}
```


In order to check If a Permission is Allowed for Actor and Scope (Static Check):

```php
$isAllowed = Permission::isAllowedFor(
    'edit_post',
    'user',     // actor type
    'group'     // scope type
);
```

This validates if a permission exists and applies to the given actor and scope.

Here is an example Using `create()`, `for()`, `on()`:

```php
Permission::create([
    'manage_offices' => 'Manage Offices',
    'view_reports' => 'View Reports',
])->for('user')->on(['group', 'office']);

$permissions = Permission::query()
    ->where('actor_type', 'user')
    ->where('scope_type', 'group')
    ->get();
```

## Permission assignment

This section describes how to manage permissions assigned to actors (e.g., users) standalone, without involving roles.

### Adding Permissions

You can assign permissions directly to an actor instance (e.g., a User) using the `allow` method from the `HasPermissions` concern or via the Permissions class:

```php
use App\Models\User;
use App\Enums\Permissions\UserPermission;

$user = User::find(1);

// Using enum
$user->allow(UserPermission::ViewClients);

// Or using string
$user->allow('edit_post');
```

Use the `permissions` helper:

```php
permissions('view_clients')
    ->for($user)
    ->allow();
```

Adding permissions using the permissions method:

```php
$user->permissions('view_clients')->allow();
```

You can also use the optional scope. If permissions are scoped to a specific model (e.g., a content item or project), you can specify the related model when denying the permission:

```php
$office = Office::find(1);

$user->allow('edit_office', $office);

// Or also
$user->permissions('edit_office')->on($office)->allow();

// Or with Permissions class:
Permissions::query()
    ->permissions('edit_office')
    ->for($user)
    ->on($office)
    ->allow();

// Or with Permissions helper:
permissions('edit_office')
    ->for($user)
    ->on($office)
    ->allow();

```

### Removing Permissions

To remove (revoke) direct permissions from an actor (such as a user), you can use the deny method provided by the `HasPermissions` trait or via the `Permissions` class.

You simply call `deny()` passing the permission to remove:

```php
$user = User::find(1);

// Remove a direct permission by string or enum
$user->deny('edit_post');

// Or using enum
$user->deny(UserPermission::EditPost);
```

This will delete the record that directly grants this permission to the user.

You can also use the Permissions class:

```php
use EduLazaro\Larallow\Permissions;

Permissions::query()
    ->permissions('edit_post')  // or enum
    ->for($user)
    ->deny();

// Or with Permissions helper:
permissions('edit_office')
    ->for($user)
    ->deny();
```

You can also use the optional scope. If permissions are scoped to a specific model (e.g., a content item or project), you can specify the related model when denying the permission:

```php
$office = Office::find(1);

$user->deny('edit_office', $office);

// Or also
$user->permissions('edit_office')->on($office)->deny();

// Or with Permissions class:
Permissions::query()
    ->permissions('edit_office')
    ->for($user)
    ->on($office)
    ->deny();

// Or with Permissions helper:
permissions('edit_office')
    ->for($user)
    ->on($office)
    ->deny();

```

## Managing Roles

This setup allows you to manage roles and their associated permissions easily, keeping role definitions and permission assignments clear and flexible.

### Creating Roles

To create a new role, instantiate the Role model and save it. You can define the role’s attributes such as `handle`, `name`, `tenant`, `actor_type`, and allowed `scope_type`.

```php
use EduLazaro\Larallow\Models\Role;

$role = new Role();
$role->handle = 'office_manager';
$role->name = 'Office Manager';
$role->tenant_type = $tenant->getMorphClass();
$role->tenant_id = $tenant->id;
$role->actor_type = 'user'; // or App\Models\User
$role->scope_type = 'office'; // or App\Models\Office
$role->save();
```

### Removing Roles

To remove a role, you can simply delete the `Role` model instance:

```php
$role = Role::find($roleId);
$role->delete();
```

Make sure to detach any assignments or permissions related to this role before deleting to maintain data integrity.

The `remove()` method also removes assigned roles from the given actor. If a `scope` (scope model) is set, it removes only those role assignments tied to that scope via the pivot table. If no scope is set, it removes all assignments of those roles without scope.

```php
Roles::query()
    ->roles($roleOrRoleIds)
    ->for($actor)
    ->on($scopeModel)   // optional scope (e.g. an office, group)
    ->remove();

// Or also
roles($roleOrRoleIds)
    ->for($actor)
    ->on($scopeModel)   // optional scope (e.g. an office, group)
    ->remove();
```

### Assigning roles to actors

You can assing a role to a user:

```php
$user->assignRole($role);
```

You can also specify a scope or context model, which can be an organisaton, an office, a department... etc.

```php
$user->assignRole($role, $scopedModel);
```

The `tenant()` method allows you to optionally specify the tenant model (e.g., a Group, Company, or Organization) in a multi-tenant application context when working with roles. This enables scoping role assignments, removals, and checks to a particular tenant, ensuring roles belong to the correct tenant context.

```php
$tenant = Group::find(1); // Some tenant model

Roles::query()
    ->roles($roles)         // Roles to assign or check
    ->for($user)            // The actor (user, client, etc.)
    ->on($scopeModel)       // Optional scope model (e.g., Office)
    ->tenant($tenant)       // Optional tenant model (e.g., Group)
    ->assign();

// Or also

roles($roles)         // Roles to assign or check
    ->for($user)      // The actor (user, client, etc.)
    ->on($scopeModel) // Optional scope model (e.g., Office)
    ->tenant($tenant) // Optional tenant model (e.g., Group)
    ->assign();

```

During assignment, the method will throw an `InvalidArgumentException` if any role does not belong to the specified tenant, avoiding cross-tenant contamination.

## Checking Actor Roles

You can check roles using the Roles class:

### Checking any role

This will check if a role exists:

```php
$user->hasRole('admin');
```

If using scopes:

```php
$user->hasRole('admin', $scopedModel);
```

The `check()` method returns true if the actor has at least one of the specified roles assigned within the given scope (if any). If no scope is provided, it checks roles assigned without scope:

```php
$hasRole = Roles::query()
    ->roles($roleOrRoleIds)
    ->for($actor)
    ->on($scopeModel)  // Optional scope
    ->check();

// Or also
roles($roleOrRoleIds)
    ->for($actor)
    ->on($scopeModel)  // Optional scope
    ->check();

// Or also
$actor->roles($roleOrRoleIds)
    ->on($scopeModel)  // Optional scope
    ->check();
```

### Checking all roles

You can use Blade directives to check roles directly in your views:

```php
@roles(['admin', 'editor'])
    <p>You have some elevated role access.</p>
@endroles
```

The `checkAll()` method returns true if the actor has all of the specified roles assigned within the given scope (if any). If no scope is provided, it checks roles assigned without scope:


```php
$hasRole = Roles::query()
    ->roles($roleOrRoleIds)
    ->for($actor)
    ->on($scopeModel)  // Optional scope
    ->checkAll();

// Or also
roles($roleOrRoleIds)
    ->for($actor)
    ->on($scopeModel)  // Optional scope
    ->checkAll();

// Or also
$actor->roles($roleOrRoleIds)
    ->on($scopeModel)  // Optional scope
    ->checkAll();
```

You can use Blade directives to check roles directly in your views:

```php
@allroles(['admin', 'editor'])
    <p>You have full admin/editor access.</p>
@endallroles
```

## Managing role permissions

Permissions are attached to roles via the `RolePermission` model relationship.

Example to add permissions to a role:

```php
$role = Role::find($roleId);

$role->permissions()->create(['permission' => 'edit_office']);
$role->permissions()->create(['permission' => 'delete_office']);
```

Or add multiple permissions:

```php
foreach (['edit_office', 'delete_office', 'view_office'] as $permission) {
    $role->permissions()->create(['permission' => $permission]);
}

```

### Removing permissions from roles

To remove permissions from a role, you can delete the related `RolePermission` entries:

```php
$role = Role::find($roleId);

$role->permissions()->where('permission', 'edit_office')->delete();
```

To remove multiple permissions:

```php
$role->permissions()->whereIn('permission', ['edit_office', 'delete_office'])->delete();
```

### Removing roles from actors

You can remove a role from a user using the method `removeRole`:

```php
$user->removeRole($role);
```

Or if using an scope:

```php
$user->removeRole($role, $scopedModel);
```

## Checking Permissions

You can check both direct and role-based permissions using the Permissions class. There are two methods:

### Checking any permission

The method `check(): returns `true` if the user has any of the given permissions:

```php
use EduLazaro\Larallow\Permissions;

$allowed = Permissions::query()
    ->permissions([UserPermissions::EditPost, UserPermissions::ViewDashboard])
    ->for($user)
    ->check(); // true if at least one is granted
```

Or using e the `permissions` helper:

```php
$allowed = permissions([UserPermissions::EditPost, UserPermissions::ViewDashboard])
    ->for($user)
    ->check(); // true if at least one is granted
```

Or using e the `permissions` method:

```php
$allowed = $user->permissions('edit_post')  // or use enum
    ->check();
```

Or using the Blade Directive for Permissions:

```php
@permissions('edit_post')
    <button>Edit Post</button>
@endpermissions
```

You can also check the scope:

```php
@permissions('edit_post', $scope)
    <button>Edit Post</button>
@endpermissions
```


The directive accepts permission strings or enums:

```php
@permissions(\App\Enums\Permissions\UserPermission::EditPost)
    <button>Edit Post</button>
@endpermissions
```

### Checking all permissions

The method `checkAll()` returns `true` only if the user has all of the given permissions:

```php
$allowed = Permissions::query()
    ->permissions([UserPermissions::EditPost, UserPermissions::ViewDashboard])
    ->for($user)
    ->checkAll(); // true only if both are granted
```

Or using e the `permissions` helper:

```php
$allowed = permissions([UserPermissions::EditPost, UserPermissions::ViewDashboard])
    ->for($user)
    ->checkAll(); // true if all are granted
```

Or using e the `permissions` method:

```php
$allowed = $user->permissions('edit_post')  // or use enum
    ->checkAll();
```

Or using the Blade Directive for Permissions:

```php
@allpermissions('edit_post')
    <button>Edit Post</button>
@endallpermissions
```

The directive accepts permission strings or enums:

```php
@allpermissions(\App\Enums\Permissions\UserPermission::EditPost)
    <button>Edit Post</button>
@endallpermissions
```

You can also check the scope:

```php
@allpermissions('edit_post', $scope)
    <button>Edit Post</button>
@endallpermissions
```

## Checking Direct Permissions Only

Use the `hasPermission` method of the `HasPermissions` trait to check if a user has a permission directly assigned, excluding permissions assigned via roles:

```php
if ($user->hasPermission('edit_post')) {
    // permission granted
}
```

Or the `hasPermissions` method of the `HasPermissions` trait to check if a user has many permissions directly assigned, excluding permissions assigned via roles:

```php
if ($user->hasPermission(['edit_post','delete_post'])) {
    // permission granted
}
```

The `hasPermission()` method internally checks for direct permissions assigned to the user.


You can also retrieve the list of permissions directly assigned to an actor via the `permissions()` Eloquent relation:

```php
$permissions = $user->permissions; // Collection of ActorPermission models

foreach ($permissions as $permission) {
    echo $permission->permission; // string permission name
}
```

You can also query for specific permissions usig the Laravel query builder:

```php
$canEdit = $user->permissions()->where('permission', 'edit-post')->exists();
```

Or you can also do:

```php
$user->permissions([UserPermissions::EditPost, UserPermissions::ViewDashboard])
    ->on($scopedModel) // optional
    ->check();
```

You can also use the `Permissions` class:

```php
Permissions::query()
    ->permissions([UserPermissions::EditPost, UserPermissions::ViewDashboard])
    ->for($user)
    ->on($scopedModel) // optional
    ->check();
```

If using scopes:

```php
$result = Permissions::query()
    ->permissions(['edit_post', 'create_post'])
    ->for($user)
    ->on($scopedModel) // optional
    ->check();
```

Or using the permissions helper:

```php
$result = permissions(['edit_post', 'create_post'])
    ->for($user)
    ->on($scopedModel) // optional
    ->check();
```

## Translation Support

Roles in Larallow support multilingual translations without susing any other package. This allows you to define and retrieve the role's name in different languages.

If translations are provided, this is how you can get a role name in the current locale of your app:

```php
echo $role->name;
```

You can retrieve a specific translated version of the role name by specifying the desired locale. If a translation for the specified locale does not exist, a fallback can be used:

```php
$nameInSpanish = $role->getTranslation('name', 'es', 'Nombre por defecto'); 
```

You can also set the translation for a specific language using:

```php
$role->setTranslation('name', 'fr', 'Nom en Français');
$role->save();
```

## Testing

You can run the pacakge tests with:

```
./vendor/bin/phpunit
```

## Contributing

Contributions are welcome! Please fork the repo, add tests, and submit a PR.

## License

Larakeep is open-sourced software licensed under the [MIT license](LICENSE.md).
