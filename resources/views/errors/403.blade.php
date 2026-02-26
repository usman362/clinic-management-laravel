<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access restricted | {{ getAppName() }}</title>
    <link rel="icon" href="{{ asset(getAppFavicon()) }}" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f4f6f9; }
        .error-card { max-width: 480px; }
        .error-card .card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,.08); }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-card mx-auto">
            <div class="card rounded-3">
                <div class="card-body p-5 text-center">
                    <div class="mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="text-warning" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                        </svg>
                    </div>
                    <h1 class="h4 mb-3">Access restricted</h1>
                    <p class="text-muted mb-4">
                        @auth
                            This page is for <strong>patients</strong>. You are currently logged in with a different account.
                            To use the patient area, please log out and sign in as a patient. Otherwise, go back to your dashboard.
                        @else
                            You need to sign in to view this page. Please log in with a patient account.
                        @endauth
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                        @auth
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary">Log out and go to login</button>
                            </form>
                            @php $user = auth()->user(); @endphp
                            @if($user && $user->hasRole('admin'))
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Go to admin dashboard</a>
                            @elseif($user && $user->hasRole('doctor'))
                                <a href="{{ route('doctors.dashboard') }}" class="btn btn-primary">Go to my dashboard</a>
                            @else
                                <a href="{{ url('/') }}" class="btn btn-primary">Go to dashboard</a>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="btn btn-primary">Go to login</a>
                            <a href="{{ url('/') }}" class="btn btn-outline-secondary">Back to dashboard</a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
