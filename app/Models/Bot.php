<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bot extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'member_id',
        'name',
        'bitrix_bot_id',
        'bot_token',
        'openline_id',
        'status',
        'system_prompt',
        'welcome_menu',
    ];

    protected $casts = [
        'welcome_menu' => 'array',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function knowledgeDocuments(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function menuEnabled(): bool
    {
        return ! empty($this->welcome_menu['enabled'])
            && ! empty($this->welcome_menu['options']);
    }

    public function menuGreeting(): string
    {
        return trim((string) ($this->welcome_menu['greeting'] ?? ''));
    }

    public function menuOptions(): array
    {
        return $this->welcome_menu['options'] ?? [];
    }
}
