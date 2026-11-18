# Data repair: paid passes with no zone

**Problem:** passes sold before a zone was required have `subdivision_id = NULL`. Every zone gate now refuses them
("WRONG ZONE: this pass is not assigned to a zone"), so they must be given a zone before the event.

## 1. Size of the problem (read-only)

This works on PostgreSQL (Neon), MySQL and SQLite. It changes nothing. It is also saved as
[`zoneless-paid-passes.sql`](zoneless-paid-passes.sql).

```sql
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
```

## 2. Fix it (in this order)

1. **Buyers choose themselves (most passes).** Do a dry run first. It lists the orders and sends nothing:
   ```bash
   php artisan passes:request-zone-choice --organization=<org-slug>
   ```
   Then send. Each buyer gets one email, their ticket page shows "Choose your zone", and the choice is locked once made:
   ```bash
   php artisan passes:request-zone-choice --organization=<org-slug> --send
   ```
   Orders that are already emailed are skipped unless you add `--resend`. The queue worker must be running (it is
   on the scheduler) for the emails to go out.
2. **Organisation admin handles the rest.** On the Attendee Roster, filter by "No zone yet", tick the passes, choose
   a zone and click **Assign zone**. Single passes can also be assigned from a pass's detail panel. Zone admins
   cannot do this.
3. **Settle money by hand.** Assigning a zone does **not** move money that has already settled (it went to the
   main account). Admin → **Zone Reassignments** lists every affected order and the ticket value each zone is
   owed. Every assignment, by an admin or by the buyer, is in the audit trail on that page.
