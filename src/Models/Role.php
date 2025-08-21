<?php

namespace EduLazaro\Larallow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use EduLazaro\Larallow\Models\RolePermission;
use EduLazaro\Larallow\Observers\RoleObserver;

#[ObservedBy(RoleObserver::class)]
class Role extends Model
{
    protected $fillable = [
        'tenant_type',
        'tenant_id',
        'scope_type',
        'actor_type',
        'handle',
        'name',
        'translations',
    ];

    protected $casts = [
        'translations' => 'array',
    ];

    /**
     * Get the polymorphic tenant model.
     *
     * @return MorphTo
     */
    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the permissions associated with this role.
     *
     * @return HasMany
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * Get all the actor roles
     *
     * @return HasMany
     */
    public function actorRoles(): HasMany
    {
        return $this->hasMany(ActorRole::class);
    }

    /**
     * Scope to get an array of id => name from an existing query.
     *
     * @return array<int, string>
     */
    public function scopeOptions($query): array
    {
        return $query->pluck('name', 'roles.id')->toArray();
    }

    /**
     * Scope to get an array of primary keys from an existing query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return array<int|string>
     */
    public function scopeKeys($query): array
    {
        $model = $query->getModel();
        $keyName = $model->getQualifiedKeyName();

        return $query->pluck('roles.' . $keyName)->toArray();
    }

    /**
     * Scope to filter by polymorphic tenant.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model|null $tenant
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereTenant($query, ?Model $tenant)
    {
        if ($tenant) {
            return $query
                ->where('tenant_type', $tenant->getMorphClass())
                ->where('tenant_id', $tenant->getKey());
        }

        // If no tenant provided, return query unmodified or filter NULL if desired
        return $query;
    }

    /**
     * Scope to filter by polymorphic tenant.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model|null $tenant
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereScope($query, ?Model $scopeable)
    {
        if ($scopeable) {
            return $query
                ->where('actor_role.scope_type', $scopeable->getMorphClass())
                ->where('actor_role.scope_id', $scopeable->getKey());
        }

        return $query;
    }

    /**
     * Scope to filter by actor_type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $actorType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereActorType($query, string $actorType)
    {
        return $query->where('actor_type', $actorType);
    }

    /**
     * Scope to filter by scope_type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $scopeType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereScopeType($query, string $scopeType)
    {
        return $query->where('scope_type', $scopeType);
    }

    /**
     * Get the translated name based on the current locale,
     *
     * @return string|null
     */
    public function getNameAttribute(?string $value): ?string
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        if (!empty($this->translations['name'][$locale])) {
            return $this->translations['name'][$locale];
        }

        if (!empty($this->translations['name'][$fallbackLocale])) {
            return $this->translations['name'][$fallbackLocale];
        }

        return $value;
    }

    /**
     * Get a translation for a given field and locale.
     *
     * @param string $field e.g. 'name'
     * @param string|null $locale Locale code, defaults to current app locale
     * @param string|null $default Fallback if translation not found
     * @return string|null
     */
    public function getTranslation(string $field, ?string $locale = null, $default = null)
    {
        $locale = $locale ?? app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        if (!empty($this->translations[$field][$locale])) {
            return $this->translations[$field][$locale];
        }

        if (!empty($this->translations[$field][$fallbackLocale])) {
            return $this->translations[$field][$fallbackLocale];
        }

        if ($this->getAttribute($field) !== null) {
            return $this->getAttribute($field);
        }

        return $default;
    }

    /**
     * Set a translation for a given field and locale.
     *
     * @param string $field e.g. 'name'
     * @param string $locale Locale code
     * @param string $value The translated string
     * @return void
     */
    public function setTranslation(string $field, string $locale, string $value): void
    {
        $translations = $this->translations ?? [];

        if (!isset($translations[$field]) || !is_array($translations[$field])) {
            $translations[$field] = [];
        }

        $translations[$field][$locale] = $value;

        $this->translations = $translations;
    }
}
