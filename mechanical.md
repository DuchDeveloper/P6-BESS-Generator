
SWITCHYARD MECHANICAL INSTALL
#    Activity ID        Activity Description                               Predecessor
1    SUB.ELE.SY.010     Structural Steel Erection                          -
2    SUB.ELE.SY.020     Earth Mat and Earthing Down-Leads Installation     SUB.ELE.SY.010
3    SUB.ELE.SY.030     Post Insulator / Support Column Installation       SUB.ELE.SY.020
4    SUB.ELE.SY.040     Busbar Installation                                SUB.ELE.SY.030
5    SUB.ELE.SY.050     Surge Arrester Installation                        SUB.ELE.SY.020
6    SUB.ELE.SY.060     Current Transformer Installation                   SUB.ELE.SY.020
7    SUB.ELE.SY.070     Voltage Transformer / CVT Installation             SUB.ELE.SY.020
8    SUB.ELE.SY.080     Disconnector / Isolator Installation               SUB.ELE.SY.020
9    SUB.ELE.SY.090     Earth Switch Installation                          SUB.ELE.SY.080
10   SUB.ELE.SY.100     Circuit Breaker Installation                       SUB.ELE.SY.080
11   SUB.ELE.SY.110     Line Trap / Wave Trap Installation                 SUB.ELE.SY.070
12   SUB.ELE.SY.120     Shunt Reactor Installation                         SUB.ELE.SY.010
13   SUB.ELE.SY.130     Capacitor Bank Installation                        SUB.ELE.SY.010



SWITCHROOM MECHANICAL INSTALL
#    Activity ID        Activity Description                               Predecessor
1    SUB.ELE.SR.010     Cable Ladder, Tray and Containment Installation    -
2    SUB.ELE.SR.020     Earthing and Bonding Installation                  SUB.ELE.SR.010
3    SUB.ELE.SR.030     Lighting and Small Power Installation              SUB.ELE.SR.010
4    SUB.ELE.SR.040     Fire Detection and Suppression Installation        SUB.ELE.SR.010
5    SUB.ELE.SR.050     Auxiliary Transformer Installation                 SUB.ELE.SR.020
6    SUB.ELE.SR.060     MV / LV Switchgear Installation                    SUB.ELE.SR.020
7    SUB.ELE.SR.070     AC Auxiliary Supply / ACDB Installation            SUB.ELE.SR.060
8    SUB.ELE.SR.080     DC System and Battery Installation                 SUB.ELE.SR.070
9    SUB.ELE.SR.090     Protection and Control Panel Installation          SUB.ELE.SR.060
10   SUB.ELE.SR.100     Marshalling Kiosk / LCC Installation               SUB.ELE.SR.090


CONTROLROOM MECHANICAL INSTALL

#    Activity ID        Activity Description                               Predecessor
1    SUB.ELE.CR.010     Cable Ladder, Tray and Containment Installation    -
2    SUB.ELE.CR.020     Earthing and Bonding Installation                  SUB.ELE.CR.010
3    SUB.ELE.CR.030     Lighting and Small Power Installation              SUB.ELE.CR.010
4    SUB.ELE.CR.040     Fire Detection and Suppression Installation        SUB.ELE.CR.010
5    SUB.ELE.CR.050     HVAC Equipment Installation                        SUB.ELE.CR.010
6    SUB.ELE.CR.060     Access Floor Installation                          SUB.ELE.CR.020
7    SUB.ELE.CR.070     AC Auxiliary Supply / ACDB Installation            SUB.ELE.CR.060
8    SUB.ELE.CR.080     DC System and Battery Installation                 SUB.ELE.CR.070
9    SUB.ELE.CR.090     Protection and Control Panel Installation          SUB.ELE.CR.060
10   SUB.ELE.CR.100     SCADA / RTU Cabinet Installation                   SUB.ELE.CR.090
11   SUB.ELE.CR.110     Communications and Telecoms Rack Installation      SUB.ELE.CR.090
12   SUB.ELE.CR.120     Operator Workstations and Mimic Panel Installation SUB.ELE.CR.100
13   SUB.ELE.CR.130     Marshalling Kiosk / Interface Cabinet Installation SUB.ELE.CR.090


For switchyard, link civil works of each equipment to its installation. for example
Circuit Breaker foundations -> Circuit Breaker Installation
etc

Delete activities under old Control rool installation and switchroom installation and replace with the new ones above.
you may leave the milestones Switchroom Mechanical Sign-Off and Control Room Construction Complete as they are.
