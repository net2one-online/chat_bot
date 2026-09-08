<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['group' => 'gemini', 'key' => 'api_key', 'value' => env('OPENAI_API_KEY'), 'is_secret' => true],
            ['group' => 'gemini', 'key' => 'organization', 'value' => env('OPENAI_ORGANIZATION'), 'is_secret' => false],
            ['group' => 'gemini', 'key' => 'model', 'value' => env('OPENAI_MODEL', 'gpt-4o'), 'is_secret' => false],
            ['group' => 'gemini', 'key' => 'base_url', 'value' => env('OPENAI_BASE_URL'), 'is_secret' => false],
            ['group' => 'bitrix', 'key' => 'webhook_url', 'value' => env('BITRIX_WEBHOOK_URL'), 'is_secret' => true],
            ['group' => 'bitrix', 'key' => 'client_id', 'value' => env('BITRIX_CLIENT_ID'), 'is_secret' => false],
            ['group' => 'bitrix', 'key' => 'client_secret', 'value' => env('BITRIX_CLIENT_SECRET'), 'is_secret' => true],
            ['group' => 'bitrix', 'key' => 'domain', 'value' => env('BITRIX_DOMAIN'), 'is_secret' => false],
            ['group' => 'bitrix', 'key' => 'application_token', 'value' => env('BITRIX_APPLICATION_TOKEN'), 'is_secret' => true],
            ['group' => 'bitrix', 'key' => 'scope', 'value' => env('BITRIX_SCOPE'), 'is_secret' => false],
        ];

        foreach ($settings as $setting) {
            if ($setting['value'] === null || $setting['value'] === '') {
                continue;
            }

            Setting::updateOrCreate(
                ['group' => $setting['group'], 'key' => $setting['key']],
                ['value' => $setting['value'], 'is_secret' => $setting['is_secret']],
            );
        }
    }
}
