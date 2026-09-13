# Main Transformer — Complete Electrical Works, Testing and Commissioning
## Claude Code Instruction

---

## 1. Entry Anchor Definitions

These activity codes are referenced as predecessors throughout this instruction.
They must be resolved before dependent activities can begin.

| Code | Description | Source |
|------|-------------|--------|
| MT-005 | Site Earthing Grid and Earth Electrode Complete | Output of site civil earthing package |
| MT-010 | Main Transformer Mechanical Installation Complete | Output of transformer mechanical section |
| MT-100 | Transformer Foundation and Civil Works Complete | Output of transformer civil section |
| MT-110 | Oil Filling, Treatment and Post-Fill Testing Complete | Output of transformer oil filling section |
| MT-120 | Approved Protection Relay Settings Received | Output of design / protection settings package |
| MT-140 | Transformer Oil Test Results Available | Output of oil filling section |
| MT-640 | OLTC Control Cables Installed | Internal — see Section 6 |

---

## 2. HV Connection Works

WBS: Construction → Main Transformer → Electrical Works → HV Connection Works

| Activity ID | Activity Description | Duration | Predecessor(s) | Notes |
|-------------|---------------------|----------|----------------|-------|
| MT-200 | Install HV Cable Tray, Supports or Busduct Supports | 3d | MT-110, MT-010 | |
| MT-210 | Supply and Install HV Busduct / Cable to Main Transformer | 5d | MT-200 | |
| MT-220 | Gland, Lug and Dress HV Cable | 2d | MT-210 | |
| MT-230 | Terminate and Joint HV Busduct / Cable | 3d | MT-220 | |
| MT-240 | Earth HV Cable Sheath / Screen | 1d | MT-230 | |
| MT-250 | Identify and Label HV Phases | 1d | MT-230 | Parallel with MT-240 |
| MT-260 | Torque-Check and Sign Off HV Connections | 1d | MT-240, MT-250 | |

---

## 3. LV Connection Works

WBS: Construction → Main Transformer → Electrical Works → LV Connection Works

Runs in parallel with HV Connection Works from the same entry anchors.

| Activity ID | Activity Description | Duration | Predecessor(s) | Notes |
|-------------|---------------------|----------|----------------|-------|
| MT-300 | Install LV Cable Tray, Supports or Busduct Supports | 3d | MT-110, MT-010 | Parallel with MT-200 |
| MT-310 | Supply and Install LV Busduct / Cable to Main Transformer | 5d | MT-300 | |
| MT-320 | Gland, Lug and Dress LV Cable | 2d | MT-310 | |
| MT-330 | Terminate and Joint LV Busduct / Cable | 3d | MT-320 | |
| MT-340 | Connect LV Neutral | 1d | MT-330 | |
| MT-350 | Identify and Label LV Phases | 1d | MT-330 | Parallel with MT-340 |
| MT-360 | Torque-Check and Sign Off LV Connections | 1d | MT-340, MT-350 | |

---

## 4. Earthing and Protection Equipment

WBS: Construction → Main Transformer → Electrical Works → Earthing and Protection Equipment

| Activity ID | Activity Description | Duration | Predecessor(s) | Notes |
|-------------|---------------------|----------|----------------|-------|
| MT-400 | Connect Transformer to Site Earthing System | 2d | MT-005, MT-100 | |
| MT-410 | Install and Connect Neutral Earthing Resistor (NER) | 2d | MT-400 | |
| MT-420 | Install and Connect Surge Arresters on HV Bushings | 2d | MT-210 | |
| MT-430 | Connect Surge Arrester Earth Leads | 1d | MT-400, MT-420 | |
| MT-440 | Earth and Bond Transformer Tank | 1d | MT-400 | |
| MT-450 | Connect Transformer Neutral Earthing | 1d | MT-410, MT-400 | |
| MT-460 | Wire NER Control and Alarm Circuits | 1d | MT-410, MT-640 | MT-640 from Section 6 |
| MT-470 | Test Earth Continuity | 1d | MT-430, MT-440, MT-450 | |
| MT-480 | Sign Off Main Transformer Earthing and Bonding | 1d | MT-460, MT-470 | |

---

## 5. Transformer Auxiliary Electrical Works

WBS: Construction → Main Transformer → Electrical Works → Auxiliary Electrical Works

| Activity ID | Activity Description | Duration | Predecessor(s) | Notes |
|-------------|---------------------|----------|----------------|-------|
| MT-500 | Install and Wire Marshalling Kiosk | 3d | MT-110 | |
| MT-510 | Wire Cooler Fan / Pump Power and Control Circuits | 2d | MT-500 | |
| MT-520 | Wire Space Heater and Lighting Circuits | 1d | MT-500 | |
| MT-530 | Wire OLTC Motor Supply and Control Circuits | 2d | MT-500 | |
| MT-540 | Wire RTCC / AVC Panel | 2d | MT-530 | Conditional — only if OLTC with AVC specified |
| MT-550 | Connect Auxiliary AC Supply | 1d | MT-510, MT-520, MT-530, MT-540 | |
| MT-560 | Connect DC Control Supply | 1d | MT-500, MT-640 | MT-640 from Section 6 |
| MT-570 | Perform Auxiliary System Functional Checks | 2d | MT-550, MT-560 | |

---

## 6. Control, Protection and SCADA Cabling

WBS: Construction → Main Transformer → Electrical Works → Control, Protection and SCADA Cabling

| Activity ID | Activity Description | Duration | Predecessor(s) | Notes |
|-------------|---------------------|----------|----------------|-------|
| MT-600 | Install and Wire Marshalling Kiosk Cables | 3d | MT-500 | |
| MT-610 | Install Protection Relay Panel Control Cables | 3d | MT-110 | |
| MT-620 | Install CT and VT Secondary Cables | 2d | MT-110 | Conditional — only if CTs and VTs in scope |
| MT-630 | Install Buchholz, PRD, OTI and WTI Instrument Cables | 2d | MT-500 | |
| MT-640 | Install OLTC Control Cables | 2d | MT-530 | Referenced as predecessor in MT-460 and MT-560 |
| MT-650 | Install SCADA and Telemetry Signal Cables | 3d | MT-600, MT-610 | |
| MT-660 | Install Auxiliary Power and Lighting Supply Cables | 2d | MT-500 | |
| MT-670 | Wire Oil Level, Oil Temperature and Winding Temperature Alarms | 2d | MT-630 | |
| MT-680 | Ferrule and Label Control, Protection and SCADA Cables | 2d | MT-600, MT-610, MT-620, MT-630, MT-640, MT-650, MT-660, MT-670 | |
| MT-690 | Sign Off Control and Protection Cabling | 1d | MT-680 | |

---

## 7. Pre-Commissioning Testing

WBS: Commissioning → Main Transformer → Pre-Commissioning Testing

| Activity ID | Activity Description | Duration | Predecessor(s) | Notes |
|-------------|---------------------|----------|----------------|-------|
| MT-700 | Test HV Cable Insulation Resistance | 1d | MT-260 | |
| MT-710 | Test LV Cable Insulation Resistance | 1d | MT-360 | |
| MT-720 | Perform Point-to-Point Cable Continuity Checks | 2d | MT-690 | |
| MT-730 | Test Control Cable Insulation Resistance | 1d | MT-690 | |
| MT-740 | Test Transformer Winding Insulation Resistance / PI | 1d | MT-260, MT-360, MT-480 | |
| MT-750 | Test Transformer Winding Resistance | 1d | MT-740 | |
| MT-760 | Perform Transformer Ratio Test | 1d | MT-750 | |
| MT-770 | Check Vector Group, Polarity and Phase Relationship | 1d | MT-760 | |
| MT-780 | Test Bushing CT Ratio, Polarity and Knee Point | 1d | MT-620 | Conditional — only if CTs in scope |
| MT-790 | Test NER Resistance | 1d | MT-450 | |
| MT-800 | Inspect and Test Surge Arresters | 1d | MT-430 | |
| MT-810 | Review Transformer Oil Test Results | 1d | MT-140 | Conditional — only if oil-filled transformer |
| MT-820 | Compile Electrical Pre-Commissioning Test Report | 2d | MT-700, MT-710, MT-720, MT-730, MT-740, MT-750, MT-760, MT-770, MT-780, MT-790, MT-800, MT-810 | |

---

## 8. Protection, Control and SCADA Commissioning

WBS: Commissioning → Main Transformer → Protection, Control and SCADA Commissioning

| Activity ID | Activity Description | Duration | Predecessor(s) | Notes |
|-------------|---------------------|----------|----------------|-------|
| MT-900 | Upload Approved Protection Relay Settings | 1d | MT-120, MT-690 | MT-120 = approved settings from design |
| MT-910 | Perform Protection Relay Secondary Injection Test | 2d | MT-900 | |
| MT-920 | Test Transformer Differential Protection | 2d | MT-780, MT-910 | |
| MT-930 | Test REF, Earth Fault and Overcurrent Protection | 2d | MT-780, MT-910 | Parallel with MT-920 |
| MT-940 | Test Buchholz Alarm and Trip Function | 1d | MT-670, MT-910 | |
| MT-950 | Test PRD Alarm and Trip Function | 1d | MT-670, MT-910 | Parallel with MT-940 |
| MT-960 | Test OTI / WTI Alarm and Trip Function | 1d | MT-670, MT-910 | Parallel with MT-940 |
| MT-970 | Test OLTC Raise, Lower, Auto and Manual Functions | 1d | MT-570, MT-640 | |
| MT-980 | Test Trip Circuit Supervision | 1d | MT-910 | |
| MT-990 | Test HV Breaker Trip / Close Interface | 1d | MT-920, MT-930, MT-980 | |
| MT-1000 | Test LV Breaker Trip / Close Interface | 1d | MT-920, MT-930, MT-980 | Parallel with MT-990 |
| MT-1010 | Test Interlocks and Permissives | 1d | MT-970, MT-990, MT-1000 | |
| MT-1020 | Test SCADA Signal Point-to-Point | 2d | MT-650, MT-690 | |
| MT-1030 | Test SCADA Alarms and Status Indications | 2d | MT-940, MT-950, MT-960, MT-970, MT-990, MT-1000, MT-1020 | |
| MT-1040 | Sign Off Protection, Control and SCADA Commissioning | 1d | MT-920, MT-930, MT-940, MT-950, MT-960, MT-970, MT-980, MT-990, MT-1000, MT-1010, MT-1030 | |

---

## 9. Energisation Readiness

WBS: Commissioning → Main Transformer → Energisation Readiness

| Activity ID | Activity Description | Duration | Predecessor(s) | Notes |
|-------------|---------------------|----------|----------------|-------|
| MT-1100 | Close Out Electrical Punch List | 2d | MT-820, MT-1040 | |
| MT-1110 | Review and Accept Test Certificates | 1d | MT-820, MT-1040 | |
| MT-1120 | Confirm Protection Settings Approval | 1d | MT-1040 | |
| MT-1130 | Confirm Earthing Sign-Off | 1d | MT-480 | |
| MT-1140 | Approve Switching Program / Energisation Procedure | 2d | MT-1110, MT-1120, MT-1130 | |
| MT-1150 | Perform Pre-Energisation Electrical Inspection | 1d | MT-1100, MT-1110, MT-1120, MT-1130, MT-1140 | |
| MT-1160 | Issue Ready-for-Energisation Certificate | 1d | MT-1150 | Output key: transformer_ready_to_energise |

---

## 10. Electrical Completion

WBS: Commissioning → Main Transformer → Electrical Completion

| Activity ID | Activity Description | Duration | Predecessor(s) | Notes |
|-------------|---------------------|----------|----------------|-------|
| MT-1200 | Energise Main Transformer | 1d | MT-1160 | |
| MT-1210 | Perform No-Load Voltage and Phase Rotation Check | 1d | MT-1200 | |
| MT-1220 | Observe Transformer Noise, Vibration and Temperature | 1d | MT-1200 | Parallel with MT-1210 |
| MT-1230 | Perform Load Acceptance Check | 1d | MT-1210, MT-1220 | |
| MT-1240 | Check Post-Energisation SCADA and Protection Status | 1d | MT-1230 | |
| MT-1250 | Mark Up As-Built Electrical Drawings | 2d | MT-1240 | |
| MT-1260 | Submit Final Electrical Test Dossier | 3d | MT-820, MT-1040, MT-1250 | |
| MT-1270 | Issue Electrical Works Completion Certificate | 1d | MT-1260 | |
| MT-1280 | ◆ Main Transformer Electrical Works Complete (Milestone) | 0d | MT-1270 | Output key: transformer_electrical_complete |

---

## 11. Conditional Activities

The following activities are only generated when the relevant scope flag
is set on the project. If the flag is false the activity is suppressed
and predecessor references are rerouted to the next applicable activity.

| Activity ID | Condition Flag | Description | When Suppressed — Reroute |
|-------------|---------------|-------------|---------------------------|
| MT-540 | oltc_with_avc = true | Wire RTCC / AVC Panel | MT-550 predecessor becomes MT-510, MT-520, MT-530 |
| MT-620 | ct_vt_in_scope = true | Install CT and VT Secondary Cables | MT-680 and MT-820 predecessor lists exclude MT-620 |
| MT-780 | ct_vt_in_scope = true | Test Bushing CT Ratio, Polarity and Knee Point | MT-820 predecessor list excludes MT-780 |
| MT-810 | oil_filled_transformer = true | Review Transformer Oil Test Results | MT-820 predecessor list excludes MT-810 |

---

## 12. Output Keys

| Milestone | Activity ID | Output Key | Consumed By |
|-----------|-------------|------------|-------------|
| Transformer Ready to Energise | MT-1160 | transformer_ready_to_energise | Energisation milestone prerequisites |
| Main Transformer Electrical Works Complete | MT-1280 | transformer_electrical_complete | Project handover and closeout |

---

## 13. WBS Placement Summary

| Activity Range | Section | WBS Location |
|---------------|---------|-------------|
| MT-200 to MT-260 | HV Connection Works | Construction → Main Transformer → Electrical Works → HV Connection Works |
| MT-300 to MT-360 | LV Connection Works | Construction → Main Transformer → Electrical Works → LV Connection Works |
| MT-400 to MT-480 | Earthing and Protection Equipment | Construction → Main Transformer → Electrical Works → Earthing and Protection Equipment |
| MT-500 to MT-570 | Auxiliary Electrical Works | Construction → Main Transformer → Electrical Works → Auxiliary Electrical Works |
| MT-600 to MT-690 | Control, Protection and SCADA Cabling | Construction → Main Transformer → Electrical Works → Control, Protection and SCADA Cabling |
| MT-700 to MT-820 | Pre-Commissioning Testing | Commissioning → Main Transformer → Pre-Commissioning Testing |
| MT-900 to MT-1040 | Protection, Control and SCADA Commissioning | Commissioning → Main Transformer → Protection, Control and SCADA Commissioning |
| MT-1100 to MT-1160 | Energisation Readiness | Commissioning → Main Transformer → Energisation Readiness |
| MT-1200 to MT-1280 | Electrical Completion | Commissioning → Main Transformer → Electrical Completion |

---

## 14. Total Activity Count

| Section | Activities | Milestones |
|---------|-----------|------------|
| HV Connection Works | 7 | 0 |
| LV Connection Works | 7 | 0 |
| Earthing and Protection Equipment | 9 | 0 |
| Auxiliary Electrical Works | 7 | 0 |
| Control, Protection and SCADA Cabling | 10 | 0 |
| Pre-Commissioning Testing | 13 | 0 |
| Protection, Control and SCADA Commissioning | 15 | 0 |
| Energisation Readiness | 7 | 0 |
| Electrical Completion | 7 | 1 |
| **Total** | **82** | **1** |
