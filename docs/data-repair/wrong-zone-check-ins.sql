-- Read-only. See 2026-11-wrong-zone-check-ins.md
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
