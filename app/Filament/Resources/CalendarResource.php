<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CalendarResource\Pages;
use App\Models\Calendar;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Work calendars: which weekdays are worked and which dates are public
 * holidays. A project's calendar is exported with its XER, so P6 schedules
 * around the holidays.
 */
class CalendarResource extends Resource
{
    protected static ?string $model = Calendar::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Calendars';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Calendar')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->placeholder('e.g. VIC 5 Day Workweek'),
                    CheckboxList::make('working_days')
                        ->label('Working days')
                        ->options(Calendar::WEEKDAYS)
                        ->default([1, 2, 3, 4, 5])
                        ->columns(['default' => 2, 'sm' => 4, 'lg' => 7])
                        ->gridDirection('row')
                        ->required()
                        ->helperText('Each working day is 08:00–16:00 in the exported calendar.'),
                    Toggle::make('is_default')
                        ->label('Default for new projects'),
                ]),
            Section::make('Public holidays')
                ->columnSpanFull()
                ->description('Non-working days, exported to P6 as calendar exceptions.')
                ->schema([
                    Repeater::make('holidays')
                        ->hiddenLabel()
                        ->schema([
                            DatePicker::make('date')->required()->native(false),
                            TextInput::make('name')->maxLength(100)->placeholder('e.g. Christmas Day'),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Add holiday')
                        ->reorderable(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('working_days')
                    ->label('Working days')
                    ->state(fn (Calendar $record) => $record->workingDaysLabel()),
                Tables\Columns\TextColumn::make('holidays')
                    ->label('Holidays')
                    ->state(fn (Calendar $record) => count($record->holidayDates())),
                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Projects')
                    ->counts('projects'),
                Tables\Columns\IconColumn::make('is_default')->label('Default')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Calendar $record) => $record->projects()->doesntExist()),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCalendars::route('/'),
            'create' => Pages\CreateCalendar::route('/create'),
            'edit' => Pages\EditCalendar::route('/{record}/edit'),
        ];
    }
}
