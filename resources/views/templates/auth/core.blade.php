@extends('templates/wrapper', [
    'css' => ['body' => 'bg-neutral-900']
])

@section('assets')
<style>
/* ── Login page DANN-GUARD custom ── */
body {
    background: linear-gradient(135deg, #0a0a1a 0%, #1a0a2e 50%, #0a0a1a 100%) !important;
}

#app > div {
    color: #e0e0f0 !important;
}

/* Fix text readability */
#app label,
#app .input-label,
#app [class*="text-gray"],
#app [class*="text-neutral"],
#app p, #app span, #app h1, #app h2, #app h3 {
    color: #d0d0e0 !important;
}

/* Style input fields */
#app input[type="text"],
#app input[type="email"],
#app input[type="password"] {
    background: rgba(26, 26, 48, 0.8) !important;
    border: 1px solid rgba(124, 58, 237, 0.3) !important;
    color: #e0e0f0 !important;
    border-radius: 8px !important;
    padding: 12px 16px !important;
    font-size: 14px !important;
}

#app input:focus {
    border-color: #7c3aed !important;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15) !important;
    outline: none !important;
}

/* Style the login button */
#app button[type="submit"],
#app .btn-primary {
    background: #7c3aed !important;
    border: none !important;
    color: #fff !important;
    border-radius: 8px !important;
    padding: 12px 24px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: background 0.2s ease !important;
}

#app button[type="submit"]:hover {
    background: #6d28d9 !important;
}

/* Login card background */
#app > div > div {
    background: rgba(16, 16, 32, 0.9) !important;
    backdrop-filter: blur(12px) !important;
    border: 1px solid rgba(124, 58, 237, 0.2) !important;
    border-radius: 12px !important;
    padding: 32px !important;
    box-shadow: 0 0 60px rgba(124, 58, 237, 0.08) !important;
}

/* Mascot image */
#app::before {
    content: '';
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 120px;
    height: 120px;
    background: url('https://files.catbox.moe/cnwx8t.jpg') no-repeat center/contain;
    z-index: 9999;
    pointer-events: none;
    opacity: 0.9;
    image-rendering: auto;
}

/* Forgot password link */
#app a {
    color: #a78bfa !important;
}
#app a:hover {
    color: #c4b5fd !important;
}

/* Error messages */
#app .error, #app .input-help.error {
    color: #f87171 !important;
    font-size: 13px !important;
}

/* Checkbox styling */
#app input[type="checkbox"] {
    accent-color: #7c3aed !important;
}

/* Responsive mascot */
@media (max-width: 640px) {
    #app::before {
        width: 80px;
        height: 80px;
        bottom: 10px;
        right: 10px;
    }
}
</style>
@endsection

@section('container')
    <div id="app"></div>
@endsection
