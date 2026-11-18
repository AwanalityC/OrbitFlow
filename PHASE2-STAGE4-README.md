# OrbitFlow — Phase 2 Stage 4: Borrowing Concurrency

This patch hardens physical-unit allocation so a unit cannot be actively assigned to two borrowings.

## What changed

- Adds `active_equipment_unit_id` to `borrowing_item_equipment_unit`.
- Backfills that field for all existing allocations where `returned_at` is NULL.
- Adds a unique index (`bi_active_unit_unique`) so one physical unit can have at most one active allocation at the database level.
- Refuses to run the migration if historical data already has multiple active allocations for the same unit. It reports the affected unit IDs rather than silently choosing one.
- Approval continues to run inside a database transaction and locks the borrowing and candidate unit rows.
- Approval retries deadlocks up to three times and processes equipment items in a consistent order.
- Approval excludes units that already have an active allocation, even if their visible status was accidentally changed back to `Tersedia`.
- Returns release the active allocation key (`active_equipment_unit_id = NULL`) so a returned unit can be borrowed again.
- Availability counts in student/admin pages and the borrowing form use the same guarded availability scope.
- Preserves the existing rule: `Baik` first, `Rusak Ringan` second; `Rusak Berat` and `Hilang` are not borrowable. Pending requests still do not reserve stock.

## Before applying

1. Export the `orbitflow` database through phpMyAdmin (Export → Quick → SQL).
2. Do not run `php artisan migrate:fresh`.
3. Optional pre-check in phpMyAdmin:

```sql
SELECT equipment_unit_id, COUNT(*) AS active_allocations
FROM borrowing_item_equipment_unit
WHERE returned_at IS NULL
GROUP BY equipment_unit_id
HAVING COUNT(*) > 1;
```

This query should return zero rows. If it returns any rows, stop and review those allocations before running the migration. The migration itself performs the same check and will stop safely if duplicates exist.

## Apply

Run `APPLY-ORBITFLOW-PHASE2-STAGE4.ps1` from the folder containing this script and the patch ZIP. The script backs up all replaced files to a timestamped Desktop folder and does not run migrations automatically.

Then, in the project directory:

```powershell
php artisan migrate
php artisan view:clear
php artisan route:clear
php artisan test
```

No `.env` file is included or replaced.

## Automated tests

Seven new feature tests cover:

- The unique active-allocation database constraint.
- Blocking a stale `Tersedia` unit that already has an active claim.
- Rollback of all allocations if one requested equipment type is unavailable.
- Releasing an active claim on return and allowing the unit to be borrowed again.
- Preventing approval of the same borrowing twice.
- Excluding active-claimed units from student availability views.
- Rejecting a new request quantity based on a unit with an active claim.

The existing suite had 79 tests; the expected total after this patch is 86. Run the tests locally to confirm.

## Important concurrency-test limitation

The automated suite uses SQLite as configured in `phpunit.xml`. SQLite does not reproduce MySQL/InnoDB row-level `SELECT ... FOR UPDATE` behavior. The unique index verifies the database-level invariant in automated tests, but the real two-admin race test must be done against the MySQL/MariaDB database used by the local application. Follow the manual test kit for that check.
