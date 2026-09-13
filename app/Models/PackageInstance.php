<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OwnershipMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageInstance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'template_id',
        'wbs_node_id',
        'ownership_mode',
        'included_in_package_id',
        'selected',
        'applicable',
        'exclusion_reason',
        'zone_number',
        'block_number',
        'group_number',
        'topology_label',
        'name_override',
    ];

    protected function casts(): array
    {
        return [
            'ownership_mode' => OwnershipMode::class,
            'selected' => 'boolean',
            'applicable' => 'boolean',
            'zone_number' => 'integer',
            'block_number' => 'integer',
            'group_number' => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PackageTemplate::class, 'template_id');
    }

    public function wbsNode(): BelongsTo
    {
        return $this->belongsTo(WbsNode::class);
    }

    public function includedInPackage(): BelongsTo
    {
        return $this->belongsTo(PackageInstance::class, 'included_in_package_id');
    }

    public function includedPackages(): HasMany
    {
        return $this->hasMany(PackageInstance::class, 'included_in_package_id');
    }

    public function activityInstances(): HasMany
    {
        return $this->hasMany(ActivityInstance::class);
    }

    public function providerResolutions(): HasMany
    {
        return $this->hasMany(ProviderResolution::class, 'provider_package_instance_id');
    }

    public function equipmentNodes(): HasMany
    {
        return $this->hasMany(EquipmentNode::class);
    }
}