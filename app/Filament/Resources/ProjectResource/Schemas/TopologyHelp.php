<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectResource\Schemas;

use Filament\Schemas\Components\View;

/**
 * Plain-English help for the site layout fields, shared by the create wizard
 * and the edit form. The examples add up: 2 zones × 2 blocks × (5 groups × 4
 * batteries) = 80 batteries.
 */
final class TopologyHelp
{
    /**
     * Helper text for a topology field. Integrated-container projects count
     * containers rather than batteries.
     */
    public static function for(string $field, bool $containers = false): string
    {
        $unit = $containers ? 'container' : 'battery';
        $units = $containers ? 'containers' : 'batteries';

        return match ($field) {
            'total_battery_count' => "Every {$unit} on the site. The zones and blocks below must add up to this, or validation blocks export. e.g. 80",
            'zone_count' => 'A large area of the site — its own stage, boundary or grid connection. Zones are built in parallel. e.g. 2 gives Zone 1 and Zone 2',
            'blocks_per_zone' => 'A self-contained work area inside a zone, built as one workfront. e.g. 2 per zone × 2 zones = 4 blocks',
            'batteries_per_group' => ucfirst($units) . ' installed together as one job — one installation activity in the schedule. e.g. 4',
            'battery_groups_per_block' => "How many of those groups each block has. e.g. 5 groups × 4 = 20 {$units} per block",
            'pcs_per_group' => 'PCS (inverter) units installed together as one job. A PCS turns battery DC into AC. e.g. 2',
            'pcs_groups_per_block' => 'e.g. 5 groups × 2 = 10 PCS per block. Battery group N is cabled to PCS group N, so this usually matches the battery groups',
            'suts_per_block' => 'Step-up transformers in each block. They raise the PCS output to medium voltage for the run to the switchroom. e.g. 2',
            default => '',
        };
    }

    /**
     * The site hierarchy diagram with a live summary of the numbers entered.
     *
     * @param  string|null  $architecture  Fallback when the form has no bess_architecture field (edit form)
     * @param  int|null  $totalBatteryCount  Fallback when the form has no total_battery_count field
     */
    public static function guide(?string $architecture = null, ?int $totalBatteryCount = null): View
    {
        return View::make('filament.components.site-layout-guide')
            ->viewData([
                'architecture' => $architecture,
                'totalBatteryCount' => $totalBatteryCount,
            ]);
    }
}
