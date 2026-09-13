<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RelationshipOrigin;
use App\Enums\RelationshipType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'predecessor_id',
        'successor_id',
        'relationship_type',
        'lag_days',
        'origin',
    ];

    protected function casts(): array
    {
        return [
            'relationship_type' => RelationshipType::class,
            'lag_days' => 'integer',
            'origin' => RelationshipOrigin::class,
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(ActivityInstance::class, 'predecessor_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(ActivityInstance::class, 'successor_id');
    }
}