<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExportProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'format',
        'p6_version',
        'settings',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_default' => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}