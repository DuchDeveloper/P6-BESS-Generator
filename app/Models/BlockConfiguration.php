<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'zone_configuration_id',
        'zone_number',
        'block_number',
        'block_label',
        'battery_count',
        'battery_group_size',
        'pcs_count',
        'pcs_group_size',
        'sut_count',
        'full_battery_group_count',
        'battery_remainder',
        'has_battery_partial_group',
        'full_pcs_group_count',
        'pcs_remainder',
        'has_pcs_partial_group',
        'battery_remainder_decision',
        'pcs_remainder_decision',
        'is_configured',
    ];

    protected function casts(): array
    {
        return [
            'zone_number' => 'integer',
            'block_number' => 'integer',
            'battery_count' => 'integer',
            'battery_group_size' => 'integer',
            'pcs_count' => 'integer',
            'pcs_group_size' => 'integer',
            'sut_count' => 'integer',
            'full_battery_group_count' => 'integer',
            'battery_remainder' => 'integer',
            'has_battery_partial_group' => 'boolean',
            'full_pcs_group_count' => 'integer',
            'pcs_remainder' => 'integer',
            'has_pcs_partial_group' => 'boolean',
            'is_configured' => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function zoneConfiguration(): BelongsTo
    {
        return $this->belongsTo(ZoneConfiguration::class);
    }

    // ── Calculation ──────────────────────────────────────────

    /**
     * Recalculate group counts and remainders from user inputs.
     */
    public function recalculate(): void
    {
        // Batteries
        $this->full_battery_group_count = intdiv($this->battery_count, $this->battery_group_size);
        $this->battery_remainder = $this->battery_count % $this->battery_group_size;
        $this->has_battery_partial_group = $this->battery_remainder > 0;

        // PCS
        if ($this->pcs_count > 0 && $this->pcs_group_size > 0) {
            $this->full_pcs_group_count = intdiv($this->pcs_count, $this->pcs_group_size);
            $this->pcs_remainder = $this->pcs_count % $this->pcs_group_size;
            $this->has_pcs_partial_group = $this->pcs_remainder > 0;
        }

        // Reset remainder decisions if values changed
        if ($this->battery_remainder === 0) {
            $this->battery_remainder_decision = 'accepted';
        }
        if ($this->pcs_remainder === 0) {
            $this->pcs_remainder_decision = 'accepted';
        }

        // Configuration gate
        $this->is_configured = $this->isFullyConfigured();

        $this->save();
    }

    /**
     * Check if all remainder decisions are resolved.
     */
    public function isFullyConfigured(): bool
    {
        if ($this->has_battery_partial_group && $this->battery_remainder_decision === 'pending') {
            return false;
        }
        if ($this->has_pcs_partial_group && $this->pcs_remainder_decision === 'pending') {
            return false;
        }

        return true;
    }

    /**
     * Total battery groups including partial.
     */
    public function totalBatteryGroups(): int
    {
        return $this->full_battery_group_count + ($this->has_battery_partial_group ? 1 : 0);
    }

    /**
     * Total PCS groups including partial.
     */
    public function totalPcsGroups(): int
    {
        return $this->full_pcs_group_count + ($this->has_pcs_partial_group ? 1 : 0);
    }

    public function displayLabel(): string
    {
        return $this->block_label ?? "Block {$this->block_number}";
    }
}