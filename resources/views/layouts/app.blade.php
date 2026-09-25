<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&display=swap" rel="stylesheet">
    <style>
        {!! file_exists(public_path('css/app.css')) ? file_get_contents(public_path('css/app.css')) : '' !!}
    </style>
</head>
<body>
    @auth
    <header class="mobile-header">
        <div class="mobile-brand">
            <strong>{{ \App\Models\SchoolSetting::get('school_name', 'Attendance System') }}</strong>
            <span>{{ \App\Models\SchoolSetting::get('academic_term', 'Semester 1') }} · {{ auth()->user()->name }}</span>
        </div>
        <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
    </header>
    <div class="layout nav-md" id="appLayout">
        <aside class="sidebar" id="appSidebar">
            <div class="sidebar-header-bar">
                <div class="sidebar-header-brand">
                    <div class="brand-title">{{ \App\Models\SchoolSetting::get('school_name', 'Attendance System') }}</div>
                    <div class="brand-sub">{{ \App\Models\SchoolSetting::get('academic_term', 'Semester 1') }} · {{ \App\Models\SchoolSetting::get('academic_year', '2026-2027') }}</div>
                </div>
                <div class="brand-mini" title="{{ \App\Models\SchoolSetting::get('school_name', 'Attendance System') }}">
                    <i class="fa-solid fa-school"></i>
                </div>
            </div>

            <div class="user-chip mobile-only">
                <strong>{{ auth()->user()->name }}</strong>
                <span>{{ auth()->user()->role }}</span>
            </div>

            <nav class="nav-accordion">
                <a href="{{ route('dashboard') }}" class="nav-single-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="{{ __('Dashboard') }}">
                    <i class="nav-icon fa-solid fa-house"></i>
                    <span class="nav-label">{{ __('Dashboard') }}</span>
                </a>
                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('module.admin'))
                    @php
                        $isAdminActive = request()->routeIs('admin.students.*') || request()->routeIs('admin.classes.*') || request()->routeIs('admin.reports.*') || request()->routeIs('admin.audit-logs.*');
                        $isPermissionsActive = request()->routeIs('admin.permissions.*') || request()->routeIs('admin.absence.*');
                    @endphp
                    <div class="nav-group {{ $isAdminActive ? 'open active-group' : '' }}">
                        <button type="button" class="nav-parent-btn" title="{{ __('Admin') }}">
                            <div class="nav-parent-left">
                                <i class="nav-icon fa-solid fa-landmark"></i>
                                <span class="nav-label">{{ __('Admin') }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-down nav-chevron"></i>
                        </button>
                        <div class="nav-submenu">
                            <div class="flyout-header"><i class="fa-solid fa-landmark"></i> {{ __('Admin') }}</div>
                            <div class="nav-submenu-tree"></div>
                            <a href="{{ route('admin.students.index') }}" class="nav-child-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}">{{ __('Students') }}</a>
                            <a href="{{ route('admin.classes.index') }}" class="nav-child-link {{ request()->routeIs('admin.classes.*') ? 'active' : '' }}">{{ __('Classes') }}</a>
                            <a href="{{ route('admin.reports.index') }}" class="nav-child-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">{{ __('Reports') }}</a>
                            <a href="{{ route('admin.audit-logs.index') }}" class="nav-child-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">{{ __('Audit Logs') }}</a>
                        </div>
                    </div>

                    <div class="nav-group {{ $isPermissionsActive ? 'open active-group' : '' }}">
                        <button type="button" class="nav-parent-btn" title="{{ __('Permissions') }}">
                            <div class="nav-parent-left">
                                <i class="nav-icon fa-solid fa-clipboard-list"></i>
                                <span class="nav-label">{{ __('Permissions') }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-down nav-chevron"></i>
                        </button>
                        <div class="nav-submenu">
                            <div class="flyout-header"><i class="fa-solid fa-clipboard-list"></i> {{ __('Permissions') }}</div>
                            <div class="nav-submenu-tree"></div>
                            <a href="{{ route('admin.permissions.index') }}" class="nav-child-link {{ request()->routeIs('admin.permissions.index') || request()->routeIs('admin.permissions.show') ? 'active' : '' }}">{{ __('Permissions') }}</a>
                            <a href="{{ route('admin.permissions.create') }}" class="nav-child-link {{ request()->routeIs('admin.permissions.create') ? 'active' : '' }}">{{ __('Request Permission') }}</a>
                            <a href="{{ route('admin.absence.index') }}" class="nav-child-link {{ request()->routeIs('admin.absence.*') ? 'active' : '' }}">{{ __('Verification') }}</a>
                        </div>
                    </div>
                @endif

                @if(auth()->user()->isTeacher() || auth()->user()->hasPermission('module.teacher'))
                    @php $isTeacherActive = request()->routeIs('teacher.*'); @endphp
                    <div class="nav-group {{ $isTeacherActive ? 'open active-group' : '' }}">
                        <button type="button" class="nav-parent-btn" title="{{ __('Attendance') }}">
                            <div class="nav-parent-left">
                                <i class="nav-icon fa-solid fa-clipboard-check"></i>
                                <span class="nav-label">{{ __('Attendance') }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-down nav-chevron"></i>
                        </button>
                        <div class="nav-submenu">
                            <div class="flyout-header"><i class="fa-solid fa-clipboard-check"></i> {{ __('Attendance') }}</div>
                            <div class="nav-submenu-tree"></div>
                            <a href="{{ route('teacher.attendance.history') }}" class="nav-child-link {{ request()->routeIs('teacher.attendance.*') ? 'active' : '' }}">{{ __('Attendance') }}</a>
                        </div>
                    </div>
                @endif

                @if(auth()->user()->isStudentAffairs() || auth()->user()->hasPermission('module.student_affairs'))
                    @php $isAffairsActive = request()->routeIs('student-affairs.*'); @endphp
                    <div class="nav-group {{ $isAffairsActive ? 'open active-group' : '' }}">
                        <button type="button" class="nav-parent-btn" title="{{ __('Verification') }}">
                            <div class="nav-parent-left">
                                <i class="nav-icon fa-solid fa-magnifying-glass"></i>
                                <span class="nav-label">{{ __('Verification') }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-down nav-chevron"></i>
                        </button>
                        <div class="nav-submenu">
                            <div class="flyout-header"><i class="fa-solid fa-magnifying-glass"></i> {{ __('Verification') }}</div>
                            <div class="nav-submenu-tree"></div>
                            <a href="{{ route('student-affairs.review.index') }}" class="nav-child-link {{ request()->routeIs('student-affairs.review.*') ? 'active' : '' }}">{{ __('Gate Review') }}</a>
                        </div>
                    </div>
                @endif

                @if(auth()->user()->isParent() || auth()->user()->hasPermission('module.parent'))
                    @php $isParentActive = request()->routeIs('parent.*'); @endphp
                    <div class="nav-group {{ $isParentActive ? 'open active-group' : '' }}">
                        <button type="button" class="nav-parent-btn" title="{{ __('Parent Portal') }}">
                            <div class="nav-parent-left">
                                <i class="nav-icon fa-solid fa-users"></i>
                                <span class="nav-label">{{ __('Parent Portal') }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-down nav-chevron"></i>
                        </button>
                        <div class="nav-submenu">
                            <div class="flyout-header"><i class="fa-solid fa-users"></i> {{ __('Parent Portal') }}</div>
                            <div class="nav-submenu-tree"></div>
                            <a href="{{ route('parent.permissions.index') }}" class="nav-child-link {{ request()->routeIs('parent.permissions.index') ? 'active' : '' }}">{{ __('Permission History') }}</a>
                            <a href="{{ route('parent.permissions.create') }}" class="nav-child-link {{ request()->routeIs('parent.permissions.create') ? 'active' : '' }}">{{ __('Request Permission') }}</a>
                        </div>
                    </div>
                @endif
                @if(auth()->user()->isSuperAdmin())
                    @php $isSuperAdminActive = request()->routeIs('super-admin.*'); @endphp
                    <div class="nav-group {{ $isSuperAdminActive ? 'open active-group' : '' }}">
                        <button type="button" class="nav-parent-btn" title="{{ __('Super Admin') }}">
                            <div class="nav-parent-left">
                                <i class="nav-icon fa-solid fa-user-shield"></i>
                                <span class="nav-label">{{ __('Setting') }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-down nav-chevron"></i>
                        </button>
                        <div class="nav-submenu">
                            <div class="flyout-header"><i class="fa-solid fa-user-shield"></i> {{ __('Setting') }}</div>
                            <div class="nav-submenu-tree"></div>
                            <a href="{{ route('super-admin.users.index') }}" class="nav-child-link {{ request()->routeIs('super-admin.users.*') ? 'active' : '' }}">{{ __('Manage Users') }}</a>
                            <a href="{{ route('super-admin.roles.index') }}" class="nav-child-link {{ request()->routeIs('super-admin.roles.*') ? 'active' : '' }}">{{ __('Manage Roles') }}</a>
                            <a href="{{ route('super-admin.settings.index') }}" class="nav-child-link {{ request()->routeIs('super-admin.settings.*') ? 'active' : '' }}">{{ __('School Settings') }}</a>
                        </div>
                    </div>
                @endif
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="logout mobile-only">
                @csrf
                <button type="submit" title="{{ __('Sign out') }}">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> <span class="logout-text">{{ __('Sign out') }}</span>
                </button>
            </form>
        </aside>
        <div class="content-wrapper">
            <header class="app-topbar">
                <div class="topbar-left">
                    <button type="button" class="topbar-toggle-btn" id="topbarToggleBtn" aria-label="Toggle Sidebar Navigation">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                </div>
                <div class="topbar-right">
                    <div class="topbar-actions">
                        @if(auth()->user()->campus)
                            <div style="display:inline-flex; align-items:center; background:#eff6ff; border:1px solid #bfdbfe; border-radius:20px; padding:3px 10px; font-size:12px; font-weight:700; color:#1d4ed8; gap:6px;" title="{{ auth()->user()->campus->name_en }} ({{ auth()->user()->campus->name_kh }})">
                                <i class="fa-solid fa-school" style="font-size:11px;"></i>
                                <span>{{ auth()->user()->campus->code }} — {{ auth()->user()->campus->name_en }}</span>
                            </div>
                        @endif

                        @if(auth()->user()->isAdmin() || auth()->user()->isSuperAdmin())
                            @php
                                $pendingCount = \App\Models\Permission::where('status', 'pending')->count();
                            @endphp
                            <a href="{{ route('admin.permissions.index') }}" class="topbar-icon-btn topbar-badge-wrap" title="{{ __('Pending Reviews') }}">
                                <i class="fa-solid fa-bell"></i>
                                @if($pendingCount > 0)
                                    <span class="topbar-badge">{{ $pendingCount }}</span>
                                @endif
                            </a>
                        @endif
                        <div class="topbar-lang-pill">
                            <a href="{{ route('locale.switch', 'km') }}" title="{{ __('Khmer') }}" class="lang-switch-link {{ app()->getLocale() === 'km' ? 'active' : '' }}">KH</a>
                            <span class="lang-separator">|</span>
                            <a href="{{ route('locale.switch', 'en') }}" title="{{ __('English') }}" class="lang-switch-link {{ app()->getLocale() === 'en' ? 'active' : '' }}">EN</a>
                        </div>
                        <div class="topbar-user-dropdown" id="topbarUserDropdown">
                            <button type="button" class="topbar-user-btn" id="topbarUserBtn">
                                <span class="user-avatar-circle">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                                <span class="topbar-username">{{ auth()->user()->name }}</span>
                                <span class="topbar-role-pill">{{ __(ucfirst(str_replace('_', ' ', auth()->user()->role))) }}</span>
                                <i class="fa-solid fa-chevron-down topbar-user-chevron"></i>
                            </button>
                            <div class="user-dropdown-menu" id="userDropdownMenu">
                                <div class="user-dropdown-header">
                                    <strong>{{ auth()->user()->name }}</strong>
                                    <span>{{ auth()->user()->email }}</span>
                                </div>
                                @if(auth()->user()->isSuperAdmin())
                                    <a href="{{ route('super-admin.settings.index') }}" class="user-dropdown-item"><i class="fa-solid fa-gear" style="margin-right: 6px;"></i> {{ __('School Settings') }}</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="user-dropdown-item user-dropdown-logout"><i class="fa-solid fa-arrow-right-from-bracket" style="margin-right: 6px;"></i> {{ __('Sign out') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
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
    </div>
    @else
        @yield('content')
    @endauth

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const layout = document.getElementById('appLayout');
            const sidebar = document.getElementById('appSidebar');

            // Apply saved sidebar mode from localStorage (nav-md vs nav-sm)
            if (layout && window.innerWidth > 768) {
                const savedMode = localStorage.getItem('sidebar_mode');
                if (savedMode === 'nav-sm') {
                    layout.classList.remove('nav-md');
                    layout.classList.add('nav-sm');
                } else {
                    layout.classList.remove('nav-sm');
                    layout.classList.add('nav-md');
                }
            }

            // Accordion dropdown menu toggle (active in nav-md)
            document.querySelectorAll('.nav-parent-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (layout && layout.classList.contains('nav-sm') && window.innerWidth > 768) {
                        return; // In nav-sm, hover flyout handles menu
                    }
                    const group = btn.closest('.nav-group');
                    if (group) {
                        group.classList.toggle('open');
                    }
                });
            });

            // Hover flyout handling for nav-sm mode
            document.querySelectorAll('.sidebar .nav-group').forEach(group => {
                group.addEventListener('mouseenter', () => {
                    if (layout && layout.classList.contains('nav-sm') && window.innerWidth > 768) {
                        group.classList.add('flyout-open');
                    }
                });
                group.addEventListener('mouseleave', () => {
                    group.classList.remove('flyout-open');
                });
            });

            // Sidebar toggle buttons (toggles nav-md vs nav-sm on desktop, drawer on mobile)
            const toggleButtons = [
                document.getElementById('sidebarToggleBtn'),
                document.getElementById('topbarToggleBtn'),
                document.getElementById('mobileMenuBtn')
            ].filter(Boolean);

            toggleButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (window.innerWidth <= 768) {
                        if (sidebar) {
                            sidebar.classList.toggle('open');
                        }
                    } else {
                        if (layout) {
                            if (layout.classList.contains('nav-sm')) {
                                layout.classList.remove('nav-sm');
                                layout.classList.add('nav-md');
                                localStorage.setItem('sidebar_mode', 'nav-md');
                            } else {
                                layout.classList.remove('nav-md');
                                layout.classList.add('nav-sm');
                                localStorage.setItem('sidebar_mode', 'nav-sm');
                            }
                        }
                    }
                });
            });

            // Topbar User Avatar Dropdown
            const userBtn = document.getElementById('topbarUserBtn');
            const userMenu = document.getElementById('userDropdownMenu');
            if (userBtn && userMenu) {
                userBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userMenu.classList.toggle('show');
                });

                document.addEventListener('click', (e) => {
                    if (!userMenu.contains(e.target) && !userBtn.contains(e.target)) {
                        userMenu.classList.remove('show');
                    }
                });
            }

            // Prefetch on hover
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