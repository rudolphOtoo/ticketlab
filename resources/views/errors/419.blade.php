@extends('errors.layout')

@section('title', __('Session Expired'))
@section('code', '419')
@section('message', __('Your security token has expired due to inactivity. Please refresh the page and try your action again.'))
