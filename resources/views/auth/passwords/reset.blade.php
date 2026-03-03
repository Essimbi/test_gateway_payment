@extends('layouts.app')

@section('content')
<div style="max-width: 500px; margin: 2rem auto;">
    <h2 style="text-align: center; margin-bottom: 2rem;">Reset Password</h2>

    <form method="POST" action="{{ route('password.update') }}" style="background: #fff; padding: 2rem; border: 1px solid #dee2e6; border-radius: 0.25rem;">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div style="margin-bottom: 1.5rem;">
            <label for="email" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Email</label>
            <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autofocus
                   style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 0.25rem;">
            @error('email')
                <span style="color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; display: block;">{{ $message }}</span>
            @enderror
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label for="password" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">New Password</label>
            <input id="password" type="password" name="password" required
                   style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 0.25rem;">
            @error('password')
                <span style="color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; display: block;">{{ $message }}</span>
            @enderror
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label for="password_confirmation" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Confirm Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required
                   style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 0.25rem;">
        </div>

        <div style="margin-bottom: 1rem;">
            <button type="submit" style="width: 100%; padding: 0.75rem; background-color: #007bff; color: white; border: none; border-radius: 0.25rem; cursor: pointer; font-size: 1rem;">
                Reset Password
            </button>
        </div>
    </form>
</div>
@endsection
