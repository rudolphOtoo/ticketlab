@extends('mail.layout')

@section('brand', $organizationName)

@section('content')
    <p style="margin:0 0 12px;">Hi {{ $recipientName }},</p>
    <div style="margin:0 0 16px;white-space:pre-line;">{{ $broadcast->body }}</div>
    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:16px 0 0;border-top:1px solid #e4e4e7;">
        <tr>
            <td style="padding-top:12px;font-size:12px;color:#71717a;">
                <strong style="color:#18181b;">{{ $event->title }}</strong><br>
                {{ $event->start_at->format('l, F j, Y · g:i A') }}@if ($event->venue)<br>{{ $event->venue }}@endif
            </td>
        </tr>
    </table>
@endsection
