<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZoneConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'zone_number',
        'zone_label',
        'block_count',
        'total_batteries',
        'total_groups',
    ];

    protected function casts(): array
    {
        return [
            'zone_number' => 'integer',
            'block_count' => 'integer',
            'total_batteries' => 'integer',
            'total_groups' => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function blockConfigurations(): HasMany
    {
        return $this->hasMany(BlockConfiguration::class)->orderBy('block_number');
    }

    // ── Helpers ──────────────────────────────────────────────

    public function recalculateTotals(): void
    {
        $blocks = $this->blockConfigurations;

        $this->update([
            'total_batteries' => $blocks->sum('battery_count'),
            'total_groups' => $blocks->sum(fn (BlockConfiguration $b) => $b->full_battery_group_count + ($b->has_battery_partial_group ? 1 : 0)),
        ]);
    }

    public function displayLabel(): string
    {
        return $this->zone_label ?? "Zone {$this->zone_number}";
    }
}