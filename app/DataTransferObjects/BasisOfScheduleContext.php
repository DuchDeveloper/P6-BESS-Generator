<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * Snapshot of everything a Basis of Schedule needs to reason about
 * a project. Gathered once, then passed to deterministic builders
 * and LLM prompts so the two halves see identical facts.
 */
final readonly class BasisOfScheduleContext
{
    public function __construct(
        /** @var array<string, mixed> Project header — name, code, client, start, dates */
        public array $header,
        /** @var array<string, mixed> Topology — zones, blocks, groups, totals */
        public array $topology,
        /** @var array<string, mixed> Scope flags — substation, switchroom, BOP etc. */
        public array $scope,
        /** @var array<string, mixed> Delivery model, contracting arrangement */
        public array $delivery,
        /** @var array<string, mixed> Key milestone codes and dates */
        public array $milestones,
        /** @var array<int, array<string, mixed>> Compacted WBS tree summary */
        public array $wbs,
        /** @var array<string, int> Package counts by branch/discipline */
        public array $counts,
        /** @var array<string, mixed> Calendar — workdays per week, holiday count */
        public array $calendar,
        /** @var array<string, mixed> Schedule envelope — start, finish, duration, activity count */
        public array $schedule,
        /** @var array<int, array<string, mixed>> Long-lead procurement items with durations */
        public array $longLeads,
        /** @var array<string, mixed> Block sequencing config — zone parallelism, FS/SS mode */
        public array $sequencing,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'header' => $this->header,
            'topology' => $this->topology,
            'scope' => $this->scope,
            'delivery' => $this->delivery,
            'milestones' => $this->milestones,
            'wbs' => $this->wbs,
            'counts' => $this->counts,
            'calendar' => $this->calendar,
            'schedule' => $this->schedule,
            'long_leads' => $this->longLeads,
            'sequencing' => $this->sequencing,
        ];
    }
}