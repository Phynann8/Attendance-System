@extends('layouts.app')

@section('title', __('Admin Dashboard'))

@section('content')
<div class="flex-between">
    <div>
        <div class="page-title">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</div>
        <div class="page-sub">{{ __('Please verify students absent status with Student Affairs before contacting parents') }}</div>
    </div>
    <a href="{{ route('admin.permissions.create') }}" class="btn">+ {{ __('Request Permission') }}</a>
</div>

<div class="grid grid-4">
    <div class="stat"><div class="num">{{ $pendingPermissions }}</div><div class="label">{{ __('Pending Permissions') }}</div></div>
    <div class="stat"><div class="num">{{ $approvedPermissionsToday }}</div><div class="label">{{ __('Approved Permissions Today') }}</div></div>
    <div class="stat"><div class="num">{{ $openSessions }}</div><div class="label">{{ __('Open Attendance Sessions') }}</div></div>
    <div class="stat"><div class="num">{{ $pendingAbsenceReviews }}</div><div class="label">{{ __('Absences Awaiting Decision') }}</div></div>
</div>

@endsection