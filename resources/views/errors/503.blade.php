@extends('errors.layout')

@section('title', __('System Under Maintenance'))
@section('code', '503')
@section('message', __('TicketLab is currently undergoing scheduled platform upgrades or high-load optimization. Service will resume momentarily.'))
