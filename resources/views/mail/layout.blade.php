<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:24px 12px;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e4e4e7;border-radius:14px;">
        <tr>
            <td style="padding:28px 28px 8px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#71717a;">
                @yield('brand', config('app.name', 'TicketLab'))
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 28px;font-size:14px;line-height:1.6;">
                @yield('content')
            </td>
        </tr>
    </table>
    <p style="max-width:560px;margin:16px auto 0;text-align:center;font-size:11px;color:#a1a1aa;">Sent by {{ config('app.name', 'TicketLab') }}</p>
</body>
</html>
