<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Enums\BlockSequencingMode;
use App\Enums\DeliveryModel;
use App\Filament\Resources\ProjectResource;
use App\Services\Bess\ProjectSetupService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard;

class CreateProject extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = ProjectResource::class;

    public function getSteps(): array
    {
        return [
            $this->projectSetupStep(),
            $this->deliveryModelStep(),
            $this->topologyStep(),
            $this->scopeFlagsStep(),
        ];
    }

    // ── Step 1: Project Setup ────────────────────────────────

    private function projectSetupStep(): Wizard\Step
    {
        return Wizard\Step::make('Project Setup')
            ->icon('heroicon-o-document-text')
            ->description('Basic project information')
            ->schema([
                Section::make('Project Details')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Project Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g., Greenfield BESS 200MW'),
                            TextInput::make('code')
                                ->label('Project Code')
                                ->required()
                                ->unique('projects', 'code')
                                ->maxLength(50)
                                ->placeholder('e.g., BESS-001'),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('client')
                                ->maxLength(255)
                                ->placeholder('Client name'),
                            DatePicker::make('start_date')
                                ->label('Project Start Date')
                                ->required()
                                ->native(false)
                                ->default(now()->addMonth()->startOfWeek()),
                        ]),
                        Textarea::make('description')
                            ->rows(3)
                            ->placeholder('Optional project description'),
                    ]),
            ]);
    }

    // ── Step 2: Delivery Model ───────────────────────────────

    private function deliveryModelStep(): Wizard\Step
    {
        return Wizard\Step::make('Delivery Model')
            ->icon('heroicon-o-truck')
            ->description('Select contract delivery strategy')
            ->schema([
                Section::make('Contract Delivery Model')
                    ->description('This determines which packages are internal vs external.')
                    ->schema([
                        Select::make('delivery_model')
                            ->label('Delivery Model')
                            ->options(collect(DeliveryModel::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()]))
                            ->default(DeliveryModel::Epc->value)
                            ->required()
                            ->native(false)
                            ->live(),
                    ]),
            ]);
    }

    // ── Step 3: Equipment and Topology ───────────────────────

    private function topologyStep(): Wizard\Step
    {
        // Architectures where PCS is integrated (no separate PCS fields)
        $pcsIntegrated = fn (Get $get): bool => $get('bess_architecture') === 'integrated_container';

        // Architectures where SUT does not exist
        $noSut = fn (Get $get): bool => $get('bess_architecture') === 'no_sut';

        // Whether an architecture has been selected at all
        $hasArch = fn (Get $get): bool => $get('bess_architecture') !== null;

        return Wizard\Step::make('Equipment & Topology')
            ->icon('heroicon-o-cpu-chip')
            ->description('Select BESS architecture, RMU configuration, and equipment counts')
            ->schema([
                // Architecture and RMU selection — Alpine.js driven
                View::make('filament.steps.architecture-selection'),

                // Hidden live fields — Alpine sets these via $wire.set(),
                // Filament includes them in the submitted form data.
                Hidden::make('bess_architecture')->live(),
                Hidden::make('dc_cable_type'),
                Hidden::make('ac_cable_type'),
                Hidden::make('mv_cable_type'),
                Hidden::make('oem_product_name'),
                Hidden::make('container_unit_label'),
                Hidden::make('containers_per_sut'),
                Hidden::make('pcs_factory_fitted')->default(true),
                Hidden::make('pcs_unit_type'),
                Hidden::make('batteries_per_pcs'),
                Hidden::make('pcs_per_sut'),
                Hidden::make('pcs_output_voltage'),
                Hidden::make('blocks_per_cluster_sut'),
                Hidden::make('rmu_topology')->default('none')->live(),
                Hidden::make('rmu_on_mv_ring')->default(false),
                Hidden::make('rmu_mv_ring_voltage'),
                Hidden::make('rmu_unit_type'),

                Section::make('Site Layout and Equipment Counts')
                    ->description('Configure zones, blocks, and equipment groupings for your selected architecture.')
                    ->visible($hasArch)
                    ->schema([
                        TextInput::make('total_battery_count')
                            ->label(fn (Get $get) => $pcsIntegrated($get)
                                ? 'Total Container Count'
                                : 'Total Battery Count')
                            ->numeric()
                            ->placeholder('e.g., 160')
                            ->helperText(fn (Get $get) => $pcsIntegrated($get)
                                ? 'Total containers across all zones and blocks.'
                                : 'Total batteries across all zones and blocks. Used for reconciliation validation.'),

                        Grid::make(3)->schema([
                            TextInput::make('zone_count')
                                ->label('Zones')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(10)
                                ->required(),
                            TextInput::make('blocks_per_zone')
                                ->label('Blocks per Zone')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(20)
                                ->required(),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('batteries_per_group')
                                ->label(fn (Get $get) => $pcsIntegrated($get)
                                    ? 'Containers per Group'
                                    : 'Batteries per Group')
                                ->numeric()
                                ->default(4)
                                ->minValue(1)
                                ->maxValue(20)
                                ->required(),
                            TextInput::make('battery_groups_per_block')
                                ->label(fn (Get $get) => $pcsIntegrated($get)
                                    ? 'Container Groups per Block'
                                    : 'Battery Groups per Block')
                                ->numeric()
                                ->default(5)
                                ->minValue(1)
                                ->maxValue(50)
                                ->required(),
                        ]),

                        // PCS fields — hidden for Architecture 1 (integrated container)
                        Grid::make(3)
                            ->schema([
                                TextInput::make('pcs_per_group')
                                    ->label('PCS per Group')
                                    ->numeric()
                                    ->default(2)
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->required(fn (Get $get) => !$pcsIntegrated($get)),
                                TextInput::make('pcs_groups_per_block')
                                    ->label('PCS Groups per Block')
                                    ->numeric()
                                    ->default(5)
                                    ->minValue(1)
                                    ->maxValue(50)
                                    ->required(fn (Get $get) => !$pcsIntegrated($get)),
                            ])
                            ->hidden($pcsIntegrated),

                        // SUT field — hidden for Architecture 4 (no SUT)
                        Grid::make(3)
                            ->schema([
                                TextInput::make('suts_per_block')
                                    ->label('SUTs per Block')
                                    ->numeric()
                                    ->default(2)
                                    ->minValue(1)
                                    ->maxValue(20)
                                    ->required(fn (Get $get) => !$noSut($get)),
                            ])
                            ->hidden($noSut),
                    ]),

                Section::make('Block Sequencing')
                    ->description('How consecutive blocks within a zone are related. Zones always run in parallel.')
                    ->visible($hasArch)
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('block_sequencing_mode')
                                ->label('Block-to-Block Relationship')
                                ->options(collect(BlockSequencingMode::cases())
                                    ->mapWithKeys(fn ($m) => [$m->value => $m->label()]))
                                ->default(BlockSequencingMode::FinishToStart->value)
                                ->helperText(fn (Get $get) => BlockSequencingMode::tryFrom(
                                    $get('block_sequencing_mode') ?? 'FS'
                                )?->description())
                                ->required()
                                ->native(false)
                                ->live(),
                            TextInput::make('block_sequencing_lag_days')
                                ->label('Lag (days)')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(365)
                                ->helperText('Days between the start of one block and the start of the next.')
                                ->visible(fn (Get $get) => $get('block_sequencing_mode') === BlockSequencingMode::StartToStartWithLag->value)
                                ->required(fn (Get $get) => $get('block_sequencing_mode') === BlockSequencingMode::StartToStartWithLag->value),
                        ]),
                    ]),
            ]);
    }

    // ── Step 4: Scope Flags ──────────────────────────────────

    private function scopeFlagsStep(): Wizard\Step
    {
        return Wizard\Step::make('Scope & Conditions')
            ->icon('heroicon-o-adjustments-horizontal')
            ->description('Infrastructure and conditional scope')
            ->schema([
                Section::make('Infrastructure Scope')
                    ->description('Toggle which infrastructure elements are included in this project.')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('switchroom_exists')
                                ->label('Switchroom')
                                ->default(true),
                            Toggle::make('transformer_exists')
                                ->label('Main Transformer')
                                ->default(true),
                            Toggle::make('substation_exists')
                                ->label('Substation')
                                ->default(true),
                            Toggle::make('control_room_exists')
                                ->label('Control Room')
                                ->default(false),
                        ]),
                    ]),
                Section::make('Conditional Scope')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('scada_included')
                                ->label('SCADA / Communications')
                                ->default(true),
                            Toggle::make('bess_free_issued')
                                ->label('BESS Free-Issued')
                                ->default(false),
                            Toggle::make('hvac_in_vendor_package')
                                ->label('HVAC in Vendor Package')
                                ->default(false),
                            Toggle::make('fire_in_vendor_package')
                                ->label('Fire in Vendor Package')
                                ->default(false),
                        ]),
                    ]),

                Section::make('Buildings & Facilities')
                    ->description('Standalone buildings on site (rare — defaults off).')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('oam_building_exists')
                                ->label('Warehouse / O&M Building')
                                ->default(false),
                            Toggle::make('site_office_exists')
                                ->label('Main Office')
                                ->default(false),
                            Toggle::make('guardhouse_exists')
                                ->label('Guard House')
                                ->default(false),
                            Toggle::make('ablutions_building_exists')
                                ->label('Ablution Building')
                                ->default(false),
                            Toggle::make('workshop_building_exists')
                                ->label('Workshop Building')
                                ->default(false),
                            Toggle::make('fire_pump_house_exists')
                                ->label('Fire / Pump House')
                                ->default(false),
                        ]),
                    ]),

                Section::make('Site-wide Civil')
                    ->description('Perimeter, roads, drainage, water.')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('perimeter_fencing_exists')
                                ->label('Perimeter Fencing')
                                ->default(true),
                            Toggle::make('access_roads_exists')
                                ->label('Access Roads')
                                ->default(true),
                            Toggle::make('site_drainage_exists')
                                ->label('Site Drainage')
                                ->default(true),
                            Toggle::make('potable_water_exists')
                                ->label('Potable Water')
                                ->default(false),
                        ]),
                    ]),

                Section::make('Site-wide E&I')
                    ->description('Site lighting, security, communications, UPS.')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('external_lighting_exists')
                                ->label('External Lighting')
                                ->default(true),
                            Toggle::make('site_ups_exists')
                                ->label('Site UPS')
                                ->default(true),
                            Toggle::make('site_comms_backbone_exists')
                                ->label('Site Comms Backbone')
                                ->default(false),
                            Toggle::make('cctv_security_exists')
                                ->label('CCTV / Security')
                                ->default(false),
                            Toggle::make('access_control_exists')
                                ->label('Access Control')
                                ->default(false),
                            Toggle::make('public_address_exists')
                                ->label('Public Address')
                                ->default(false),
                        ]),
                    ]),

                Section::make('Safety & Fire')
                    ->description('Building fire systems and lightning protection.')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('building_fire_system_exists')
                                ->label('Building Fire System')
                                ->default(true),
                            Toggle::make('lightning_protection_site_exists')
                                ->label('Site Lightning Protection')
                                ->default(false),
                        ]),
                    ]),
            ]);
    }

    // ── Create Handling ──────────────────────────────────────

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Architecture and RMU fields are set via Alpine $wire.set() which
        // updates the Livewire data but bypasses Filament's field state.
        // Merge them from the component data into the creation payload.
        $archFields = [
            'bess_architecture', 'dc_cable_type', 'ac_cable_type', 'mv_cable_type',
            'oem_product_name', 'container_unit_label', 'containers_per_sut',
            'pcs_factory_fitted', 'pcs_unit_type', 'batteries_per_pcs', 'pcs_per_sut',
            'pcs_output_voltage', 'blocks_per_cluster_sut',
            'rmu_topology', 'rmu_on_mv_ring', 'rmu_mv_ring_voltage', 'rmu_unit_type',
        ];
        foreach ($archFields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                $wireValue = data_get($this->data, $field);
                if ($wireValue !== null && $wireValue !== '') {
                    $data[$field] = $wireValue;
                }
            }
        }

        $setupService = app(ProjectSetupService::class);
        $project = $setupService->createProject($data);

        Notification::make()
            ->title('Project created successfully')
            ->body("Project {$project->code} created with WBS skeleton. Use 'Manage' to configure packages and generate the schedule.")
            ->success()
            ->send();

        return $project;
    }

    protected function getRedirectUrl(): string
    {
        return ProjectResource::getUrl('manage', ['record' => $this->record]);
    }
}