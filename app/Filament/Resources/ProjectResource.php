<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\DeliveryModel;
use App\Enums\ProjectStatus;
use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Projects';

    protected static ?string $modelLabel = 'BESS Project';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('delivery_model')
                    ->label('Delivery Model')
                    ->formatStateUsing(fn (DeliveryModel $state) => $state->label())
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->formatStateUsing(fn (ProjectStatus $state) => $state->label())
                    ->badge()
                    ->color(fn (ProjectStatus $state) => match ($state) {
                        ProjectStatus::Draft => 'gray',
                        ProjectStatus::Configured => 'info',
                        ProjectStatus::Compiled => 'warning',
                        ProjectStatus::Validated => 'success',
                        ProjectStatus::Exported => 'primary',
                    }),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('activityInstances_count')
                    ->label('Activities')
                    ->counts('activityInstances')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ProjectStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('delivery_model')
                    ->options(collect(DeliveryModel::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()])),
            ])
            ->actions([
                EditAction::make(),
                Action::make('manage')
                    ->label('Manage')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn (Project $record) => Pages\ManageProject::getUrl(['record' => $record])),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'manage' => Pages\ManageProject::route('/{record}/manage'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}