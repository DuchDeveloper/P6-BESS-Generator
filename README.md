# BESS Schedule Generator

A rule-driven schedule logic engine for battery energy storage (BESS) projects.
It takes a project configuration — delivery model, site topology, scope flags —
and compiles a dependency-connected schedule graph that exports to Primavera P6
(XER), CSV or JSON.

`CLAUDE.md` is the governing specification: read it before changing an engine.
The topic notes (`WBS.md`, `BOP.md`, `Transformer.md`, `civil.md`, …) carry the
discipline detail behind the seeded package library.

**Stack:** Laravel 13 · Filament 5 · Tailwind CSS · Alpine.js · Pest · PHP 8.4

## Getting started

```bash
composer setup              # install, .env, key, migrate, build assets
php artisan db:seed         # test user + the full package template library
composer dev                # server, queue worker and Vite together
```

Sign in at `/admin` as `test@example.com` / `password` (created by the seeder —
change it, or create your own user with `php artisan make:filament-user`).

The default `.env.example` points at MySQL. SQLite works too: set
`DB_CONNECTION=sqlite` and remove the other `DB_*` lines.

## How a schedule is built

Create a project with the wizard (**Projects → New**), then on its **Manage** page:

1. **Init Topology** and **Instantiate Packages** (first time only): zones and
   blocks from the project defaults, then package instances from the template
   library with the conditional flags applied.
2. Review the package table: select or deselect packages and set ownership.
3. **Build Schedule**: resolve, compile and validate in one go. Run it again
   after any change in the table.
4. **Export**: XER, CSV or JSON.

The **Steps** menu runs the individual engines:

| Step | Engine |
|------|--------|
| 2. Apply Delivery Model | `DeliveryModelService`: EPC, BOP free-issued, owner design, construct only, split contract |
| 3. Resolve Dependencies | `DependencyResolverService`: a provider for every required `output_key` |
| 4. Compile Schedule | `GraphCompilerService`: activities, relationships, boundary milestones, topology, roll-up closures |
| 5. Validate | `ValidationService`: the rules in `app/Services/Bess/Validation` |

### Rules the engine keeps

- **Dependencies attach to outputs, never to packages.** A consumer requires an
  `output_key`. The resolver finds whichever active package provides it:
  - an internal package provides it itself;
  - an **external** package provides it through a boundary receipt milestone;
  - an **included elsewhere** package provides it through its containing package;
  - a **not applicable** package's outputs aren't required at all.
- **Design reviews pick up their predecessors dynamically.** Each review is
  linked to every selected discipline package at its maturity level (30 / 60 /
  90 / IFC).
- **No open ends.**
  - A milestone that drives nothing downstream rolls up to its phase closure
    (for example *Construction Complete*).
  - Every phase closure rolls up to a single **Project Complete** milestone.
  - The first activity (NTP) carries a Start On or After constraint at the
    project start date.
  - So does any external receipt that nothing drives; replace that date with the
    contractual receipt date in P6.
- **Critical errors block export.** Export re-runs validation on the schedule as
  it stands, and refuses if any critical error remains. Warnings and notes can be
  acknowledged on the Manage page; critical errors can only be fixed.

### Validation

| Rule | Severity |
|------|----------|
| MissingOutputProviderRule: every required output has a provider | Critical |
| OpenStartRule / OpenFinishRule: no activity without a predecessor / successor | Critical |
| OrphanMilestoneRule: no milestone with no links at all | Critical |
| InvalidPackageCombinationRule: "included elsewhere" points at an active package | Critical |
| EnergisationPrerequisiteRule: energisation milestones have every prerequisite | Critical |
| SuppressedOutputRule: a suppressed package isn't needed by an active one | Critical |
| BatteryCountReconciliationRule: block battery counts add up to the project total | Critical |
| MissingCompletionRollupRule: a package completion that drives nothing is a warning; one that only rolls up to its phase closure is a note to review | Warning / Info |

## Reports

From **Manage → Basis of Schedule** (signed-in users only):

- Basis of Schedule: an LLM-drafted narrative, viewable as HTML, PDF or Word.
  Configure the provider in `config/prism.php`.
- Summary Gantt: HTML or PowerPoint.

## Development

```bash
composer test                       # Pest: engines, validation, export, UI flows
php vendor/bin/pest --parallel      # faster
```

Tests run on in-memory SQLite and seed the real package library.
- `tests/Unit` covers each engine.
- `tests/Feature` covers whole-project generation, the Manage page flow, the
  export formats and report access.

Package templates are seeded data (`database/seeders`). To add or change a
package, edit its seeder and re-run `php artisan db:seed --class=PackageLibrarySeeder`.
The seeder rebuilds the library inside a transaction.
