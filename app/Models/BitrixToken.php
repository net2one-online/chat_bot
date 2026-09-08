<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BitrixToken extends Model
{
    protected $fillable = [
        'member_id',
        'access_token',
        'refresh_token',
        'client_endpoint',
        'domain',
        'expires_at',
        'scope',
        'file_bot_id',
        'file_bot_token',
        'setup_completed',
        'gemini_api_key',
        'gemini_model',
        'gemini_base_url',
        'user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'setup_completed' => 'boolean',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $seconds = 300): bool
    {
        return $this->expires_at && $this->expires_at->isPast(Carbon::now()->addSeconds($seconds));
    }

    public function hasToken(): bool
    {
        return ! empty($this->access_token);
    }
}
