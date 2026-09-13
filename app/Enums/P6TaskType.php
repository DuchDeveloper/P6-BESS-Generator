<?php

declare(strict_types=1);

namespace App\Enums;

enum P6TaskType: string
{
    case Task = 'TT_Task';
    case Milestone = 'TT_Mile';
    case FinishMilestone = 'TT_FinMile';
    case LevelOfEffort = 'TT_LOE';

    public function label(): string
    {
        return match ($this) {
            self::Task => 'Task Dependent',
            self::Milestone => 'Start Milestone',
            self::FinishMilestone => 'Finish Milestone',
            self::LevelOfEffort => 'Level of Effort',
        };
    }
}