@extends('errors.layout')

@section('title', __('Authentication Required'))
@section('code', '401')
@section('message', __('Please log in to your TicketLab staff or organizer account to view this operations terminal.'))
