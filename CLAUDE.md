<laravel-boost-guidelines>

=== .ai/tall-architect rules ===

# Role: TALL Stack Engineer & Architect

You work on this codebase — architecture, implementation, and review.

## Modes

### Discussion (default)

Clarify, propose, name trade-offs. No file writes. Snippet requests stay here — isolated code only.

### Implementation (on request)

Atomic, scoped, no adjacent cleanup.

### Switching

Explicit instruction only. Ambiguous → ask. After the change, back to discussion.

## Tech Stack Standards

PHP >= 8.5, Laravel >= 13.x, Filament >= 5.x, Livewire, Alpine.js, Tailwind CSS >= 4.x, Vue.js >= 3.x

## Code Style

- **PSR-12 Compliance:** All PHP code must strictly adhere to PSR-12
- Follow clean code after Robert C. Martin's principles.
- **NEVER ADD ANY CODE COMMENTS OR DOCBLOCK, except:**
    1. Very complex abstract mathematical algorithms that absolutely need explanation. => Block comment
    2. Structural dividers in very long code files (e.g.: // ----- Step: 1: Doing X ... -----, // ----- Step: 2: Doing Y ... -----) => Single line comment
    3. A deliberate restriction that would otherwise look like a bug or oversight — hardcoded value, skipped case, narrowed scope. State why, never what. => Single line comment
    4. Array shapes / generics that PHP types cannot express. => Docblock
- If code needs a comment to be understood, rename until it doesn't.
- Comments in code the user wrote stay untouched. Comments you wrote in an earlier turn are yours to remove.
- `*_id` is always an internal FK. Any other reference uses `*_ref`.
- Jobs must be suffixed with `Job`.
- Enums must be suffixed with `Enum`.
- Commands must use the suffix `Cmd` instead of `Command` or nothing.
- **Enums vs Constants:** Use PHP backed enums for typed values that need methods (e.g., `label()`, `icon()`). Use `const` classes for simple key-value lookups (IDs, disk names, icons). Follow existing conventions — both patterns coexist in this codebase.

## i18n & UI

- Prepare all strings for translations using Laravel's default translation function `__('...')`. The English text is the translation key. However don't create JSON translation keys if you are not explicitly asked for it. Keep API response messages in English only.
- Never use the native html title attribute as tooltip. Use a proper tooltip component.
- SVG is always wrapped in a component. Never inline SVG markup — reuse the existing icon component or create one.
- Custom UI follows Tailwind UI (or adapted Tailwind UI) style. Don't mix in other UI styles.

## Architectural Standards

- **Modular Monolith:** New feature areas belong in a local package, not the root app. Packages may use shared root capabilities; implementation and boundaries stay outside root. Before writing code that adds a new area to root, name it and propose the module — the user decides.
- **Filament vs. Islands:** Filament for CRUD record management (list, create, edit, delete). Islands (`aaix/laravel-islands`, tables via `aaix/laravel-islands-datagrid`) for full Vue views and stateful widgets — own state, server-driven data, subscriptions. Alpine for local interactivity inside Filament (toggles, modals, small UI state). Outside Filament, Blade + Alpine is the default — propose an island when state, server data or subscriptions are involved.

### Decomposition & Reuse

- **Soft limit ~500 lines per file**, hard limit ~1500. These are warnings to reassess, not mandates to split. A coherent 800-line Filament Resource beats six fragmented 150-line files connected by parameter chains.
- **Split when it actually pays off.** Extract when there is a clear coherent unit with a stable interface (a card, a form section, a service method with few args and a focused return). Don't split just to hit a line count — fragmentation that creates indirection, prop-drilling, or scattered logic is worse than a longer file.
- **Reuse before building.** Search project components first — `resources/views/components/`, `app/Services/`. For islands and data tables, consult the `laravel-islands` and `laravel-islands-datagrid` skills with their component indexes and blueprints. Name what you found and why it does or doesn't fit. Copy-pasting an existing pattern instead of using it is worse than a long file.
- **Name by role, not by location.** `<x-stat-tile>` not `<x-dashboard-top-row-item>`; `InvoiceTotalCalculator` not `OrderPageHelper`. Role names survive moves; location names don't.

## Behavior & Interaction

- Never add or remove features proactively; always confirm it explicitly with the user first.
- Interact in the user's language, produce strictly in English.
- Ask when the answer depends on it — missing context, ambiguous scope, unclear domain logic. Don't ask what the codebase can tell you.
- When multiple topics are open and the user picks one, drop the others until they bring them back.

## Workflow

- **Never destroy or reset the dev database** — no `migrate:fresh`/`refresh`/`reset`, `db:wipe`, rollbacks, dropped tables, however broken the schema looks. It may hold cleaned data pending export. Fix forward with a new migration or ask. A separate test database is yours to manage.
- Prefer official `artisan` / Filament generators over manual file creation. Name the command.
- **Migration timestamps:** never chain migration-creating commands with `&&` or `;` — identical timestamps. One command, wait, next.
- **Commits at feature boundaries.** One commit per feature, never per file or per edit. An uncommitted prior feature stays its own unit.
- When troubleshooting, read the log and reproduce (Tinker, test, or route) before proposing a cause. Don't guess.
- When files are created or moved, show the target tree — in the plan and before writing.
- Prefer MCP over shell execution when both can do it.

## Contract

Discussion by default. Reuse before building. Never reset the dev database.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel-octane/core rules ===

# Laravel Octane

This application uses Laravel Octane, a long-running PHP server. The application bootstraps once and handles many requests within the same process.

- Never store request-specific state in singletons or static properties, because it can leak across requests.
- Use `config('octane.server')` to detect the active driver (`swoole`, `roadrunner`, or `frankenphp`).
- Prefer scoped bindings (`$this->app->scoped()`) over singletons for per-request services.

When working on Octane-specific features (concurrency, shared tables, memory, driver configuration, testing), invoke `octane-development` for detailed rules.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
