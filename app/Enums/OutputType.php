<?php

declare(strict_types=1);

namespace App\Enums;

enum OutputType: string
{
    case ApprovalMilestone = 'approval_milestone';
    case DeliveryMilestone = 'delivery_milestone';
    case CompletionMilestone = 'completion_milestone';
    case BoundaryMilestone = 'boundary_milestone';

    public function label(): string
    {
        return match ($this) {
            self::ApprovalMilestone => 'Approval Milestone',
            self::DeliveryMilestone => 'Delivery Milestone',
            self::CompletionMilestone => 'Completion Milestone',
            self::BoundaryMilestone => 'Boundary Milestone',
        };
    }
}