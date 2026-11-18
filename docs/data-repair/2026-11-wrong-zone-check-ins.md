# Data repair: check-ins made at the wrong zone's gate

**Bug:** before the fix in `ScannerTerminal::validatePass()`, a zone usher or zone admin could check in a pass that
belonged to another zone (for example, a Cape Coast pass scanned at the Kumasi gate). The check-in was recorded, and
later scans showed "Duplicate scan".

**Fix:** a zone gate now refuses another zone's pass with "WRONG ZONE", records nothing, and logs
`Wrong-zone pass refused at the gate.` Organisation admins with no zone can still admit any pass.

## 1. Find affected check-ins (read-only)

This works on PostgreSQL (Neon), MySQL and SQLite. It changes nothing.

```sql
SELECT
    a.id               AS attendee_id,
    a.name             AS attendee,
    o.slug             AS organisation,
    pz.name            AS pass_zone,
    gz.name            AS scanner_zone,
    u.email            AS scanned_by,
    a.checked_in_at
FROM attendees a
JOIN organizations o ON o.id = a.organization_id
JOIN users u         ON u.id = a.checked_in_by
LEFT JOIN subdivisions pz ON pz.id = a.subdivision_id
LEFT JOIN subdivisions gz ON gz.id = u.subdivision_id
WHERE a.checked_in_at IS NOT NULL
  AND u.subdivision_id IS NOT NULL                                    -- org admins (no zone) may admit any zone
  AND (a.subdivision_id IS NULL OR a.subdivision_id <> u.subdivision_id)
ORDER BY o.slug, a.checked_in_at;
```

**Limitations:**
- The query compares the pass with the scanning user's **current** zone. If someone's zone was changed after they
  scanned, their rows can be false positives or can be missed. Check those by hand.
- Rows where `checked_in_by` is empty (for example, the account was deleted) cannot be attributed, so they are not listed.

Local:
```bash
php artisan tinker --execute="dump(DB::select(file_get_contents('docs/data-repair/wrong-zone-check-ins.sql')));"
```
Or paste the SQL into `php artisan db` (local) or the Neon SQL editor (production).

## 2. Repair (only after reviewing the list)

If a guest was really admitted at that gate, the record is a true account of what happened, and you may want to keep
it. If the check-in was a mistake and the guest must be able to check in at their own zone's gate, clear only the rows
you have reviewed:

```sql
-- Replace the ids with the attendee_id values you reviewed. Run inside a transaction and check the count first.
BEGIN;
UPDATE attendees SET checked_in_at = NULL, checked_in_by = NULL WHERE id IN (/* reviewed ids */);
-- Expect the row count to equal the number of ids; COMMIT if it does, otherwise ROLLBACK.
COMMIT;
```

This changes data, so nobody runs it for you. On production, take a Neon branch or snapshot first.
