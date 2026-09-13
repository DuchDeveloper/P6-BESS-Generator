# BESS Construction Installation — Claude Code Instruction
## Battery, PCS and SUT Installation Structure

---

## 1. Governing Rule

The construction schedule for Battery, PCS, and SUT installation is organised
by WORK TYPE at the top level, and by GROUP within each work type.

The FS predecessor chain runs GROUP-TO-GROUP across work types — not
section-to-section. Each group has its own unbroken chain from civil works
through to the group completion milestone.

This rule is absolute. The GraphCompilerService must enforce it for every
group in every block in every zone.

---

## 2. WBS Structure — Battery Installation

### Top-Level Structure per Block

```
Construction
└── Zone {Z}
    └── Block {B}
        ├── Civil Works
        │   └── (group-first — see Section 3)
        ├── Mechanical
        │   └── (flat list by group — see Section 4)
        ├── Electrical Cable Installation
        │   └── (flat list by group — see Section 5)
        └── Termination
            └── (flat list by group — see Section 6)
```

---

## 3. Civil Works — Group First Inside This Section Only

Civil Works is the only section that uses a group-first WBS structure.
Each group has its own WBS sub-node under Civil Works, and all civil
activities for that group sit under it.

```
Civil Works
├── Battery Group 1 — Batteries 1–4
│   ├── Battery Pad Excavation and Blinding — Group 1: Batteries 1–4
│   ├── Battery Pad Rebar, Anchor Bolts and Embedments — Group 1: Batteries 1–4
│   ├── Battery Pad Formwork — Group 1: Batteries 1–4
│   ├── Battery Pad Concrete Pour — Group 1: Batteries 1–4
│   ├── Battery Pad Concrete Curing — Group 1: Batteries 1–4
│   └── Battery Pad Formwork Strip and Pad Finish — Group 1: Batteries 1–4
│
├── Battery Group 2 — Batteries 5–8
│   ├── Battery Pad Excavation and Blinding — Group 2: Batteries 5–8
│   ├── Battery Pad Rebar, Anchor Bolts and Embedments — Group 2: Batteries 5–8
│   ├── Battery Pad Formwork — Group 2: Batteries 5–8
│   ├── Battery Pad Concrete Pour — Group 2: Batteries 5–8
│   ├── Battery Pad Concrete Curing — Group 2: Batteries 5–8
│   └── Battery Pad Formwork Strip and Pad Finish — Group 2: Batteries 5–8
│
├── Battery Group 3 — Batteries 9–12
│   └── ... same pattern
│
├── Battery Group 4 — Batteries 13–16
│   └── ... same pattern
│
└── Battery Group 5 — Batteries 17–20
    └── ... same pattern
```

### Civil Activities per Group — Durations and Internal Predecessors

| Seq | Activity Name | Duration | Predecessor |
|-----|--------------|----------|-------------|
| 1 | Battery Pad Excavation and Blinding — Group {N}: Batteries {X}–{Y} | 1d | Block Workfront Released |
| 2 | Battery Pad Rebar, Anchor Bolts and Embedments — Group {N}: Batteries {X}–{Y} | 2d | Seq 1 same group |
| 3 | Battery Pad Formwork — Group {N}: Batteries {X}–{Y} | 1d | Seq 2 same group |
| 4 | Battery Pad Concrete Pour — Group {N}: Batteries {X}–{Y} | 1d | Seq 3 same group |
| 5 | Battery Pad Concrete Curing — Group {N}: Batteries {X}–{Y} | 7d | Seq 4 same group |
| 6 | Battery Pad Formwork Strip and Pad Finish — Group {N}: Batteries {X}–{Y} | 1d | Seq 5 same group |

All civil activities within a group are Finish-to-Start.
Concrete Curing uses calendar days — not working days.
Seq 6 (Formwork Strip and Pad Finish) is the civil exit gate for each group.
It drives the Mechanical activity for the same group.

---

## 4. Mechanical — Flat List by Group

No sub-WBS nodes. All mechanical activities sit directly under the
Mechanical WBS node as a flat list, one activity per group.

```
Mechanical
├── Battery Container Installation — Group 1: Batteries 1–4
├── Battery Container Installation — Group 2: Batteries 5–8
├── Battery Container Installation — Group 3: Batteries 9–12
├── Battery Container Installation — Group 4: Batteries 13–16
└── Battery Container Installation — Group 5: Batteries 17–20
```

### Mechanical Activity per Group

| Activity Name | Duration | Predecessor |
|--------------|----------|-------------|
| Battery Container Installation — Group {N}: Batteries {X}–{Y} | 2d | Battery Pad Formwork Strip and Pad Finish — same group (FS) |

Battery Container Installation covers: crane mobilisation, lifting,
positioning onto pad, levelling, anchor bolt tensioning, and mechanical
sign-off. This is one activity covering the full mechanical scope for
the group.

---

## 5. Electrical Cable Installation — Flat List by Group

No sub-WBS nodes. All cable installation activities sit directly under
the Electrical Cable Installation WBS node, one activity per group.

```
Electrical Cable Installation
├── DC Cable Installation from Battery to PCS — Group 1: Batteries 1–4
├── DC Cable Installation from Battery to PCS — Group 2: Batteries 5–8
├── DC Cable Installation from Battery to PCS — Group 3: Batteries 9–12
├── DC Cable Installation from Battery to PCS — Group 4: Batteries 13–16
└── DC Cable Installation from Battery to PCS — Group 5: Batteries 17–20
```

### Cable Installation Activity per Group

| Activity Name | Duration | Predecessor |
|--------------|----------|-------------|
| DC Cable Installation from Battery to PCS — Group {N}: Batteries {X}–{Y} | 2d | Battery Container Installation — same group (FS) |

DC Cable Installation covers: cable pulling from battery container to
the corresponding PCS unit, cable support installation, cable management,
and protection. The cable route must be physically clear before this
activity can start — this is ensured by the civil trench works being
complete at block level before the Block Workfront is released.

---

## 6. Termination — Flat List by Group

No sub-WBS nodes. All termination activities sit directly under the
Termination WBS node, one activity per group.

```
Termination
├── Terminate Cable at Battery and PCS — Group 1: Batteries 1–4
├── Terminate Cable at Battery and PCS — Group 2: Batteries 5–8
├── Terminate Cable at Battery and PCS — Group 3: Batteries 9–12
├── Terminate Cable at Battery and PCS — Group 4: Batteries 13–16
└── Terminate Cable at Battery and PCS — Group 5: Batteries 17–20
```

### Termination Activity per Group

| Activity Name | Duration | Predecessor |
|--------------|----------|-------------|
| Terminate Cable at Battery and PCS — Group {N}: Batteries {X}–{Y} | 2d | DC Cable Installation from Battery to PCS — same group (FS) |

Termination covers: stripping, lugging, and terminating DC cables at
both the battery container terminals and the PCS DC input terminals,
torque verification, and insulation resistance check.

---

## 7. Group Completion Milestone

After termination is complete for a group, a zero-duration milestone
is generated. This is the output key for that group and gates the
pre-commissioning activities for this group.

```
◆ Battery Group {N} Installation Complete — Batteries {X}–{Y}   (0d milestone)
```

Predecessor: Terminate Cable at Battery and PCS — same group (FS)

This milestone is NOT placed under any of the four work type sections.
It sits directly under the Block WBS node as a standalone milestone.

Output key format: `battery_group_{zone}_{block}_{group}_complete`
Example: `battery_group_1_1_3_complete` = Zone 1, Block 1, Group 3

---

## 8. The Full Chain per Group — Summary

This is the complete FS chain for one group end-to-end.
Every arrow below is a Finish-to-Start relationship.

```
Block Workfront Released (Milestone)
    ↓ FS
Battery Pad Excavation and Blinding — Group {N}: Batteries {X}–{Y}
    ↓ FS
Battery Pad Rebar, Anchor Bolts and Embedments — Group {N}: Batteries {X}–{Y}
    ↓ FS
Battery Pad Formwork — Group {N}: Batteries {X}–{Y}
    ↓ FS
Battery Pad Concrete Pour — Group {N}: Batteries {X}–{Y}
    ↓ FS
Battery Pad Concrete Curing — Group {N}: Batteries {X}–{Y}
    ↓ FS
Battery Pad Formwork Strip and Pad Finish — Group {N}: Batteries {X}–{Y}
    ↓ FS  ← crosses from Civil Works into Mechanical
Battery Container Installation — Group {N}: Batteries {X}–{Y}
    ↓ FS  ← crosses from Mechanical into Electrical Cable Installation
DC Cable Installation from Battery to PCS — Group {N}: Batteries {X}–{Y}
    ↓ FS  ← crosses from Electrical Cable Installation into Termination
Terminate Cable at Battery and PCS — Group {N}: Batteries {X}–{Y}
    ↓ FS
◆ Battery Group {N} Installation Complete — Batteries {X}–{Y}   (Milestone)
```

Each group has its own independent chain.
Group 1 chain runs in parallel with Group 2 chain and so on.
The chains are independent — a delay in Group 2 does not delay Group 1.

---

## 9. Block Completion Milestone

When all group completion milestones within a block are done, a block-level
completion milestone is generated.

```
◆ Battery Group 1 Installation Complete — Batteries 1–4
◆ Battery Group 2 Installation Complete — Batteries 5–8
◆ Battery Group 3 Installation Complete — Batteries 9–12
◆ Battery Group 4 Installation Complete — Batteries 13–16
◆ Battery Group 5 Installation Complete — Batteries 17–20
    ↓ all FS into
◆ Block {B} — All Battery Groups Installation Complete   (0d Milestone)
```

This block milestone gates the PCS installation for the same block
(where applicable) and feeds into the zone completion milestone.

---

## 10. PCS Installation Structure

PCS installation follows the same four-section structure as battery
installation. The sections are:

```
Construction
└── Zone {Z}
    └── Block {B}
        ├── PCS Civil Works
        │   └── (group-first, same pattern as battery civil works)
        ├── PCS Mechanical
        │   └── (flat list by PCS group)
        ├── PCS AC Cable Installation
        │   └── (flat list by PCS group)
        └── PCS Termination
            └── (flat list by PCS group)
```

### PCS Civil Works — Group First

Using the same example: 10 PCS per block, 2 PCS per group = 5 PCS groups.

```
PCS Civil Works
├── PCS Group 1 — PCS 1–2
│   ├── PCS Pad Excavation and Blinding — Group 1: PCS 1–2
│   ├── PCS Pad Rebar, Anchor Bolts and Embedments — Group 1: PCS 1–2
│   ├── PCS Pad Formwork — Group 1: PCS 1–2
│   ├── PCS Pad Concrete Pour — Group 1: PCS 1–2
│   ├── PCS Pad Concrete Curing — Group 1: PCS 1–2
│   └── PCS Pad Formwork Strip and Pad Finish — Group 1: PCS 1–2
│
├── PCS Group 2 — PCS 3–4
│   └── ... same pattern
│
├── PCS Group 3 — PCS 5–6
│   └── ... same pattern
│
├── PCS Group 4 — PCS 7–8
│   └── ... same pattern
│
└── PCS Group 5 — PCS 9–10
    └── ... same pattern
```

### PCS Mechanical — Flat List

```
PCS Mechanical
├── PCS Unit Installation — Group 1: PCS 1–2
├── PCS Unit Installation — Group 2: PCS 3–4
├── PCS Unit Installation — Group 3: PCS 5–6
├── PCS Unit Installation — Group 4: PCS 7–8
└── PCS Unit Installation — Group 5: PCS 9–10
```

Duration per group: 2d
Predecessor: PCS Pad Formwork Strip and Pad Finish — same PCS group (FS)

### PCS AC Cable Installation — Flat List

```
PCS AC Cable Installation
├── AC Cable Installation from PCS to SUT — Group 1: PCS 1–2
├── AC Cable Installation from PCS to SUT — Group 2: PCS 3–4
├── AC Cable Installation from PCS to SUT — Group 3: PCS 5–6
├── AC Cable Installation from PCS to SUT — Group 4: PCS 7–8
└── AC Cable Installation from PCS to SUT — Group 5: PCS 9–10
```

Duration per group: 2d
Predecessor: PCS Unit Installation — same PCS group (FS)

### PCS Termination — Flat List

```
PCS Termination
├── Terminate Cable at PCS and SUT — Group 1: PCS 1–2
├── Terminate Cable at PCS and SUT — Group 2: PCS 3–4
├── Terminate Cable at PCS and SUT — Group 3: PCS 5–6
├── Terminate Cable at PCS and SUT — Group 4: PCS 7–8
└── Terminate Cable at PCS and SUT — Group 5: PCS 9–10
```

Duration per group: 2d
Predecessor: AC Cable Installation from PCS to SUT — same PCS group (FS)

### PCS Group Completion Milestone

```
◆ PCS Group {N} Installation Complete — PCS {X}–{Y}   (0d milestone)
```

Predecessor: Terminate Cable at PCS and SUT — same group (FS)
Output key format: `pcs_group_{zone}_{block}_{group}_complete`

### PCS Full Chain per Group

```
Block Workfront Released (Milestone)
    ↓ FS
PCS Pad Excavation and Blinding — Group {N}: PCS {X}–{Y}
    ↓ FS
PCS Pad Rebar, Anchor Bolts and Embedments — Group {N}: PCS {X}–{Y}
    ↓ FS
PCS Pad Formwork — Group {N}: PCS {X}–{Y}
    ↓ FS
PCS Pad Concrete Pour — Group {N}: PCS {X}–{Y}
    ↓ FS
PCS Pad Concrete Curing — Group {N}: PCS {X}–{Y}
    ↓ FS
PCS Pad Formwork Strip and Pad Finish — Group {N}: PCS {X}–{Y}
    ↓ FS
PCS Unit Installation — Group {N}: PCS {X}–{Y}
    ↓ FS
AC Cable Installation from PCS to SUT — Group {N}: PCS {X}–{Y}
    ↓ FS
Terminate Cable at PCS and SUT — Group {N}: PCS {X}–{Y}
    ↓ FS
◆ PCS Group {N} Installation Complete — PCS {X}–{Y}   (Milestone)
```

---

## 11. SUT Installation Structure

SUT is not grouped. Each SUT is its own individual equipment node and
generates its own individual activity chain. There is no group concept
for SUT.

```
Construction
└── Zone {Z}
    └── Block {B}
        ├── SUT Civil Works
        │   └── (individual per SUT — same civil pattern)
        ├── SUT Mechanical
        │   └── (individual per SUT)
        ├── SUT MV Cable Installation
        │   └── (individual per SUT)
        └── SUT Termination
            └── (individual per SUT)
```

### SUT Civil Works — Individual per SUT

```
SUT Civil Works
├── SUT 1
│   ├── SUT Pad Excavation and Blinding — SUT 1
│   ├── SUT Pad Rebar, Anchor Bolts and Embedments — SUT 1
│   ├── SUT Pad Formwork — SUT 1
│   ├── SUT Pad Concrete Pour — SUT 1
│   ├── SUT Pad Concrete Curing — SUT 1
│   └── SUT Pad Formwork Strip and Pad Finish — SUT 1
│
└── SUT 2
    ├── SUT Pad Excavation and Blinding — SUT 2
    ├── SUT Pad Rebar, Anchor Bolts and Embedments — SUT 2
    ├── SUT Pad Formwork — SUT 2
    ├── SUT Pad Concrete Pour — SUT 2
    ├── SUT Pad Concrete Curing — SUT 2
    └── SUT Pad Formwork Strip and Pad Finish — SUT 2
```

### SUT Mechanical — Flat List

```
SUT Mechanical
├── SUT Installation — SUT 1
└── SUT Installation — SUT 2
```

Duration: 3d per SUT (heavier lift than battery or PCS)
Predecessor: SUT Pad Formwork Strip and Pad Finish — same SUT (FS)

### SUT MV Cable Installation — Flat List

```
SUT MV Cable Installation
├── MV Cable Installation from SUT to Switchroom — SUT 1
└── MV Cable Installation from SUT to Switchroom — SUT 2
```

Duration: 3d per SUT
Predecessor: SUT Installation — same SUT (FS)

### SUT Termination — Flat List

```
SUT Termination
├── Terminate MV Cable at SUT and Switchroom — SUT 1
└── Terminate MV Cable at SUT and Switchroom — SUT 2
```

Duration: 2d per SUT
Predecessor: MV Cable Installation from SUT to Switchroom — same SUT (FS)

### SUT Completion Milestone

```
◆ SUT {N} Installation Complete   (0d milestone)
```

Predecessor: Terminate MV Cable at SUT and Switchroom — same SUT (FS)
Output key format: `sut_{zone}_{block}_{sut_number}_complete`
Example: `sut_1_2_1_complete` = Zone 1, Block 2, SUT 1

### SUT Full Chain

```
Block Workfront Released (Milestone)
    ↓ FS
SUT Pad Excavation and Blinding — SUT {N}
    ↓ FS
SUT Pad Rebar, Anchor Bolts and Embedments — SUT {N}
    ↓ FS
SUT Pad Formwork — SUT {N}
    ↓ FS
SUT Pad Concrete Pour — SUT {N}
    ↓ FS
SUT Pad Concrete Curing — SUT {N}
    ↓ FS
SUT Pad Formwork Strip and Pad Finish — SUT {N}
    ↓ FS
SUT Installation — SUT {N}
    ↓ FS
MV Cable Installation from SUT to Switchroom — SUT {N}
    ↓ FS
Terminate MV Cable at SUT and Switchroom — SUT {N}
    ↓ FS
◆ SUT {N} Installation Complete   (Milestone)
```

---

## 12. Cross-Equipment Predecessor Rules

The Battery, PCS, and SUT chains run in parallel within a block.
However, the cable works between equipment types create cross-chain
dependencies as follows:

### Battery DC Cable depends on PCS being installed

```
Battery Container Installation — Group {N}  }
                                            } → FS → DC Cable Installation from Battery to PCS — Group {N}
PCS Unit Installation — Group {N}           }
```

The DC cable from the battery group to the PCS group cannot be installed
until BOTH the battery container AND the PCS unit are physically in place.
This is a dual-predecessor rule.

The battery group number maps to the PCS group number as defined by the
project topology. Group 1 batteries connect to Group 1 PCS. Group 2
batteries connect to Group 2 PCS. And so on. This mapping is stored in
the interface_records table.

### PCS AC Cable depends on SUT being installed

```
PCS Unit Installation — Group {N}  }
                                   } → FS → AC Cable Installation from PCS to SUT — Group {N}
SUT Installation — SUT {M}         }
```

The AC cable from PCS to SUT cannot be installed until BOTH the PCS unit
AND the SUT are physically in place. The PCS group to SUT mapping is also
stored in interface_records.

### SUT MV Cable depends on Switchroom being ready

```
SUT Installation — SUT {N}                    }
                                              } → FS → MV Cable Installation from SUT to Switchroom — SUT {N}
Switchroom Civil and Building Works Complete  }
```

The MV cable from SUT to the switchroom cannot be installed until the
switchroom building is erected and the cable trench route is complete.

---

## 13. Complete Block Activity Count — Example

Using: 5 battery groups, 5 PCS groups, 2 SUTs per block.

### Civil Works
```
Battery civil: 5 groups × 6 activities = 30 activities
PCS civil:     5 groups × 6 activities = 30 activities
SUT civil:     2 SUTs   × 6 activities = 12 activities
Total civil:                             72 activities
```

### Mechanical
```
Battery mechanical: 5 groups × 1 activity = 5 activities
PCS mechanical:     5 groups × 1 activity = 5 activities
SUT mechanical:     2 SUTs   × 1 activity = 2 activities
Total mechanical:                           12 activities
```

### Cable Installation
```
Battery DC cable:  5 groups × 1 activity = 5 activities
PCS AC cable:      5 groups × 1 activity = 5 activities
SUT MV cable:      2 SUTs   × 1 activity = 2 activities
Total cable:                               12 activities
```

### Termination
```
Battery termination: 5 groups × 1 activity = 5 activities
PCS termination:     5 groups × 1 activity = 5 activities
SUT termination:     2 SUTs   × 1 activity = 2 activities
Total termination:                           12 activities
```

### Milestones
```
Battery group complete milestones: 5
PCS group complete milestones:     5
SUT complete milestones:           2
Block workfront released:          1
Block all batteries complete:      1
Block all PCS complete:            1
Block all SUTs complete:           1
Block complete:                    1
Total milestones:                  17
```

### Total per Block
```
Activities:  72 + 12 + 12 + 12 = 108 activities
Milestones:  17
Grand total: 125 schedule items per block
```

For a project with 2 zones × 4 blocks = 8 blocks:
```
8 × 125 = 1,000 schedule items from BESS topology alone
```

This confirms the schedule generator must be data-driven and never
hardcoded — the GraphCompilerService must generate all items
programmatically from the topology configuration.

---

## 14. GraphCompilerService — Generation Rules for Topology Activities

When compiling the schedule for a project, the GraphCompilerService
must follow this sequence for each block:

```
1. Read all equipment_nodes for this block from the database
   (type: battery_group, pcs_group, sut — ordered by group_number)

2. For each battery_group node:
   a. Create Civil Works WBS sub-node for this group
   b. Generate 6 civil activities under it (Seq 1–6)
   c. Link civil activities FS internally
   d. Generate 1 mechanical activity under Mechanical WBS
   e. Link: civil Seq 6 → mechanical (FS)
   f. Generate 1 cable installation activity under Electrical Cable WBS
   g. Link: mechanical → cable installation (FS) — PENDING dual predecessor
   h. Generate 1 termination activity under Termination WBS
   i. Link: cable installation → termination (FS)
   j. Generate group completion milestone
   k. Link: termination → milestone (FS)

3. For each pcs_group node:
   a–k. Same pattern as battery — using PCS activity names

4. For each sut node:
   a–k. Same pattern as battery — using SUT activity names

5. Apply cross-equipment dual predecessors:
   a. For each battery group {n}:
      Find matching PCS group {n} via interface_records
      Add predecessor: PCS mechanical → battery DC cable install
   b. For each PCS group {n}:
      Find matching SUT via interface_records
      Add predecessor: SUT mechanical → PCS AC cable install
   c. For each SUT:
      Add predecessor: switchroom building complete → SUT MV cable install

6. Generate block-level completion milestones:
   a. All battery group milestones → Block Battery Groups Complete
   b. All PCS group milestones → Block PCS Groups Complete
   c. All SUT milestones → Block SUTs Complete
   d. All three block sub-milestones → Block Complete
```

---

## 15. Activity Code Format

All generated activities must follow this code format for P6 export:

```
{zone}-{block}-{equipment_type}-{group}-{activity_type}

Examples:
Z1-B1-BAT-G1-EXC   = Zone 1, Block 1, Battery Group 1, Excavation
Z1-B1-BAT-G1-REB   = Zone 1, Block 1, Battery Group 1, Rebar
Z1-B1-BAT-G1-FWK   = Zone 1, Block 1, Battery Group 1, Formwork
Z1-B1-BAT-G1-POU   = Zone 1, Block 1, Battery Group 1, Concrete Pour
Z1-B1-BAT-G1-CUR   = Zone 1, Block 1, Battery Group 1, Curing
Z1-B1-BAT-G1-FIN   = Zone 1, Block 1, Battery Group 1, Finish
Z1-B1-BAT-G1-MEC   = Zone 1, Block 1, Battery Group 1, Mechanical Install
Z1-B1-BAT-G1-CAB   = Zone 1, Block 1, Battery Group 1, Cable Install
Z1-B1-BAT-G1-TER   = Zone 1, Block 1, Battery Group 1, Termination
Z1-B1-BAT-G1-COM   = Zone 1, Block 1, Battery Group 1, Complete (Milestone)

Z1-B1-PCS-G1-MEC   = Zone 1, Block 1, PCS Group 1, Mechanical Install
Z1-B1-PCS-G1-CAB   = Zone 1, Block 1, PCS Group 1, Cable Install
Z1-B1-PCS-G1-TER   = Zone 1, Block 1, PCS Group 1, Termination
Z1-B1-PCS-G1-COM   = Zone 1, Block 1, PCS Group 1, Complete (Milestone)

Z1-B1-SUT-01-MEC   = Zone 1, Block 1, SUT 1, Mechanical Install
Z1-B1-SUT-01-CAB   = Zone 1, Block 1, SUT 1, MV Cable Install
Z1-B1-SUT-01-TER   = Zone 1, Block 1, SUT 1, Termination
Z1-B1-SUT-01-COM   = Zone 1, Block 1, SUT 1, Complete (Milestone)
```

---

## 16. Duration Reference Table — All Activity Types

| Equipment | Section | Activity | Duration |
|-----------|---------|----------|----------|
| Battery | Civil | Excavation and Blinding | 1d |
| Battery | Civil | Rebar, Anchor Bolts and Embedments | 2d |
| Battery | Civil | Formwork | 1d |
| Battery | Civil | Concrete Pour | 1d |
| Battery | Civil | Concrete Curing | 7d (calendar) |
| Battery | Civil | Formwork Strip and Pad Finish | 1d |
| Battery | Mechanical | Container Installation | 2d |
| Battery | Electrical | DC Cable Installation to PCS | 2d |
| Battery | Termination | Terminate Cable at Battery and PCS | 2d |
| Battery | Milestone | Group Installation Complete | 0d |
| PCS | Civil | Excavation and Blinding | 1d |
| PCS | Civil | Rebar, Anchor Bolts and Embedments | 2d |
| PCS | Civil | Formwork | 1d |
| PCS | Civil | Concrete Pour | 1d |
| PCS | Civil | Concrete Curing | 7d (calendar) |
| PCS | Civil | Formwork Strip and Pad Finish | 1d |
| PCS | Mechanical | Unit Installation | 2d |
| PCS | Electrical | AC Cable Installation to SUT | 2d |
| PCS | Termination | Terminate Cable at PCS and SUT | 2d |
| PCS | Milestone | Group Installation Complete | 0d |
| SUT | Civil | Excavation and Blinding | 1d |
| SUT | Civil | Rebar, Anchor Bolts and Embedments | 2d |
| SUT | Civil | Formwork | 1d |
| SUT | Civil | Concrete Pour | 1d |
| SUT | Civil | Concrete Curing | 7d (calendar) |
| SUT | Civil | Formwork Strip and Pad Finish | 1d |
| SUT | Mechanical | SUT Installation | 3d |
| SUT | Electrical | MV Cable Installation to Switchroom | 3d |
| SUT | Termination | Terminate MV Cable at SUT and Switchroom | 2d |
| SUT | Milestone | SUT Installation Complete | 0d |

Note: All durations above are default values stored in the package template
library. The user may override durations at the project level before
compilation. Partial groups use proportional duration with a minimum of 1d.

---

*This instruction governs how the GraphCompilerService generates all
Battery, PCS, and SUT installation activities, relationships, WBS nodes,
and milestones. Every rule in this document is mandatory.*
