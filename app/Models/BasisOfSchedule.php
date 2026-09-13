<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasisOfSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'version',
        'status',
        'sections',
        'llm_model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'project_fingerprint',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'generated_at' => 'datetime',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'version' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}