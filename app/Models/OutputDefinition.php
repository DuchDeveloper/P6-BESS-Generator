<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OutputType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutputDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_template_id',
        'output_key',
        'output_name',
        'output_type',
        'produced_by_sequence',
    ];

    protected function casts(): array
    {
        return [
            'output_type' => OutputType::class,
            'produced_by_sequence' => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function packageTemplate(): BelongsTo
    {
        return $this->belongsTo(PackageTemplate::class);
    }
}