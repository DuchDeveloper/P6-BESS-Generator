@php
    /** @var \App\Models\BasisOfSchedule $bos */
    /** @var \App\Models\Project $project */
    $s = $bos->sections;
    $header = $s['header'] ?? [];
    $topology = $s['topology'] ?? [];
    $scope = $s['scope'] ?? [];
    $delivery = $s['delivery'] ?? [];
    $milestones = $s['milestones'] ?? [];
    $counts = $s['counts'] ?? [];
    $calendar = $s['calendar'] ?? [];
    $schedule = $s['schedule'] ?? [];
    $longLeads = $s['long_leads'] ?? [];
    $sequencing = $s['sequencing'] ?? [];
    $md = fn (?string $text) => $text ? \Illuminate\Support\Str::markdown($text) : '<p><em>Not generated.</em></p>';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Basis of Schedule — {{ $header['name'] ?? $project->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 920px;
            margin: 40px auto;
            padding: 0 24px;
            color: #111;
            line-height: 1.55;
            font-size: 14px;
        }
        h1 { font-size: 28px; margin: 0 0 4px; }
        h1 + .sub { color: #666; margin-bottom: 32px; }
        h2 {
            font-size: 18px; margin: 32px 0 12px;
            padding-bottom: 6px; border-bottom: 2px solid #1f2937;
            color: #1f2937;
        }
        h3 { font-size: 14px; margin: 18px 0 6px; color: #374151; }
        .narrative h2 { font-size: 14px; margin: 16px 0 6px; padding: 0; border: 0; color: #4b5563; text-transform: uppercase; letter-spacing: 0.5px; }
        .narrative h3 { font-size: 13px; }
        .narrative p { margin: 0 0 10px; }
        .narrative ul { margin: 0 0 12px 20px; padding: 0; }
        .narrative li { margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 16px; font-size: 13px; }
        th, td { text-align: left; padding: 6px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { color: #4b5563; font-weight: 600; width: 32%; background: #f9fafb; }
        .pill {
            display: inline-block; padding: 2px 9px; border-radius: 10px;
            background: #eef2ff; color: #3730a3; font-size: 11px; margin: 2px 4px 2px 0;
        }
        .pill.off { background: #f3f4f6; color: #9ca3af; }
        .narrative { font-size: 14px; }
        .actions { margin-bottom: 24px; }
        .actions a {
            display: inline-block; padding: 6px 12px; border: 1px solid #ddd; border-radius: 6px;
            color: #333; text-decoration: none; font-size: 13px; margin-right: 8px;
        }
        @media print { .actions { display: none; } body { margin: 20px auto; } }
        .toc { background: #f9fafb; border: 1px solid #e5e7eb; padding: 12px 18px; border-radius: 6px; margin-bottom: 24px; }
        .toc ol { margin: 0 0 0 18px; padding: 0; }
        .toc li { margin: 2px 0; font-size: 13px; }
        code { font-family: 'Menlo', 'Consolas', monospace; font-size: 12px; }
    </style>
</head>
<body>
    <div class="actions">
        <a href="javascript:window.print()">Print / Save PDF</a>
        <a href="{{ url()->previous() }}">Back</a>
    </div>

    <h1>Basis of Schedule</h1>
    <div class="sub">
        {{ $header['name'] ?? $project->name }}
        · Code {{ $header['code'] ?? '—' }}
        · Version {{ $bos->version }}
        · {{ $bos->generated_at?->toDateString() }}
    </div>

    <div class="toc">
        <strong>Contents</strong>
        <ol>
            <li>Project Header</li>
            <li>Schedule Envelope</li>
            <li>Scope Narrative</li>
            <li>Delivery Approach</li>
            <li>Topology</li>
            <li>Scope Inclusions</li>
            <li>Sequencing &amp; Logic</li>
            <li>Calendar</li>
            <li>Long-Lead Procurement</li>
            <li>Key Milestones</li>
            <li>WBS Summary</li>
            <li>Key Assumptions</li>
            <li>Schedule Risks</li>
            <li>Exclusions</li>
        </ol>
    </div>

    <h2>1. Project Header</h2>
    <table>
        <tr><th>Project</th><td>{{ $header['name'] ?? '—' }}</td></tr>
        <tr><th>Code</th><td>{{ $header['code'] ?? '—' }}</td></tr>
        <tr><th>Client</th><td>{{ $header['client'] ?? '—' }}</td></tr>
        <tr><th>Description</th><td>{{ $header['description'] ?? '—' }}</td></tr>
        <tr><th>Start date</th><td>{{ $header['start_date'] ?? '—' }}</td></tr>
        <tr><th>Delivery model</th><td>{{ $delivery['model_label'] ?? $delivery['model'] ?? '—' }}</td></tr>
        <tr><th>Status</th><td>{{ $header['status'] ?? '—' }}</td></tr>
    </table>

    <h2>2. Schedule Envelope</h2>
    @if (! ($schedule['compiled'] ?? false))
        <p><em>Schedule has not been compiled yet — dates and durations will populate after the Compile step.</em></p>
        <table>
            <tr><th>Planned start</th><td>{{ $schedule['planned_start'] ?? '—' }}</td></tr>
        </table>
    @else
        <table>
            <tr><th>Planned start</th><td>{{ $schedule['planned_start'] ?? '—' }}</td></tr>
            <tr><th>Planned finish</th><td>{{ $schedule['planned_finish'] ?? '—' }}</td></tr>
            <tr><th>Total duration</th>
                <td>
                    {{ $schedule['duration_days'] ?? '—' }} days
                    @if (! empty($schedule['duration_months']))
                        (~{{ $schedule['duration_months'] }} months)
                    @endif
                </td>
            </tr>
            <tr><th>Activities</th><td>{{ number_format($schedule['activity_count'] ?? 0) }}</td></tr>
            <tr><th>Milestones</th><td>{{ number_format($schedule['milestone_count'] ?? 0) }}</td></tr>
            <tr><th>Relationships</th><td>{{ number_format($schedule['relationship_count'] ?? 0) }}</td></tr>
        </table>
    @endif

    <h2>3. Scope Narrative</h2>
    <div class="narrative">
        {!! $md($s['scope_narrative'] ?? null) !!}
    </div>

    <h2>4. Delivery Approach</h2>
    <div class="narrative">
        {!! $md($s['delivery_narrative'] ?? null) !!}
    </div>

    <h2>5. Topology</h2>
    <table>
        <tr><th>Zones</th><td>{{ $topology['zones'] ?? '—' }}</td></tr>
        <tr><th>Blocks per zone</th><td>{{ $topology['blocks_per_zone'] ?? '—' }}</td></tr>
        <tr><th>Total blocks</th><td>{{ $topology['total_blocks'] ?? '—' }}</td></tr>
        <tr><th>Battery groups per block</th><td>{{ $topology['battery_groups_per_block'] ?? '—' }}</td></tr>
        <tr><th>Batteries per group</th><td>{{ $topology['batteries_per_group'] ?? '—' }}</td></tr>
        <tr><th>PCS groups per block</th><td>{{ $topology['pcs_groups_per_block'] ?? '—' }}</td></tr>
        <tr><th>PCS per group</th><td>{{ $topology['pcs_per_group'] ?? '—' }}</td></tr>
        <tr><th>SUTs per block</th><td>{{ $topology['suts_per_block'] ?? '—' }}</td></tr>
        <tr><th>Total batteries</th><td>{{ $topology['total_batteries'] ?? '—' }}</td></tr>
        <tr><th>Architecture</th><td>{{ $topology['architecture'] ?? '—' }}</td></tr>
    </table>

    <h2>6. Scope Inclusions</h2>
    <h3>Core HV / MV plant</h3>
    @foreach (['switchroom','control_room','transformer','substation','scada','bess_free_issued','hvac_in_vendor_package','fire_in_vendor_package'] as $k)
        <span class="pill {{ ($scope[$k] ?? false) ? '' : 'off' }}">
            {{ str_replace('_', ' ', $k) }}: {{ ($scope[$k] ?? false) ? 'yes' : 'no' }}
        </span>
    @endforeach

    @foreach (['buildings' => 'Buildings', 'site_civil' => 'Site Civil', 'site_ei' => 'Site E&I', 'safety_fire' => 'Safety & Fire'] as $key => $label)
        @if (! empty($scope[$key]))
            <h3>{{ $label }}</h3>
            @foreach ($scope[$key] as $name => $on)
                <span class="pill {{ $on ? '' : 'off' }}">{{ str_replace('_', ' ', $name) }}</span>
            @endforeach
        @endif
    @endforeach

    <h2>7. Sequencing &amp; Logic</h2>
    <table>
        <tr><th>Zone strategy</th><td>Zones run in parallel — independent work fronts.</td></tr>
        <tr><th>Block sequencing mode</th>
            <td>
                {{ $sequencing['block_sequencing_mode_label'] ?? $sequencing['block_sequencing_mode'] ?? '—' }}
                @if (($sequencing['block_sequencing_lag_days'] ?? 0) > 0)
                    (lag {{ $sequencing['block_sequencing_lag_days'] }}&nbsp;d)
                @endif
            </td>
        </tr>
        <tr><th>Mechanical install gate</th>
            <td>{{ $sequencing['mech_install_gate'] ?? 'Civil sign-off + bulk equipment delivery' }}</td>
        </tr>
    </table>
    <div class="narrative">
        {!! $md($s['sequencing_rationale'] ?? null) !!}
    </div>

    <h2>8. Calendar</h2>
    <table>
        <tr><th>Calendar</th><td>{{ $calendar['name'] ?? 'Default' }}</td></tr>
        <tr><th>Workdays per week</th><td>{{ $calendar['workdays_per_week'] ?? '—' }}</td></tr>
        <tr><th>Holidays defined</th><td>{{ $calendar['holiday_count'] ?? 0 }}</td></tr>
    </table>

    <h2>9. Long-Lead Procurement</h2>
    @if (! empty($longLeads))
        <table>
            <tr><th style="width:18%">Code</th><th>Package</th><th style="width:20%">Ownership</th></tr>
            @foreach ($longLeads as $ll)
                <tr>
                    <td><code>{{ $ll['code'] ?? '' }}</code></td>
                    <td>{{ $ll['name'] ?? '' }}</td>
                    <td>{{ $ll['ownership'] ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p><em>No procurement packages selected.</em></p>
    @endif

    <h2>10. Key Milestones</h2>
    @if (! empty($milestones))
        <table>
            <tr><th style="width:8%">Seq</th><th>Name</th><th style="width:40%">Output Key</th></tr>
            @foreach ($milestones as $m)
                <tr>
                    <td>{{ $m['sequence'] ?? '' }}</td>
                    <td>{{ $m['name'] ?? '' }}</td>
                    <td><code>{{ $m['output_key'] ?? '' }}</code></td>
                </tr>
            @endforeach
        </table>
    @else
        <p><em>No energisation milestones configured.</em></p>
    @endif

    <h2>11. WBS Summary</h2>
    <table>
        <tr><th style="width:15%">Code</th><th>Name</th><th style="width:12%">Level</th></tr>
        @foreach (($s['wbs'] ?? []) as $n)
            <tr>
                <td><code>{{ $n['code'] }}</code></td>
                <td>{{ $n['name'] }}</td>
                <td>{{ $n['level'] }}</td>
            </tr>
        @endforeach
    </table>

    <h3>Package counts</h3>
    <table>
        @foreach ($counts as $k => $v)
            <tr><th>{{ str_replace('_',' ',$k) }}</th><td>{{ $v }}</td></tr>
        @endforeach
    </table>

    <h2>12. Key Assumptions</h2>
    <div class="narrative">
        {!! $md($s['assumptions'] ?? null) !!}
    </div>

    <h2>13. Schedule Risks</h2>
    <div class="narrative">
        {!! $md($s['risks'] ?? null) !!}
    </div>

    <h2>14. Exclusions</h2>
    <div class="narrative">
        {!! $md($s['exclusions'] ?? null) !!}
    </div>
</body>
</html>