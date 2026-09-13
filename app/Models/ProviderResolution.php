<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResolutionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderResolution extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'output_key',
        'provider_package_instance_id',
        'provider_activity_instance_id',
        'resolution_type',
    ];

    protected function casts(): array
    {
        return [
            'resolution_type' => ResolutionType::class,
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function providerPackageInstance(): BelongsTo
    {
        return $this->belongsTo(PackageInstance::class, 'provider_package_instance_id');
    }

    public function providerActivityInstance(): BelongsTo
    {
        return $this->belongsTo(ActivityInstance::class, 'provider_activity_instance_id');
    }
}