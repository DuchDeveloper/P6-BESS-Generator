<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Calendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'workdays_per_week',
        'working_days',
        'holidays',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'workdays_per_week' => 'integer',
            'working_days' => 'array',
            'holidays' => 'array',
            'is_default' => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}