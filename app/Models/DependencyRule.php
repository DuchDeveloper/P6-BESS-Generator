<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DependencyRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumer_template_id',
        'required_output_key',
        'gates_activity_sequence',
        'is_mandatory',
    ];

    protected function casts(): array
    {
        return [
            'gates_activity_sequence' => 'integer',
            'is_mandatory' => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function consumerTemplate(): BelongsTo
    {
        return $this->belongsTo(PackageTemplate::class, 'consumer_template_id');
    }
}