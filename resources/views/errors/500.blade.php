{{-- Branded error page (go-live to-do U8) --}}
@extends('errors.xl')

@section('code', '500')
@section('title', 'Something went wrong')
@section('message', 'An unexpected error stopped this request. It has been logged; please try again in a moment.')
@section('reference', \App\Support\ErrorRef::get())
