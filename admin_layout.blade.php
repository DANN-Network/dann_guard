<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ config('app.name', 'Pterodactyl') }} - @yield('title')</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <meta name="_token" content="{{ csrf_token() }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
        <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
        <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
        <link rel="manifest" href="/favicons/manifest.json">
        <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#bc6e3c">
        <link rel="shortcut icon" href="/favicons/favicon.ico">
        <meta name="msapplication-config" content="/favicons/browserconfig.xml">
        <meta name="theme-color" content="#6366f1">

        @include('layouts.scripts')

        {!! Theme::css('vendor/select2/select2.min.css?t={cache-version}') !!}
        {!! Theme::css('vendor/bootstrap/bootstrap.min.css?t={cache-version}') !!}
        {!! Theme::css('vendor/adminlte/admin.min.css?t={cache-version}') !!}
        {!! Theme::css('vendor/adminlte/colors/skin-blue.min.css?t={cache-version}') !!}
        {!! Theme::css('vendor/sweetalert/sweetalert.min.css?t={cache-version}') !!}
        {!! Theme::css('vendor/animate/animate.min.css?t={cache-version}') !!}
        {!! Theme::css('css/pterodactyl.css?t={cache-version}') !!}
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">

        <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->

        @section('scripts')
        @show
    </head>
    <body class="hold-transition skin-blue fixed sidebar-mini layout-fixed">
        <div id="dann-overlay"></div>
        <div class="dann-app">
            {{-- TOP NAVBAR --}}
            <header class="dann-topbar">
                <div class="dann-topbar-left">
                    <button type="button" class="dann-sidebar-toggle" id="sidebarToggle">
                        <i class="fa fa-bars"></i>
                    </button>
                    <a href="{{ route('index') }}" class="dann-topbar-brand">
                        <div class="dann-brand-icon">
                            <i class="fa fa-diamond"></i>
                        </div>
                        <span class="dann-brand-text">{{ config('app.name', 'Pterodactyl') }}</span>
                    </a>
                </div>
                <div class="dann-topbar-right">
                    <div class="dann-topbar-user">
                        <div class="dann-topbar-user-info">
                            <span class="dann-topbar-user-name">{{ Auth::user()->name_first }} {{ Auth::user()->name_last }}</span>
                            <span class="dann-topbar-user-role">{{ Auth::user()->root_admin ? 'Administrator' : 'User' }}</span>
                        </div>
                        <div class="dann-topbar-user-avatar">
                            <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(Auth::user()->email)) }}?s=160" alt="Avatar">
                            <span class="dann-avatar-status"></span>
                        </div>
                        <div class="dann-topbar-dropdown">
                            <a href="{{ route('account') }}"><i class="fa fa-user"></i> My Account</a>
                            <a href="{{ route('index') }}"><i class="fa fa-server"></i> Panel</a>
                            <div class="dann-dropdown-divider"></div>
                            <a href="{{ route('auth.logout') }}" id="logoutButton"><i class="fa fa-sign-out"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            {{-- SIDEBAR --}}
            @php
            $perms = [];
            if (Auth::check()) {
                $raw = Auth::user()->protect_permissions;
                if ($raw) {
                    $perms = json_decode($raw, true) ?? [];
                }
            }
            function canSee($key, $perms) {
                if (Auth::user()->root_admin && empty($perms)) return true;
                return $perms[$key] ?? false;
            }
            @endphp
            <aside class="dann-sidebar" id="mainSidebar">
                <div class="dann-sidebar-profile">
                    <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(Auth::user()->email)) }}?s=160" class="dann-sidebar-avatar" alt="Avatar">
                    <div class="dann-sidebar-profile-info">
                        <p class="dann-sidebar-name">{{ Auth::user()->name_first }} {{ Auth::user()->name_last }}</p>
                        <span class="dann-sidebar-role"><i class="fa fa-circle"></i> Online</span>
                    </div>
                </div>
                <nav class="dann-sidebar-nav">
                    @if (canSee('overview', $perms))
                    <div class="dann-nav-section">MAIN</div>
                    <a href="{{ route('admin.index') }}" class="dann-nav-item {{ Route::currentRouteName() === 'admin.index' ? 'active' : '' }}">
                        <i class="fa fa-th-large"></i><span>Overview</span>
                    </a>
                    @endif
                    @if (canSee('settings', $perms))
                    <a href="{{ route('admin.settings') }}" class="dann-nav-item {{ starts_with(Route::currentRouteName(), 'admin.settings') ? 'active' : '' }}">
                        <i class="fa fa-cog"></i><span>Settings</span>
                    </a>
                    @endif
                    @if (canSee('api', $perms))
                    <a href="{{ route('admin.api.index') }}" class="dann-nav-item {{ starts_with(Route::currentRouteName(), 'admin.api') ? 'active' : '' }}">
                        <i class="fa fa-key"></i><span>API</span>
                    </a>
                    @endif

                    @if (canSee('databases', $perms) || canSee('locations', $perms) || canSee('nodes', $perms) || canSee('servers', $perms) || canSee('users', $perms))
                    <div class="dann-nav-section">MANAGEMENT</div>
                    @endif
                    @if (canSee('users', $perms))
                    <a href="{{ route('admin.users') }}" class="dann-nav-item {{ starts_with(Route::currentRouteName(), 'admin.users') ? 'active' : '' }}">
                        <i class="fa fa-users"></i><span>Users</span>
                    </a>
                    @endif
                    @if (canSee('servers', $perms))
                    <a href="{{ route('admin.servers') }}" class="dann-nav-item {{ starts_with(Route::currentRouteName(), 'admin.servers') ? 'active' : '' }}">
                        <i class="fa fa-server"></i><span>Servers</span>
                    </a>
                    @endif
                    @if (canSee('nodes', $perms))
                    <a href="{{ route('admin.nodes') }}" class="dann-nav-item {{ starts_with(Route::currentRouteName(), 'admin.nodes') ? 'active' : '' }}">
                        <i class="fa fa-sitemap"></i><span>Nodes</span>
                    </a>
                    @endif
                    @if (canSee('locations', $perms))
                    <a href="{{ route('admin.locations') }}" class="dann-nav-item {{ starts_with(Route::currentRouteName(), 'admin.locations') ? 'active' : '' }}">
                        <i class="fa fa-globe"></i><span>Locations</span>
                    </a>
                    @endif
                    @if (canSee('databases', $perms))
                    <a href="{{ route('admin.databases') }}" class="dann-nav-item {{ starts_with(Route::currentRouteName(), 'admin.databases') ? 'active' : '' }}">
                        <i class="fa fa-database"></i><span>Databases</span>
                    </a>
                    @endif

                    @if (canSee('protect', $perms))
                    <div class="dann-nav-section">SECURITY</div>
                    <a href="/admin/protect" class="dann-nav-item {{ Route::currentRouteName() === 'admin.protect' ? 'active' : '' }}">
                        <i class="fa fa-shield"></i><span>Protect</span>
                        <span class="dann-nav-badge dann-badge-green">ON</span>
                    </a>
                    @endif

                    @if (canSee('mounts', $perms) || canSee('nests', $perms))
                    <div class="dann-nav-section">SERVICES</div>
                    @endif
                    @if (canSee('nests', $perms))
                    <a href="{{ route('admin.nests') }}" class="dann-nav-item {{ starts_with(Route::currentRouteName(), 'admin.nests') ? 'active' : '' }}">
                        <i class="fa fa-th-large"></i><span>Nests</span>
                    </a>
                    @endif
                    @if (canSee('mounts', $perms))
                    <a href="{{ route('admin.mounts') }}" class="dann-nav-item {{ starts_with(Route::currentRouteName(), 'admin.mounts') ? 'active' : '' }}">
                        <i class="fa fa-plug"></i><span>Mounts</span>
                    </a>
                    @endif
                </nav>
                <div class="dann-sidebar-footer">
                    <a href="{{ route('auth.logout') }}" class="dann-nav-item dann-nav-logout">
                        <i class="fa fa-power-off"></i><span>Logout</span>
                    </a>
                </div>
            </aside>

            {{-- CONTENT --}}
            <main class="dann-content">
                <div class="dann-content-header">
                    @yield('content-header')
                </div>
                <div class="dann-content-body">
                    @if (count($errors) > 0)
                        <div class="dann-alert dann-alert-danger">
                            <i class="fa fa-exclamation-triangle"></i>
                            <div>
                                <strong>Validation Error</strong>
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                    @foreach (Alert::getMessages() as $type => $messages)
                        @foreach ($messages as $message)
                            <div class="dann-alert dann-alert-{{ $type }}">
                                <i class="fa fa-{{ $type === 'danger' ? 'exclamation-circle' : ($type === 'success' ? 'check-circle' : 'info-circle') }}"></i>
                                <span>{{ $message }}</span>
                            </div>
                        @endforeach
                    @endforeach
                    @yield('content')
                </div>
            </main>

            {{-- FOOTER --}}
            <footer class="dann-footer">
                <span>&copy; {{ date('Y') }} {{ config('app.name', 'Pterodactyl') }}</span>
                <span class="dann-footer-sep">&middot;</span>
                <span>Protected by <strong><i class="fa fa-shield"></i> dann_guard</strong></span>
                <span class="dann-footer-sep">&middot;</span>
                <span class="dann-footer-version"><i class="fa fa-code-fork"></i> {{ $appVersion }}</span>
                <span class="dann-footer-time"><i class="fa fa-clock-o"></i> {{ round(microtime(true) - LARAVEL_START, 3) }}s</span>
            </footer>
        </div>

        <script src="/js/keyboard.polyfill.js" type="application/javascript"></script>
        <script>keyboardeventKeyPolyfill.polyfill();</script>

        {!! Theme::js('vendor/jquery/jquery.min.js?t={cache-version}') !!}
        {!! Theme::js('vendor/sweetalert/sweetalert.min.js?t={cache-version}') !!}
        {!! Theme::js('vendor/bootstrap/bootstrap.min.js?t={cache-version}') !!}
        {!! Theme::js('vendor/slimscroll/jquery.slimscroll.min.js?t={cache-version}') !!}
        {!! Theme::js('vendor/adminlte/app.min.js?t={cache-version}') !!}
        {!! Theme::js('vendor/bootstrap-notify/bootstrap-notify.min.js?t={cache-version}') !!}
        {!! Theme::js('vendor/select2/select2.full.min.js?t={cache-version}') !!}
        {!! Theme::js('js/admin/functions.js?t={cache-version}') !!}
        <script src="/js/autocomplete.js" type="application/javascript"></script>

        @if(Auth::user()->root_admin)
            <script>
                $('#logoutButton').on('click', function (event) {
                    event.preventDefault();
                    var that = this;
                    swal({
                        title: 'Do you want to log out?',
                        type: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d9534f',
                        cancelButtonColor: '#333',
                        confirmButtonText: 'Log out'
                    }, function () {
                         $.ajax({
                            type: 'POST',
                            url: '{{ route('auth.logout') }}',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },complete: function () {
                                window.location.href = '{{route('auth.login')}}';
                            }
                    });
                });
                </script>
            @endif

        <script>
        $(document).ready(function() {
            $('#sidebarToggle').on('click', function() {
                $('.dann-sidebar').toggleClass('collapsed');
                $('.dann-content').toggleClass('expanded');
            });
            $('[data-toggle="tooltip"]').tooltip();
        });
        </script>

        @section('footer-scripts')
        @show
    </body>
</html>
