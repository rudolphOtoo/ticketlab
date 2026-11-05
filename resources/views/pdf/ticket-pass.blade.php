@php($initials = $organization->initials())
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tickets {{ $order->reference }}</title>
    {{-- DomPDF renders CSS 2.1: tables for layout, solid fills (no gradients), and only embedded images. --}}
    <style>
        @page { size: A4 portrait; margin: 0; }
        html, body { margin: 0; padding: 0; background: #ffffff; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #18181b; font-size: 12px; line-height: 1.4; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; padding: 0; }

        .page { padding-top: 290px; page-break-after: always; }
        /* DomPDF has no flex centring: the pass is about 540px tall, so this offset centres it on the 1123px A4 page. */
        .page.last { page-break-after: auto; }
        .card { width: 560px; margin: 0 auto; border: 1px solid #e4e4e7; border-radius: 22px; background: #ffffff; }

        .brand { padding: 18px 26px; border-bottom: 1px solid #e4e4e7; }
        .initials { width: 38px; height: 38px; border-radius: 19px; background: #18181b; color: #ffffff; font-size: 13px; font-weight: bold; text-align: center; vertical-align: middle; line-height: 1; }
        .eyebrow { font-size: 8px; letter-spacing: 2px; text-transform: uppercase; color: #71717a; }
        .org { font-size: 13px; font-weight: bold; color: #18181b; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 10px; font-size: 10px; font-weight: bold; background: #d1fae5; color: #065f46; }

        .hero { background: #18181b; color: #ffffff; padding: 30px 28px 26px; }
        .hero .kicker { font-size: 9px; letter-spacing: 3px; text-transform: uppercase; color: #a7f3d0; }
        .hero h1 { margin: 8px 0 0; font-size: 26px; line-height: 1.2; font-weight: bold; color: #ffffff; }
        .hero .when { margin-top: 8px; font-size: 12px; color: #d4d4d8; }
        .hero .ref { display: inline-block; margin-top: 16px; padding: 5px 12px; border-radius: 10px; background: #3f3f46; font-family: 'DejaVu Sans Mono', monospace; font-size: 10px; letter-spacing: 1px; color: #ffffff; }
        .accent { height: 6px; background: {{ $accent }}; }

        .body { padding: 26px 28px 10px; }
        .holder { margin-top: 4px; font-size: 21px; font-weight: bold; color: #18181b; }
        .email { margin-top: 2px; font-size: 12px; color: #52525b; }
        .facts { margin-top: 18px; }
        .facts td { padding-bottom: 14px; }
        .facts td.wide { width: 60%; }
        .label { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #71717a; }
        .value { margin-top: 3px; font-size: 12px; font-weight: bold; color: #18181b; }
        .hash { margin-top: 3px; font-family: 'DejaVu Sans Mono', monospace; font-size: 8px; color: #71717a; word-wrap: break-word; }

        .qr { width: 190px; border: 1px solid #e4e4e7; border-radius: 16px; padding: 10px; text-align: center; }
        .qr img { width: 170px; height: 170px; }
        .qr .scan { margin-top: 6px; font-size: 8px; letter-spacing: 2px; text-transform: uppercase; color: #71717a; }

        .footer { padding: 14px 28px 22px; font-size: 10px; color: #71717a; text-align: center; }
    </style>
</head>
<body>
    @foreach ($order->attendees as $attendee)
        <div class="page {{ $loop->last ? 'last' : '' }}">
            <div class="card">
                <div class="brand">
                    <table>
                        <tr>
                            <td style="width: 50px; vertical-align: middle;"><table style="width: 38px;"><tr><td class="initials">{{ $initials }}</td></tr></table></td>
                            <td style="vertical-align: middle;">
                                <div class="eyebrow">Digital pass</div>
                                <div class="org">{{ $organization->name }}</div>
                            </td>
                            <td style="text-align: right; vertical-align: middle;"><span class="badge">Confirmed</span></td>
                        </tr>
                    </table>
                </div>

                <div class="hero">
                    <div class="kicker">You are in</div>
                    <h1>{{ $event?->title ?? 'Event ticket' }}</h1>
                    @if ($event)
                        <div class="when">
                            {{ $event->start_at->format('D, j M Y · g:i A') }}@if ($event->venue) · {{ $event->venue }}@endif
                        </div>
                    @endif
                    <div class="ref">{{ $order->reference }}</div>
                </div>
                <div class="accent"></div>

                <div class="body">
                    <table>
                        <tr>
                            <td style="padding-right: 22px;">
                                <div class="label">Ticket holder</div>
                                <div class="holder">{{ $attendee->name }}</div>
                                <div class="email">{{ $attendee->email }}</div>

                                <table class="facts">
                                    <tr>
                                        <td class="wide">
                                            <div class="label">Ticket tier</div>
                                            <div class="value">{{ $attendee->ticketType?->name ?? 'General admission' }}</div>
                                        </td>
                                        <td>
                                            <div class="label">{{ $organization->subdivision_label }}</div>
                                            <div class="value">{{ $attendee->subdivision?->name ?? 'General admission' }}</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="label">Issued</div>
                                            <div class="value">{{ $order->paid_at?->format('j M Y · g:i A') ?? 'Pending' }}</div>
                                        </td>
                                        <td>
                                            <div class="label">Pass</div>
                                            <div class="value">{{ $loop->iteration }} of {{ $loop->count }}</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td style="width: 192px;">
                                <div class="qr">
                                    <img src="{{ $qrCodes[$attendee->id] }}" alt="Admission QR code">
                                    <div class="scan">Scan at the gate</div>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div class="label" style="margin-top: 4px;">Pass ID</div>
                    <div class="hash">{{ $attendee->qr_code_hash }}</div>
                </div>

                <div class="footer">Present this QR code at the entrance. Each pass is unique and can only be scanned once.</div>
            </div>
        </div>
    @endforeach
</body>
</html>
