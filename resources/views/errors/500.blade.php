@extends('errors.layout')

@section('title', __('Server Encountered an Error'))
@section('code', '500')
@section('message', __('Our server experienced an unexpected condition while processing this request. The platform engineering team has been notified.'))
