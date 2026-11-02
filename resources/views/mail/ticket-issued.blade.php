@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:20px;">Your passes are confirmed</h1>
    <p style="margin:0 0 12px;">Hi {{ $order->customer_name }}, thank you for your order for <strong>{{ $eventTitle }}</strong>.</p>
    <p style="margin:0 0 12px;">Your {{ $order->attendees->count() === 1 ? 'pass is' : $order->attendees->count() . ' passes are' }} attached as a PDF. Each QR code admits one person at the gate.</p>
    <p style="margin:20px 0;">
        <a href="{{ $ticketUrl }}" style="display:inline-block;padding:10px 18px;border-radius:10px;background:#18181b;color:#ffffff;text-decoration:none;font-weight:600;font-size:13px;">View passes online</a>
    </p>
    <p style="margin:0;font-size:12px;color:#71717a;">Order reference: <span style="font-family:monospace;">{{ $order->reference }}</span></p>
@endsection
