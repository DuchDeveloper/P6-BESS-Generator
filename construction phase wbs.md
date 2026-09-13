# Construction WBS Structure — Specification for Claude Code

## Purpose
Implement a hierarchical Work Breakdown Structure (WBS) for BESS (Battery Energy Storage System) construction projects. Every construction **Phase** must be grouped into exactly three **Work Groups**: **BESS**, **Substation**, and **Balance of Plant (BOP)**. Each Work Group then contains specific **Work Packages**.

## Core Principle — Every Node is a WBS

Every node in the tree is itself a WBS element — not descriptive text, not a label, not a tag. Phases are WBS nodes. Work Groups are WBS nodes. **Work Packages are WBS nodes.** Activities are WBS nodes.

That means every item in the lists below (e.g. *"DC cabling (battery to PCS)"*, *"PCS foundations"*, *"Main transformer erection"*) is a first-class WBS record in the database with its own:

- WBS code (e.g. `3.1.1`)
- Name
- Parent reference
- Budget / cost
- Planned start and finish
- Percent complete
- Responsible party / contractor
- Status
- Rollup contribution to its parent

Treat the bulleted lists in this document as the **default set of Work Package WBS nodes** to seed — not as prose.

## Hierarchy

Every level in this diagram is a WBS node:

```
Project                          (WBS level 0)
└── Phase                        (WBS level 1 — e.g. Civil, Mechanical, Electrical, C&I, T&C)
    └── Work Group               (WBS level 2 — BESS | Substation | BOP, always these three)
        └── Work Package         (WBS level 3 — e.g. DC cabling, PCS foundations)
            └── Activity         (WBS level 4 — schedulable task, optional)
```

Rule: a Phase is never directly linked to a Work Package. It must go through a Work Group.

## WBS Coding Convention

Use a numeric dotted code: `P.G.K.A`

- `P` = Phase (1 = Civil, 2 = Mechanical, 3 = Electrical, 4 = C&I, 5 = T&C)
- `G` = Work Group (1 = BESS, 2 = Substation, 3 = BOP)
- `K` = Work Package (sequential per group)
- `A` = Activity (sequential per package, optional)

Example: `1.1.2` = Civil → BESS → PCS Foundations.

## Phases and Their Contents

### Phase 1 — Civil Works (WBS `1`)

**1.1 BESS Civil** — Work Group WBS
Each line below is a Work Package WBS:
- `1.1.1` Battery unit foundations / plinths / piling
- `1.1.2` PCS (Power Conversion System) foundations
- `1.1.3` SUT (Step-Up Transformer) foundations and bunds
- `1.1.4` Cable trenches and pits within the BESS compound
- `1.1.5` Hardstand, access roads, and laydown within the BESS compound
- `1.1.6` BESS compound earthworks, drainage, and fenceline civil (post footings, gate post footings — install of panels/gates is in Phase 2)

**1.2 Substation Civil** — Work Group WBS
Each line below is a Work Package WBS:
- `1.2.1` Main power transformer foundations and oil containment bunds
- `1.2.2` Switchyard equipment foundations (CBs, CTs, VTs, disconnectors, surge arresters, NERs)
- `1.2.3` Gantry and structural steel foundations
- `1.2.4` Earthmat trenching and backfill
- `1.2.5` Switchyard crushed-rock surfacing, cable trenches, and fenceline civil (post footings — install of panels/gates is in Phase 2)

**1.3 BOP Civil** — Work Group WBS
Each line below is a Work Package WBS:
- `1.3.1` Switchroom building
- `1.3.2` Control room building
- `1.3.3` Site office, warehouse, O&M building
- `1.3.4` Guardhouse / security building
- `1.3.5` Site-wide access roads, hardstands, and drainage
- `1.3.6` Perimeter fenceline civil (post footings, gate post footings — install of panels/gates is in Phase 2)
- `1.3.7` General earthworks not attributable to BESS or Substation

---

### Phase 2 — Mechanical Installation (WBS `2`)

**2.1 BESS Mechanical** — Work Group WBS
Each line below is a Work Package WBS:
- `2.1.1` Battery enclosure / container placement and anchoring
- `2.1.2` PCS unit installation and anchoring
- `2.1.3` SUT erection, assembly, and oil filling
- `2.1.4` HVAC installation for battery enclosures
- `2.1.5` Fire detection and suppression system (mechanical scope)
- `2.1.6` BESS compound security fencing and gate installation (posts, panels, gate leaves, hardware)

**2.2 Substation Mechanical** — Work Group WBS
Each line below is a Work Package WBS:
- `2.2.1` Main transformer erection, assembly, bushing fit, and oil filling
- `2.2.2` Switchyard equipment erection (disconnectors, CBs, CTs, VTs, arresters)
- `2.2.3` Busbar, clamp, and connector installation
- `2.2.4` Gantry and support structure erection
- `2.2.5` Compressed-air / SF6 plant installation (if applicable)
- `2.2.6` Switchyard security fencing and gate installation (posts, panels, gate leaves, hardware)

**2.3 BOP Mechanical** — Work Group WBS
Each line below is a Work Package WBS:
- `2.3.1` Switchroom and control room HVAC
- `2.3.2` Building firefighting systems (sprinklers, hydrants, extinguishers)
- `2.3.3` Plumbing, drainage, and potable water
- `2.3.4` Doors, louvres, roller shutters, and architectural metalwork
- `2.3.5` Lifting equipment and davit arms
- `2.3.6` Site perimeter fencing and gate installation (posts, panels, gate leaves, hardware, manual gate operators)

---

### Phase 3 — Electrical Installation (WBS `3`)

**3.1 BESS Electrical** — Work Group WBS
Each line below is a Work Package WBS:
- `3.1.1` DC cabling (battery to PCS)
- `3.1.2` AC cabling (PCS to SUT LV side)
- `3.1.3` MV cabling (SUT HV side to collection switchgear)
- `3.1.4` Auxiliary and UPS supplies within the BESS compound
- `3.1.5` Earthing and lightning protection within the BESS compound
- `3.1.6` Lighting and small power within the BESS compound

**3.2 Substation Electrical** — Work Group WBS
Each line below is a Work Package WBS:
- `3.2.1` HV cabling (SUT to MV/HV switchgear)
- `3.2.2` Transformer LV/HV terminations
- `3.2.3` Switchyard primary and secondary wiring
- `3.2.4` Main earthing grid and down-conductors
- `3.2.5` Protection, control, and metering cabling

**3.3 BOP Electrical** — Work Group WBS
Each line below is a Work Package WBS:
- `3.3.1` Building lighting, small power, and distribution boards
- `3.3.2` Site-wide external lighting
- `3.3.3` Auxiliary LV distribution and essential services board
- `3.3.4` Site UPS and battery systems
- `3.3.5` General-purpose earthing for buildings
- `3.3.6` Automated gate power supply and motor terminations (electrical scope — access control is in Phase 4)

---

### Phase 4 — Controls, Instrumentation & Communications (C&I) (WBS `4`)

**4.1 BESS C&I** — Work Group WBS
Each line below is a Work Package WBS:
- `4.1.1` BMS (Battery Management System) integration
- `4.1.2` EMS (Energy Management System) interfacing
- `4.1.3` PCS controls and local HMI
- `4.1.4` Fire and HVAC controls

**4.2 Substation C&I** — Work Group WBS
Each line below is a Work Package WBS:
- `4.2.1` Protection relay configuration and integration
- `4.2.2` SCADA RTU / gateway
- `4.2.3` Revenue and check metering
- `4.2.4` Tele-protection and inter-tripping

**4.3 BOP C&I** — Work Group WBS
Each line below is a Work Package WBS:
- `4.3.1` Plant-wide SCADA / master controller
- `4.3.2` Site communications backbone (fibre, network switches)
- `4.3.3` CCTV, intrusion detection, access control
- `4.3.4` Public address and site telephony

---

### Phase 5 — Testing & Commissioning (T&C) (WBS `5`)

**5.1 BESS T&C** — Work Group WBS
Each line below is a Work Package WBS:
- `5.1.1` Battery capacity and performance testing
- `5.1.2` PCS commissioning and grid-code compliance
- `5.1.3` SUT FAT review, SAT, and energisation
- `5.1.4` BMS ↔ EMS ↔ PCS integration testing

**5.2 Substation T&C** — Work Group WBS
Each line below is a Work Package WBS:
- `5.2.1` Main transformer pre-energisation testing and energisation
- `5.2.2` Protection scheme end-to-end testing
- `5.2.3` Switchyard primary injection and functional checks
- `5.2.4` HV insulation and dielectric testing

**5.3 BOP T&C** — Work Group WBS
Each line below is a Work Package WBS:
- `5.3.1` Building services commissioning (HVAC, fire, lighting)
- `5.3.2` Site-wide SCADA integration testing
- `5.3.3` Grid connection and network operator witness tests
- `5.3.4` Performance / reliability run and handover

---

## WBS Applicability & Scope Families

Not every WBS Work Package applies to every project. A retrofit might not need a guardhouse. A brownfield site might already have perimeter fencing. A behind-the-meter BESS might not have its own dedicated switchyard. The seeded WBS defined above is a **master library** — each project selects the subset that applies.

### Applicability Flag

Every Work Package (and optionally every Activity) has an `is_applicable` boolean flag:

- `true` → the WP appears in the project's active WBS, Gantt, budget, rollups, and reports
- `false` → the WP is hidden from all schedule views but **retained** in the database so it can be reactivated later

Applicability is **project-scoped**. Toggling `1.3.6` off on Project A has no effect on Project B.

### Scope Families — Cross-Phase Linking

Most real-world scopes span multiple phases. Perimeter fencing has a civil component (`1.3.6` footings), a mechanical component (`2.3.6` panels and gate install), an electrical component (`3.3.6` gate motor supply), and a C&I component (part of `4.3.3` access control). If the project has no perimeter fencing, **all four lines** should deactivate together.

A **Scope Family** groups Work Packages that share a real-world scope across phases. Toggling any member cascades the applicability change to every other member.

Pre-seeded Scope Families:

| Family | Linked Work Packages | Default |
|---|---|---|
| Site Perimeter Fencing & Gates | `1.3.6`, `2.3.6`, `3.3.6` (automation), `4.3.3` (access-control portion) | On |
| BESS Compound Fencing | `1.1.6` (fenceline portion), `2.1.6` | On |
| Switchyard Fencing | `1.2.5` (fenceline portion), `2.2.6` | On |
| Guardhouse | `1.3.4` | Off |
| O&M Building | `1.3.3`, `2.3.3`, `3.3.1`, `5.3.1` | On |
| Warehouse | `1.3.3` (warehouse portion) | Off |
| SF6 / Compressed-Air Plant | `2.2.5` | Off |
| Automated Gates | `3.3.6`, `4.3.3` (access-control portion) | Off |
| CCTV & Intrusion Detection | `4.3.3` (CCTV portion) | On |
| Site External Lighting | `3.3.2` | On |

Users can create new Scope Families in the UI when they spot recurring cross-phase scopes that aren't listed.

### Toggle Behaviour (UX Contract)

1. Each Work Package row in the WBS tree shows a toggle switch in the rightmost column.
2. Toggling a WP that is **not** part of a Scope Family flips its own `is_applicable` flag only.
3. Toggling a WP that **is** part of a Scope Family opens a confirmation modal:
   > *"'Perimeter fencing and gates' is part of the Site Perimeter Fencing scope family. Turning it on/off will also affect 3 other Work Packages: `2.3.6`, `3.3.6`, `4.3.3`. Apply to all?"*
   Options: **Apply to all** · **Just this one** · **Cancel**.
4. **Apply to all** cascades the flag to every family member in a single database transaction.
5. **Just this one** sets an override flag on the pivot row so this member diverges from the family default until the user resets it.
6. Linked WPs display a small chain-link icon; hovering shows the family name and members.
7. Deactivated WPs render **greyed out** with a "Not in this project" badge — they are never removed from the tree, so reactivation is always one click away.
8. A project-level "Scope Setup" screen lets the user toggle whole Scope Families at once, as a faster alternative to hunting individual WPs in the tree.

### Rollup Implication

Budgets, percent complete, and earned value rollups **exclude** any node with `is_applicable = false`. A Work Group or Phase whose children are all deactivated rolls up as `N/A`, not `0%`.

---

## Design Deliverables — IFC Drives Construction

Most WBS Work Packages cannot start until the corresponding **Issued-for-Construction (IFC)** design is released. The civil IFC drawings for `1.1.1` (Battery foundations) drive the civil works of `1.1.1`. The mechanical IFC drawings for `2.2.1` (Main transformer erection) drive the mechanical works of `2.2.1`. The principle is general: for each Work Package, the IFC deliverable from the matching discipline is a **driving predecessor** of the construction work.

### Discipline Mapping

Each Phase is driven by a specific class of design deliverable:

| Phase | Driving Design Deliverable(s) |
|---|---|
| 1 — Civil | Civil IFC drawings, rebar schedules, pile design, geotechnical reports |
| 2 — Mechanical | Mechanical IFC drawings, GA drawings, anchor-bolt layouts, P&IDs |
| 3 — Electrical | Single-line diagrams, cable schedules, termination drawings, earthing layouts (all IFC) |
| 4 — C&I | Logic diagrams, I/O lists, network diagrams, protection settings (all IFC) |
| 5 — T&C | Approved ITPs, commissioning procedures, test scripts (rather than drawings) |

### The Driving Rule

For every Work Package flagged as `has_design_driver = true`:

1. The WP **cannot be marked as started** until its driving deliverable reaches status `IFC`.
2. The WP's `earliest_start` is calculated as `max(planned_start, driving_deliverable.ifc_issued_date)`.
3. If the driving deliverable is revised after IFC (e.g. IFC Rev A → IFC Rev B), the WP flags a **"Design updated — review impact"** warning until an engineer acknowledges it.
4. If the driving deliverable slips, the WP's earliest-start recalculates and the Gantt reflects the new constraint automatically.

### Exceptions — WPs Without a Driving IFC

Not every WBS has a single driving IFC document. Examples:

- `1.3.7` General earthworks — proceeds on the site general arrangement, not a specific IFC package.
- `1.3.5` Site-wide access roads — often driven by a civil works package plan, not a per-road IFC.
- `5.x` T&C items — driven by approved ITPs and commissioning procedures instead of construction drawings.

Mark these as `has_design_driver = false`. The earliest-start rule is skipped; the WP can start on its planned date.

### Design Status Progression

Every Design Deliverable carries a status that moves through:

```
not_started → IFR (Issued for Review)
            → IFA (Issued for Approval)
            → IFC (Issued for Construction)   ← this unlocks construction
            → superseded (when a later revision lands)
```

Only `IFC` unlocks the linked Work Package. `IFR` and `IFA` do not.

### Interaction with Applicability

If a Work Package has `is_applicable = false` (toggled off via the Scope Family toggle), its Design Deliverables are marked `not_required` and excluded from design-progress reports. Reactivating the WP restores the deliverables to their last-known status.

---

## Data Model (Laravel)

Every node type (`Phase`, `WorkGroup`, `WorkPackage`, `Activity`) represents a WBS element. They share the same core fields: `code`, `name`, `description`, `budget`, `planned_start`, `planned_finish`, `percent_complete`, `status`. Consider a shared `HasWbsAttributes` trait or a polymorphic `wbs_nodes` table if you want a single source of truth for rollups and queries.

Suggested Eloquent models and relationships:

- `Project` hasMany `Phase`
- `Phase` hasMany `WorkGroup` (always three: BESS, Substation, BOP — seed automatically on Phase creation)
- `WorkGroup` hasMany `WorkPackage`
- `WorkPackage` hasMany `Activity`

Suggested columns (core only):

- `phases`: `id`, `project_id`, `code` (1–5), `name`, `sort_order`, `budget`, `planned_start`, `planned_finish`, `percent_complete`
- `work_groups`: `id`, `phase_id`, `type` (enum: `bess`, `substation`, `bop`), `code`, `name`, `budget`, `planned_start`, `planned_finish`, `percent_complete`
- `work_packages`: `id`, `work_group_id`, `code`, `name`, `description`, `planned_start`, `planned_finish`, `percent_complete`, `budget`, `responsible_party`, `status`, `is_applicable` (bool, default `true`), `has_design_driver` (bool, default `true`)
- `activities`: `id`, `work_package_id`, `code`, `name`, `planned_start`, `planned_finish`, `percent_complete`, `responsible_party`, `status`, `is_applicable` (bool, default `true`)

Scope family tables:

- `scope_families`: `id`, `project_id`, `code`, `name`, `description`, `is_applicable` (bool — the family-level default for new members)
- `scope_family_work_package`: `id`, `scope_family_id`, `work_package_id`, `is_override` (bool — `true` when this member has been toggled independently of the family)

Design deliverable table:

- `design_deliverables`: `id`, `project_id`, `work_package_id`, `discipline` (enum: `civil`, `mechanical`, `electrical`, `ci`, `tc`), `document_number`, `title`, `current_revision`, `status` (enum: `not_started`, `ifr`, `ifa`, `ifc`, `superseded`, `not_required`), `ifc_issued_date` (nullable), `is_driving` (bool — marks the deliverable that drives the WP's earliest start), `supersedes_id` (nullable self-reference), `file_path` (nullable)

A Work Package has many Design Deliverables, but at most one `is_driving = true` per discipline. The driving deliverable's `ifc_issued_date` feeds the WP's `earliest_start` calculation.

Relationships:

- `WorkPackage` belongsToMany `ScopeFamily` through `scope_family_work_package`
- `ScopeFamily` belongsToMany `WorkPackage`
- `ScopeFamily` belongsTo `Project` (families are project-scoped so each project can tailor its own)
- `WorkPackage` hasMany `DesignDeliverable`
- `DesignDeliverable` belongsTo `WorkPackage`; `DesignDeliverable` belongsTo `DesignDeliverable` as `supersededBy`

Enforce in code:
1. A `Phase` cannot exist without its three `WorkGroup` children — create them in a model observer or factory.
2. The `WorkGroup.type` enum is fixed to `bess | substation | bop`. No other values.
3. WBS `code` is generated, not user-entered — compose from parent codes on save (`{phase.code}.{group.code}.{package.code}.{activity.code}`).
4. Every Work Package must have a non-null `budget` and `planned_start`/`planned_finish` before it can be marked active — it is a real WBS line, not a label.
5. Toggling a `ScopeFamily.is_applicable` cascades to all linked Work Packages in a single DB transaction, **except** those with `is_override = true`.
6. Toggling an individual Work Package that belongs to a Scope Family must go through a service class (e.g. `ApplicabilityService`) that handles the "Apply to all / Just this one" branching — do not let controllers or Livewire components mutate `is_applicable` directly.
7. Rollup queries (`scopeApplicable()`) must filter out `is_applicable = false` at every level; provide this as a default global scope on the WBS models.
8. A Work Package with `has_design_driver = true` cannot transition to `status = 'in_progress'` unless its driving `DesignDeliverable.status = 'ifc'`. Enforce in a `WorkPackageStatusService` (not in the controller). Allow an override with an audit-logged reason for emergency starts.
9. A Work Package's `earliest_start` accessor returns `max(planned_start, drivingDeliverable.ifc_issued_date)` when a driving deliverable exists, otherwise `planned_start`.
10. When a `DesignDeliverable` transitions to `superseded`, the linked WP raises a `DesignRevisedEvent` that flags the row for engineer acknowledgement.

## Livewire UI Guidance

### WBS Tree Component
- Build a single `WbsTree` Livewire component that renders the full hierarchy with collapsible nodes (use Alpine `x-data="{ open: false }"` for expand/collapse — keep state client-side, don't round-trip to the server for expand/collapse).
- Use a nested `wire:key` on each node so re-renders don't lose Alpine state.
- Provide inline edit for Work Package names and progress, using `wire:model.lazy` on blur — avoid `wire:model.live` on every keystroke.
- Style with Tailwind using indentation via `pl-4`, `pl-8`, `pl-12` per level; colour-code Work Groups (BESS, Substation, BOP) with distinct badge colours for fast scanning.
- Progress bars: compute `percent_complete` of a Phase as the weighted average of its Work Groups (by budget), and of a Work Group as the weighted average of its Work Packages. Do this in an accessor, not in Blade. Rollups must respect `is_applicable`.

### Applicability Toggle
- Right-align a toggle switch on every Work Package row. Use a Tailwind/Alpine-driven switch (e.g. a styled `button` with `role="switch"` and `aria-checked`) — not a raw checkbox.
- Wire the toggle to a single Livewire method: `toggleApplicable($workPackageId)`. That method delegates to `ApplicabilityService` — it does not mutate state directly.
- If the WP belongs to a Scope Family, the method returns a prompt payload instead of mutating; the component opens an Alpine-driven modal with the three choices (*Apply to all*, *Just this one*, *Cancel*).
- After confirmation, dispatch a single Livewire event (`wbs-applicability-changed`) so other panels (Gantt, budget, dashboards) re-fetch without a full page reload.
- Deactivated rows: apply `opacity-50`, a dashed left border, and a "Not in this project" pill. Keep the toggle visible so reactivation is always one click away.
- Linked WPs display a chain-link icon (`lucide:link` or equivalent) before the code; hovering shows a tooltip with the family name and the other linked codes.

### Scope Setup Screen
- Provide a separate `ScopeSetup` Livewire component listing all Scope Families with their members and a master toggle per family. This is the fastest way to configure a new project before anyone touches the tree.
- Show a "Coverage" summary at the top: *"24 of 38 Work Packages applicable · 6 of 10 Scope Families active"*.

### Design Status on WBS Rows
- Every Work Package row shows a coloured pill with the driving deliverable's status: grey (not started), amber (IFR/IFA), green (IFC), red (superseded — needs review). WPs with `has_design_driver = false` show no pill.
- Hovering the pill reveals a tooltip with the document number, revision, and issued date.
- The "Start" action on a WP is disabled when the driving deliverable is not `IFC`. The disabled button shows a tooltip: *"Civil IFC not released — document CIV-1.1.1 is still at IFA Rev B"*. Provide a separate "Override start" action behind a permission gate that requires a reason (logged to an audit table).
- Gantt view overlays a vertical marker on each WP bar at the IFC issued date; bars left of the marker render with a red left-edge warning.

### Design Dashboard
- Build a `DesignTracker` Livewire component listing all Design Deliverables grouped by discipline, with columns for document number, title, current revision, status, IFC issued date, linked WP code, and the WP's planned start.
- Highlight any row where `planned_start < today + 14 days` and `status != 'ifc'` — these are the WPs at immediate risk of design slippage.

## Seeder Requirement

Create a `ConstructionWbsSeeder` that, when a new project is created:
1. Seeds the five Phases, each with the three Work Groups, each pre-populated with the Work Packages listed in this document.
2. Seeds the Scope Families listed in the Applicability section and links each family to its member Work Packages.
3. Sets `is_applicable = true` for Work Packages and Scope Families marked **On** by default, and `is_applicable = false` for those marked **Off** (e.g. Guardhouse, SF6 Plant, Automated Gates).
4. Seeds a placeholder `DesignDeliverable` for every Work Package with `has_design_driver = true`, with `status = 'not_started'` and a document number generated from the WBS code (e.g. `CIV-1.1.1-001`, `MECH-2.2.1-001`). Design teams fill in the actual titles and revisions.
5. Sets `has_design_driver = false` on Work Packages without a single driving IFC (e.g. `1.3.5`, `1.3.7`, all `5.x` T&C items — these run off procedures/ITPs instead).

The team should be able to delete or rename Work Packages but not the Phase or Work Group skeleton. Scope Families are editable — users can add, rename, or remove them without touching the underlying WBS nodes.

## Acceptance Criteria

1. Creating a Project auto-generates 5 Phases × 3 Work Groups = 15 Work Groups, each with its default Work Packages seeded as real WBS records (not labels or enum values).
2. Every Work Package is a first-class WBS row with its own code, budget, schedule, percent complete, responsible party, and `is_applicable` flag.
3. Every Work Package rolls up to exactly one Work Group, which rolls up to exactly one Phase, which rolls up to exactly one Project.
4. No Work Package can be orphaned or attached directly to a Phase.
5. WBS codes render correctly at every level: Phase (`3`), Work Group (`3.1`), Work Package (`3.1.1`), Activity (`3.1.1.2`).
6. Progress, budget, and earned value roll up from Activity → Work Package → Work Group → Phase → Project, and **exclude** any node where `is_applicable = false`.
7. A WBS explorer view can list, filter, and export any subset of WBS nodes (e.g. "all BESS Work Packages across all Phases", "all `*.1.*` rows") — proving every node is individually addressable.
8. Every Work Package row has a visible toggle switch; flipping it updates `is_applicable` and deactivated WPs render greyed out with a "Not in this project" badge but remain in the tree.
9. Toggling a Work Package that belongs to a Scope Family triggers a confirmation modal offering *Apply to all* / *Just this one* / *Cancel*, and the chosen action runs in a single DB transaction.
10. Toggling a Scope Family at the family level cascades to every member Work Package, except those marked `is_override = true`.
11. Seeded default applicability matches the table in the Applicability section (e.g. Guardhouse off by default, O&M Building on by default).
12. The Scope Setup screen shows a coverage summary and lets the user configure all families for a new project in one view before touching the WBS tree.
