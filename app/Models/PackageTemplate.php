<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Discipline;
use App\Enums\MaturityLevel;
use App\Enums\OwnershipMode;
use App\Enums\PackageType;
use App\Enums\WbsCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'discipline',
        'wbs_category',
        'construction_wbs_group',
        'maturity_level',
        'ownership_mode_default',
        'is_optional',
        'is_conditional',
        'condition_flag',
        'is_topology_driven',
        'has_dynamic_predecessors',
        'dynamic_predecessor_maturity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => PackageType::class,
            'discipline' => Discipline::class,
            'wbs_category' => WbsCategory::class,
            'maturity_level' => MaturityLevel::class,
            'ownership_mode_default' => OwnershipMode::class,
            'is_optional' => 'boolean',
            'is_conditional' => 'boolean',
            'is_topology_driven' => 'boolean',
            'has_dynamic_predecessors' => 'boolean',
            'dynamic_predecessor_maturity' => MaturityLevel::class,
            'sort_order' => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function activityTemplates(): HasMany
    {
        return $this->hasMany(ActivityTemplate::class)->orderBy('sequence');
    }

    public function outputDefinitions(): HasMany
    {
        return $this->hasMany(OutputDefinition::class);
    }

    public function dependencyRules(): HasMany
    {
        return $this->hasMany(DependencyRule::class, 'consumer_template_id');
    }

    public function packageInstances(): HasMany
    {
        return $this->hasMany(PackageInstance::class, 'template_id');
    }
}