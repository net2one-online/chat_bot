<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'member_id',
        'bot_id',
        'bitrix_chat_id',
        'bitrix_session_id',
        'contact_id',
        'status',
        'human_mode',
        'welcome_menu_shown',
        'welcome_menu_attempts',
        'welcome_menu_choice',
    ];

    protected $casts = [
        'human_mode' => 'boolean',
        'welcome_menu_shown' => 'boolean',
        'welcome_menu_attempts' => 'integer',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'contact_id', 'bitrix_bot_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function isHumanMode(): bool
    {
        return $this->human_mode === true;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getRecentMessages(int $limit = 10): Collection
    {
        return $this->messages()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
}
