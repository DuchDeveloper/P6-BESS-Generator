<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Enums\BlockSequencingMode;
use App\Enums\DeliveryModel;
use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Project Details')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')->required()->maxLength(255),
                            TextInput::make('code')->required()->maxLength(50)->disabled(),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('client')->maxLength(255),
                            DatePicker::make('start_date')->required()->native(false),
                        ]),
                        Textarea::make('description')->rows(3),
                    ]),
                Section::make('Key Dates')
                    ->description('User-entered milestone and band-boundary dates for the summary Gantt. Each band is rendered only when both its start and finish dates are set.')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('design_freeze_date')
                                ->label('Design Freeze')
                                ->native(false)
                                ->live()
                                ->helperText('End of the Design band (start = NTP).'),
                            DatePicker::make('site_mobilisation_date')
                                ->label('Site Mobilisation')
                                ->native(false)
                                ->live()
                                ->helperText('Start of the Civil band.'),
                            DatePicker::make('civil_completion_date')
                                ->label('Civil Completion')
                                ->native(false)
                                ->live()
                                ->helperText('End of the Civil band.'),
                            DatePicker::make('mechanical_install_start_date')
                                ->label('Mech Install Start')
                                ->native(false)
                                ->live()
                                ->helperText('Start of the Mechanical band.'),
                            DatePicker::make('mechanical_install_finish_date')
                                ->label('Mech Install Finish')
                                ->native(false)
                                ->live()
                                ->helperText('End of the Mechanical band.'),
                            DatePicker::make('electrical_install_start_date')
                                ->label('Electrical Install Start')
                                ->native(false)
                                ->live()
                                ->helperText('Start of the Electrical band.'),
                            DatePicker::make('electrical_install_finish_date')
                                ->label('Electrical Install Finish')
                                ->native(false)
                                ->live()
                                ->helperText('End of the Electrical band.'),
                            DatePicker::make('mechanical_completion_date')
                                ->label('Mechanical Completion')
                                ->native(false)
                                ->live()
                                ->helperText('Milestone marker; also start of Commissioning.'),
                            DatePicker::make('energisation_date')
                                ->label('Energisation')
                                ->native(false)
                                ->helperText('Substation energised milestone.'),
                            DatePicker::make('commissioning_finish_date')
                                ->label('Commissioning Finish')
                                ->native(false)
                                ->live()
                                ->helperText('End of the Commissioning band.'),
                            DatePicker::make('practical_completion_date')
                                ->label('Practical Completion')
                                ->native(false)
                                ->helperText('Project handover.'),
                        ]),
                        Placeholder::make('derived_durations')
                            ->label('Derived durations')
                            ->content(function (Get $get): HtmlString {
                                $diff = function (?string $a, ?string $b): ?int {
                                    if (! $a || ! $b) {
                                        return null;
                                    }
                                    $start = \Carbon\Carbon::parse($a);
                                    $end = \Carbon\Carbon::parse($b);
                                    return $end->lessThan($start) ? null : (int) $start->diffInDays($end);
                                };
                                $rows = [
                                    'Design' => $diff($get('start_date'), $get('design_freeze_date')),
                                    'Civil' => $diff($get('site_mobilisation_date'), $get('civil_completion_date')),
                                    'Mechanical' => $diff($get('mechanical_install_start_date'), $get('mechanical_install_finish_date')),
                                    'Electrical' => $diff($get('electrical_install_start_date'), $get('electrical_install_finish_date')),
                                    'Commissioning' => $diff($get('mechanical_completion_date'), $get('commissioning_finish_date')),
                                ];
                                $fmt = fn (?int $d) => $d === null
                                    ? '<span style="color:#9ca3af">—</span>'
                                    : "<strong>{$d}</strong> days (~".round($d / 30.44, 1).' months)';
                                $html = '<div style="font-size:13px;line-height:1.8">';
                                foreach ($rows as $name => $days) {
                                    $html .= "{$name}: ".$fmt($days).'<br>';
                                }
                                $html .= '</div>';
                                return new HtmlString($html);
                            }),
                    ]),
                Section::make('Delivery Model')
                    ->schema([
                        Select::make('delivery_model')
                            ->options(collect(DeliveryModel::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()]))
                            ->required()
                            ->native(false),
                    ]),
                Section::make('Topology')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('zone_count')->numeric()->required()->minValue(1),
                            TextInput::make('blocks_per_zone')->numeric()->required()->minValue(1),
                            TextInput::make('battery_groups_per_block')->numeric()->required()->minValue(1),
                            TextInput::make('pcs_groups_per_block')->numeric()->required()->minValue(1),
                            TextInput::make('batteries_per_group')->numeric()->required()->minValue(1),
                            TextInput::make('pcs_per_group')->numeric()->required()->minValue(1),
                            TextInput::make('suts_per_block')->numeric()->required()->minValue(1),
                        ]),
                    ]),
                Section::make('Block Sequencing')
                    ->description('How consecutive blocks within a zone are related. Zones always run in parallel.')
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
                Section::make('Scope Flags')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('switchroom_exists')->label('Switchroom'),
                            Toggle::make('transformer_exists')->label('Main Transformer'),
                            Toggle::make('substation_exists')->label('Substation'),
                            Toggle::make('control_room_exists')->label('Control Room'),
                            Toggle::make('scada_included')->label('SCADA / Communications'),
                            Toggle::make('bess_free_issued')->label('BESS Free-Issued'),
                            Toggle::make('hvac_in_vendor_package')->label('HVAC in Vendor Package'),
                            Toggle::make('fire_in_vendor_package')->label('Fire in Vendor Package'),
                        ]),
                    ]),
                Section::make('Buildings & Facilities')
                    ->description('Standalone buildings on site.')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('oam_building_exists')->label('Warehouse / O&M Building'),
                            Toggle::make('site_office_exists')->label('Main Office'),
                            Toggle::make('guardhouse_exists')->label('Guard House'),
                            Toggle::make('ablutions_building_exists')->label('Ablution Building'),
                            Toggle::make('workshop_building_exists')->label('Workshop Building'),
                            Toggle::make('fire_pump_house_exists')->label('Fire / Pump House'),
                        ]),
                    ]),
                Section::make('Site-wide Civil')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('perimeter_fencing_exists')->label('Perimeter Fencing'),
                            Toggle::make('access_roads_exists')->label('Access Roads'),
                            Toggle::make('site_drainage_exists')->label('Site Drainage'),
                            Toggle::make('potable_water_exists')->label('Potable Water'),
                        ]),
                    ]),
                Section::make('Site-wide E&I')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('external_lighting_exists')->label('External Lighting'),
                            Toggle::make('site_ups_exists')->label('Site UPS'),
                            Toggle::make('site_comms_backbone_exists')->label('Site Comms Backbone'),
                            Toggle::make('cctv_security_exists')->label('CCTV / Security'),
                            Toggle::make('access_control_exists')->label('Access Control'),
                            Toggle::make('public_address_exists')->label('Public Address'),
                        ]),
                    ]),
                Section::make('Safety & Fire')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('building_fire_system_exists')->label('Building Fire System'),
                            Toggle::make('lightning_protection_site_exists')->label('Site Lightning Protection'),
                        ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('manage')
                ->label('Manage Schedule')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(fn () => ManageProject::getUrl(['record' => $this->record])),
        ];
    }
}