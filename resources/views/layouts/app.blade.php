<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div id="app">
        <nav style="background-color: #f8f9fa; padding: 1rem; margin-bottom: 2rem; border-bottom: 1px solid #dee2e6;">
            <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center;">
                <a href="{{ url('/') }}" style="font-size: 1.25rem; font-weight: bold; text-decoration: none; color: #333;">
                    {{ config('app.name', 'Laravel') }}
                </a>
                
                <div>
                    @guest
                        <a href="{{ route('login') }}" style="margin-right: 1rem; text-decoration: none; color: #007bff;">Login</a>
                        <a href="{{ route('register') }}" style="text-decoration: none; color: #007bff;">Register</a>
                    @else
                        <span style="margin-right: 1rem; color: #666;">{{ Auth::user()->name }}</span>
                        <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" style="background: none; border: none; color: #007bff; cursor: pointer; text-decoration: underline;">
                                Logout
                            </button>
                        </form>
                    @endguest
                </div>
            </div>
        </nav>

        <main style="max-width: 1200px; margin: 0 auto; padding: 0 1rem;">
            @if (session('status'))
                <div style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 0.75rem 1.25rem; margin-bottom: 1rem; border-radius: 0.25rem;">
                    {{ session('status') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
