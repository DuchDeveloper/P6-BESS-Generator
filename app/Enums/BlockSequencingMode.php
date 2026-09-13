<?php

declare(strict_types=1);

namespace App\Enums;

enum BlockSequencingMode: string
{
    case FinishToStart = 'FS';
    case StartToStart = 'SS';
    case StartToStartWithLag = 'SS_LAG';

    public function label(): string
    {
        return match ($this) {
            self::FinishToStart => 'Finish to Start (sequential)',
            self::StartToStart => 'Start to Start (parallel)',
            self::StartToStartWithLag => 'Start to Start with Lag',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FinishToStart => "Next block's workfront releases only after the previous block's civil works are complete.",
            self::StartToStart => "All blocks in a zone release their workfront simultaneously.",
            self::StartToStartWithLag => "Each block's workfront releases N days after the previous block's workfront.",
        };
    }

    public function relationshipType(): RelationshipType
    {
        return match ($this) {
            self::FinishToStart => RelationshipType::FinishToStart,
            self::StartToStart, self::StartToStartWithLag => RelationshipType::StartToStart,
        };
    }

    public function requiresLag(): bool
    {
        return $this === self::StartToStartWithLag;
    }
}