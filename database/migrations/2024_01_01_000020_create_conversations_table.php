<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('bitrix_chat_id');
            $table->string('bitrix_session_id')->nullable();
            $table->string('contact_id')->nullable();
            $table->string('status')->default('active');
            $table->boolean('human_mode')->default(false);
            $table->timestamps();

            $table->index(['bot_id', 'bitrix_chat_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
