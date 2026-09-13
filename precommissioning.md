#    Activity ID              Activity Description                                      Duration    Predecessor
1    BESS.PC.MOB.010          OEM Commissioning Team Mobilisation                       10 d        -
2    BESS.PC.MOB.020          Site Safety Inductions and Permit to Work Setup           3 d         BESS.PC.MOB.010
3    BESS.PC.MOB.030          Commissioning Tools, Test Equipment and Spares On Site    5 d         BESS.PC.MOB.010
4    BESS.PC.MOB.040          Pre-Commissioning ITPs and Procedures Issued              5 d         BESS.PC.MOB.020

5    BESS.PC.BLK.B01          Block 01 Pre-Commissioning (10 units)                     10 d        BESS.PC.MOB.040
6    BESS.PC.BLK.B02          Block 02 Pre-Commissioning (10 units)                     10 d        BESS.PC.BLK.B01
7    BESS.PC.BLK.B03          Block 03 Pre-Commissioning (10 units)                     10 d        BESS.PC.BLK.B02
8    BESS.PC.BLK.B04          Block 04 Pre-Commissioning (10 units)                     10 d        BESS.PC.BLK.B03
9    BESS.PC.BLK.B05          Block 05 Pre-Commissioning (10 units)                     10 d        BESS.PC.BLK.B04
10   BESS.PC.BLK.B06          Block 06 Pre-Commissioning (10 units)                     10 d        BESS.PC.BLK.B05
11   BESS.PC.BLK.B07          Block 07 Pre-Commissioning (10 units)                     10 d        BESS.PC.BLK.B06
12   BESS.PC.BLK.B08          Block 08 Pre-Commissioning (10 units)                     10 d        BESS.PC.BLK.B07
13   BESS.PC.BLK.B09          Block 09 Pre-Commissioning (10 units)                     10 d        BESS.PC.BLK.B08
14   BESS.PC.BLK.B10          Block 10 Pre-Commissioning (10 units)                     10 d        BESS.PC.BLK.B09
15   BESS.PC.BLK.B11          Block 11 Pre-Commissioning (10 units)                     10 d        BESS.PC.BLK.B10
16   BESS.PC.BLK.B12          Block 12 Pre-Commissioning (10 units)                     10 d        BESS.PC.BLK.B11
17   BESS.PC.BLK.B13          Block 13 Pre-Commissioning (10 units)                     10 d        BESS.PC.BLK.B12


30   BESS.CX.ENG.010          MV Network Back-Energisation from POC                     3 d         BESS.PC.BLK.B01
31   BESS.CX.ENG.020          Auxiliary Supply Energisation to Blocks                   5 d         BESS.CX.ENG.010
32   BESS.CX.ENG.030          First Block DC Bus Energisation (Block 01)                2 d         BESS.PC.BLK.B01, BESS.CX.ENG.020
33   BESS.CX.ENG.040          First Block AC Synchronisation and First Power (Block 01) 3 d         BESS.CX.ENG.030
34   BESS.CX.ENG.050          Remaining Blocks Energisation (Blocks 02-25)              25 d        BESS.CX.ENG.040

35   BESS.CX.SYS.010          PPC (Power Plant Controller) Commissioning                10 d        BESS.CX.ENG.040
36   BESS.CX.SYS.020          SCADA and Site Controller Integration                     10 d        BESS.CX.SYS.010
37   BESS.CX.SYS.030          Site-Level Charge/Discharge Functional Tests              10 d        BESS.CX.SYS.020
38   BESS.CX.SYS.040          Round-Trip Efficiency Test                                5 d         BESS.CX.SYS.030
39   BESS.CX.SYS.050          Full Site Capacity Test (Rated MWh)                       5 d         BESS.CX.SYS.040

40   BESS.CX.GCT.010          Fault Ride Through (FRT/LVRT) Testing                     5 d         BESS.CX.SYS.050
41   BESS.CX.GCT.020          Frequency Response Testing (FFR, FCAS)                    5 d         BESS.CX.GCT.010
42   BESS.CX.GCT.030          Reactive Power and Voltage Control Testing                5 d         BESS.CX.GCT.020
43   BESS.CX.GCT.040          Active Power Curtailment and Ramp Rate Testing            3 d         BESS.CX.GCT.030
44   BESS.CX.GCT.050          Grid Code Compliance Witness and Sign-off                 5 d         BESS.CX.GCT.040

45   BESS.CX.REL.010          30-Day Reliability Run Commencement                       1 d         BESS.CX.GCT.050
46   BESS.CX.REL.020          30-Day Reliability Run                                    30 d        BESS.CX.REL.010
47   BESS.CX.REL.030          Performance Test Final Report                             10 d        BESS.CX.REL.020
48   BESS.CX.REL.040          Practical Completion Inspection and Punch List            5 d         BESS.CX.REL.030
49   BESS.CX.REL.050          Handover to Operations and PCD Certificate                5 d         BESS.CX.REL.040
