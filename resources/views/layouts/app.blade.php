<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="/css/app.css?v={{ file_exists(public_path('css/app.css')) ? filemtime(public_path('css/app.css')) : time() }}">
</head>
<body>
    @auth
    <div class="layout">
        <aside class="sidebar">
            <div class="brand">Attendance System</div>

            <div class="user-chip">
                <strong>{{ auth()->user()->name }}</strong>
                <span>{{ auth()->user()->role }}</span>
            </div>

            <nav>
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>

                @if(auth()->user()->isAdmin())
                    <div class="nav-head">Permissions</div>
                    <a href="{{ route('admin.permissions.index') }}" class="{{ request()->routeIs('admin.permissions.index') || request()->routeIs('admin.permissions.show') ? 'active' : '' }}">Review Permission Requests</a>
                    <a href="{{ route('admin.permissions.create') }}" class="{{ request()->routeIs('admin.permissions.create') ? 'active' : '' }}">Assign Permissions</a>
                    <div class="nav-head">Absence Review</div>
                    <a href="{{ route('admin.absence.index') }}" class="{{ request()->routeIs('admin.absence.*') ? 'active' : '' }}">Review Absences</a>
                    <div class="nav-head">Administration</div>
                    <a href="{{ route('admin.students.index') }}" class="{{ request()->routeIs('admin.students.*') ? 'active' : '' }}">Students</a>
                    <a href="{{ route('admin.classes.index') }}" class="{{ request()->routeIs('admin.classes.*') ? 'active' : '' }}">Classes</a>
                    <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">Reports</a>
                @endif

                @if(auth()->user()->isTeacher())
                    <div class="nav-head">Attendance</div>
                    <a href="{{ route('teacher.attendance.history') }}" class="{{ request()->routeIs('teacher.attendance.*') ? 'active' : '' }}">Attendance</a>
                @endif

                @if(auth()->user()->isStudentAffairs())
                    <div class="nav-head">Verification</div>
                    <a href="{{ route('student-affairs.review.index') }}" class="{{ request()->routeIs('student-affairs.review.*') ? 'active' : '' }}">Review Absences</a>
                @endif

                @if(auth()->user()->isParent())
                    <div class="nav-head">Permissions</div>
                    <a href="{{ route('parent.permissions.index') }}" class="{{ request()->routeIs('parent.permissions.index') ? 'active' : '' }}">Permission History</a>
                    <a href="{{ route('parent.permissions.create') }}" class="{{ request()->routeIs('parent.permissions.create') ? 'active' : '' }}">Request Permission</a>
                @endif
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="logout">
                @csrf
                <button type="submit">→ Sign out</button>
            </form>
        </aside>
        <main class="main">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif
            @if(session('info'))
                <div class="alert alert-info">{{ session('info') }}</div>
            @endif

            @yield('content')
        </main>
    </div>
    @else
        @yield('content')
    @endauth

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const prefetched = new Set();
            document.querySelectorAll('.sidebar nav a').forEach(link => {
                link.addEventListener('mouseenter', () => {
                    const url = link.href;
                    if (url && !prefetched.has(url) && url.startsWith(window.location.origin)) {
                        prefetched.add(url);
                        const prefetcher = document.createElement('link');
                        prefetcher.rel = 'prefetch';
                        prefetcher.href = url;
                        document.head.appendChild(prefetcher);
                    }
                });
            });
        });
    </script>
</body>
</html>