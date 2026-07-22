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
