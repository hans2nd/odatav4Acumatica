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

### v1.4.9 (RBAC Granularity & System Utilities Updates)
- **Security & Authorization**: Menerapkan arsitektur RBAC granular yang lebih ketat di seluruh modul aplikasi.
  - Memisahkan izin `query_builder.view` dari `manage` agar user dengan akses *read-only* tetap dapat mengeksekusi *query* yang telah dibuat.
  - Memperkenalkan izin `sync_engine.view` untuk halaman Sinkronisasi Riwayat dan Scheduler agar user auditor dapat memantau log tanpa bisa menjalankan sinkronisasi manual.
  - Mengunci Endpoint Sinkronisasi (`SyncController`) agar mewajibkan izin eksklusif per entitas (misalnya `customers.sync`), mencegah user tanpa izin sinkronisasi entitas tertentu untuk menjalankannya.
  - Mengunci Endpoint Penghapusan Log Massal agar membutuhkan izin spesifik `logs.delete`.
- **Feature: Interactive Seeder Selection**: Memodifikasi fitur *Run Seeder* di halaman System Utilities dengan pop-up (modal) interaktif. Pengguna sekarang dapat memilih dan mengeksekusi kelas seeder tertentu (misalnya `DefaultMenuSeeder` atau `DefaultRbacSeeder`) secara individual untuk mencegah duplikasi data saat migrasi inkremental di *production*.

### v1.4.8 (Queue Stability & Background Sync Hardening)
- **Enhancement: Unique Queue Jobs**: Mengimplementasikan interface `ShouldBeUnique` dan method `uniqueId()` pada `SyncEntityJob`. Hal ini menjamin bahwa antrean sinkronisasi yang duplikat untuk entitas yang sama akan otomatis ditolak oleh Laravel jika job sebelumnya masih berjalan. Update ini mencegah penumpukan antrean (queue buildup), proses yang tumpang tindih, dan resource VPS (RAM/CPU) yang hang di production.

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
