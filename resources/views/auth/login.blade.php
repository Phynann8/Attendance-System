@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="login-wrap">
    <div class="login-card">
        <div class="login-brand-header">
            <div class="login-crest">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <h1>{{ __('Sign in') }}</h1>
            <div class="sub">{{ __('School Attendance System') }}</div>
        </div>

        @if(session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf
            <div class="form-group">
                <label for="email"><i class="fa-solid fa-envelope" style="color:var(--brand-muted); margin-right:4px;"></i> {{ __('Email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@school.edu">
            </div>
            <div class="form-group">
                <label for="password"><i class="fa-solid fa-lock" style="color:var(--brand-muted); margin-right:4px;"></i> {{ __('Password') }}</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn" style="width:100%; padding:10px; font-size:15px; margin-top:6px;">
                <i class="fa-solid fa-right-to-bracket" style="margin-right:6px;"></i> {{ __('Sign in') }}
            </button>
        </form>

        <div class="login-hint">
            <i class="fa-solid fa-circle-info" style="color:var(--brand-gold); margin-right:4px;"></i>
            <strong>{{ __("Don't have an account or forgot password?") }}</strong><br>
            <span>{{ __('Contact your school administrator for assistance.') }}</span>
        </div>
    </div>
</div>
@endsection