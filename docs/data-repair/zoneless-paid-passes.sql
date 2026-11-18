-- Read-only. See 2026-11-zoneless-paid-passes.md
SELECT
    o.slug                                   AS organisation,
    e.title                                  AS event,
    COUNT(a.id)                              AS zoneless_passes,
    COUNT(DISTINCT a.order_id)               AS orders,
    SUM(CASE WHEN a.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS already_checked_in
FROM attendees a
JOIN orders ord      ON ord.id = a.order_id AND ord.payment_status = 'paid'
JOIN ticket_types tt ON tt.id = a.ticket_type_id
JOIN events e        ON e.id = tt.event_id
JOIN organizations o ON o.id = a.organization_id
WHERE a.subdivision_id IS NULL
GROUP BY o.slug, e.title
ORDER BY zoneless_passes DESC;
