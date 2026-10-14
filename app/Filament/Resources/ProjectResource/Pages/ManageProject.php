<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Enums\BuildStatus;
use App\Enums\OwnershipMode;
use App\Enums\ValidationSeverity;
use App\Filament\Resources\ProjectResource;
use App\Models\PackageInstance;
use App\Services\Bess\BasisOfScheduleService;
use App\Services\Bess\DeliveryModelService;
use App\Services\Bess\DependencyResolverService;
use App\Services\Bess\ExportService;
use App\Services\Bess\GraphCompilerService;
use App\Services\Bess\PackageLibraryService;
use App\Services\Bess\ProjectBusyException;
use App\Services\Bess\ProjectLock;
use App\Services\Bess\ScheduleBuilder;
use App\Services\Bess\TopologyService;
use App\Services\Bess\ValidationService;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

    /** A build was in progress when the page last looked; notify when it ends. */
    public bool $watchingBuild = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->watchingBuild = $this->record->isBuilding();
    }

    /**
     * Polled while a build runs (see the banner in the view).
     */
    public function checkBuild(): void
    {
        $this->record->refresh();

        if ($this->watchingBuild && ! $this->record->isBuilding()) {
            $this->watchingBuild = false;
            $this->notifyBuildResult();
            $this->resetTable();
        }
    }

    // ── Package Instances Table ──────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PackageInstance::query()
                    ->where('project_id', $this->record->id)
                    ->with(['template', 'includedInPackage.template'])
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
                TextColumn::make('ownership_mode')
                    ->label('Ownership')
                    ->badge()
                    ->formatStateUsing(fn (OwnershipMode $state) => $state->label())
                    ->color(fn (OwnershipMode $state) => match ($state) {
                        OwnershipMode::Internal => 'gray',
                        OwnershipMode::External => 'info',
                        OwnershipMode::IncludedElsewhere => 'warning',
                        OwnershipMode::NotApplicable => 'danger',
                    })
                    ->description(fn (PackageInstance $record) => $record->ownership_mode === OwnershipMode::IncludedElsewhere
                        ? 'In: ' . ($record->includedInPackage?->template?->name ?? 'no package chosen')
                        : null)
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
                SelectFilter::make('ownership_mode')
                    ->label('Ownership')
                    ->options(self::ownershipOptions()),
            ])
            ->recordActions([
                Actions\Action::make('editOwnership')
                    ->label('Ownership')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading(fn (PackageInstance $record) => "Ownership — {$record->template->name}")
                    ->modalSubmitActionLabel('Save')
                    ->fillForm(fn (PackageInstance $record) => [
                        'ownership_mode' => $record->ownership_mode->value,
                        'included_in_package_id' => $record->included_in_package_id,
                    ])
                    ->schema([
                        Radio::make('ownership_mode')
                            ->label('Who does this package?')
                            ->options(self::ownershipOptions())
                            ->descriptions([
                                OwnershipMode::Internal->value => 'Built by us: all its activities and milestones are scheduled.',
                                OwnershipMode::External->value => 'Provided by others: one receipt milestone per output.',
                                OwnershipMode::IncludedElsewhere->value => 'Covered by another package: its outputs come from that package.',
                                OwnershipMode::NotApplicable->value => 'Not needed on this project: its outputs aren\'t required.',
                            ])
                            ->required()
                            ->live(),
                        Select::make('included_in_package_id')
                            ->label('Included in')
                            ->helperText('The package whose scope already covers this one. Anything waiting on this package waits on that one instead.')
                            ->options(fn (PackageInstance $record) => app(PackageLibraryService::class)->containerOptions($record))
                            ->searchable()
                            ->required()
                            ->visible(fn (Get $get) => $get('ownership_mode') === OwnershipMode::IncludedElsewhere->value),
                    ])
                    ->action(function (PackageInstance $record, array $data): void {
                        app(PackageLibraryService::class)->setOwnershipMode(
                            $record,
                            OwnershipMode::from($data['ownership_mode']),
                            isset($data['included_in_package_id']) ? (int) $data['included_in_package_id'] : null,
                        );

                        Notification::make()
                            ->title('Ownership updated')
                            ->body('Build the schedule again to apply it.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('setOwnership')
                    ->label('Set ownership')
                    ->icon('heroicon-o-pencil-square')
                    ->schema([
                        Select::make('ownership_mode')
                            ->label('Ownership')
                            ->helperText('To include packages in another package, edit them one at a time.')
                            ->options(collect(self::ownershipOptions())->except(OwnershipMode::IncludedElsewhere->value)->all())
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $mode = OwnershipMode::from($data['ownership_mode']);
                        $library = app(PackageLibraryService::class);
                        $records->each(fn (PackageInstance $record) => $library->setOwnershipMode($record, $mode));

                        Notification::make()
                            ->title('Ownership updated')
                            ->body("{$records->count()} packages set to {$mode->label()}. Build the schedule again to apply it.")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('id');
    }

    /**
     * @return array<string, string>
     */
    private static function ownershipOptions(): array
    {
        return collect(OwnershipMode::cases())->mapWithKeys(fn (OwnershipMode $m) => [$m->value => $m->label()])->all();
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
                ->action(fn () => $this->withProjectLock(fn () => $this->initializeTopology()))
                ->visible(fn () => $this->record->zoneConfigurations()->count() === 0),

            Actions\Action::make('instantiate')
                ->label('1. Instantiate Packages')
                ->icon('heroicon-o-plus-circle')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('This will create package instances from the template library based on project configuration.')
                ->action(fn () => $this->withProjectLock(fn () => $this->instantiatePackages()))
                ->visible(fn () => $this->record->packageInstances()->count() === 0),

            Actions\Action::make('buildSchedule')
                ->label('Build Schedule')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('Resolves dependencies, compiles the schedule and validates it, using the packages as they are set below. The previous compilation is replaced. Large sites take a minute or so; you can keep working while it builds.')
                ->action(fn () => $this->buildSchedule())
                ->visible(fn () => $this->record->packageInstances()->exists() && ! $this->record->isBuilding()),

            Actions\ActionGroup::make([
                Actions\Action::make('applyDeliveryModel')
                    ->label('2. Apply Delivery Model')
                    ->icon('heroicon-o-truck')
                    ->requiresConfirmation()
                    ->modalDescription('Applies the delivery model\'s ownership rules to the packages. Ownership you changed by hand on the affected packages is overwritten.')
                    ->action(fn () => $this->withProjectLock(fn () => $this->applyDeliveryModel())),

                Actions\Action::make('resolve')
                    ->label('3. Resolve Dependencies')
                    ->icon('heroicon-o-link')
                    ->requiresConfirmation()
                    ->action(fn () => $this->withProjectLock(fn () => $this->resolveDependencies())),

                Actions\Action::make('compile')
                    ->label('4. Compile Schedule')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->requiresConfirmation()
                    ->modalDescription('This will generate all activities, relationships, and equipment. Previous compilation will be cleared.')
                    ->action(fn () => $this->withProjectLock(fn () => $this->compileGraph()))
                    ->visible(fn () => $this->record->providerResolutions()->exists()),

                Actions\Action::make('validate')
                    ->label('5. Validate')
                    ->icon('heroicon-o-check-badge')
                    ->action(fn () => $this->withProjectLock(fn () => $this->runValidation()))
                    ->visible(fn () => $this->record->activityInstances()->exists()),
            ])
                ->label('Steps')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->button()
                ->visible(fn () => $this->record->packageInstances()->exists()),

            Actions\Action::make('acknowledgeWarnings')
                ->label('Acknowledge Warnings')
                ->icon('heroicon-o-check')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Marks the outstanding warnings and notes as reviewed. Critical errors are not affected — they block export until the schedule is fixed and recompiled.')
                ->action(fn () => $this->acknowledgeWarnings())
                ->visible(fn () => $this->record->validationErrors()
                    ->where('severity', '!=', ValidationSeverity::Critical)
                    ->where('resolved', false)
                    ->exists()),

            Actions\ActionGroup::make([
                Actions\Action::make('exportXer')
                    ->label('Primavera P6 (XER)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => $this->exportAndDownload('xer')),

                Actions\Action::make('exportCsv')
                    ->label('Excel / CSV')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn () => $this->exportAndDownload('csv')),

                Actions\Action::make('exportJson')
                    ->label('JSON')
                    ->icon('heroicon-o-code-bracket')
                    ->action(fn () => $this->exportAndDownload('json')),
            ])
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->button()
                ->visible(fn () => $this->record->activityInstances()->exists()),

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

    private function buildSchedule(): void
    {
        app(ScheduleBuilder::class)->queue($this->record);
        $this->record->refresh();

        // With a sync queue the build has already run.
        if (! $this->record->isBuilding()) {
            $this->notifyBuildResult();

            return;
        }

        $this->watchingBuild = true;

        Notification::make()
            ->title('Build Started')
            ->body('The schedule is building in the background. This page updates when it finishes.')
            ->info()
            ->send();
    }

    private function notifyBuildResult(): void
    {
        $project = $this->record;
        $summary = $project->build_summary ?? [];

        if ($project->build_status === BuildStatus::Failed) {
            Notification::make()
                ->title('Build Failed')
                ->body($project->build_message)
                ->danger()
                ->send();

            return;
        }

        $canExport = (bool) ($summary['can_export'] ?? false);

        Notification::make()
            ->title($canExport ? 'Schedule Built' : 'Schedule Built with Critical Errors')
            ->body(
                'Activities: ' . ($summary['activities'] ?? 0) . ', Relationships: ' . ($summary['relationships'] ?? 0) . '. '
                . 'Critical: ' . ($summary['critical'] ?? 0) . ', Warnings: ' . ($summary['warning'] ?? 0) . ', Notes: ' . ($summary['info'] ?? 0)
            )
            ->color($canExport ? 'success' : 'danger')
            ->send();
    }

    private function acknowledgeWarnings(): void
    {
        $count = app(ValidationService::class)->acknowledgeWarnings($this->record);

        Notification::make()
            ->title('Warnings Acknowledged')
            ->body("{$count} warnings and notes marked as reviewed.")
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
            ->body("Critical: {$summary['critical']}, Warnings: {$summary['warning']}, Notes: {$summary['info']}")
            ->color($summary['can_export'] ? 'success' : 'danger')
            ->send();
    }

    /**
     * Run an engine step while holding the project lock, so two people can't
     * rebuild or export the same project at once.
     */
    private function withProjectLock(callable $work): void
    {
        try {
            app(ProjectLock::class)->run($this->record, $work);
        } catch (ProjectBusyException $e) {
            $this->notifyBusy($e);
        }
    }

    private function notifyBusy(ProjectBusyException $e): void
    {
        Notification::make()
            ->title('Project Busy')
            ->body($e->getMessage())
            ->warning()
            ->send();
    }

    private function exportAndDownload(string $format): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $exporter = app(ExportService::class);

        try {
            $path = $exporter->export($this->record, $format);
            $strategy = $exporter->resolveStrategy($format);

            // Each export has its own temporary file: read it, then remove it.
            $contents = (string) file_get_contents($path);
            $exporter->cleanUp($path);

            Notification::make()
                ->title('Export Complete')
                ->body('Downloading ' . basename($path))
                ->success()
                ->send();

            return response()->streamDownload(function () use ($contents): void {
                echo $contents;
            }, basename($path), [
                'Content-Type' => $strategy->mimeType(),
            ]);
        } catch (ProjectBusyException $e) {
            $this->notifyBusy($e);

            return null;
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