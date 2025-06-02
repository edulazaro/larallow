<?php

namespace EduLazaro\Larallow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use EduLazaro\Larallow\Models\RolePermission;

class Role extends Model
{
    protected $fillable = [
        'tenant_type',
        'tenant_id',
        'scopable_types',
        'actor_types',
        'handle',
        'name',
        'translations',
    ];

    protected $casts = [
        'scopable_types' => 'array',
        'actor_types' => 'array',
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
