@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="login-wrap">
    <div class="login-card">
        <h1>Sign in</h1>
        <div class="sub">School Attendance System</div>

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
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn" style="width:100%">Sign in</button>
        </form>

        <div class="login-hint">
            <strong>Don't have account or Forgot Password?</strong><br><strong>Contact Your School Administrator</strong>
        </div>
    </div>
</div>
@endsection