<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('welcome_menu_shown')->default(false)->after('human_mode');
            $table->unsignedTinyInteger('welcome_menu_attempts')->default(0)->after('welcome_menu_shown');
            $table->string('welcome_menu_choice')->nullable()->after('welcome_menu_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['welcome_menu_shown', 'welcome_menu_attempts', 'welcome_menu_choice']);
        });
    }
};
