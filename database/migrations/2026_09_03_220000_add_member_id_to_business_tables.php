<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['bots', 'conversations', 'messages', 'knowledge_documents'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('member_id')->nullable()->index()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (['bots', 'conversations', 'messages', 'knowledge_documents'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('member_id');
            });
        }
    }
};