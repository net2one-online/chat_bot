<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitrix_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('member_id')->unique()->index();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->string('client_endpoint', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('scope')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitrix_tokens');
    }
};
