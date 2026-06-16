<!DOCTYPE html>
<html>
    <head>
        <title>{{ config('app.name', 'Pterodactyl') }}</title>

        @section('meta')
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <meta name="robots" content="noindex">
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
        @show

        @section('user-data')
            @if(!is_null(Auth::user()))
                <script>
                    window.PterodactylUser = {!! json_encode(Auth::user()->toVueObject()) !!};
                </script>
            @endif
            @if(!empty($siteConfiguration))
                <script>
                    window.SiteConfiguration = {!! json_encode($siteConfiguration) !!};
                </script>
            @endif
        @show

        @yield('assets')

        <style id="dann-client-theme">
        /* ============================================
           CLIENT PANEL — 80% LAYOUT REDESIGN
           Overrides TailwindCSS with !important
           ============================================ */

        :root {
            --bg-deep: #05050f;
            --bg-base: #0a0a1a;
            --bg-surface: #0f0f28;
            --bg-elevated: #141438;
            --bg-hover: #1a1a48;
            --border: rgba(99, 102, 241, 0.08);
            --border-hover: rgba(99, 102, 241, 0.18);
            --accent: #6366f1;
            --accent-light: #818cf8;
            --purple: #a855f7;
            --pink: #ec4899;
            --green: #22c55e;
            --red: #ef4444;
            --yellow: #f59e0b;
            --cyan: #06b6d4;
            --text-primary: #e8ecf1;
            --text-secondary: #8892a4;
            --text-muted: #555d6e;
        }

        /* === BASE === */
        *, *::before, *::after { box-sizing: border-box; }
        html {
            background: var(--bg-deep) !important;
            scroll-behavior: smooth;
        }
        body {
            background: var(--bg-deep) !important;
            color: var(--text-primary) !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
            min-height: 100vh !important;
            -webkit-font-smoothing: antialiased;
            margin: 0;
            padding: 0;
        }
        #app {
            background: var(--bg-deep) !important;
            min-height: 100vh;
        }

        /* === SCROLLBAR === */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.2); border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(99,102,241,0.4); }

        /* === SELECTION === */
        ::selection { background: rgba(99,102,241,0.3); color: #fff; }

        /* ============================================
           ALL NEUTRAL BACKGROUNDS — dark palette
           ============================================ */
        .bg-neutral-50 { background: #0e0e22 !important; }
        .bg-neutral-100 { background: #10102a !important; }
        .bg-neutral-200 { background: #121235 !important; }
        .bg-neutral-300 { background: #18184a !important; }
        .bg-neutral-400 { background: #1e1e55 !important; }
        .bg-neutral-500 { background: #252560 !important; }
        .bg-neutral-600 { background: #2c2c6a !important; }
        .bg-neutral-700 { background: #353578 !important; }
        .bg-neutral-800 { background: #0c0c1e !important; }
        .bg-neutral-900 { background: #0a0a18 !important; }
        [class*="bg-neutral-800"][class*="/50"] { background: rgba(12,12,30,0.7) !important; }
        [class*="bg-neutral-900"][class*="/50"] { background: rgba(10,10,24,0.7) !important; }

        .bg-white {
            background: var(--bg-surface) !important;
            border: 1px solid var(--border) !important;
        }

        /* Gray backgrounds */
        .bg-gray-50, .bg-gray-100 { background: #0e0e22 !important; }
        .bg-gray-200 { background: #121235 !important; }
        .bg-gray-300 { background: #18184a !important; }
        .bg-gray-400 { background: #1e1e55 !important; }
        .bg-gray-500 { background: #252560 !important; }
        .bg-gray-600, .bg-gray-700, .bg-gray-800, .bg-gray-900 {
            background: var(--bg-elevated) !important;
        }

        /* ============================================
           TEXT COLORS
           ============================================ */
        .text-neutral-50, .text-neutral-100, .text-neutral-200 { color: var(--text-primary) !important; }
        .text-neutral-300 { color: var(--text-secondary) !important; }
        .text-neutral-400 { color: var(--text-secondary) !important; }
        .text-neutral-500 { color: var(--text-muted) !important; }
        .text-neutral-600, .text-neutral-700 { color: var(--text-muted) !important; }
        .text-white { color: var(--text-primary) !important; }
        .text-gray-100, .text-gray-200, .text-gray-300 { color: var(--text-secondary) !important; }
        .text-gray-400, .text-gray-500 { color: var(--text-muted) !important; }
        .text-gray-600, .text-gray-700, .text-gray-800, .text-gray-900 {
            color: var(--text-muted) !important;
        }

        .text-blue-400, .text-blue-500, .text-blue-600 { color: var(--accent-light) !important; }
        .text-indigo-400, .text-indigo-500, .text-indigo-600 { color: var(--accent-light) !important; }
        .text-green-400, .text-green-500, .text-green-600 { color: var(--green) !important; }
        .text-red-400, .text-red-500, .text-red-600 { color: var(--red) !important; }
        .text-yellow-400, .text-yellow-500, .text-yellow-600 { color: var(--yellow) !important; }
        .text-cyan-400, .text-cyan-500 { color: var(--cyan) !important; }
        .text-purple-400, .text-purple-500 { color: var(--purple) !important; }
        .text-pink-400, .text-pink-500 { color: var(--pink) !important; }

        /* ============================================
           HEADINGS
           ============================================ */
        h1, h2, h3, h4, h5, h6 { color: var(--text-primary) !important; }
        .text-3xl, .text-2xl, .text-xl, .text-lg, .text-base, .text-sm, .text-xs {
            color: var(--text-primary) !important;
        }

        /* ============================================
           LINKS
           ============================================ */
        a { color: var(--accent-light) !important; transition: color 0.2s !important; text-decoration: none; }
        a:hover { color: var(--purple) !important; }
        [class*="text-neutral-500"]:hover, [class*="text-neutral-400"]:hover,
        [class*="text-gray-500"]:hover, [class*="text-gray-400"]:hover {
            color: var(--purple) !important;
        }

        /* ============================================
           BUTTONS — pill gradient
           ============================================ */
        button[type="submit"], button:not([type]), a[role="button"] {
            border-radius: 9999px !important;
            font-family: 'Inter', sans-serif !important;
            font-weight: 600 !important;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        button[type="submit"]:active, button:not([type]):active {
            transform: scale(0.96) !important;
        }

        /* Blue/Indigo/Primary buttons */
        .bg-blue-500, .bg-blue-600, .bg-indigo-500, .bg-indigo-600,
        [class*="bg-blue-5"], [class*="bg-blue-6"],
        [class*="bg-indigo-5"], [class*="bg-indigo-6"] {
            background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
            color: #fff !important;
            box-shadow: 0 4px 16px rgba(99,102,241,0.35) !important;
            border: none !important;
            padding: 10px 24px !important;
            font-size: 13px !important;
        }
        .bg-blue-500:hover, .bg-blue-600:hover, .bg-indigo-500:hover, .bg-indigo-600:hover {
            box-shadow: 0 8px 28px rgba(99,102,241,0.5) !important;
            transform: translateY(-1px) !important;
        }

        /* Green buttons */
        .bg-green-500, .bg-green-600, .bg-emerald-500, .bg-emerald-600 {
            background: linear-gradient(135deg, #22c55e, #16a34a) !important;
            color: #fff !important;
            box-shadow: 0 4px 16px rgba(34,197,94,0.35) !important;
            border: none !important;
        }
        .bg-green-500:hover, .bg-green-600:hover {
            box-shadow: 0 8px 28px rgba(34,197,94,0.5) !important;
            transform: translateY(-1px) !important;
        }

        /* Red buttons */
        .bg-red-500, .bg-red-600, .bg-rose-500, .bg-rose-600 {
            background: linear-gradient(135deg, #ef4444, #dc2626) !important;
            color: #fff !important;
            box-shadow: 0 4px 16px rgba(239,68,68,0.35) !important;
            border: none !important;
        }
        .bg-red-500:hover, .bg-red-600:hover {
            box-shadow: 0 8px 28px rgba(239,68,68,0.5) !important;
            transform: translateY(-1px) !important;
        }

        /* Yellow/Amber buttons */
        .bg-yellow-500, .bg-yellow-600, .bg-amber-500, .bg-amber-600 {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            color: #000 !important;
            border: none !important;
        }

        /* Cyan/Teal buttons */
        .bg-cyan-500, .bg-cyan-600, .bg-teal-500, .bg-teal-600 {
            background: linear-gradient(135deg, #06b6d4, #0891b2) !important;
            color: #fff !important;
            border: none !important;
        }

        /* Gray/default buttons */
        .bg-gray-500, .bg-gray-600, .bg-gray-700, .bg-gray-800,
        .bg-neutral-600, .bg-neutral-700, .bg-neutral-800 {
            background: var(--bg-elevated) !important;
            color: var(--text-secondary) !important;
            border: 1px solid var(--border) !important;
        }

        /* ============================================
           INPUTS / FORMS — dark modern
           ============================================ */
        input[type="text"], input[type="email"], input[type="password"],
        input[type="number"], input[type="search"], input[type="url"],
        input[type="date"], input[type="datetime-local"], input[type="time"],
        input:not([type]), textarea, select {
            background: rgba(0,0,0,0.3) !important;
            border: 1px solid rgba(99,102,241,0.12) !important;
            color: var(--text-primary) !important;
            border-radius: 12px !important;
            padding: 11px 16px !important;
            font-size: 14px !important;
            font-family: 'Inter', sans-serif !important;
            transition: all 0.2s !important;
        }
        input:focus, textarea:focus, select:focus {
            border-color: rgba(99,102,241,0.4) !important;
            box-shadow: 0 0 0 4px rgba(99,102,241,0.08) !important;
            outline: none !important;
            background: rgba(0,0,0,0.4) !important;
        }
        input::placeholder, textarea::placeholder {
            color: var(--text-muted) !important;
        }
        label { color: var(--text-secondary) !important; font-weight: 600 !important; font-size: 13px !important; }
        select { appearance: auto !important; }

        /* ============================================
           BORDERS — subtle accent
           ============================================ */
        .border { border-color: var(--border) !important; }
        .border-t, .border-t-2 { border-top-color: var(--border) !important; }
        .border-b, .border-b-2 { border-bottom-color: var(--border) !important; }
        .border-l, .border-l-2 { border-left-color: var(--border) !important; }
        .border-r, .border-r-2 { border-right-color: var(--border) !important; }
        .border-neutral-700 { border-color: var(--border) !important; }
        .border-neutral-800 { border-color: rgba(99,102,241,0.06) !important; }
        .border-gray-200, .border-gray-300, .border-gray-200\/50 {
            border-color: var(--border) !important;
        }
        .border-gray-600, .border-gray-700, .border-gray-800 {
            border-color: var(--border) !important;
        }

        /* ============================================
           SHADOWS — deeper, darker
           ============================================ */
        .shadow { box-shadow: 0 4px 24px rgba(0,0,0,0.35) !important; }
        .shadow-sm { box-shadow: 0 2px 12px rgba(0,0,0,0.25) !important; }
        .shadow-md { box-shadow: 0 6px 32px rgba(0,0,0,0.4) !important; }
        .shadow-lg { box-shadow: 0 12px 48px rgba(0,0,0,0.5) !important; }
        .shadow-xl { box-shadow: 0 20px 60px rgba(0,0,0,0.6) !important; }

        /* ============================================
           NAVIGATION / HEADER / SIDEBAR
           ============================================ */
        nav, header {
            background: rgba(10,10,26,0.95) !important;
            border-color: var(--border) !important;
        }

        /* ============================================
           ROUNDED — pill everything
           ============================================ */
        .rounded { border-radius: 12px !important; }
        .rounded-sm { border-radius: 10px !important; }
        .rounded-md { border-radius: 14px !important; }
        .rounded-lg { border-radius: 18px !important; }
        .rounded-xl { border-radius: 22px !important; }
        .rounded-full { border-radius: 9999px !important; }
        [class*="rounded-md"] { border-radius: 14px !important; }

        /* ============================================
           CARDS — glass morphism
           ============================================ */
        .bg-neutral-800.bg-opacity-50,
        [class*="bg-neutral-800"][class*="bg-opacity"] {
            background: rgba(14,14,36,0.85) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            border: 1px solid var(--border) !important;
        }

        /* ============================================
           CODE / PRE
           ============================================ */
        code {
            background: rgba(0,0,0,0.3) !important;
            color: #c084fc !important;
            border-radius: 8px !important;
            padding: 2px 8px !important;
            font-family: 'JetBrains Mono', monospace !important;
            border: 1px solid var(--border) !important;
        }
        pre {
            background: rgba(0,0,0,0.3) !important;
            color: var(--text-primary) !important;
            border-radius: 14px !important;
            padding: 18px !important;
            font-family: 'JetBrains Mono', monospace !important;
            border: 1px solid var(--border) !important;
        }

        /* ============================================
           DIVIDER
           ============================================ */
        .divide-neutral-700 > :not([hidden]) ~ :not([hidden]) {
            border-color: rgba(99,102,241,0.06) !important;
        }
        [class*="divide-"] > :not([hidden]) ~ :not([hidden]) {
            border-color: var(--border) !important;
        }

        /* ============================================
           TABLES
           ============================================ */
        table { border-collapse: separate; border-spacing: 0; }
        th { color: var(--text-muted) !important; font-weight: 700 !important; font-size: 11px !important; text-transform: uppercase !important; letter-spacing: 0.8px !important; }
        td { border-color: rgba(99,102,241,0.04) !important; }
        tr:hover { background: rgba(99,102,241,0.03) !important; }

        /* ============================================
           DROPDOWN MENUS
           ============================================ */
        [class*="dropdown"], [class*="menu"] {
            background: var(--bg-elevated) !important;
            border: 1px solid var(--border) !important;
        }

        /* ============================================
           BADGES / TAGS
           ============================================ */
        .bg-opacity-10 { --tw-bg-opacity: 0.12 !important; }

        /* ============================================
           TRANSITIONS
           ============================================ */
        * { transition-property: background-color, border-color, color, box-shadow, opacity, transform !important; transition-duration: 0.2s !important; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1) !important; }

        /* ============================================
           PTERODACTYL LOGO
           ============================================ */
        img[src*="pterodactyl"] { filter: brightness(1.2) hue-rotate(10deg); }

        /* ============================================
           SPECIAL: ANIMATIONS
           ============================================ */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        #app > div { animation: fadeIn 0.4s ease; }

        /* ============================================
           OVERRIDES FOR SPECIFIC TAILWIND CLASSES
           ============================================ */
        .bg-gradient-to-br { background-image: none !important; }
        .from-neutral-800 { --tw-gradient-from: #0c0c1e !important; }
        .to-neutral-900 { --tw-gradient-to: #0a0a18 !important; }

        /* Ensure sidebar mobile overlay */
        [class*="fixed"][class*="inset-0"] {
            background: rgba(0,0,0,0.6) !important;
        }

        /* Login/Register form cards */
        .max-w-md { max-width: 420px !important; }
        .w-full { width: 100% !important; }

        /* Pterodactyl-specific overrides */
        .pterodactyl-login-box {
            background: rgba(14,14,36,0.92) !important;
            backdrop-filter: blur(32px) !important;
            border: 1px solid rgba(99,102,241,0.1) !important;
            border-radius: 28px !important;
            box-shadow: 0 24px 80px rgba(0,0,0,0.6), 0 0 100px rgba(99,102,241,0.04) !important;
        }
        </style>

        @include('layouts.scripts')
    </head>
    <body class="{{ $css['body'] ?? 'bg-neutral-50' }}">
        @section('content')
            @yield('above-container')
            @yield('container')
            @yield('below-container')
        @show
        @section('scripts')
            {!! $asset->js('main.js') !!}
        @show
    </body>
</html>
