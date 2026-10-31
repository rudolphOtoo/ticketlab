<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Session Ledger Audit Report — {{ $ledger->session_name }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1a1a1a;
            margin: 0;
            padding: 24px;
            font-size: 12px;
            line-height: 1.5;
        }
        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .org-name {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-title {
            font-size: 14px;
            color: #475569;
            margin-top: 4px;
        }
        .badge {
            display: inline-block;
            background: #dcfce7;
            color: #166534;
            font-size: 10px;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .meta-grid {
            width: 100%;
            margin-bottom: 24px;
        }
        .meta-grid td {
            vertical-align: top;
            padding: 4px 8px 4px 0;
        }
        .meta-label {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .meta-value {
            font-size: 12px;
            font-weight: 600;
            color: #0f172a;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 10px;
            text-transform: uppercase;
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid #cbd5e1;
        }
        table.data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .text-right {
            text-align: right;
        }
        .font-mono {
            font-family: 'Courier New', Courier, monospace;
        }
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8fafc;
            border-top: 2px solid #cbd5e1;
        }
        .footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
            margin-top: 30px;
            font-size: 10px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>

    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="org-name">{{ $organization?->name ?? 'TicketLab Operations' }}</div>
                    <div class="report-title">Financial Closeout & Attendance Audit Statement</div>
                </td>
                <td class="text-right">
                    <span class="badge">OFFICIAL AUDIT LEDGER</span>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-grid">
        <tr>
            <td style="width: 25%;">
                <div class="meta-label">Session Name</div>
                <div class="meta-value">{{ $ledger->session_name }}</div>
            </td>
            <td style="width: 25%;">
                <div class="meta-label">Event</div>
                <div class="meta-value">{{ $event?->title ?? 'Summit 2026' }}</div>
            </td>
            <td style="width: 25%;">
                <div class="meta-label">Opened At</div>
                <div class="meta-value">{{ $ledger->opened_at ? $ledger->opened_at->format('M d, Y · H:i:s') : 'N/A' }}</div>
            </td>
            <td style="width: 25%;">
                <div class="meta-label">Locked At</div>
                <div class="meta-value">{{ $ledger->locked_at ? $ledger->locked_at->format('M d, Y · H:i:s') : 'Open' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="meta-label">Closed By</div>
                <div class="meta-value">{{ $ledger->lockedByUser?->name ?? 'System Administrator' }}</div>
            </td>
            <td>
                <div class="meta-label">Venue</div>
                <div class="meta-value">{{ $event?->venue ?? 'Main Auditorium' }}</div>
            </td>
            <td>
                <div class="meta-label">Status</div>
                <div class="meta-value">{{ $ledger->isLocked() ? 'LOCKED & FROZEN' : 'ACTIVE / ACCUMULATING' }}</div>
            </td>
            <td>
                <div class="meta-label">Generated</div>
                <div class="meta-value">{{ now()->format('Y-m-d H:i:s T') }}</div>
            </td>
        </tr>
    </table>

    <div class="summary-box">
        <h3 style="margin: 0 0 12px 0; font-size: 12px; text-transform: uppercase; color: #334155;">Financial Reconciliation</h3>
        <table class="data-table" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th>Revenue Channel</th>
                    <th>Settlement Routing</th>
                    <th class="text-right">Total Amount (GHS)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Online Paystack Collections</strong></td>
                    <td>Direct Mobile Money / Card Subaccounts</td>
                    <td class="text-right font-mono">GHS {{ number_format((float) $ledger->total_online_momo, 2) }}</td>
                </tr>
                <tr>
                    <td><strong>Gate Walk-In Cash Tender</strong></td>
                    <td>Physical Cash Desk Vault / Box</td>
                    <td class="text-right font-mono">GHS {{ number_format((float) $ledger->total_cash, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td colspan="2"><strong>GROSS SESSION REVENUE</strong></td>
                    <td class="text-right font-mono" style="font-size: 13px; color: #0f172a;">
                        GHS {{ number_format((float) ($ledger->total_online_momo + $ledger->total_cash), 2) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3 style="margin: 20px 0 8px 0; font-size: 12px; text-transform: uppercase; color: #334155;">Gate Attendance & Turnout Summary</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Total Verified Admissions</th>
                <th>Total Seated Capacity</th>
                <th class="text-right">Turnout Rate</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong class="font-mono">{{ number_format($ledger->total_attendees) }}</strong> Attendees</td>
                <td class="font-mono">{{ number_format($capacityTotal ?? 1000) }} Expected Capacity</td>
                <td class="text-right font-mono">
                    {{ $capacityTotal > 0 ? round(($ledger->total_attendees / $capacityTotal) * 100, 1) : 0 }}%
                </td>
            </tr>
        </tbody>
    </table>

    @if(!empty($zoneBreakdown))
        <h3 style="margin: 20px 0 8px 0; font-size: 12px; text-transform: uppercase; color: #334155;">Subdivision Throughput Breakdown</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Subdivision</th>
                    <th>Code</th>
                    <th class="text-right">Admitted Count</th>
                </tr>
            </thead>
            <tbody>
                @foreach($zoneBreakdown as $zone)
                    <tr>
                        <td><strong>{{ $zone['name'] }}</strong></td>
                        <td class="font-mono">{{ $zone['code'] }}</td>
                        <td class="text-right font-mono">{{ number_format($zone['admitted']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <div>TicketLab System Ledger Audit Signature: <span class="font-mono">{{ hash('sha256', (string) $ledger->id . (string) $ledger->locked_at . (string) $ledger->total_cash) }}</span></div>
        <div class="text-right">Page 1 of 1 — Confidential Financial Audit Document</div>
    </div>

</body>
</html>
