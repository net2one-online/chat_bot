<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeDocument extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'member_id',
        'bot_id',
        'title',
        'content',
        'source',
        'file_name',
        'file_mime',
        'file_data',
    ];

    protected $casts = [
        'file_data' => 'string',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
