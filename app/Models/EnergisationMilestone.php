<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnergisationMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'output_key',
        'sequence',
        'selected',
        'applicable',
        'activity_instance_id',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'selected' => 'boolean',
            'applicable' => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function activityInstance(): BelongsTo
    {
        return $this->belongsTo(ActivityInstance::class);
    }

    public function prerequisites(): HasMany
    {
        return $this->hasMany(EnergisationPrerequisite::class);
    }
}