<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitrix_tokens', function (Blueprint $table) {
            $table->text('gemini_api_key')->nullable()->after('setup_completed');
            $table->string('gemini_model')->nullable()->after('gemini_api_key');
            $table->string('gemini_base_url')->nullable()->after('gemini_model');
        });
    }

    public function down(): void
    {
        Schema::table('bitrix_tokens', function (Blueprint $table) {
            $table->dropColumn(['gemini_api_key', 'gemini_model', 'gemini_base_url']);
        });
    }
};
