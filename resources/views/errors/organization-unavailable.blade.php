@extends('errors.layout')

@section('title', __(':name is unavailable', ['name' => $organizationName]))
@section('code', __('Unavailable'))
@section('message', __("This organisation's pages and ticket sales are not available at the moment. If you already have a ticket, its link still works."))
