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
2. Review the package table: select or deselect packages, and use
   **Ownership** on a row (or **Set ownership** for several) to mark packages
   external, not applicable, or included in another package.
3. **Build Schedule**: resolve, compile and validate in one go. It runs in
   the background; the page shows its progress and tells you when it's done.
   Run it again after any change in the table.
4. **Export**: XER, CSV or JSON.

Only one build or export runs per project at a time; a second request is told
the project is busy. Different projects never wait for each other.

**Calendars** (in the menu) hold working days and public holidays. Pick one on
the project; it is exported with the XER, so P6 schedules around the holidays.

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

## Running it for a team

`php artisan serve` handles one request at a time, which is fine for one person
but makes everyone wait while someone builds. For shared use:

1. **Serve through a real web server.** With XAMPP, point an Apache virtual
   host's document root at this project's `public/` folder.
2. **Run a queue worker** — builds run as queued jobs:
   ```bash
   php artisan queue:work --timeout=900
   ```
   Keep it running (on Windows, e.g. with Task Scheduler or NSSM). Without a
   worker the Manage page says the build is waiting to start. A single user
   can instead set `QUEUE_CONNECTION=sync` to build inside the request.
3. **Keep `CACHE_STORE=database`** (or redis/file): the per-project build lock
   uses it. Never `array` outside tests.
4. **Production settings:** `APP_ENV=production`, `APP_DEBUG=false`, then
   `php artisan migrate --force` and `php artisan optimize` after each update.

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
