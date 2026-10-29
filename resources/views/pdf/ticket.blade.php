<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tickets {{ $order->reference }}</title>
    <style>
        @page { margin: 28px; }
        body { font-family: DejaVu Sans, sans-serif; color: #18181b; font-size: 11px; }
        .pass { border: 1px solid #d4d4d8; border-radius: 12px; padding: 22px; page-break-after: always; }
        .pass:last-child { page-break-after: auto; }
        .brand { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #71717a; }
        .accent { height: 6px; border-radius: 3px; background: {{ $accent }}; margin: 10px 0 16px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #71717a; }
        .label { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #a1a1aa; margin-top: 12px; }
        .value { font-size: 12px; font-weight: bold; }
        .qr { text-align: center; margin-top: 18px; }
        .qr img { width: 210px; height: 210px; }
        .hash { font-family: DejaVu Sans Mono, monospace; font-size: 7px; color: #a1a1aa; word-break: break-all; text-align: center; }
    </style>
</head>
<body>
    @foreach ($order->attendees as $attendee)
        <div class="pass">
            <div class="brand">{{ $organizationName }} · Admission Pass</div>
            <div class="accent"></div>
            <h1>{{ $event?->title ?? 'Event Pass' }}</h1>
            <div class="muted">
                {{ $event?->start_at?->format('l, F j, Y · g:i A') }}@if ($event?->venue) · {{ $event->venue }}@endif
            </div>

            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="50%">
                        <div class="label">Attendee</div>
                        <div class="value">{{ $attendee->name }}</div>
                    </td>
                    <td width="50%">
                        <div class="label">Ticket</div>
                        <div class="value">{{ $attendee->ticketType?->name }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Order</div>
                        <div class="value">{{ $order->reference }}</div>
                    </td>
                    <td>
                        <div class="label">Pass</div>
                        <div class="value">{{ $loop->iteration }} of {{ $loop->count }}</div>
                    </td>
                </tr>
            </table>

            <div class="qr">
                <img src="{{ $qrCodes[$attendee->id] }}" alt="QR pass">
            </div>
            <div class="hash">{{ $attendee->qr_code_hash }}</div>
        </div>
    @endforeach
</body>
</html>
