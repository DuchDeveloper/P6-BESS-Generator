<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConstraintType;
use App\Enums\P6TaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityInstance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'package_instance_id',
        'wbs_node_id',
        'activity_code',
        'name',
        'duration_days',
        'is_milestone',
        'sequence',
        'early_start_date',
        'early_finish_date',
        'late_start_date',
        'late_finish_date',
        'constraint_type',
        'constraint_date',
        'p6_task_type',
        'p6_task_id',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'is_milestone' => 'boolean',
            'sequence' => 'integer',
            'early_start_date' => 'date',
            'early_finish_date' => 'date',
            'late_start_date' => 'date',
            'late_finish_date' => 'date',
            'constraint_type' => ConstraintType::class,
            'constraint_date' => 'date',
            'p6_task_type' => P6TaskType::class,
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function packageInstance(): BelongsTo
    {
        return $this->belongsTo(PackageInstance::class);
    }

    public function wbsNode(): BelongsTo
    {
        return $this->belongsTo(WbsNode::class);
    }

    public function predecessorRelationships(): HasMany
    {
        return $this->hasMany(Relationship::class, 'successor_id');
    }

    public function successorRelationships(): HasMany
    {
        return $this->hasMany(Relationship::class, 'predecessor_id');
    }

    public function providerResolutions(): HasMany
    {
        return $this->hasMany(ProviderResolution::class, 'provider_activity_instance_id');
    }

    public function energisationMilestones(): HasMany
    {
        return $this->hasMany(EnergisationMilestone::class);
    }
}