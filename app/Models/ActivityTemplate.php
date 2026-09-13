<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\P6TaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_template_id',
        'sequence',
        'name',
        'duration_days',
        'is_milestone',
        'predecessor_sequence',
        'p6_task_type',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'duration_days' => 'integer',
            'is_milestone' => 'boolean',
            'predecessor_sequence' => 'integer',
            'p6_task_type' => P6TaskType::class,
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function packageTemplate(): BelongsTo
    {
        return $this->belongsTo(PackageTemplate::class);
    }
}