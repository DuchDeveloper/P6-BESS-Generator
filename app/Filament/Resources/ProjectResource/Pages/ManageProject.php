<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Enums\OwnershipMode;
use App\Filament\Resources\ProjectResource;
use App\Models\PackageInstance;
use App\Services\Bess\BasisOfScheduleService;
use App\Services\Bess\DeliveryModelService;
use App\Services\Bess\DependencyResolverService;
use App\Services\Bess\ExportService;
use App\Services\Bess\GraphCompilerService;
use App\Services\Bess\PackageLibraryService;
use App\Services\Bess\TopologyService;
use App\Services\Bess\ValidationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ManageProject extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.resources.project-resource.pages.manage-project';

    public function getTitle(): string
    {
        return "Manage: {$this->record->name}";
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    // ── Package Instances Table ──────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PackageInstance::query()
                    ->where('project_id', $this->record->id)
                    ->with('template')
            )
            ->columns([
                TextColumn::make('template.code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->size('sm'),
                TextColumn::make('template.name')
                    ->label('Package')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('template.type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->badge()
                    ->sortable(),
                TextColumn::make('template.discipline')
                    ->label('Discipline')
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('selected')
                    ->label('Selected')
                    ->sortable(),
                IconColumn::make('applicable')
                    ->boolean()
                    ->sortable(),
                SelectColumn::make('ownership_mode')
                    ->label('Ownership')
                    ->options(collect(OwnershipMode::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))
                    ->sortable(),
                TextColumn::make('topology_label')
                    ->label('Topology')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('template_type')
                    ->label('Type')
                    ->options([
                        'design' => 'Design',
                        'procurement' => 'Procurement',
                        'construction' => 'Construction',
                        'commissioning' => 'Commissioning',
                        'management' => 'Management',
                        'milestone' => 'Milestone',
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, string $type) => $q->whereHas('template', fn ($tq) => $tq->where('type', $type))
                    )),
                TernaryFilter::make('selected'),
                TernaryFilter::make('applicable'),
            ])
            ->defaultSort('id');
    }

    // ── Header Actions ───────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('initTopology')
                ->label('0. Init Topology')
                ->icon('heroicon-o-cpu-chip')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Initialize zone and block configurations from project defaults. You can then customize each block individually.')
                ->action(fn () => $this->initializeTopology())
                ->visible(fn () => $this->record->zoneConfigurations()->count() === 0),

            Actions\Action::make('instantiate')
                ->label('1. Instantiate Packages')
                ->icon('heroicon-o-plus-circle')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('This will create package instances from the template library based on project configuration.')
                ->action(fn () => $this->instantiatePackages())
                ->visible(fn () => $this->record->packageInstances()->count() === 0),

            Actions\Action::make('applyDeliveryModel')
                ->label('2. Apply Delivery Model')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->requiresConfirmation()
                ->action(fn () => $this->applyDeliveryModel())
                ->visible(fn () => $this->record->packageInstances()->count() > 0),

            Actions\Action::make('resolve')
                ->label('3. Resolve Dependencies')
                ->icon('heroicon-o-link')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn () => $this->resolveDependencies())
                ->visible(fn () => $this->record->packageInstances()->count() > 0),

            Actions\Action::make('compile')
                ->label('4. Compile Schedule')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('This will generate all activities, relationships, and equipment. Previous compilation will be cleared.')
                ->action(fn () => $this->compileGraph())
                ->visible(fn () => $this->record->providerResolutions()->count() > 0),

            Actions\Action::make('validate')
                ->label('5. Validate')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->action(fn () => $this->runValidation())
                ->visible(fn () => $this->record->activityInstances()->count() > 0),

            Actions\Action::make('resolveAll')
                ->label('Resolve All Errors')
                ->icon('heroicon-o-check')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This will mark all validation errors as resolved, allowing export. Use this only if you have reviewed the errors.')
                ->action(fn () => $this->resolveAllErrors())
                ->visible(fn () => $this->record->validationErrors()->where('resolved', false)->exists()),

            Actions\Action::make('exportXer')
                ->label('Export XER')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    return $this->exportAndDownload('xer');
                })
                ->visible(fn () => $this->record->activityInstances()->count() > 0),

            Actions\Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-table-cells')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    return $this->exportAndDownload('csv');
                })
                ->visible(fn () => $this->record->activityInstances()->count() > 0),

            Actions\ActionGroup::make([
                Actions\Action::make('generateBos')
                    ->label('Generate Basis of Schedule')
                    ->icon('heroicon-o-document-text')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Basis of Schedule')
                    ->modalDescription('Calls the LLM to draft scope narrative and other BoS sections. A new version is stored each time.')
                    ->action(fn () => $this->generateBasisOfSchedule()),

                Actions\Action::make('viewBos')
                    ->label('View Basis of Schedule')
                    ->icon('heroicon-o-eye')
                    ->url(fn () => route('bess.bos.latest', $this->record), shouldOpenInNewTab: true)
                    ->visible(fn () => $this->record->basisOfSchedules()->exists()),

                Actions\Action::make('downloadBosPdf')
                    ->label('Download BoS PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn () => route('bess.bos.pdf', $this->record))
                    ->visible(fn () => $this->record->basisOfSchedules()->exists()),

                Actions\Action::make('downloadBosWord')
                    ->label('Download BoS Word')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn () => route('bess.bos.docx', $this->record))
                    ->visible(fn () => $this->record->basisOfSchedules()->exists()),

                Actions\Action::make('viewSummaryGantt')
                    ->label('View Summary Gantt')
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn () => route('bess.gantt.show', $this->record), shouldOpenInNewTab: true),

                Actions\Action::make('downloadGanttPptx')
                    ->label('Download Gantt PowerPoint')
                    ->icon('heroicon-o-presentation-chart-bar')
                    ->url(fn () => route('bess.gantt.pptx', $this->record)),
            ])
                ->label('Basis of Schedule')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->button(),
        ];
    }

    // ── Engine Actions ───────────────────────────────────────

    private function initializeTopology(): void
    {
        $topology = app(TopologyService::class);
        $topology->initializeFromDefaults($this->record);
        $summary = $topology->getSummary($this->record);

        Notification::make()
            ->title('Topology Initialized')
            ->body(
                "Zones: {$this->record->zone_count}, "
                . "Blocks: " . $this->record->blockConfigurations()->count() . ", "
                . "Battery Groups: {$summary['total_groups']}, "
                . "Total Batteries: {$summary['total_batteries']}"
            )
            ->success()
            ->send();
    }

    private function instantiatePackages(): void
    {
        $library = app(PackageLibraryService::class);
        $instances = $library->instantiatePackages($this->record);

        Notification::make()
            ->title('Packages Instantiated')
            ->body("{$instances->count()} package instances created.")
            ->success()
            ->send();
    }

    private function applyDeliveryModel(): void
    {
        $delivery = app(DeliveryModelService::class);
        $library = app(PackageLibraryService::class);
        $active = $library->getActivePackages($this->record);
        $delivery->apply($this->record, $active);

        Notification::make()
            ->title('Delivery Model Applied')
            ->body("'{$this->record->delivery_model->label()}' ownership rules applied.")
            ->success()
            ->send();
    }

    private function resolveDependencies(): void
    {
        $resolver = app(DependencyResolverService::class);
        $unresolved = $resolver->resolve($this->record);

        if (count($unresolved) > 0) {
            Notification::make()
                ->title('Dependencies Resolved (with warnings)')
                ->body(count($unresolved) . ' output keys remain unresolved.')
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('All Dependencies Resolved')
                ->body($this->record->providerResolutions()->count() . ' output keys resolved.')
                ->success()
                ->send();
        }
    }

    private function compileGraph(): void
    {
        $compiler = app(GraphCompilerService::class);
        $compiler->compile($this->record);
        $this->record->refresh();

        Notification::make()
            ->title('Schedule Compiled')
            ->body(
                "Activities: {$this->record->activityInstances()->count()}, " .
                "Relationships: {$this->record->relationships()->count()}, " .
                "Equipment: {$this->record->equipmentNodes()->count()}"
            )
            ->success()
            ->send();
    }

    private function resolveAllErrors(): void
    {
        $count = $this->record->validationErrors()->where('resolved', false)->count();
        $this->record->validationErrors()->where('resolved', false)->update([
            'resolved' => true,
            'resolved_at' => now(),
            'resolution_note' => 'Bulk resolved by user',
        ]);

        Notification::make()
            ->title('Errors Resolved')
            ->body("{$count} validation errors marked as resolved.")
            ->success()
            ->send();
    }

    private function generateBasisOfSchedule(): void
    {
        try {
            $bos = app(BasisOfScheduleService::class)->generate($this->record);

            Notification::make()
                ->title('Basis of Schedule Generated')
                ->body("Version {$bos->version} is ready.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('BoS Generation Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function runValidation(): void
    {
        $validator = app(ValidationService::class);
        $validator->validate($this->record);
        $summary = $validator->summary($this->record);

        $title = $summary['can_export'] ? 'Validation Passed' : 'Validation Issues Found';

        Notification::make()
            ->title($title)
            ->body("Critical: {$summary['critical']}, Warnings: {$summary['warning']}")
            ->color($summary['can_export'] ? 'success' : 'danger')
            ->send();
    }

    private function exportAndDownload(string $format): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $exporter = app(ExportService::class);

        try {
            $path = $exporter->export($this->record, $format);
            $strategy = $exporter->resolveStrategy($format);

            Notification::make()
                ->title('Export Complete')
                ->body('Downloading ' . basename($path))
                ->success()
                ->send();

            return response()->streamDownload(function () use ($path) {
                echo file_get_contents($path);
            }, basename($path), [
                'Content-Type' => $strategy->mimeType(),
            ]);
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Export Blocked')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }
}