import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';
// @ts-expect-error untyped font file
import font from '@fontsource-variable/ibm-plex-sans/files/ibm-plex-sans-latin-wght-normal.woff2';

export default createGlobalStyle`
    :root {
        --primary-purple: #8B5CF6;
        --secondary-blue: #3B82F6;
        --white: #FFFFFF;
        --black: #07070B;
        --gray: #6B7280;
        --accent-teal: #06B6D4;
        --accent-pink: #8B5CF6;
        --accent-lime: #10B981;
        --el7-accent-light: #A78BFA;
        --el7-bg-1: #07070B;
        --el7-bg-2: #0B0B10;
        --el7-bg-3: #111117;
        --el7-surface: #0B0B10;
        --el7-surface-soft: #0E0E15;
        --el7-surface-strong: #111117;
        --el7-surface-raised: #15151D;
        --el7-border: rgba(139, 92, 246, 0.34);
        --el7-border-soft: rgba(139, 92, 246, 0.18);
        --el7-accent: var(--primary-purple);
        --el7-accent-2: var(--secondary-blue);
        --el7-text: var(--white);
        --el7-text-muted: #A3A3B2;
        --el7-text-dim: #74748A;
        --el7-danger: #EF4444;
        --el7-success: #10B981;
        --el7-warning: #F59E0B;
        --el7-shadow: 0 18px 48px rgba(0, 0, 0, 0.52);
        --el7-shadow-deck: 0 22px 54px rgba(0, 0, 0, 0.58), 0 1px 0 rgba(255, 255, 255, 0.045) inset;
        --el7-glow: 0 0 28px rgba(139, 92, 246, 0.24);
        --el7-telemetry-cyan: #22D3EE;
        --el7-telemetry-amber: #FBBF24;
        --el7-perspective: 1200px;
        --el7-route-z: 22px;
        --el7-route-tilt: 1.2deg;
        --el7-duration-fast: 140ms;
        --el7-duration: 220ms;
        --el7-duration-slow: 360ms;
        --el7-ease: cubic-bezier(0.4, 0, 0.2, 1);
        --el7-ease-out: cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes danex-fade-up {
        0% { opacity: 0; transform: translateY(14px); filter: blur(4px); }
        100% { opacity: 1; transform: translateY(0); filter: blur(0); }
    }

    @keyframes danex-pulse-border {
        0%, 100% { box-shadow: var(--el7-shadow), 0 0 0 rgba(139, 92, 246, 0); }
        50% { box-shadow: var(--el7-shadow), 0 0 26px rgba(139, 92, 246, 0.18); }
    }

    @keyframes danex-row-scan {
        0% { box-shadow: inset 0 0 0 rgba(139, 92, 246, 0); }
        45% { box-shadow: inset 3px 0 0 rgba(139, 92, 246, 0.68), 0 0 22px rgba(139, 92, 246, 0.12); }
        100% { box-shadow: inset 1px 0 0 rgba(139, 92, 246, 0.2); }
    }

    @keyframes danex-background-drift {
        0%, 100% { transform: translate3d(0, 0, 0); opacity: 0.78; }
        50% { transform: translate3d(0, 18px, 0); opacity: 1; }
    }

    @keyframes danex-scanline {
        0% { transform: translateY(-18%); opacity: 0; }
        20%, 58% { opacity: 0.5; }
        100% { transform: translateY(118%); opacity: 0; }
    }

    @keyframes danex-spinner-rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    @keyframes danex-deck-arrive {
        0% { opacity: 0; transform: translate3d(18px, 18px, -32px) rotateX(1.4deg) rotateY(-1.2deg) scale(0.986); }
        100% { opacity: 1; transform: translate3d(0, 0, 0) rotateX(0) rotateY(0) scale(1); }
    }

    @font-face {
        font-family: 'IBM Plex Sans';
        font-style: normal;
        font-display: swap;
        font-weight: 100 700;
        src: url(${font}) format('woff2-variations');
        unicode-range: U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
    }

    body {
        ${tw`font-sans text-neutral-200`};
        letter-spacing: 0;
        background:
            radial-gradient(circle at 15% -10%, rgba(139, 92, 246, 0.12), transparent 34rem),
            radial-gradient(circle at 88% 0%, rgba(6, 182, 212, 0.06), transparent 28rem),
            linear-gradient(rgba(139, 92, 246, 0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(139, 92, 246, 0.024) 1px, transparent 1px),
            var(--el7-bg-1);
        background-size: auto, auto, 46px 46px, 46px 46px, auto;
        background-attachment: fixed;
        min-height: 100vh;
        color: var(--el7-text);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 0;
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.035), transparent 22rem),
            linear-gradient(90deg, transparent, rgba(139, 92, 246, 0.04), transparent),
            radial-gradient(circle at 32% 70%, rgba(16, 185, 129, 0.035), transparent 28rem),
            radial-gradient(circle at 72% 64%, rgba(245, 158, 11, 0.026), transparent 26rem);
        opacity: 0.9;
        animation: danex-background-drift 16s ease-in-out infinite;
    }

    body::after {
        content: '';
        position: fixed;
        left: 0;
        right: 0;
        top: 0;
        height: 34vh;
        pointer-events: none;
        z-index: 0;
        background: linear-gradient(180deg, transparent, rgba(139, 92, 246, 0.055), transparent);
        mix-blend-mode: screen;
        animation: danex-scanline 9s linear infinite;
    }

    @media (max-width: 640px) {
        body {
            background-size: auto, auto, 34px 34px, 34px 34px, auto;
        }
    }

    h1, h2, h3, h4, h5, h6 {
        ${tw`font-medium tracking-normal font-header`};
    }

    p {
        ${tw`leading-snug font-sans`};
        color: inherit;
    }

    #app, #root {
        min-height: 100vh;
        position: relative;
        z-index: 1;
    }

    a, button {
        transition: color var(--el7-duration) var(--el7-ease), border-color var(--el7-duration) var(--el7-ease), background-color var(--el7-duration) var(--el7-ease), box-shadow var(--el7-duration) var(--el7-ease), transform var(--el7-duration) var(--el7-ease);
    }

    button {
        line-height: 1.15;
    }

    button + button,
    a + button,
    button + a {
        margin-left: 0.35rem;
    }

    @media (max-width: 640px) {
        button + button,
        a + button,
        button + a {
            margin-left: 0;
            margin-top: 0.35rem;
        }
    }

    button:hover {
        transform: translateY(-1px);
    }

    .el7-glass {
        background: var(--el7-surface);
        border: 1px solid var(--el7-border);
        box-shadow: var(--el7-shadow);
    }

    .el7-route-shell {
        position: relative;
        perspective: var(--el7-perspective);
        transform-style: preserve-3d;
        padding: clamp(0.7rem, 1.8vw, 1.35rem);
    }

    .el7-route-shell::before {
        content: '';
        position: absolute;
        inset: 0.35rem 0.2rem auto;
        height: 9rem;
        pointer-events: none;
        border-radius: 1.4rem;
        background: linear-gradient(105deg, rgba(34, 211, 238, 0.09), rgba(139, 92, 246, 0.08) 42%, rgba(251, 191, 36, 0.035));
        filter: blur(18px);
        opacity: 0.72;
        transform: translateZ(-34px);
    }

    .el7-route-panel,
    .el7-ops-card,
    .el7-hero-panel {
        position: relative;
        overflow: hidden;
        background: linear-gradient(145deg, rgba(18, 18, 26, 0.96), rgba(7, 7, 11, 0.98));
        border: 1px solid rgba(139, 92, 246, 0.24);
        border-radius: 1.05rem;
        box-shadow: var(--el7-shadow-deck);
        transform: translateZ(0);
    }

    .el7-route-panel::before,
    .el7-ops-card::before,
    .el7-hero-panel::before {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
        border-top: 1px solid rgba(255, 255, 255, 0.075);
        background:
            linear-gradient(120deg, transparent 8%, rgba(34, 211, 238, 0.045), transparent 36%),
            repeating-linear-gradient(90deg, rgba(255,255,255,0.018) 0 1px, transparent 1px 54px);
        opacity: 0.78;
    }

    .el7-ops-card,
    .el7-lift-3d {
        transition: transform var(--el7-duration) var(--el7-ease-out), border-color var(--el7-duration) var(--el7-ease), box-shadow var(--el7-duration) var(--el7-ease);
    }

    .el7-ops-card:hover,
    .el7-lift-3d:hover {
        transform: translate3d(0, -3px, 18px) rotateX(0.45deg);
        border-color: rgba(34, 211, 238, 0.38);
        box-shadow: var(--el7-shadow-deck), 0 0 34px rgba(34, 211, 238, 0.12);
    }

    .el7-telemetry-kicker {
        color: var(--el7-telemetry-cyan);
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }

    .danex-monitor-surface {
        position: relative;
        overflow: hidden;
        background: var(--el7-surface);
        border: 1px solid var(--el7-border-soft);
        box-shadow: var(--el7-shadow);
        animation: danex-fade-up 360ms var(--el7-ease) both;
    }

    .danex-monitor-surface::before {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .danex-monitor-surface:hover {
        border-color: var(--el7-border);
        box-shadow: var(--el7-shadow), var(--el7-glow);
    }

    .el7-panel,
    .el7-form-panel,
    .el7-table-shell,
    .el7-response {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, rgba(17, 17, 24, 0.96), rgba(8, 8, 13, 0.98));
        border: 1px solid var(--el7-border-soft);
        border-radius: 0.875rem;
        box-shadow: var(--el7-shadow);
    }

    .el7-panel::before,
    .el7-form-panel::before,
    .el7-table-shell::before,
    .el7-response::before {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        background: linear-gradient(110deg, transparent 15%, rgba(139, 92, 246, 0.04), transparent 62%);
        opacity: 0.62;
    }

    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    ::-webkit-scrollbar-track {
        background: #07070b;
    }

    ::-webkit-scrollbar-thumb {
        background: #2a3348;
        border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #50607c;
    }

    ::-webkit-scrollbar-corner {
        background: transparent;
    }

    input, textarea, select {
        ${tw`text-neutral-200`};
    }

    input::placeholder, textarea::placeholder {
        ${tw`text-neutral-500`};
    }

    a {
        ${tw`text-violet-400 no-underline transition-colors duration-150`};
    }

    a:hover {
        ${tw`text-violet-300`};
    }
`;

