<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EquipmentNodeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentNode extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'type',
        'zone_number',
        'block_number',
        'group_number',
        'label',
        'unit_from',
        'unit_to',
        'package_instance_id',
        'ready_output_key',
        'is_partial',
        'actual_unit_count',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => EquipmentNodeType::class,
            'zone_number' => 'integer',
            'block_number' => 'integer',
            'group_number' => 'integer',
            'unit_from' => 'integer',
            'unit_to' => 'integer',
            'is_partial' => 'boolean',
            'actual_unit_count' => 'integer',
            'sort_order' => 'integer',
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

    public function outgoingInterfaces(): HasMany
    {
        return $this->hasMany(InterfaceRecord::class, 'from_node_id');
    }

    public function incomingInterfaces(): HasMany
    {
        return $this->hasMany(InterfaceRecord::class, 'to_node_id');
    }
}