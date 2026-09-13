# BESS Schedule Generator — Claude Code Instruction

> **Stack:** Laravel 13 · Filament 5 · Tailwind CSS · Alpine.js  
> **This file is the governing instruction. Read it entirely before writing any code.**

---

## 1. Governing Principles — Non-Negotiable

- **Packages are optional. Outputs are mandatory. Dependencies attach to outputs.**
- Never hardcode a dependency between two specific packages. Always resolve through output keys.
- No package may be generated with an open start or open finish.
- Every package completion milestone must drive a downstream package or roll up to a phase closure.
- Validation runs before export. Critical errors block export entirely.
- **Build order:** data model → seeders → engines → UI. Never jump to UI first.
- Domain logic lives in Service classes. UI logic lives in Filament resources. Never mix them.
- All package templates are seeded data, not hardcoded logic.

---

## 2. Product Vision

A rule-driven schedule logic engine that takes a BESS project configuration as input and compiles a logically valid, dependency-connected schedule graph ready for Primavera P6 XER export.

**Not** a CRUD app. **Not** a static template tool.

---

## 3. Standard WBS Structure

Every generated schedule must use these six top-level branches:

| Branch | Contains |
|--------|----------|
| Milestones | NTP, design review approvals, procurement awards, construction start, energisation milestones, handover |
| Project Management Plan | Kickoff, PMP, schedule plan, risk register, design management plan, procurement strategy |
| Design | All discipline-based design packages and design management activities |
| Procurement | All procurement packages, vendor data, long-lead award milestones |
| Construction | All installation, civil, interface, and workfront execution packages |
| Commissioning | Pre-commissioning, energisation, online commissioning, hold-point testing, SAT, handover |

> Design packages must NOT appear under Construction. Procurement must NOT appear under Design.

---

## 4. Site Topology Model

```
Site
└── Zone (1, 2, 3...)
    └── Block (1, 2, 3...)
        ├── Battery Groups (e.g. 1-4, 5-8, 9-12, 13-16, 17-20) — 4 batteries per group
        ├── PCS Groups (e.g. 1-2, 3-4, 5-6, 7-8, 9-10) — 2 PCS per group
        └── SUT Units (SUT 1, SUT 2...)
```

**Configurable parameters:** number of zones, blocks per zone, batteries per group, battery groups per block, PCS per group, PCS groups per block, SUTs per block.

**Interface connections generated:**
- Battery Group N → PCS Group N (DC cables)
- PCS Group N → SUT N (LV AC cables)
- SUT N → Switchroom Feeder N (MV cables)
- Switchroom → Main Transformer
- Switchroom → Control Room
- Switchroom → Substation

---

## 5. Seven Engines — Architecture

### 5.1 ProjectSetupService
Captures and persists project configuration. Triggers engine chain.

### 5.2 DeliveryModelService — Strategy Pattern
```
DeliveryModelStrategy (interface)
├── EpcDeliveryStrategy
├── BopFreeIssuedStrategy
├── OwnerProvidedDesignStrategy
├── ConstructOnlyStrategy
└── SplitContractStrategy
```

### 5.3 PackageLibraryService
Instantiates package_templates into package_instances for a project.

### 5.4 DependencyResolverService
Resolves provider for every required output_key. Handles dynamic predecessor gathering for design management packages. Flags unresolved outputs as validation errors.

### 5.5 GraphCompilerService — Pipeline Pattern
Sequential stages: instantiate activities → build relationships → assign WBS → generate boundary milestones → generate roll-up closures → generate interface activities → generate topology activities.

### 5.6 ValidationService — Rule Collection Pattern
Eight independent rules (see Section 10). Each implements `validate(Project): ValidationResult`.

### 5.7 ExportService — Strategy Pattern
```
ExportStrategy (interface)
├── XerExportStrategy
├── ExcelExportStrategy
└── JsonExportStrategy
```

---

## 6. Database Schema

### Core Tables

| Table | Key Columns |
|-------|-------------|
| projects | id, name, code, delivery_model, calendar, start_date, bess_free_issued, switchroom_exists, control_room_exists, transformer_exists, scada_included, export_profile_id |
| wbs_nodes | id, project_id, parent_id, code, name, level, sort_order |
| package_templates | id, code, name, type, discipline, wbs_category, maturity_level, ownership_mode_default, is_optional, is_conditional |
| activity_templates | id, package_template_id, sequence, name, duration_days, predecessor_sequence, is_milestone |
| output_definitions | id, package_template_id, output_key, output_name, output_type |
| dependency_rules | id, consumer_template_id, required_output_key, is_mandatory |
| package_instances | id, project_id, template_id, wbs_node_id, ownership_mode, selected, applicable, included_in_package_id |
| activity_instances | id, project_id, package_instance_id, wbs_node_id, activity_id_code, name, duration_days, is_milestone |
| relationships | id, project_id, predecessor_instance_id, successor_instance_id, relationship_type |
| provider_resolutions | id, project_id, output_key, provider_package_instance_id, provider_activity_instance_id |
| equipment_nodes | id, project_id, type, zone, block, group_number, label |
| interface_records | id, project_id, from_node_id, to_node_id, interface_type, generates_activities |
| calendars | id, name, workdays_per_week, holidays |
| export_profiles | id, name, format, settings_json |
| validation_errors | id, project_id, rule_class, severity, message, context_json, resolved |

### Ownership Mode Enum
- `internal` — generate all activities and milestones
- `external` — suppress activities, generate boundary receipt milestone only
- `included_elsewhere` — suppress standalone, resolve output through containing package
- `not_applicable` — suppress entirely, mark output as not required

---

## 7. Design Package Library — Complete Template Set

Every package follows this structure:
1. Review previous approval (2d)
2. Develop content activity 1 (varies)
3. Develop content activity 2 (varies)
4. Develop quantities and interfaces (varies)
5. Issue package (1d)
6. **[Milestone] Package Approved (0d)** ← this is the output_key

### 7.1 Root Package
| Package | Output Key | Depends On |
|---------|-----------|------------|
| Basis of Design / Concept GA | `bod_concept_ga_approved` | NTP / Project start |

### 7.2 Civil Design Stream
| Package Chain | Entry Anchor | 30% | 60% | 90% | IFC |
|--------------|-------------|-----|-----|-----|-----|
| Equipment Pad Design | `civil_design_inputs_approved` | `civil_30_eqpad_approved` | `civil_60_eqpad_approved` | `civil_90_eqpad_approved` | `civil_ifc_eqpad_approved` |
| BESS Foundation Design (Battery+PCS+SUT) | `civil_design_inputs_approved` | `civil_30_bess_fdn_approved` | `civil_60_bess_fdn_approved` | `civil_90_bess_fdn_approved` | `civil_ifc_bess_fdn_approved` |
| Switchroom Foundation Design | `civil_design_inputs_approved` | `civil_30_sr_fdn_approved` | `civil_60_sr_fdn_approved` | `civil_90_sr_fdn_approved` | `civil_ifc_sr_fdn_approved` |
| Control Room Foundation Design | `civil_design_inputs_approved` | `civil_30_cr_fdn_approved` | `civil_60_cr_fdn_approved` | `civil_90_cr_fdn_approved` | `civil_ifc_cr_fdn_approved` |
| Drainage Design | `civil_design_inputs_approved` | `civil_30_drain_approved` | `civil_60_drain_approved` | `civil_90_drain_approved` | `civil_ifc_drain_approved` |
| Road and Hardstand Design | `civil_design_inputs_approved` | `civil_30_road_approved` | `civil_60_road_approved` | `civil_90_road_approved` | `civil_ifc_road_approved` |
| Fence and Security Civil Design | `civil_design_inputs_approved` | `civil_30_fence_approved` | `civil_60_fence_approved` | `civil_90_fence_approved` | `civil_ifc_fence_approved` |

**Civil Design Inputs Package:** Depends on `bod_concept_ga_approved`. Output: `civil_design_inputs_approved`.

### 7.3 Electrical Primary Design Stream
**Concept SLD:** Depends on `bod_concept_ga_approved`. Output: `elec_primary_concept_sld_approved`.

| Package Chain | 30% | 60% | 90% | IFC |
|--------------|-----|-----|-----|-----|
| Collector / Feeder Design | `elec_p_30_feeder_approved` | `elec_p_60_feeder_approved` | `elec_p_90_feeder_approved` | `elec_p_ifc_feeder_approved` |
| Earthing Design | `elec_p_30_earth_approved` | `elec_p_60_earth_approved` | `elec_p_90_earth_approved` | `elec_p_ifc_earth_approved` |
| Lightning Protection Design | `elec_p_30_lp_approved` | `elec_p_60_lp_approved` | `elec_p_90_lp_approved` | `elec_p_ifc_lp_approved` |
| Switchroom Interface Design | `elec_p_30_sr_ifc_approved` | `elec_p_60_sr_ifc_approved` | `elec_p_90_sr_ifc_approved` | `elec_p_ifc_sr_ifc_approved` |
| Substation Interface Design | `elec_p_30_sub_ifc_approved` | `elec_p_60_sub_ifc_approved` | `elec_p_90_sub_ifc_approved` | `elec_p_ifc_sub_ifc_approved` |

All depend on `elec_primary_concept_sld_approved`.

### 7.4 Electrical Secondary / Protection Stream
**Protection Philosophy:** Depends on `elec_primary_concept_sld_approved`. Output: `elec_s_protection_philosophy_approved`.

| Package Chain | 30% | 60% | 90% | IFC |
|--------------|-----|-----|-----|-----|
| Relay and Interlocking Design | `elec_s_30_relay_approved` | `elec_s_60_relay_approved` | `elec_s_90_relay_approved` | `elec_s_ifc_relay_approved` |
| Metering Design | `elec_s_30_meter_approved` | `elec_s_60_meter_approved` | `elec_s_90_meter_approved` | `elec_s_ifc_meter_approved` |

### 7.5 SCADA / Communications Stream
**RTU / Network Architecture:** Depends on `bod_concept_ga_approved`. Output: `scada_rtu_network_arch_approved`.

| Package Chain | 30% | 60% | 90% | IFC/AFC |
|--------------|-----|-----|-----|---------|
| Telemetry and Point List Design | `scada_30_tpl_approved` | `scada_60_tpl_approved` | `scada_90_tpl_approved` | `scada_ifc_tpl_approved` |
| Fiber / Communications Link Design | `scada_30_fiber_approved` | `scada_60_fiber_approved` | `scada_90_fiber_approved` | `scada_ifc_fiber_approved` |

### 7.6 Mechanical / HVAC Stream
**HVAC Basis of Design:** Output: `hvac_basis_approved`. Conditional — not applicable if HVAC included in vendor switchroom package.

| Package Chain | 30% | 60% | 90% | IFC |
|--------------|-----|-----|-----|-----|
| Switchroom HVAC Design | `hvac_30_sr_approved` | `hvac_60_sr_approved` | `hvac_90_sr_approved` | `hvac_ifc_sr_approved` |
| Control Room HVAC Design (optional) | `hvac_30_cr_approved` | `hvac_60_cr_approved` | `hvac_90_cr_approved` | `hvac_ifc_cr_approved` |

### 7.7 Fire System Design Stream
**Fire Basis of Design:** Output: `fire_basis_approved`. Conditional — not applicable if fire included in vendor switchroom.

| Package Chain | 30% | 60% | 90% | IFC |
|--------------|-----|-----|-----|-----|
| Switchroom Fire Detection and Alarm Design | `fire_30_sr_approved` | `fire_60_sr_approved` | `fire_90_sr_approved` | `fire_ifc_sr_approved` |

### 7.8 Commissioning / Test Documentation Stream
**Commissioning Basis and Strategy:** Output: `comm_basis_strategy_approved`.

| Package Chain | 30% | 60% | 90% | IFC |
|--------------|-----|-----|-----|-----|
| Commissioning Plan | `comm_plan_30_approved` | `comm_plan_60_approved` | `comm_plan_90_approved` | `comm_plan_ifc_approved` |
| ITP / ITC Package (depends on `comm_plan_ifc_approved`) | `itp_30_approved` | `itp_60_approved` | `itp_90_approved` | `itp_ifc_approved` |

### 7.9 Design Management Packages — Dynamic Predecessors
> The DependencyResolverService builds predecessor lists at runtime by querying all selected discipline packages at the matching maturity level.

| Package | Dynamic Predecessors | Output Key |
|---------|---------------------|------------|
| 30% Multidiscipline Design Review | All selected 30% discipline approvals | `dm_30_review_approved` |
| 60% Multidiscipline Design Review | All selected 60% discipline approvals | `dm_60_review_approved` |
| 90% Multidiscipline Design Review | All selected 90% discipline approvals | `dm_90_review_approved` |
| IFC / Final Multidiscipline Design Review | All selected IFC discipline approvals | `dm_ifc_review_approved` |
| Design Freeze / Overall Design Complete | `dm_ifc_review_approved` + remaining IFC approvals | `design_freeze_approved` |

---

## 8. Procurement Package Library

### Conditional Rules
| Condition | Behaviour |
|-----------|-----------|
| BESS free-issued | Suppress BESS procurement entirely |
| Substation scope exists | Generate substation main equipment procurement |
| Switchroom contractor-procured | Generate switchroom equipment procurement |
| Transformer contractor-procured | Generate transformer procurement |
| SCADA included | Generate RTU/SCADA panel procurement |
| HVAC in vendor switchroom | Suppress standalone HVAC procurement |

### Main Transformer Procurement
Activities: Spec/Bid/PO (10d) → Manufacturing (45d) → FAT (4d) → Shipping (14d) → Incoming Inspection (1d) → **`transformer_delivered` (Milestone)**

**Parallel track** — runs concurrently with civil works. Converges at mechanical installation.

### Switchroom Equipment Procurement
Two parallel chains (MV Switchgear + LV Switchboard) converging at shipping.
- MV: Spec/Bid/PO (10d) → Manufacturing (45d) → FAT (4d)
- LV: Spec/Bid/PO (10d) → Manufacturing (30d) → FAT (3d)
- Combined: Shipping (14d) → Incoming Inspection (1d) → **`switchroom_equip_delivered` (Milestone)**

> **Procurement maturity principle:** Long-lead PO award may occur at 30% design. Manufacturing release occurs only after design freeze or agreed maturity gate. These are two distinct milestone events.

---

## 9. Construction Package Library

### Main Transformer Installation
**WBS:** Construction → Main Transformer

| Sub-WBS | Key Activities | Gate |
|---------|---------------|------|
| Civil and Foundation Works | Survey → Blinding → Pour (1d) → Curing (7d) → Bund/Trench → Sign-Off | `civil_ifc_sr_fdn_approved` + mobilisation |
| Mechanical Installation | Crane/Lift/Position → Fittings → Instruments → Sign-Off | Civil sign-off + `transformer_delivered` |
| Oil Filling and Treatment | Filtration/Degassing (3d) → Fill/Settle/Test (3d) | Mechanical sign-off |
| Electrical Works | HV/LV cables → NER/Earthing → Control/SCADA → Sign-Off | Civil trench + mechanical |
| Protection and Control | Differential relay wiring → Buchholz/PRD → Cooling/SCADA → Sign-Off | Electrical sign-off |

### Switchroom Installation
**WBS:** Construction → Switchroom

| Sub-WBS | Key Activities | Gate |
|---------|---------------|------|
| Civil and Building Works | Survey → Blinding → Pour → Curing → Erection → Roofing → Doors/Louvres (parallel: Trenches/Earthing) → Sign-Off | `civil_ifc_sr_fdn_approved` |
| Mechanical and Equipment Installation | MV Switchgear position → LV Switchboard → P&C Panels → UPS → HVAC → Fire → Lighting → Sign-Off | Civil sign-off + `switchroom_equip_delivered` |
| Electrical Works | MV cables incoming/outgoing → LV power → Control/protection → SCADA → DC/UPS → Earthing → Sign-Off | Trenches + mechanical |
| Protection and Control | MV relay wiring → Busbar protection → Interlocks → SCADA/RTU → DC/UPS → HVAC/Fire integration → Sign-Off | Electrical sign-off |

---

## 10. Commissioning Package Library

### Switchroom Pre-Commissioning (Depends on: Protection and Control Sign-Off)
MV IR/HV tests → MV Cable tests → LV tests → CT/VT tests → Earthing test → DC/UPS function → Protection relay injection → Interlock tests → SCADA loop test → HVAC/Fire function → **Punch List Closeout and Sign-Off**

### Switchroom Commissioning (Depends on: Pre-Commissioning Sign-Off)
Commissioning plan/PTW → Safety clearance → First Energisation MV Incomer → Phase rotation check → MV feeder energisation → Live protection tests → SCADA live verification → HVAC/Fire live commissioning → Punch list → **As-Built and Handover**

### Transformer Pre-Commissioning (Depends on: P&C Sign-Off + Oil Filling complete)
IR/winding/TTR tests → Tan delta/hi-pot → Earthing/DGA → Protection relay injection → **SCADA loop test and Sign-Off**

### Transformer Commissioning (Depends on: Pre-Commissioning Sign-Off)
Commissioning plan/PTW → First energisation → Step load test (3d) → Live protection/DGA/SCADA → **Punch list and Handover**

### Energisation Milestones (must be specific — not one vague milestone)
| Milestone | Output Key | Prerequisites |
|-----------|-----------|---------------|
| Substation Ready to Energise | `substation_ready_to_energise` | Completion + Electrical testing + Safety + Authority |
| Substation Energised | `substation_energised` | `substation_ready_to_energise` + network readiness |
| Switchroom Ready to Energise | `switchroom_ready_to_energise` | Pre-comm sign-off + Safety + Controls + Protection |
| Switchroom Energised | `switchroom_energised` | `switchroom_ready_to_energise` + `substation_energised` |
| Auxiliary Power Available | `aux_power_available` | `switchroom_energised` + UPS/DC ready |
| First BESS Energisation | `first_bess_energisation` | BESS pre-comm + Protection + SCADA + `aux_power_available` |
| BESS Ready for Online Commissioning | `bess_ready_online_comm` | `first_bess_energisation` + hold point release |
| Hold Point 1 Released | `hold_point_1_released` | Defined by commissioning plan |

---

## 11. Validation Rules

| Rule | Severity | Check |
|------|----------|-------|
| MissingOutputProviderRule | Critical | Every required output_key has a resolved provider |
| OpenStartRule | Critical | Every activity has at least one predecessor |
| OpenFinishRule | Critical | Every activity has at least one successor |
| OrphanMilestoneRule | Critical | No milestone has zero predecessors AND zero successors |
| InvalidPackageCombinationRule | Critical | No package has conflicting ownership states |
| EnergisationPrerequisiteRule | Critical | Every energisation milestone has all prerequisite groups |
| SuppressedOutputRule | Critical | Suppressed packages do not leave required outputs unresolved |
| MissingCompletionRollupRule | Warning | Package completion milestone drives downstream or rolls up |

---

## 12. Filament UI — Ten-Step Wizard

| Step | Title | Engine |
|------|-------|--------|
| 1 | Project Setup | ProjectSetupService |
| 2 | Delivery Model | DeliveryModelService |
| 3 | Equipment and Topology | ProjectSetupService |
| 4 | Design Package Selection | PackageLibraryService |
| 5 | Procurement Package Selection | PackageLibraryService |
| 6 | Equipment Packages | PackageLibraryService |
| 7 | Interface Setup | GraphCompilerService (preview) |
| 8 | Energisation / Commissioning Settings | ProjectSetupService |
| 9 | Validation | ValidationService |
| 10 | Export | ExportService |

---

## 13. Implementation Sequence

1. Database migrations — all tables
2. Eloquent models with relationships, casts, enums
3. Package template seeders — full library
4. DependencyResolverService + unit tests
5. GraphCompilerService + unit tests
6. ValidationService — all 8 rules + unit tests
7. DeliveryModelService — all 5 strategies
8. ProjectSetupService + PackageLibraryService
9. Filament wizard Steps 1–4
10. Filament wizard Steps 5–8
11. Validation step + schedule preview
12. ExportService — XER format
13. Topology construction activities (zone/block/group generation)

---

## 14. File Structure

```
app/
  Enums/
    OwnershipMode.php
    PackageType.php
    DeliveryModel.php
    RelationshipType.php
    ValidationSeverity.php
  Models/
    Project.php, WbsNode.php, PackageTemplate.php
    ActivityTemplate.php, OutputDefinition.php
    DependencyRule.php, PackageInstance.php
    ActivityInstance.php, MilestoneInstance.php
    Relationship.php, ProviderResolution.php
    EquipmentNode.php, InterfaceRecord.php
    Calendar.php, ExportProfile.php, ValidationError.php
  Services/Bess/
    ProjectSetupService.php
    DeliveryModelService.php
    PackageLibraryService.php
    DependencyResolverService.php
    GraphCompilerService.php
    ValidationService.php
    ExportService.php
    Strategies/
      DeliveryModelStrategy.php (interface)
      EpcDeliveryStrategy.php
      BopFreeIssuedStrategy.php
      OwnerProvidedDesignStrategy.php
      ConstructOnlyStrategy.php
      SplitContractStrategy.php
    Export/
      ExportStrategy.php (interface)
      XerExportStrategy.php
      ExcelExportStrategy.php
    Validation/
      ValidationRule.php (interface)
      MissingOutputProviderRule.php
      OpenStartRule.php
      OpenFinishRule.php
      OrphanMilestoneRule.php
      InvalidPackageCombinationRule.php
      EnergisationPrerequisiteRule.php
      SuppressedOutputRule.php
      MissingCompletionRollupRule.php
database/
  migrations/
  seeders/
    PackageLibrarySeeder.php
    CivilDesignSeeder.php
    ElectricalPrimarySeeder.php
    ElectricalSecondarySeeder.php
    ScadaDesignSeeder.php
    HvacDesignSeeder.php
    FireDesignSeeder.php
    CommissioningDesignSeeder.php
    DesignManagementSeeder.php
    ProcurementSeeder.php
    ConstructionSeeder.php
    CommissioningPackageSeeder.php
tests/
  Unit/
    DependencyResolverTest.php
    GraphCompilerTest.php
    ValidationServiceTest.php
    DeliveryModelStrategyTest.php
  Feature/
    ProjectGenerationTest.php
    ExportTest.php
```

---

## 15. Coding Standards

- PHP 8.3+, strict types on all files
- All service classes in `app/Services/Bess/` namespace
- All engines injected via Laravel service container
- No business logic in Filament resources or controllers
- Domain logic must be unit-testable in isolation
- Use Laravel Enums for all categorical values
- Output keys are snake_case string constants

### Critical Rules
- NEVER link two package instances directly — always through output_key → provider_resolution
- NEVER hardcode a predecessor package ID in any engine
- NEVER generate activities without checking ownership_mode first
- NEVER allow export if ValidationService returns Critical errors
- ALWAYS generate boundary milestones for External packages even when activities are suppressed
- ALWAYS preserve parallel track structure for procurement

---

## 16. Outstanding Templates (Stub Seeders Required)

| Package | Status |
|---------|--------|
| Protection Philosophy Package | Template not yet provided — create stub |
| Relay and Interlocking Design 30/60/90/IFC | Template not yet provided — create stub |
| Fiber / Communications Link Design 30/60/90/IFC | Template not yet provided — create stub |
| BESS Battery Group Installation | Topology-driven — generate from zone/block/group config |
| PCS Group Installation | Topology-driven — generate from zone/block/group config |
| SUT Installation | Topology-driven |
| Substation Construction Package | Template not yet provided — create stub |
| Control Room Installation Package | Template not yet provided — create stub |
| BESS Procurement Package | Conditional — suppressed if BESS free-issued |
| Substation Equipment Procurement | Template not yet provided — create stub |

---

*Think like: a planner. A project controls engineer. A package interface manager. And a software architect.*
