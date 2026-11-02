@extends('errors.layout')

@section('title', __('Rate Limit Exceeded'))
@section('code', '429')
@section('message', __('Too many requests were sent to the terminal. Please pause for a few seconds before trying again.'))
