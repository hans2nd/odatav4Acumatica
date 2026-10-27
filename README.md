# PL Sync Data Warehouse

A powerful synchronization engine built on Laravel to bridge Acumatica ERP (OData V4) and local SQL Server/MySQL databases.

## Key Features
- **Dynamic OData to SQL Mapping**: Easily map OData entities to relational database tables.
- **Incremental & Full Sync**: Supports fast incremental backups using `LastModifiedDateTime` and full replacements.
- **Database-Driven Scheduler**: Robust cron-based background synchronization to prevent overlapping syncs.
- **Multi-Database Support**: Connects to SQL Server, MySQL, Firebird, and other relational endpoints.
- **System Utilities**: Built-in UI to manage deployments, cache, and queue workers dynamically.
- **ChatEDP AI Data Analytics**: Intelligent natural language querying with metadata exploration and query repository integration.
- **Granular RBAC & Dynamic Menu Management**: Role-based access control with visual Permission Matrix and automatic Super Administrator bypass.

---

## Changelog

### v1.6.1 - October 27, 2026 (Sync Engine Timeout Hardening, Stuck Job Recovery & Hosting Compatibility)
- **Stuck Job & Lock Recovery Engine**:
  - Implemented `POST /sync/{entity}/reset-stuck` (`SyncController::resetStuckJob`) to safely abort hanging `SyncJob`s (`pending`/`running`), signal cancellation to active `SyncLog`s, release distributed locks on `sync_states`, and purge Laravel `laravel_unique_job` cache locks.
  - Implemented `GET /sync/{entity}/status` (`SyncController::entitySyncStatus`) to return real-time lock ownership, lease expiration, active job metrics, and latest execution logs.
  - Upgraded Single-Entity Synchronization Modals on both `transactions/show.blade.php` and `master-data/show.blade.php` with:
    - Real-time progress bar, stage indicator, processed rows counter, and elapsed time display.
    - Automatic stuck job / lock detection banner with **Reset & Buka Kunci (Force Unlock)** and **Pantau Status (Monitor)** actions.
    - Graceful handling of HTTP 423 / 429 (`already_running`) responses, displaying direct unlock actions instead of dead-end errors.
    - Client-side Watchdog Timer alerting the user if no rows are processed for > 45 seconds due to silent background worker termination.
- **Dynamic Execution Timeout & Hosting Hardening (Laragon / Jagoan Hosting / LiteSpeed)**:
  - **LiteSpeed & Web Server Timeout Bypass**: Added `RewriteRule .* - [E=noconntimeout:1]` and `[E=noabort:1]` in `public/.htaccess` to prevent LiteSpeed Web Server and CloudLinux from killing PHP sync/cron processes at 120 seconds.
  - **QueueWorkerService CLI PHP Resolution**: Upgraded `QueueWorkerService::startBackgroundWorker()` to dynamically resolve the CLI PHP binary path (`PHP_BINARY` / `php.exe`), escaping arguments with `escapeshellarg()` to ensure background workers spawn cleanly on Windows/Laragon and Linux even when `php` is not registered in system PATH.
  - **Dynamic Queue Job Timeout**: Implemented dynamic `timeout()` method on `SyncEntityJob` (600s for master data, 3600s for transaction data, or entity-specific configurations).
  - **PHP Execution Time Limits**: Added `@set_time_limit(0)` and `@ini_set('max_execution_time', '0')` in `SyncEntityJob::handle` and `SystemUtilityController::runWebCron`. Added per-batch timer extensions (`@set_time_limit(600)`) in `SyncService::sync` on every chunk processed, guaranteeing active sync pipelines never hit execution time limits.

### v1.6.0 - October 24, 2026 (Enterprise Performance, Security Hardening, Streaming Architecture & RBAC Optimization)
- **Security Audit & Public Cleanup**:
  - Eliminated high-risk exposed debugging scripts and sensitive connection dumps from the `public/` directory (`test_ai.php`, `benchmark.php`, `migrate.php`, `dump_connections.php`, `get_connections.php`, `verify*.php`, `connections_dump.json`).
  - Added safe CLI Artisan command `php artisan ai:test-schema {connection_id?}` (`AiTestSchemaCommand`) to safely inspect AI schema discovery without exposing public HTTP endpoints.
  - Hardened `.gitignore` to prevent accidental commits of public dump, benchmark, verification, and temporary test scripts.
- **Sync Engine & Scheduler Low-Memory Footprint**:
  - Added `DB::disableQueryLog()` across default, source, and destination database connections in `SyncEntityJob` and `SyncService`, preventing memory leaks from accumulating millions of query logs during massive synchronization cycles.
  - Enforced strict batch chunk bounds between 100 and 1,000 rows in `MySqlBatchStrategy` and `SqlServerBatchStrategy` to prevent PDO parameter overflow.
  - Refactored relational DB source extraction in `SyncService` to use streaming generator cursors (`$query->cursor()`) with explicit garbage clearance (`unset()`), maintaining flat RAM usage regardless of table size.
  - Optimized `routes/console.php` to query only active schedules (`where('is_active', true)`) and added a 120-minute mutex TTL (`withoutOverlapping(120)`) to prevent hung cron locks.
- **Report Builder & Excel Export Streaming**:
  - Introduced `ReportCacheService` providing deterministic SHA-256 caching of report payloads in `storage/app/reports/cache/`.
  - Refactored Excel exports across `ReportBuilderController`, `QueryBuilderController`, `QueryRepositoryController`, `DynamicReportController`, and `ReportController` to stream rows directly to disk via `SimpleExcelWriter` using cursor iteration instead of buffering full datasets into RAM.
  - Implemented disk-cached downloads with cache preservation (`is_cached`), allowing multiple users to download identical reports directly from disk with zero database queries.
- **ChatEDP AI Data Analytics Performance & Query Guardrails**:
  - Implemented versioned AI schema caching in `SchemaCacheService` with a 3,600s TTL (`Cache::remember("ai_schema_{$connectionId}_v{$version}_{$domainKey}", ...)`), eliminating repetitive multi-table joins on every chat message.
  - Hooked automated cache invalidation in `AiSettingsController` (`update`, `metadataUpdate`, `refreshSchema`) to ensure metadata and semantics changes propagate immediately.
  - Cached ChatEDP suggested questions (`Cache::remember("ai_suggested_questions_{$connectionId}", 3600, ...)`) with dynamic array shuffling to preserve question variety on page reloads without querying the database.
  - Hardened analytics query row limits to a strict ceiling of 1,000 rows in both `ChatEDPController` and `QueryRepositoryService::executeForAnalytics()`.
- **Granular RBAC, Dynamic Menu & Authorization Caching**:
  - Implemented versioned per-user menu caching in `MenuService` (`Cache::remember("user_menus_{$user->id}_v{$menuVer}_{$userPermVer}", 3600, ...)`), eliminating redundant `menus` table scans and recursive permission evaluations on every page load.
  - Added persistent versioned caching for `isSuperAdmin()` and `loadEffectivePermissions()` in `User.php`, reducing `CheckPermission` middleware overhead to zero database queries on authenticated requests.
  - Wired real-time cache invalidation in `MenuManagementController` (on menu create, update, delete, reorder, toggle, and publish) and `UserManagementController` (on user update, role permission update, role delete, and table access update).

### v1.5.2 - September 24, 2026 (Append Only Sync & UI Enhancements)
- **Feature: Append Only (Backdate Sync)**: Introduced a new synchronization mode that performs pure `INSERT` operations (Append Only) instead of full truncations or upserts. This is useful for building historical transaction logs or slowly changing dimensions (SCD). This mode is fully integrated into the Master Data, Transactions, and Auto-Schedule modules.
- **Feature: Entity Form Auto-Suggestion**: The 'Add/Edit Entity' form now automatically suggests standardized names for `Entity Key`, `Target SQL Table`, and `Display Description` based on the selected Source Connection and Source Object.
- **Enhancement: Report Builder Table Filtering**: The Report Builder "Search or select table" dropdown has been optimized. When selecting a Database Connection, it now filters the schema and exclusively displays tables that are configured in the Entity Configuration (synced tables), hiding irrelevant database tables.
- **Bugfix: SQL Server Datatype Mapping**: Fixed an issue in `MetadataRepository` where SQL Server `INT` data types were incorrectly suggested as `Number (Decimal)` when refreshing metadata. They are now correctly mapped to `Integer`.

### v1.5.1 - September 24, 2026 (SharePoint Sync Optimization & Job Management)
- **Performance: Native XMLReader for Excel**: Replaced `SimpleXML` with `Native XMLReader C-level Stream` in the _ZIP Parser_ for `SharePointGraphClient`. This eliminates the overhead of creating a DOM Object per row, allowing massive files (e.g., 20,000+ rows) to be synced in seconds without severely impacting RAM or CPU usage.
- **Performance: SharePoint Graph Search API**: Replaced the recursive per-folder file traversal and Delta API iteration with the **Microsoft Graph Search API** (`/search(q=...)`) for discovering Excel/CSV source objects. This reduces hundreds of sequential API calls down to 1-2 paginated queries across all document libraries, making the Source Object reload near-instant. Falls back to per-drive Delta API if the search index is unavailable.
- **Feature: Abort Synchronization Job**: Added an "Abort" button in the *Synchronization History* menu, along with a Backend API endpoint to stop *SyncJob*s that are in `pending` or `running` status. The job abortion runs safely using a *Cache Control Signal* that commands the worker engine to halt execution with a graceful stop.
- **Bugfix: SharePoint Site Preview & Cache Invalidation**: Fixed the SharePoint URL preview function on the *Connection* form which previously didn't respond to *Site Path* changes. Furthermore, resolved a bug where the *Add Entity* menu still loaded the old *Source Object* list after the *Site Path* was updated. Connection updates or clicking the *Reload* button now automatically triggers *cache invalidation* on the frontend (`localStorage`) and backend (Laravel `Cache`), ensuring that the *Source Object* data is always real-time.

### v1.5.0 (Table-Level Dynamic RBAC & Metadata Caching & Report Template Sharing)
- **Feature: Share Report Templates to Roles**: Added a feature to share Report Templates with specific *Roles*. Users (like Superadmins) can now share configured report templates with other roles, allowing those users to instantly use them via the "Shared with Me" tab in the Report Templates menu without rebuilding configurations from scratch.
- **Feature: Dynamic Table Level Access**: Implemented dynamic access restrictions at the table level (`connection.{key}.table.{table}.access`). A new "Table Access" UI in the Users & Roles menu allows Super Admins to define exactly which specific tables can be viewed and queried by a particular Role per database connection, without cluttering the main Permission Matrix. These rules are automatically enforced across the **Report Builder**, **Query Builder**, **Query Repository**, and **Endpoint APIs**.
- **Feature: Database Connection Permissions**: Implemented specific database connection permissions (`connection.{key}.access`). Users can now be restricted to only view and use specific database connections.
- **Enhancement: Database Metadata Caching**: Optimized the loading performance of the Report Builder and Query Builder by caching table structures for 24 hours. Added a UI *refresh* button so users can instantly reload table structures without manually running `php artisan cache:clear` from the backend.
- **UI/UX: Permission Matrix Update**: Added a dedicated "Database Connection Permissions" section to the Users & Roles menu, enabling admins to interactively check database connection access rights for each user role.

### v1.4.9 (RBAC Granularity & System Utilities Updates)
- **Security & Authorization**: Implemented stricter granular RBAC architecture across all application modules.
  - Separated `query_builder.view` permission from `manage` so read-only users can still execute pre-built queries.
  - Introduced `sync_engine.view` permission for the Synchronization History and Scheduler pages so auditors can monitor logs without being able to run manual syncs.
  - Locked the Synchronization Endpoint (`SyncController`) to require explicit per-entity permissions (e.g., `customers.sync`), preventing users from running syncs on unauthorized entities.
  - Locked the Mass Log Deletion endpoint to explicitly require the `logs.delete` permission.
- **Feature: Interactive Seeder Selection**: Modified the *Run Seeder* feature on the System Utilities page with an interactive modal popup. Users can now individually select and execute specific seeder classes (e.g., `DefaultMenuSeeder` or `DefaultRbacSeeder`) to prevent data duplication during incremental production migrations.

### v1.4.8 (Queue Stability & Background Sync Hardening)
- **Enhancement: Unique Queue Jobs**: Implemented the `ShouldBeUnique` interface and `uniqueId()` method on `SyncEntityJob`. This ensures that duplicate synchronization queues for the same entity are automatically rejected by Laravel if a previous job is still running. This update prevents queue buildup, overlapping processes, and hanging VPS resources (RAM/CPU) in production.

### v1.4.7 (Schema Validation & Scheduler UI Fixes)
- **Feature: Strict Schema-on-Configuration**: Replaced flexible legacy DB mapping with strict schema generation in `EntityController` and `SchemaManager`. Tables are now accurately built with strict data types based exactly on the user's mapping configuration in "Manage Fields".
- **Enhancement: Settings UI Modernization**: Redesigned the "Manage Fields" table. Upgraded legacy inputs to modern global Alpine components (`customSelect`) for Data Types and Semantic Types, and implemented resizable textareas (`resize-y`) to cleanly wrap long aliases, descriptions, and SQL transformations.
- **Bugfix: SQL Server Binary Timestamp Migration**: Fixed `SQLSTATE[22007]: Invalid datetime format` during SQL Server Master Warehouse syncs by strictly casting Acumatica `tstamp` rowversion hex codes to `string` columns in the `SchemaManager`.
- **Bugfix: Scheduler Preset Creation (404 Error)**: Added a dynamic Entity selection dropdown to the "Add Preset" modal in Scheduler Management. This fixes a bug where attempting to create a new schedule sent an empty Entity Key to the `PUT /api/scheduler/` endpoint.
- **Bugfix: Scheduler Alpine.js Rendering**: Fixed a fatal `sortedSchedules is not defined` template loop bug, and added optional-chaining (`?.`) to prevent `TypeError: Cannot read properties of null` when toggling the cron preview UI before it generated data.

### v1.4.6 (Report Builder Templates & Excel Export Fixes)
- **Feature: Dynamic Template Auto-Loading**: Re-engineered the Report Templates CRUD interface. Clicking "Edit" on a saved template now seamlessly redirects and auto-loads the configuration directly into the interactive Report Builder.
- **Enhancement: In-Place Template Updates**: The Report Builder now supports `PUT` requests when saving an actively loaded template, allowing users to continuously update existing reports without creating duplicates.
- **Bugfix: Excel Raw Date Serial Format**: Resolved an issue where exported `.xlsx` files displayed dates as raw Excel serial numbers (e.g., `45600`) due to missing default formatting. The export engine now correctly detects ISO-8601 OData timestamps (including `T` and timezone offsets) using an advanced Regex, and pre-formats them into clean strings that Excel natively parses as Date data types.

### v1.4.5 (ChatEDP Semantic Suggestion Hardening & UI/UX Fixes)
- **Security & Stability**: Implemented comprehensive hardening in `SemanticSuggestionService` with canonical metric structures, missing array fallbacks, and strict SQL expression validation against malicious injections.
- **Enhancement: Grain Evidence Validation**: Replaced blind schema-based grain inference with statistical uniqueness (`COUNT(DISTINCT)`) verification for accurate `transaction_grain` detection.
- **Enhancement: Ranking Analysis Compilation**: Upgraded `QueryCompilerService` to natively handle `ROW_NUMBER()` for grouped top-detail dimensions, and configured `GROUP_CONCAT` / `STRING_AGG` integration for detail columns inside grouped entity analytics.
- **Bugfix: UI Rendering for Aliased Metrics**: Resolved a critical data-mapping bug in `ResultInterpretationService` where ChatEDP tables displayed empty columns for `PENJUALAN SUM` and detail dimensions. Synchronized the frontend column metadata keys with the label-mapped JSON payload.

### v1.4.4 (RBAC Security Observability & Dynamic Permission Fixes)
- **Feature: RBAC Audit Trail**: Implemented a comprehensive, append-only `RbacAuditLog` system to track all security-sensitive mutations without altering authorization semantics. Tracks `actor`, `target`, `action`, `before_state`, `after_state`, and environment metadata.
- **Feature: Security Observability**: Added automated audit logging for user creation, deletion, activation toggles, role assignments, and role permission matrix updates.
- **Enhancement: Audit Redaction**: Ensured sensitive fields (like `password`, `api_token`, `remember_token`) are automatically redacted from `before_state` and `after_state` payloads prior to persistence.
- **Bugfix: Menu vs Dynamic Entity Permission Separation**: Separated Dynamic API Endpoint permissions from standard generic module permissions in the UI. Endpoints are now logically grouped and cleanly persisted without overlapping.
- **Bugfix: API Endpoint Case Sensitivity**: Fixed an issue where accessing API endpoints with mixed-case URL slugs resulted in 403 Unauthorized errors despite holding the valid permissions. `DataEndpointController` now strictly constructs the permission check using the canonical `endpoint_slug` from the database.

### v1.4.3 (ChatEDP Intelligence & UI/UX Overhaul)
- **Feature: Modern Conversational UI**: Completely redesigned ChatEDP (`resources/views/chatedp/index.blade.php`) with a premium glassmorphism aesthetic, gradient charts, avatar-based chat bubbles, and smooth fade-in animations.
- **Feature: Chat History Management**: Implemented a `DELETE` endpoint (`/api/chatedp/conversations/{id}`) and sidebar UI to allow users to permanently delete chat histories.
- **Bugfix: Semantic Alias Resolution for Sorting**: Fixed a critical SQL exception (`Unknown column 'X_SUM' in 'order clause'`) in `QueryValidatorService`. The validator now correctly uses the generated semantic alias rather than attempting to sort by the physical column name when interpreting aggregated metrics.
- **Enhancement: Dynamic Narrative Sizing**: Increased the hardcoded limit for LLM narrative context in `ChatEDPController` from 3 to 10 rows. This ensures that when users request "Top 5" or "Top 10", the AI actually narrates all requested items rather than truncating its response.

### v1.4.2 (Default Migrations & Seeders Standardization)
- **Menu & Seeder Synchronization**: Standardized `DefaultMenuSeeder` and `MenuSeeder` to include all modern application modules:
  - **Dashboard**: `dashboard`
  - **ChatEDP**: `chatedp.index` (`chatedp.access`)
  - **Report**: Report Builder (`report.builder`), Endpoint API (`endpoint.api.index`), Query Builder (`query.builder`), Query Repository (`query-repository.index`).
  - **Synchronization**: Master Data (`master-data.index`), Transaction (`transactions.index`), Scheduler (`scheduler.index`), Sync History (`sync-history.index`), Logs (`logs.index`).
  - **Settings**: General Settings (`settings.index`), Users & Roles (`users.index`), Menu Management (`menu-management.index`), System Utilities (`settings.system-utilities`), OpenRouter AI (`settings.ai.openrouter`), AI Metadata (`settings.ai.metadata`), ChatEDP Analytics (`settings.chatedp.analytics`).
- **RBAC Seeder Updates**: Added explicit `'is_superadmin' => true` flag on `Super Administrator` creation in `DefaultRbacSeeder` and `RbacSeeder`.
- **Action & Resource Expansion**: Added `access` and `test` to the `actions` table and registered `ai_settings`, `ai_metadata`, and `chatedp` system resources in `PermissionGeneratorService`.
- **Safe Migration**: Added migration `2026_08_30_163000_update_default_menus_and_permissions.php` to synchronize menu hierarchies and permissions without data loss.

### v1.4.1 (Bulk Operations & Entity Management in General Settings)
- **Batch Delete for Connections & Entities**: Added multi-select checkboxes with a "Select All" header toggle for both Connection Management and Entity Configuration tables in General Settings.
- **Dynamic Bulk Action Bar**: Interactive `Delete (Selected X)` action button that appears dynamically with real-time selection counts.
- **Backend Batch Delete Endpoints**: Implemented `POST /api/connections/batch-delete` and `POST /api/entities/batch-delete` with validation and transaction-safe cascading deletions for entity fields.
- **Unified Deletion Modal**: Refactored deletion flows to use the unified modal dialog (`#delete-modal`) instead of intrusive browser confirmation popups (`window.confirm`).
- **JavaScript Scope Fix**: Resolved a fatal `SyntaxError: Identifier 'csrfToken' has already been declared` by removing duplicate declarations in page scripts and utilizing the global layout token.

### v1.4.0 (RBAC Super Administrator Global Bypass)
- **Global Superadmin Authorization Bypass**:
  - Roles with `is_superadmin = true` and `is_active = true` now receive full access to all menus, protected routes, and endpoints without requiring manual permission assignment.
  - Automatically applies to newly created menus and query-repository reports in the future.
  - Avoided creating redundant `role_permissions` records for Super Administrator.
- **Permission Matrix UI Updates**: In `MenuManagementController::getPermissionMatrix()`, the `Super Administrator` column is automatically marked as permanently granted (`allowed: true`, checkboxes visually checked and disabled).
- **Core RBAC Methods Updated**: Enhanced `User::isSuperAdmin()`, `User::hasPermission()`, `User::hasAnyPermission()`, and `SuperAdminMiddleware` with optimized superadmin verification.

### v1.3.0 (P0 Security Hardening)
- **Security: System Utilities Hardening**: Removed hardcoded `deploy123` tokens. All `sys-*` routes are now strictly protected by standard RBAC middleware (`permission:settings.manage` and `superadmin`).
- **Security: Command Whitelisting**: The arbitrary `sys-artisan` command execution endpoint has been secured with a strict hardcoded whitelist (`storage:link`, `db:seed`, `optimize:clear`, `cache:clear`, `config:clear`, `route:clear`, `view:clear`).
- **Security: Production Debug Routes Removed**: Completely removed `/debug-sync`, `/test-save-filter`, `/debug-odata`, and `/dev/debug-logs` to eliminate production attack vectors.
- **Security: Query Repository Granular Permissions**: Refactored the monolithic `query_repository.manage` permission into distinct `view`, `create`, `update`, `delete`, and `execute` permissions to enforce separation of duties.
- **Security: AI Read-Only Enforcement**: Hardened the SQL validation engine (`QueryRepositoryService`) to actively block all destructive keywords (`INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `TRUNCATE`, `EXEC`, `CALL`, etc.). Queries must explicitly start with `SELECT` or `WITH`.
- **Security: PDO Parameter Binding**: Migrated SQL preview execution from insecure string interpolation (`addslashes`) to standard PDO positional bindings (`?`) to prevent SQL injection.
- **Feature: AI Data Source Integration**: Added a configuration flag (`is_analytics_source`) to the `connections` table, allowing Administrators to designate a specific database as the AI read-only execution source.
- **Performance/Security: AI Execution Limits**: Added `executeForAnalytics()` featuring strict server-side driver-agnostic row limits (`LIMIT 500` / `TOP 500`) and configurable execution timeouts (`PDO::ATTR_TIMEOUT`) to prevent resource exhaustion.

### v1.2.0 (Menu Management & Query Repository)
- **Bugfix: SQL Server Query Limit Issue**: Removed hardcoded `LIMIT 500` injections in the Query Repository UI to prevent `Incorrect syntax near '500'` errors when connected to Microsoft SQL Server endpoints.
- **Bugfix: Permission Matrix Hierarchy**: Refactored `MenuManagementController` to build recursive menu trees, ensuring that parent and child menus appear chronologically in the Permission Matrix (not alphabetically).
- **Bugfix: Role & Permission Sync**: Standardized the system role as `Super Administrator` across all `Seeders` and the `PermissionGeneratorService`. Addressed a fallback bug where menus without pivot records appeared unchecked despite possessing valid string-based permission keys.
- **Bugfix: Missing DataList Element**: Reinserted the `<datalist id="routeDataList">` in the `menu-management` UI to resolve a fatal Javascript Exception (`Cannot set properties of null`) when fetching dynamic route data.
- **Enhancement: Query Repository Permissions**: Registered `query_repository.view` in the dynamic permission generator, allowing it to be securely assigned via the Permission Matrix UI.
- **Enhancement: System Menu Ordering**: Restructured `DefaultMenuSeeder` and `MenuSeeder` to organize top-level menus into a more logical layout (`Report` appearing before `Synchronization`).
- **Feature Info: Date Range Filters**: Confirmed that the "Date Range" configuration automatically provisions `_from` and `_to` variables in the background, eliminating the need to select the same column twice in the UI.

### v1.1.0 (Core Synchronization & Scheduler)
- **Bugfix: Incremental Sync Scope**: Fixed a critical issue where scheduled syncs with custom filters were deleting older data in the local database. The engine now properly performs true incremental upserts via `NoteID` or `_row_hash` matching.
- **Performance: Forced IPv4**: Forced Guzzle HTTP client to use `CURLOPT_IPRESOLVE_V4` to prevent massive delays (1-2 seconds per request) caused by IPv6 DNS resolution fallback on Windows/Laragon environments.
- **Feature: Database-Driven Scheduler**: Upgraded the Auto-Schedule engine to use standard Cron Expressions (`* * * * *`) backed by a database table (`sync_schedules`). This replaces the old overlapping memory-heavy queues.
- **UI/UX: System Utilities**: Added a new "System Utilities" tab for quick deployment commands (`migrate`, `optimize`, `storage:link`), including clear copy-paste instructions for VPS Cronjob setup.
- **UI/UX: Dynamic Connection Validation**: The application now strictly validates connections before initiating syncs and warns users via `SweetAlert` if a connection is broken, rather than silently falling back to a default configuration.
- **UI/UX: Fixed Toggle Displays**: Added Eloquent Accessors to seamlessly translate Cron expressions back into human-readable Dropdowns for frequency and interval settings on the UI.

### v1.6.3 (Live Queue & Worker Monitoring in Sync History and Logs)
- **Live Worker & Queue Monitoring Dashboard**: Added real-time operational cards on `/sync-history` and `/logs` displaying running workers, pending queue backlog, and active distributed locks.
- **Queue Status API**: Added `GET /api/sync-jobs/queue-status` with live background polling every 4 seconds.
- **Instant Entity Unlock**: Integrated direct "Buka Kunci" action inside Sync History to release hung distributed locks on `sync_states`.
- **Bugfix: Undefined Relationship in SyncState**: Resolved `500 Internal Server Error` caused by calling undefined `schedule` relation; added `schedule()` alias and updated controller to use canonical `syncSchedule` and `entity_name`.

---

## Production / Hosting Deployment Guide (cPanel, LiteSpeed & Dedicated Hosting)

When deploying to production hosting environments such as **Jagoan Hosting**, **cPanel**, or servers utilizing **LiteSpeed Web Server / CloudLinux**:

### 1. Web Server Timeout Protection (`.htaccess`)
LiteSpeed Web Server automatically kills long-running HTTP scripts after 120 seconds unless explicitly exempted. The `public/.htaccess` file includes directives to prevent connection aborts and timeout drops during web-triggered synchronization:
```apache
RewriteEngine On

# Prevent LiteSpeed / CloudLinux Web Server timeout & abort for long-running sync/cron
RewriteRule .* - [E=noconntimeout:1]
RewriteRule .* - [E=noabort:1]
```

### 2. Recommended: Native cPanel Cron Jobs
Because system execution functions like `exec()` or `popen()` are often disabled in shared/cloud hosting environments for security (`disable_functions`), background queue workers cannot always be spawned automatically from web requests. Setting up native cPanel Cron Jobs is strongly recommended:

1. In cPanel, navigate to **Cron Jobs** (Tugas Cron).
2. Add a new cron job scheduled to run **Every Minute** (`* * * * *`):
   ```bash
   /usr/local/bin/php /home/USERNAME/public_html/artisan schedule:run >> /dev/null 2>&1
   ```
3. To process synchronization queues asynchronously without web server execution limits:
   ```bash
   /usr/local/bin/php /home/USERNAME/public_html/artisan queue:work --stop-when-empty --memory=1024 --timeout=3600 >> /dev/null 2>&1
   ```
*(Replace `/usr/local/bin/php` with your hosting's PHP CLI path and `/home/USERNAME/public_html` with your project root path).*

