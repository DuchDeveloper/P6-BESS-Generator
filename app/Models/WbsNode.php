<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WbsCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WbsNode extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'parent_id',
        'code',
        'name',
        'level',
        'sort_order',
        'wbs_category',
        'p6_wbs_id',
        'p6_wbs_short_name',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'sort_order' => 'integer',
            'wbs_category' => WbsCategory::class,
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WbsNode::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WbsNode::class, 'parent_id');
    }

    public function packageInstances(): HasMany
    {
        return $this->hasMany(PackageInstance::class);
    }

    public function activityInstances(): HasMany
    {
        return $this->hasMany(ActivityInstance::class);
    }
}