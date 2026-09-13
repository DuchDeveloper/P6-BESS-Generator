<?php

declare(strict_types=1);

namespace App\Enums;

enum ConstraintType: string
{
    case None = 'none';
    case StartOnOrAfter = 'start_on_or_after';
    case FinishOnOrBefore = 'finish_on_or_before';
    case MandatoryStart = 'mandatory_start';
    case MandatoryFinish = 'mandatory_finish';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::StartOnOrAfter => 'Start On or After',
            self::FinishOnOrBefore => 'Finish On or Before',
            self::MandatoryStart => 'Mandatory Start',
            self::MandatoryFinish => 'Mandatory Finish',
        };
    }
}