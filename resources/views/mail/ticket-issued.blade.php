@extends('mail.layout')

@section('brand', $order->organization->name)

@section('content')
    <h1 style="margin:0 0 12px;font-size:20px;">Your passes are confirmed</h1>
    <p style="margin:0 0 12px;">Hi {{ $order->customer_name }}, thank you for your order for <strong>{{ $eventTitle }}</strong>.</p>
    @if ($venue)
        <p style="margin:0 0 12px;" data-zone-venue>
            @if ($venue->zone)<strong>{{ $order->organization->subdivision_label }}:</strong> {{ $venue->zone->name }}<br>@endif
            <strong>When:</strong> {{ $venue->startAt->format('D, j M Y · g:i A') }}<br>
            @if ($venue->place())<strong>Where:</strong> {{ $venue->place() }}@if ($venue->mapUrl) (<a href="{{ $venue->mapUrl }}">map</a>)@endif @endif
        </p>
    @endif
    <p style="margin:0 0 12px;">Your {{ $order->attendees->count() === 1 ? 'pass is' : $order->attendees->count() . ' passes are' }} attached as a PDF. Each QR code admits one person at the gate.</p>
    <p style="margin:20px 0;">
        <a href="{{ $ticketUrl }}" style="display:inline-block;padding:10px 18px;border-radius:10px;background:#18181b;color:#ffffff;text-decoration:none;font-weight:600;font-size:13px;">View passes online</a>
    </p>
    <p style="margin:0;font-size:12px;color:#71717a;">Order reference: <span style="font-family:monospace;">{{ $order->reference }}</span></p>
@endsection
