{{-- Branded error page (go-live to-do U8) --}}
@extends('errors.xl')

@section('code', '401')
@section('title', 'Sign-in needed')
@section('message', 'Your session has ended or you are not signed in. Please sign in again.')
