@extends('layouts.app')

@section('content')
<div style="max-width: 500px; margin: 2rem auto;">
    <h2 style="text-align: center; margin-bottom: 2rem;">Login</h2>

    <form method="POST" action="{{ route('login') }}" style="background: #fff; padding: 2rem; border: 1px solid #dee2e6; border-radius: 0.25rem;">
        @csrf

        <div style="margin-bottom: 1.5rem;">
            <label for="email" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 0.25rem;">
            @error('email')
                <span style="color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; display: block;">{{ $message }}</span>
            @enderror
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label for="password" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Password</label>
            <input id="password" type="password" name="password" required
                   style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 0.25rem;">
            @error('password')
                <span style="color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; display: block;">{{ $message }}</span>
            @enderror
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label style="display: flex; align-items: center;">
                <input type="checkbox" name="remember" style="margin-right: 0.5rem;">
                <span>Remember Me</span>
            </label>
        </div>

        <div style="margin-bottom: 1rem;">
            <button type="submit" style="width: 100%; padding: 0.75rem; background-color: #007bff; color: white; border: none; border-radius: 0.25rem; cursor: pointer; font-size: 1rem;">
                Login
            </button>
        </div>

        <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
            <a href="{{ route('register') }}" style="color: #007bff; text-decoration: none;">Create an account</a>
            <a href="{{ route('password.request') }}" style="color: #007bff; text-decoration: none;">Forgot password?</a>
        </div>
    </form>
</div>
@endsection
