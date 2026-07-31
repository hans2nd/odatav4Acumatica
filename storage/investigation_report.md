# Sync Engine Investigation Report

## Missing Logs & Execution Silence

The primary reason why the synchronization appears to "do nothing" after logging `Starting…` is because of an unlogged early return in `SyncService.php`.

If the source (OData) returns **0 records**, the loop terminates and hits this block:

```php
// SyncService.php:390
if ($fetchedCount === 0) {
    $this->syncStateService->updateWatermark($schedule->id, null, null);
    $this->syncStateService->completeSynchronization($schedule->id);
    
    $log->update([...]);
    if ($syncJobId) { ... }
    
    return $log; // <--- SILENT RETURN
}
```
Because there is no `Log::info()` or `Log::warning()` here, the log output stops dead at `Starting...`, providing no evidence that the sync actually finished successfully but found no data.

## Why executeUpsert() is Skipped

Even when records are fetched, `executeUpsert()` is frequently skipped due to specific `if()` conditions in `SyncService.php`.

### Condition 1: Non-Incremental Syncs
If the destination table lacks data or doesn't have a `lastmodifieddatetime` column, `$isIncremental` evaluates to `false`.

```php
// SyncService.php:266
if ($isIncremental) {
    $inserted = $this->upsertTableData(...);
} else {
    // replaceTableData calls executeInsert(), skipping executeUpsert() entirely.
    $inserted = $this->replaceTableData(...);
}
```

### Condition 2: Missing 'id' Key (Case-Sensitive)
When `upsertTableData` is called, it checks for a very specific primary key structure:

```php
// SyncService.php:563
$hasId = isset($cleanData[0]['id']); // EXACT case-sensitive match for 'id'

if ($hasId) {
    // ...
    $batchStrategy->executeUpsert(...);
} else {
    // If the primary key is 'ID', 'NoteID', etc., $hasId is false.
    // It falls back to bulkInsertData, which calls executeInsert()
    $insertedOrUpdated = $this->bulkInsertData(...);
}
```
If the API returns a primary key named `ID` (uppercase) or any other name, `executeUpsert()` is skipped, falling back to a combination of delete and `executeInsert()`.

## Missing Instrumentation in executeInsert()
The user-provided STEP 4 through STEP 9 logging only exists in `executeUpsert()`. Because of the conditions above, the engine often falls back to `executeInsert()`.

```php
// SqlServerBatchStrategy.php:78
public function executeInsert(string $connectionName, string $tableName, array $chunk): int
{
    DB::connection($connectionName)->table($tableName)->insert($chunk); // Unlogged!
    return count($chunk);
}
```
Since this method was not instrumented, any insertion activity happening here is completely invisible.

## Proposed Action Plan
1. **Instrument executeInsert()**: Add STEP logs to `executeInsert()` in both `SqlServerBatchStrategy` and `MySqlBatchStrategy`.
2. **Instrument SyncService.php**: 
   - Add explicit logging when 0 records are fetched.
   - Add explicit logging to show whether `replaceTableData`, `bulkInsertData`, or `upsertTableData` is chosen, and why.
3. **Execute test_sync.php**: Since the agent environment is currently experiencing a fatal terminal sandbox error (`opening NUL for ACL write: Access is denied`), **you will need to run `php test_sync.php` on your terminal** after I apply the instrumentation to view the actual runtime evidence.
