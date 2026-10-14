<?php

declare(strict_types=1);

use App\Filament\Resources\CalendarResource\Pages\CreateCalendar;
use App\Filament\Resources\CalendarResource\Pages\ListCalendars;
use App\Models\Calendar;
use App\Models\User;
use App\Services\Bess\Export\XerExportStrategy;
use App\Services\Bess\ExportService;
use Livewire\Livewire;

beforeEach(function (): void {
    seedLibrary();
    $this->actingAs(User::factory()->create());
});

it('sets up a calendar with working days and public holidays', function (): void {
    Livewire::test(CreateCalendar::class)
        ->fillForm([
            'name' => 'VIC 6 Day Week',
            'working_days' => [1, 2, 3, 4, 5, 6],
            'is_default' => true,
            'holidays' => [
                ['date' => '2026-12-25', 'name' => 'Christmas Day'],
                ['date' => '2026-12-28', 'name' => 'Boxing Day (observed)'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $calendar = Calendar::where('name', 'VIC 6 Day Week')->firstOrFail();

    expect($calendar->workingDays())->toBe([1, 2, 3, 4, 5, 6])
        ->and($calendar->workdays_per_week)->toBe(6)
        ->and(array_map(fn ($d) => $d->toDateString(), $calendar->holidayDates()))->toBe(['2026-12-25', '2026-12-28'])
        ->and($calendar->workingDaysLabel())->toBe('Mon–Sat');

    Livewire::test(ListCalendars::class)
        ->assertSee('VIC 6 Day Week')
        ->assertSee('Mon–Sat');
});

it('keeps a single default calendar and gives it to new projects', function (): void {
    $first = Calendar::create(['name' => 'First', 'working_days' => [1, 2, 3, 4, 5], 'is_default' => true]);
    $second = Calendar::create(['name' => 'Second', 'working_days' => [1, 2, 3, 4, 5], 'is_default' => true]);

    expect($first->refresh()->is_default)->toBeFalse()
        ->and($second->refresh()->is_default)->toBeTrue()
        ->and(makeProject()->calendar_id)->toBe($second->id)
        ->and(makeProject(['calendar_id' => $first->id])->calendar_id)->toBe($first->id);
});

it('exports the project calendar to P6 with its holidays', function (): void {
    $calendar = Calendar::create([
        'name' => 'Site Calendar',
        'working_days' => [1, 2, 3, 4, 5, 6],
        'holidays' => [['date' => '2026-12-25', 'name' => 'Christmas Day'], ['date' => '2027-01-01', 'name' => "New Year's Day"]],
    ]);
    $project = compiledProject(['calendar_id' => $calendar->id]);

    $path = app(ExportService::class)->export($project, 'xer');
    $xer = file_get_contents($path);
    app(ExportService::class)->cleanUp($path);

    expect($xer)->toContain("Site Calendar\t")
        ->toContain("\t48\t") // 6 days × 8 hours a week
        ->toContain('(0||7()((0||0(f|16:00|s|08:00)())))') // Saturday worked
        ->toContain('(0||1()())') // Sunday off
        ->toContain('(0||Exceptions()((0||0(d|46381)())(0||1(d|46388)())))');
});

it('keeps the plain five-day calendar unchanged when there are no holidays', function (): void {
    expect(XerExportStrategy::calendarData([1, 2, 3, 4, 5], []))->toBe(
        '(0||CalendarData()((0||DaysOfWeek()((0||1()())(0||2()((0||0(f|16:00|s|08:00)())))(0||3()((0||0(f|16:00|s|08:00)())))'
        . '(0||4()((0||0(f|16:00|s|08:00)())))(0||5()((0||0(f|16:00|s|08:00)())))(0||6()((0||0(f|16:00|s|08:00)())))(0||7()())))'
        . '(0||Exceptions()())))'
    );
});
