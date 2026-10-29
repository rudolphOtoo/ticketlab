@extends('mail.layout')

@section('brand', $organizationName)

@section('content')
    <h1 style="margin:0 0 12px;font-size:18px;">{{ $enquiry['subject'] }}</h1>
    <p style="margin:0 0 4px;font-size:12px;color:#71717a;">From {{ $enquiry['name'] }} &lt;{{ $enquiry['email'] }}&gt;</p>
    <div style="margin:16px 0 0;padding:14px;border-radius:10px;background:#f4f4f5;white-space:pre-line;">{{ $enquiry['message'] }}</div>
    <p style="margin:16px 0 0;font-size:12px;color:#71717a;">Reply to this email to respond directly.</p>
@endsection
