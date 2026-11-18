@extends('mail.layout')

@section('brand', $order->organization->name)

@section('content')
    <h1 style="margin:0 0 12px;font-size:20px;">Choose your {{ $label }}</h1>
    <p style="margin:0 0 12px;">Hi {{ $order->customer_name }}, your {{ $order->attendees->count() === 1 ? 'pass' : 'passes' }} for <strong>{{ $eventTitle }}</strong> {{ $order->attendees->count() === 1 ? 'does' : 'do' }} not have a {{ $label }} yet.</p>
    <p style="margin:0 0 12px;">Each {{ $label }} has its own gate, so the ushers can only admit you once you have chosen. Open your ticket page and pick the {{ $label }} you will attend. You can only choose once.</p>
    <p style="margin:20px 0;">
        <a href="{{ $ticketUrl }}" style="display:inline-block;padding:10px 18px;border-radius:10px;background:#18181b;color:#ffffff;text-decoration:none;font-weight:600;font-size:13px;">Choose my {{ $label }}</a>
    </p>
    <p style="margin:0;font-size:12px;color:#71717a;">Order reference: <span style="font-family:monospace;">{{ $order->reference }}</span></p>
@endsection
