<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ValidationSeverity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidationError extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'rule_class',
        'severity',
        'message',
        'context',
        'resolved',
        'resolved_at',
        'resolution_note',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'severity' => ValidationSeverity::class,
            'context' => 'array',
            'resolved' => 'boolean',
            'resolved_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}