<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InterfaceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterfaceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'from_node_id',
        'to_node_id',
        'interface_type',
        'generates_activities',
        'activity_types',
        'cable_type',
        'cable_count',
        'wbs_node_id',
        'completion_output_key',
    ];

    protected function casts(): array
    {
        return [
            'interface_type' => InterfaceType::class,
            'generates_activities' => 'boolean',
            'activity_types' => 'array',
            'cable_count' => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(EquipmentNode::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(EquipmentNode::class, 'to_node_id');
    }

    public function wbsNode(): BelongsTo
    {
        return $this->belongsTo(WbsNode::class);
    }
}