# BESS Topology Configuration — Instruction for Claude Code

## Section 5A: Zone, Block, and Group Configuration

---

## 1. The Three-Level Hierarchy

```
SITE
└── ZONE        ← Highest level. A physical area of the site.
    └── BLOCK   ← A physical group of equipment pads within a zone.
        └── GROUP ← A collection of battery containers/cabinets on one pad or string.
                    One GROUP = one installation activity in the schedule.
```

### Definitions

**Zone** — The highest physical division of the BESS site. A large project may have
multiple zones for construction staging, network connection, or land boundary reasons.
Zones are independent of each other and can be built in sequence or in parallel.

**Block** — A subdivision within a Zone. A Block represents a physical cluster of
battery pads, PCS units, and SUTs. Each Block is a self-contained construction
workfront. The user decides how many Blocks exist in each Zone and how many
batteries go in each Block.

**Group** — The smallest schedulable unit. A Group is a collection of battery
containers that are installed together as one activity. One Group = one installation
activity in the schedule. The user defines the group size (how many batteries per
container/string) and this determines how many groups exist in each block.

---

## 2. User Configuration — What the User Controls

The user configures the topology explicitly. The application NEVER auto-distributes
batteries across zones or blocks without the user's instruction.

### Project-Level Inputs
```
Total battery count         e.g. 160
Total number of zones       e.g. 2
Battery group size          e.g. 5  (can be overridden per block — see Section 4)
```

### Per-Zone Inputs
For each zone the user defines:
```
Zone label / name           e.g. "Zone 1" or custom name
Number of blocks in zone    e.g. 4
```

### Per-Block Inputs
For each block within a zone the user defines:
```
Block label / name          e.g. "Block 1" or custom name
Number of batteries         e.g. 20
Group size (batteries per   e.g. 5  (inherits project default, user can override)
  group) for this block
```

---

## 3. What the Application Calculates Automatically

Once the user has defined batteries per block and group size for that block,
the application automatically calculates:

```
groups_in_block      = floor(batteries_in_block / group_size)
remainder_batteries  = batteries_in_block MOD group_size
has_partial_group    = remainder_batteries > 0
```

### Example — Clean Division
```
Block 1: 20 batteries, group size 5
  → groups_in_block     = 20 ÷ 5 = 4
  → remainder           = 0
  → has_partial_group   = false

Result:
  Group 1: Batteries 1–5
  Group 2: Batteries 6–10
  Group 3: Batteries 11–15
  Group 4: Batteries 16–20
```

### Example — Uneven Division
```
Block 4: 22 batteries, group size 5
  → groups_in_block     = floor(22 ÷ 5) = 4
  → remainder           = 22 MOD 5 = 2
  → has_partial_group   = true

Result:
  Group 1: Batteries 1–5    (5 units — full)
  Group 2: Batteries 6–10   (5 units — full)
  Group 3: Batteries 11–15  (5 units — full)
  Group 4: Batteries 16–20  (5 units — full)
  Group 5: Batteries 21–22  (2 units — PARTIAL ⚠)
```

---

## 4. Remainder Handling — The Partial Group

A remainder occurs when the number of batteries in a block does not divide
evenly by the group size.

A partial group is NOT an error. It is a valid construction unit. However,
the application must detect it and present the user with a clear choice
BEFORE compiling the schedule.

### The Three Options the User Must Be Presented With

When a remainder is detected, show this decision prompt in the UI:

```
⚠ Block [N] has [X] batteries with a group size of [Y].
  This creates [Z] full groups ([Z×Y] batteries) and 1 partial
  group ([remainder] batteries).

  How do you want to handle this?

  ○ Accept the partial group
    Creates Group [Z+1] with [remainder] batteries.
    This group will have a proportionally shorter installation duration.

  ○ Change the group size for this block
    Adjust the group size so batteries divide evenly.
    [Show input field for new group size]

  ○ Change the battery count for this block
    Adjust the number of batteries in this block.
    [Show input field for new battery count]
```

### Option A — Accept the Partial Group

The system creates an additional equipment_node for the partial group:

```php
equipment_nodes record:
  type              = 'battery_group'
  zone_number       = z
  block_number      = b
  group_number      = Z + 1
  label             = "Zone {z} Block {b} Group {Z+1} (Batteries {from}–{to}) — Partial"
  unit_from         = first battery number in this group
  unit_to           = last battery number in this group
  actual_unit_count = remainder  (e.g. 2)
  is_partial        = true
```

Duration for the partial group installation activity is calculated as:
```
full_group_duration  = project default (e.g. 3 days)
partial_duration     = max(1, round(full_group_duration × (remainder / group_size)))

Example: 3 days × (2/5) = 1.2 → rounded = 1 day
Minimum is always 1 day — never 0.
```

The partial group generates the same activity structure as a full group:
  - Foundation/pad construction activity (if applicable)
  - Equipment installation activity (with partial duration)
  - Cable installation activity
  - Cable termination activity
  - Group installation complete milestone

### Option B — Change Group Size for This Block

The user enters a new group size for this specific block only. The application
recalculates and re-checks for remainder. Repeat until no remainder or user
accepts a partial.

This means group size is PER BLOCK, not fixed globally. The equipment_nodes
record stores the actual group size used for that block.

### Option C — Change Battery Count for This Block

The user adjusts the battery count. The application recalculates. The total
battery count validation (see Section 5) will flag if the overall total no
longer matches the project total.

---

## 5. Total Battery Count Validation

Before compiling the schedule, the application must validate:

```
sum of all batteries across all blocks in all zones
MUST EQUAL
project.total_battery_count
```

If the totals do not match, show a critical validation error:

```
✗ Total batteries configured: 158
  Project total batteries:    160
  Difference:                 2 batteries unaccounted for.

  Please review your zone and block configuration before compiling.
```

This validation runs as part of ValidationService and BLOCKS compilation
if the total does not reconcile. It does not block the user from saving
their topology configuration — only from compiling the schedule.

---

## 6. Database Structure for Topology

### equipment_nodes table — additional columns required

```php
// Add these to the equipment_nodes migration
$table->boolean('is_partial')->default(false);
$table->unsignedSmallInteger('actual_unit_count')->nullable();
// actual_unit_count = real battery count in this group
// For full groups: actual_unit_count = project batteries_per_group
// For partial groups: actual_unit_count = remainder
```

### projects table — topology inputs

```php
// These already exist in the projects migration
$table->unsignedTinyInteger('zone_count');
$table->unsignedTinyInteger('blocks_per_zone');        // Default, user can override per zone
$table->unsignedTinyInteger('batteries_per_group');    // Default group size, overridable per block
$table->unsignedTinyInteger('battery_groups_per_block'); // Calculated, not user-entered
$table->unsignedSmallInteger('total_battery_count');   // NEW — add this column
```

### New table — zone_configurations

Because each zone can have a different number of blocks and each block can
have a different battery count and group size, we need a dedicated
configuration table:

```php
Schema::create('zone_configurations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')
          ->constrained('projects')
          ->cascadeOnDelete();

    // Zone level
    $table->unsignedTinyInteger('zone_number');
    $table->string('zone_label')->nullable();        // Custom zone name
    $table->unsignedTinyInteger('block_count');      // How many blocks in this zone
    $table->timestamps();

    $table->unique(['project_id', 'zone_number']);
});
```

### New table — block_configurations

```php
Schema::create('block_configurations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')
          ->constrained('projects')
          ->cascadeOnDelete();
    $table->foreignId('zone_configuration_id')
          ->constrained('zone_configurations')
          ->cascadeOnDelete();

    // Block level
    $table->unsignedTinyInteger('zone_number');
    $table->unsignedTinyInteger('block_number');
    $table->string('block_label')->nullable();       // Custom block name

    // User-defined for this block
    $table->unsignedSmallInteger('battery_count');   // How many batteries in this block
    $table->unsignedTinyInteger('group_size');        // Batteries per group (overrides project default)

    // Calculated by application
    $table->unsignedTinyInteger('full_group_count'); // floor(battery_count / group_size)
    $table->unsignedTinyInteger('remainder_count');  // battery_count MOD group_size
    $table->boolean('has_partial_group');            // remainder_count > 0

    // User's decision on remainder handling
    // accepted | group_size_changed | battery_count_changed | pending
    $table->string('remainder_decision')->default('pending');

    $table->timestamps();

    $table->unique(['project_id', 'zone_number', 'block_number']);
});
```

---

## 7. PCS and SUT Follow the Same Pattern

PCS units and SUTs are configured at the Block level using the same logic.

### PCS Configuration (per block)
```
pcs_count           User enters how many PCS in this block
pcs_per_group       User enters grouping size (e.g. 2 PCS per group)
pcs_group_count     = floor(pcs_count / pcs_per_group)
pcs_remainder       = pcs_count MOD pcs_per_group
```

Same three options apply if remainder exists.

### SUT Configuration (per block)
```
sut_count           User enters how many SUTs in this block
```
SUTs are not grouped — each SUT is its own equipment_node and its own
installation activity. No grouping logic required for SUTs.

---

## 8. Schedule Activities Generated per Group

For every equipment_node of type battery_group, the GraphCompilerService
generates these activities in this order:

```
1. Battery Group Foundation / Pad Works          (construction — civil)
2. Install Battery Group [N] (Batteries [X]–[Y]) (construction — equipment)
3. Battery DC Cable Installation — Group [N]     (construction — electrical)
4. Battery DC Cable Termination — Group [N]      (construction — electrical)
5. Battery Group [N] Installation Complete       (milestone — 0 days)
```

For a PARTIAL group, activity names reflect the actual count:
```
2. Install Battery Group 5 (Battery 21–22) — Partial  (proportional duration)
```

The Group Installation Complete milestone (step 5) feeds into:
```
→ PCS Group installation (the PCS group that this battery group connects to)
→ Block Complete milestone (when all groups in the block are done)
```

---

## 9. Predecessor Logic Within a Block

Construction within a block follows this sequence:

```
Block Workfront Released (milestone)
    ↓
Battery Group 1 Foundation
    ↓ (FS)
Battery Group 2 Foundation       ← can be parallel with Group 1 if separate crews
    ↓
...
    ↓
Battery Group [N] Foundation
    ↓
Battery Group 1 Install
    ↓
Battery Group 1 DC Cable Install
    ↓
Battery Group 1 DC Cable Termination
    ↓
Battery Group 1 Complete (milestone)
    ↓
[Repeat for groups 2, 3, 4, 5...]
    ↓
All Battery Groups in Block Complete
    ↓
PCS Group 1 Install
    ↓
...
PCS Groups Complete
    ↓
SUT 1 Install
    ↓
SUT 2 Install
    ↓
Block [N] Complete (milestone)
    ↓
Zone [N] Complete (milestone — when all blocks in zone are done)
```

---

## 10. UI Flow for Topology Configuration

The topology configuration happens in Step 3 of the Filament wizard.
The flow must be:

```
Step 3a — Enter project-level battery total and zone count
Step 3b — For each zone: enter zone label and number of blocks
Step 3c — For each block: enter battery count and group size
Step 3d — Application shows calculated group breakdown per block
Step 3e — If any block has a remainder: show decision prompt
Step 3f — User resolves all remainders
Step 3g — Application shows topology summary with running battery total
Step 3h — Validation: total configured == total batteries → proceed
           If mismatch: show reconciliation error → user must fix
```

Do not allow the user to proceed past Step 3 until:
  1. All remainders are resolved (no 'pending' remainder_decision records)
  2. Total battery count reconciles with project.total_battery_count

---

## 11. Example — Full 160 Battery Topology

```
Project: 160 batteries total, 2 zones

Zone 1 — 4 blocks
  Block 1: 20 batteries, group size 5 → 4 groups (clean)
  Block 2: 20 batteries, group size 5 → 4 groups (clean)
  Block 3: 20 batteries, group size 5 → 4 groups (clean)
  Block 4: 20 batteries, group size 5 → 4 groups (clean)
  Zone 1 total: 80 batteries ✓

Zone 2 — 4 blocks
  Block 1: 20 batteries, group size 5 → 4 groups (clean)
  Block 2: 20 batteries, group size 5 → 4 groups (clean)
  Block 3: 20 batteries, group size 5 → 4 groups (clean)
  Block 4: 20 batteries, group size 5 → 4 groups (clean)
  Zone 2 total: 80 batteries ✓

Grand total: 160 batteries ✓

equipment_nodes generated:
  32 battery_group nodes
  (4 blocks × 4 groups × 2 zones = 32)

schedule activities generated from battery groups alone:
  32 × 4 activities + 32 milestones = 160 activities
```

---

## 12. Example — Uneven 162 Battery Topology

```
Project: 162 batteries total, 2 zones

Zone 1 — 4 blocks
  Block 1: 20 batteries, group size 5 → 4 groups (clean)
  Block 2: 20 batteries, group size 5 → 4 groups (clean)
  Block 3: 20 batteries, group size 5 → 4 groups (clean)
  Block 4: 21 batteries, group size 5
    → 4 full groups (20 batteries)
    → remainder = 1 battery
    → ⚠ PARTIAL GROUP DECISION REQUIRED
    → User selects: Accept partial group
    → Group 5: Battery 21 (1 unit, is_partial = true)
               duration = max(1, round(3 × 1/5)) = 1 day

  Zone 1 total: 81 batteries ✓

Zone 2 — 4 blocks
  Block 1: 20 batteries, group size 5 → 4 groups (clean)
  Block 2: 20 batteries, group size 5 → 4 groups (clean)
  Block 3: 20 batteries, group size 5 → 4 groups (clean)
  Block 4: 21 batteries, group size 5
    → 4 full groups + 1 partial group (1 battery)
    → User selects: Accept partial group

  Zone 2 total: 81 batteries ✓

Grand total: 162 batteries ✓

equipment_nodes generated:
  Zone 1: 4 blocks × 4 full groups + 2 partial groups = 18 battery_group nodes
  Zone 2: same = 18 battery_group nodes
  Total: 36 battery_group nodes
```

