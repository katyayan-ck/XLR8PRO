{{-- Branded error page (go-live to-do U8) --}}
@extends('errors.xl')

@section('code', '429')
@section('title', 'Too many requests')
@section('message', 'You\'ve tried this too many times in a short while. Wait a minute, then try again.')
