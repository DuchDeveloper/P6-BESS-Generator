@php
    use App\Services\Bess\SummaryGanttLayout as L;
    /** @var \App\Models\Project $project */
    /** @var array $layout */
    $w = L::CANVAS_WIDTH;
    $h = L::CANVAS_HEIGHT;
    $legendItems = [
        ['label' => 'Design', 'colour' => L::COLOUR_DESIGN],
        ['label' => 'Civil', 'colour' => L::COLOUR_CIVIL],
        ['label' => 'Mechanical', 'colour' => L::COLOUR_MECHANICAL],
        ['label' => 'Electrical', 'colour' => L::COLOUR_ELECTRICAL],
        ['label' => 'Commissioning', 'colour' => L::COLOUR_COMMISSIONING],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Summary Gantt — {{ $project->name }}</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, 'Segoe UI', sans-serif;
            margin: 0; padding: 24px; color: #111; background: #f5f5f5;
        }
        .card { background: #fff; max-width: 1180px; margin: 0 auto;
                padding: 24px 28px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .sub { color: #666; font-size: 13px; margin-bottom: 18px; }
        .actions a {
            display: inline-block; padding: 6px 12px; border: 1px solid #ddd; border-radius: 6px;
            color: #333; text-decoration: none; font-size: 13px; margin-right: 8px;
        }
        .warnings { background: #fef3c7; border: 1px solid #fbbf24; padding: 10px 14px;
                    border-radius: 6px; font-size: 13px; margin: 12px 0; }
        .legend { font-size: 12px; color: #555; margin-top: 12px; }
        .legend span { display: inline-block; margin-right: 14px; }
        .legend i { display: inline-block; width: 12px; height: 12px; border-radius: 2px;
                    vertical-align: middle; margin-right: 5px; }
        table.summary { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 13px; }
        table.summary th, table.summary td {
            text-align: left; padding: 6px 10px; border-bottom: 1px solid #eee;
        }
        table.summary th { width: 30%; color: #555; font-weight: 600; }
        @media print { body { background: #fff; padding: 0; } .actions, .card { box-shadow: none; } }
    </style>
</head>
<body>
<div class="card">
    <div class="actions" style="float:right">
        <a href="{{ route('bess.gantt.pptx', $project) }}">Download PowerPoint</a>
        <a href="javascript:window.print()">Print / Save PDF</a>
    </div>
    <h1>Summary Gantt</h1>
    <div class="sub">
        {{ $project->name }} · Code {{ $project->code }} · Generated {{ now()->toDateString() }}
    </div>

    @if (! empty($layout['warnings']))
        <div class="warnings">
            @foreach ($layout['warnings'] as $msg)
                <div>{{ $msg }}</div>
            @endforeach
        </div>
    @endif

    @if ($layout['has_window'])
        <svg viewBox="0 0 {{ $w }} {{ $h }}" width="100%" style="height:auto;background:#fff">

            <!-- Vertical leader lines: drop from milestones through timeline into bars area -->
            @foreach ($layout['milestones'] as $m)
                <line x1="{{ $m['x'] }}" y1="{{ L::MILESTONE_Y + 9 }}"
                      x2="{{ $m['x'] }}" y2="{{ L::PLOT_BOTTOM }}"
                      stroke="{{ L::COLOUR_LEADER_LINE }}" stroke-width="0.8"
                      stroke-dasharray="3,3" opacity="0.7"/>
            @endforeach

            <!-- Plot background (under the bars) -->
            <rect x="{{ L::PLOT_LEFT }}" y="{{ L::TIMELINE_BOTTOM }}"
                  width="{{ L::PLOT_WIDTH }}"
                  height="{{ L::PLOT_BOTTOM - L::TIMELINE_BOTTOM }}"
                  fill="#fafafa" stroke="#e5e7eb"/>

            <!-- Timeline band (dark blue bar with year labels in white) -->
            <rect x="{{ L::PLOT_LEFT }}" y="{{ L::TIMELINE_TOP }}"
                  width="{{ L::PLOT_WIDTH }}"
                  height="{{ L::TIMELINE_BOTTOM - L::TIMELINE_TOP }}"
                  fill="{{ L::COLOUR_TIMELINE_BAND }}"/>
            @foreach ($layout['axis_ticks'] as $t)
                @if (! $loop->first)
                    <line x1="{{ $t['x'] }}" y1="{{ L::TIMELINE_TOP }}"
                          x2="{{ $t['x'] }}" y2="{{ L::TIMELINE_BOTTOM }}"
                          stroke="rgba(255,255,255,0.35)" stroke-width="1"/>
                @endif
                <text x="{{ $t['x'] + 6 }}" y="{{ (L::TIMELINE_TOP + L::TIMELINE_BOTTOM) / 2 + 4 }}"
                      font-size="12" fill="#fff" font-weight="700">
                    {{ $t['label'] }}
                </text>
            @endforeach

            <!-- Bars -->
            @foreach ($layout['bars'] as $bar)
                <g>
                    <rect x="{{ $bar['x'] }}" y="{{ $bar['y'] }}"
                          width="{{ $bar['width'] }}" height="{{ L::BAR_HEIGHT }}"
                          fill="{{ $bar['colour'] }}" rx="3" ry="3" opacity="0.95"/>
                    @if ($bar['width'] > 80)
                        <text x="{{ $bar['x'] + $bar['width'] / 2 }}"
                              y="{{ $bar['y'] + L::BAR_HEIGHT / 2 + 4 }}"
                              text-anchor="middle" font-size="11" fill="#fff" font-weight="600">
                            {{ $bar['label'] }} ({{ $bar['duration_days'] }}d)
                        </text>
                    @endif
                    <text x="{{ L::PLOT_LEFT - 6 }}"
                          y="{{ $bar['y'] + L::BAR_HEIGHT / 2 + 4 }}"
                          text-anchor="end" font-size="11" fill="#374151" font-weight="600">
                        {{ $bar['label'] }}
                    </text>
                </g>
            @endforeach

            <!-- Milestone diamonds with labels above (over the timeline band) -->
            @foreach ($layout['milestones'] as $i => $m)
                @php
                    $cx = $m['x'];
                    $cy = L::MILESTONE_Y;
                    $half = 8;
                    $points = ($cx).','.($cy - $half).' '
                             .($cx + $half).','.($cy).' '
                             .($cx).','.($cy + $half).' '
                             .($cx - $half).','.($cy);
                @endphp
                <text x="{{ $cx }}" y="{{ L::MILESTONE_LABEL_TOP + 12 }}" text-anchor="middle"
                      font-size="10" fill="#1f2937" font-weight="700">
                    {{ $m['short'] }}
                </text>
                <text x="{{ $cx }}" y="{{ L::MILESTONE_LABEL_TOP + 26 }}" text-anchor="middle"
                      font-size="9" fill="#6b7280">
                    {{ $m['date']->format('d M Y') }}
                </text>
                <polygon points="{{ $points }}" fill="{{ $m['colour'] }}" stroke="#fff" stroke-width="1.5"/>
            @endforeach
        </svg>

        <div class="legend">
            @foreach ($legendItems as $li)
                <span><i style="background:{{ $li['colour'] }}"></i>{{ $li['label'] }}</span>
            @endforeach
            <span style="color:#1f2937">◆ milestone</span>
        </div>

        <table class="summary">
            <tr><th>Window start</th><td>{{ $layout['start']->format('d M Y') }}</td></tr>
            <tr><th>Window finish</th><td>{{ $layout['finish']->format('d M Y') }}</td></tr>
            <tr><th>Total span</th><td>{{ $layout['total_days'] }} days
                (~{{ round($layout['total_days'] / 30.44, 1) }} months)</td></tr>
            @foreach ($layout['bars'] as $bar)
                <tr><th>{{ $bar['label'] }} duration</th>
                    <td>{{ $bar['duration_days'] }} days
                        (~{{ round($bar['duration_days'] / 30.44, 1) }} months)
                        — {{ $bar['start']->format('d M Y') }} → {{ $bar['finish']->format('d M Y') }}</td></tr>
            @endforeach
        </table>
    @else
        <p style="color:#6b7280">Not enough dates to render a Gantt yet. Open the project edit form and fill in the Key Dates section.</p>
    @endif
</div>
</body>
</html>