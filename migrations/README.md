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

`004` corrects seven ON DELETE rules, `005` adds soft delete, `006`
creates the two stored procedures the PHP calls, `007` rebuilds
`Student.StudentType`, and `008` retires `StudentHistory`.

## 008 changes the numbers, not just the schema

`StudentHistory` held 6,696 rows against `StudentEnrollment`'s 31,056,
every one of them mirrored by a `COMPLETED` enrolment with the same
grade — a strict subset, recorded twice. The degree audit read the
smaller copy, so 8,931 graded courses and 865 students were invisible to
it.

`008` points `UpdateDegreeAudit` at `StudentEnrollment WHERE Status =
'COMPLETED'` and drops the table. `009` then rebuilds every stored
`DegreeAudit` row, because `008` replaces the procedure without calling
it — immediately after `008`, 1,081 of 1,602 rows still held the figure
computed from `StudentHistory`. Run both:

```bash
# Back up first. DROP TABLE commits implicitly.
mysqldump -h 127.0.0.1 -u root --set-gtid-purged=OFF --single-transaction \
  --routines --triggers --databases University > University_before_008.sql

mysql -h 127.0.0.1 -u root University < migrations/008_retire_studenthistory.sql
mysql -h 127.0.0.1 -u root University < migrations/009_recompute_degree_audits.sql
```

`008` refuses to run if any `StudentHistory` row is not mirrored by a
`COMPLETED` enrolment carrying the same grade, and stops before the drop
rather than after it. `009` refuses to run against the pre-`008`
procedure, and is safe to re-run — the upsert converges.

Then regenerate `University.sql` with `--routines`, or the recreated
procedure is lost.

### Verified

Applied to a clone of the live database on 2026-09-06. Of 1,602
students, 475 were unchanged, 1,127 gained credits and 838 went from
zero to a real figure. **No student lost credits**, which is what a
strict subset predicts. GPAs stayed inside 0.00–4.00, no
`Credits_Remaining` went negative, and one student was checked by hand
against their enrolment rows (24 credits, 2.70 GPA — matched). The guard
was tested by diverging a single grade: it raised `SQLSTATE 45000` and
left the table in place.

Both then applied to `University` itself the same day. Audits carrying
credits went from 521 to 1,358 — matching the 1,358 students who have a
`COMPLETED` enrolment — and the average GPA from 1.037 to 2.408, the
same figure the clone produced. All four post-conditions in `009`
returned zero.

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
