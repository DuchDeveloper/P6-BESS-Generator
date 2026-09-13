<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BessArchitecture;
use App\Enums\BlockSequencingMode;
use App\Enums\DeliveryModel;
use App\Enums\ProjectStatus;
use App\Enums\RmuTopology;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'client',
        'description',
        'calendar_id',
        'export_profile_id',
        'start_date',
        'design_freeze_date',
        'site_mobilisation_date',
        'civil_completion_date',
        'mechanical_install_start_date',
        'mechanical_install_finish_date',
        'electrical_install_start_date',
        'electrical_install_finish_date',
        'mechanical_completion_date',
        'energisation_date',
        'commissioning_finish_date',
        'practical_completion_date',
        'delivery_model',
        'bess_free_issued',
        'switchroom_exists',
        'control_room_exists',
        'transformer_exists',
        'substation_exists',
        'scada_included',
        'hvac_in_vendor_package',
        'fire_in_vendor_package',
        // BOP scope flags
        'oam_building_exists',
        'guardhouse_exists',
        'site_office_exists',
        'ablutions_building_exists',
        'workshop_building_exists',
        'fire_pump_house_exists',
        'perimeter_fencing_exists',
        'access_roads_exists',
        'site_drainage_exists',
        'potable_water_exists',
        'external_lighting_exists',
        'cctv_security_exists',
        'access_control_exists',
        'public_address_exists',
        'site_comms_backbone_exists',
        'site_ups_exists',
        'building_fire_system_exists',
        'lightning_protection_site_exists',
        'zone_count',
        'blocks_per_zone',
        'batteries_per_group',
        'battery_groups_per_block',
        'pcs_per_group',
        'pcs_groups_per_block',
        'suts_per_block',
        'block_sequencing_mode',
        'block_sequencing_lag_days',
        'total_battery_count',
        'wbs_profile',
        // Architecture and RMU
        'bess_architecture',
        'dc_cable_type',
        'ac_cable_type',
        'mv_cable_type',
        'oem_product_name',
        'container_unit_label',
        'containers_per_sut',
        'pcs_factory_fitted',
        'pcs_unit_type',
        'batteries_per_pcs',
        'pcs_per_sut',
        'pcs_output_voltage',
        'blocks_per_cluster_sut',
        'rmu_topology',
        'rmu_on_mv_ring',
        'rmu_mv_ring_voltage',
        'rmu_unit_type',
        'status',
        'compiled_at',
        'validated_at',
        'exported_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_model' => DeliveryModel::class,
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'design_freeze_date' => 'date',
            'site_mobilisation_date' => 'date',
            'civil_completion_date' => 'date',
            'mechanical_install_start_date' => 'date',
            'mechanical_install_finish_date' => 'date',
            'electrical_install_start_date' => 'date',
            'electrical_install_finish_date' => 'date',
            'mechanical_completion_date' => 'date',
            'energisation_date' => 'date',
            'commissioning_finish_date' => 'date',
            'practical_completion_date' => 'date',
            'bess_free_issued' => 'boolean',
            'switchroom_exists' => 'boolean',
            'control_room_exists' => 'boolean',
            'transformer_exists' => 'boolean',
            'substation_exists' => 'boolean',
            'scada_included' => 'boolean',
            'hvac_in_vendor_package' => 'boolean',
            'fire_in_vendor_package' => 'boolean',
            // BOP scope flags
            'oam_building_exists' => 'boolean',
            'guardhouse_exists' => 'boolean',
            'site_office_exists' => 'boolean',
            'ablutions_building_exists' => 'boolean',
            'workshop_building_exists' => 'boolean',
            'fire_pump_house_exists' => 'boolean',
            'perimeter_fencing_exists' => 'boolean',
            'access_roads_exists' => 'boolean',
            'site_drainage_exists' => 'boolean',
            'potable_water_exists' => 'boolean',
            'external_lighting_exists' => 'boolean',
            'cctv_security_exists' => 'boolean',
            'access_control_exists' => 'boolean',
            'public_address_exists' => 'boolean',
            'site_comms_backbone_exists' => 'boolean',
            'site_ups_exists' => 'boolean',
            'building_fire_system_exists' => 'boolean',
            'lightning_protection_site_exists' => 'boolean',
            'zone_count' => 'integer',
            'blocks_per_zone' => 'integer',
            'batteries_per_group' => 'integer',
            'battery_groups_per_block' => 'integer',
            'pcs_per_group' => 'integer',
            'pcs_groups_per_block' => 'integer',
            'suts_per_block' => 'integer',
            'block_sequencing_mode' => BlockSequencingMode::class,
            'block_sequencing_lag_days' => 'integer',
            'total_battery_count' => 'integer',
            // Architecture and RMU
            'bess_architecture' => BessArchitecture::class,
            'containers_per_sut' => 'integer',
            'pcs_factory_fitted' => 'boolean',
            'batteries_per_pcs' => 'integer',
            'pcs_per_sut' => 'integer',
            'blocks_per_cluster_sut' => 'integer',
            'rmu_topology' => RmuTopology::class,
            'rmu_on_mv_ring' => 'boolean',
            'compiled_at' => 'datetime',
            'validated_at' => 'datetime',
            'exported_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function exportProfile(): BelongsTo
    {
        return $this->belongsTo(ExportProfile::class);
    }

    public function wbsNodes(): HasMany
    {
        return $this->hasMany(WbsNode::class);
    }

    public function zoneConfigurations(): HasMany
    {
        return $this->hasMany(ZoneConfiguration::class)->orderBy('zone_number');
    }

    public function blockConfigurations(): HasMany
    {
        return $this->hasMany(BlockConfiguration::class);
    }

    public function packageInstances(): HasMany
    {
        return $this->hasMany(PackageInstance::class);
    }

    public function activityInstances(): HasMany
    {
        return $this->hasMany(ActivityInstance::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(Relationship::class);
    }

    public function providerResolutions(): HasMany
    {
        return $this->hasMany(ProviderResolution::class);
    }

    public function equipmentNodes(): HasMany
    {
        return $this->hasMany(EquipmentNode::class);
    }

    public function interfaceRecords(): HasMany
    {
        return $this->hasMany(InterfaceRecord::class);
    }

    public function energisationMilestones(): HasMany
    {
        return $this->hasMany(EnergisationMilestone::class);
    }

    public function validationErrors(): HasMany
    {
        return $this->hasMany(ValidationError::class);
    }

    public function basisOfSchedules(): HasMany
    {
        return $this->hasMany(BasisOfSchedule::class);
    }
}