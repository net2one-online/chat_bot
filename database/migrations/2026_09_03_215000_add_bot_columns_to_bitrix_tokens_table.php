<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitrix_tokens', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('client_endpoint');
            $table->string('file_bot_id')->nullable()->after('scope');
            $table->string('file_bot_token')->nullable()->after('file_bot_id');
        });
    }

    public function down(): void
    {
        Schema::table('bitrix_tokens', function (Blueprint $table) {
            $table->dropColumn(['domain', 'file_bot_id', 'file_bot_token']);
        });
    }
};