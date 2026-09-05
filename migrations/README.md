# Migrations

The `University` schema ships with no foreign keys at all and eight tables
with no primary key. These scripts add them.

Run in order:

```bash
# 1. See what's wrong. Read-only, changes nothing.
mysql -h 127.0.0.1 -u root University --table < migrations/001_preflight_checks.sql

# 2. Back up. DDL commits implicitly, so this is the only way back.
mysqldump -h 127.0.0.1 -u root --set-gtid-purged=OFF --single-transaction \
  --routines --triggers --databases University > University_before_keys.sql

# 3. Apply.
mysql -h 127.0.0.1 -u root University < migrations/002_add_keys.sql
```

`003_rollback.sql` drops the constraints again. It does **not** restore the
rows deleted in Phase 2 — that needs the dump from step 2.

## What 002 does

| Phase | Change |
|---|---|
| 1 | Reconciles three column types that made a foreign key impossible |
| 2 | Removes or repairs the ~2,000 rows that would reject a constraint |
| 3 | Adds 6 primary keys (2 keyless tables are scratch — drop is commented out) |
| 4 | Adds 89 foreign keys |
| 5 | Drops the duplicate `Users.Email_2` index |
| 6 | Verifies: expects 89 foreign keys, 0 tables without a primary key |

`004` corrects seven ON DELETE rules, `005` adds soft delete, and `006`
creates the two stored procedures the PHP calls.

## Always dump with --routines

`mysqldump` includes triggers by default but **not** stored procedures.
A dump taken without `--routines` silently loses `GenerateUserEmail` and
`UpdateDegreeAudit`, and user creation then fails on a fresh install:

```bash
mysqldump -h 127.0.0.1 -u root --set-gtid-purged=OFF --single-transaction \
  --routines --triggers --databases University > University.sql
```

## Effect on the application

`DeleteUsers.php` deletes from `Users` alone and cleans up nothing else,
which is how the orphans got there. The identity chain is `ON DELETE
CASCADE`, so that statement starts working correctly — but it will now
also remove the user's enrollments, attendance and degree audit.

To block deletion instead, switch the four constraints marked
`[TRANSCRIPT]` in Phase 4 to `ON DELETE RESTRICT`.

## Verified

Applied to a clone of the live database on 2026-09-04: 89 foreign keys
added, zero orphans across all 93 checked references, rollback returns to
zero, and re-applying after rollback succeeds.
