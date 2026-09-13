<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrerequisiteGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnergisationPrerequisite extends Model
{
    use HasFactory;

    protected $fillable = [
        'energisation_milestone_id',
        'group_name',
        'description',
        'required_output_key',
        'is_resolved',
        'resolved_by_activity_id',
    ];

    protected function casts(): array
    {
        return [
            'group_name' => PrerequisiteGroup::class,
            'is_resolved' => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function energisationMilestone(): BelongsTo
    {
        return $this->belongsTo(EnergisationMilestone::class);
    }

    public function resolvedByActivity(): BelongsTo
    {
        return $this->belongsTo(ActivityInstance::class, 'resolved_by_activity_id');
    }
}