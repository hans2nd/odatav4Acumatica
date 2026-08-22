# PL Sync Data Warehouse

A powerful synchronization engine built on Laravel to bridge Acumatica ERP (OData V4) and local SQL Server/MySQL databases.

## Key Features
- **Dynamic OData to SQL Mapping**: Easily map OData entities to relational database tables.
- **Incremental & Full Sync**: Supports fast incremental backups using `LastModifiedDateTime` and full replacements.
- **Database-Driven Scheduler**: Robust cron-based background synchronization to prevent overlapping syncs.
- **Multi-Database Support**: Connects to SQL Server, MySQL, Firebird, and other relational endpoints.
- **System Utilities**: Built-in UI to manage deployments, cache, and queue workers dynamically.

## Changelog

### v1.1.0 (Recent Updates)
- **Bugfix: Incremental Sync Scope**: Fixed a critical issue where scheduled syncs with custom filters were deleting older data in the local database. The engine now properly performs true incremental upserts via `NoteID` or `_row_hash` matching.
- **Performance: Forced IPv4**: Forced Guzzle HTTP client to use `CURLOPT_IPRESOLVE_V4` to prevent massive delays (1-2 seconds per request) caused by IPv6 DNS resolution fallback on Windows/Laragon environments.
- **Feature: Database-Driven Scheduler**: Upgraded the Auto-Schedule engine to use standard Cron Expressions (`* * * * *`) backed by a database table (`sync_schedules`). This replaces the old overlapping memory-heavy queues.
- **UI/UX: System Utilities**: Added a new "System Utilities" tab for quick deployment commands (`migrate`, `optimize`, `storage:link`), including clear copy-paste instructions for VPS Cronjob setup.
- **UI/UX: Dynamic Connection Validation**: The application now strictly validates connections before initiating syncs and warns users via `SweetAlert` if a connection is broken, rather than silently falling back to a default configuration.
- **UI/UX: Fixed Toggle Displays**: Added Eloquent Accessors to seamlessly translate Cron expressions back into human-readable Dropdowns for frequency and interval settings on the UI.

### v1.2.0 (Menu Management & Query Repository)
- **Bugfix: SQL Server Query Limit Issue**: Removed hardcoded `LIMIT 500` injections in the Query Repository UI to prevent `Incorrect syntax near '500'` errors when connected to Microsoft SQL Server endpoints.
- **Bugfix: Permission Matrix Hierarchy**: Refactored `MenuManagementController` to build recursive menu trees, ensuring that parent and child menus appear chronologically in the Permission Matrix (not alphabetically).
- **Bugfix: Role & Permission Sync**: Standardized the system role as `Super Administrator` across all `Seeders` and the `PermissionGeneratorService`. Addressed a fallback bug where menus without pivot records appeared unchecked despite possessing valid string-based permission keys.
- **Bugfix: Missing DataList Element**: Reinserted the `<datalist id="routeDataList">` in the `menu-management` UI to resolve a fatal Javascript Exception (`Cannot set properties of null`) when fetching dynamic route data.
- **Enhancement: Query Repository Permissions**: Registered `query_repository.view` in the dynamic permission generator, allowing it to be securely assigned via the Permission Matrix UI.
- **Enhancement: System Menu Ordering**: Restructured `DefaultMenuSeeder` and `MenuSeeder` to organize top-level menus into a more logical layout (`Report` appearing before `Synchronization`).
- **Feature Info: Date Range Filters**: Confirmed that the "Date Range" configuration automatically provisions `_from` and `_to` variables in the background, eliminating the need to select the same column twice in the UI.
