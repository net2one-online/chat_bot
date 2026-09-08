<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    /**
     * Set of fallbacks mapping a DB setting key (group.key) to an env()
     * variable name, so values can come from the DB, the .env, or nothing.
     */
    protected array $envFallbacks = [
        'gemini.api_key' => 'OPENAI_API_KEY',
        'gemini.model' => 'OPENAI_MODEL',
        'gemini.base_url' => 'OPENAI_BASE_URL',
        'gemini.organization' => 'OPENAI_ORGANIZATION',
        'bitrix.webhook_url' => 'BITRIX_WEBHOOK_URL',
        'bitrix.client_id' => 'BITRIX_CLIENT_ID',
        'bitrix.client_secret' => 'BITRIX_CLIENT_SECRET',
        'bitrix.domain' => 'BITRIX_DOMAIN',
        'bitrix.application_token' => 'BITRIX_APPLICATION_TOKEN',
        'bitrix.scope' => 'BITRIX_SCOPE',
    ];

    /**
     * Get a setting value from the database, falling back to env() when
     * the setting is not present.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        [$group, $settingKey] = $this->parseKey($key);

        $value = $this->all()[$group][$settingKey] ?? null;

        if ($value !== null) {
            return $value;
        }

        $envVar = $this->envFallbacks[$key] ?? null;

        return $envVar ? env($envVar, $default) : $default;
    }

    /**
     * Store one or many settings (group.key => value). Values are upserted.
     */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            [$group, $settingKey] = $this->parseKey($key);

            $existing = Setting::where('group', $group)->where('key', $settingKey)->first();

            if ($existing) {
                $existing->update(['value' => $value]);
            } else {
                Setting::create([
                    'group' => $group,
                    'key' => $settingKey,
                    'value' => $value,
                    'is_secret' => $this->isSecretKey($key),
                ]);
            }
        }

        $this->clearCache();
    }

    /**
     * Forget a specific setting (returns the value to the env fallback).
     */
    public function forget(string $key): void
    {
        [$group, $settingKey] = $this->parseKey($key);

        Setting::where('group', $group)->where('key', $settingKey)->delete();

        $this->clearCache();
    }

    /**
     * True when a setting is stored in the database (not only via env).
     */
    public function hasInDb(string $key): bool
    {
        [$group, $settingKey] = $this->parseKey($key);

        return isset($this->all()[$group][$settingKey]);
    }

    /**
     * All settings grouped by group name, cached for the request lifetime.
     */
    public function all(): array
    {
        return Cache::remember('app.settings.all', 60, function () {
            return Setting::all()
                ->groupBy('group')
                ->mapWithKeys(function ($items, $group) {
                    return [$group => $items->pluck('value', 'key')->all()];
                })
                ->all();
        });
    }

    public function clearCache(): void
    {
        Cache::forget('app.settings.all');
    }

    protected function isSecretKey(string $key): bool
    {
        return str_contains($key, 'api_key')
            || str_contains($key, 'secret')
            || str_contains($key, 'client_secret');
    }

    protected function parseKey(string $key): array
    {
        $parts = explode('.', $key, 2);

        return [($parts[0] ?? ''), ($parts[1] ?? '')];
    }
}
