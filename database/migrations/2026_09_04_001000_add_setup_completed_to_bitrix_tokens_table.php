<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitrix_tokens', function (Blueprint $table) {
            $table->boolean('setup_completed')->default(false)->after('file_bot_token');
        });
    }

    public function down(): void
    {
        Schema::table('bitrix_tokens', function (Blueprint $table) {
            $table->dropColumn('setup_completed');
        });
    }
};