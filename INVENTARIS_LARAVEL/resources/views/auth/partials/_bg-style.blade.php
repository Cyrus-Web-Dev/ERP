<style>
    @keyframes authFloat1 {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(30px, -40px) scale(1.08); }
    }
    @keyframes authFloat2 {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(-40px, 30px) scale(1.05); }
    }
    @keyframes authFloat3 {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(20px, 20px) scale(1.1); }
    }
    @keyframes authDrift {
        to { stroke-dashoffset: -1000; }
    }
    @keyframes authCardIn {
        from { opacity: 0; transform: translateY(24px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes authLogoGlow {
        0%, 100% { box-shadow: 0 0 0 0 rgba(124, 58, 237, 0.35), 0 0 30px 4px rgba(59, 130, 246, 0.25); }
        50% { box-shadow: 0 0 0 10px rgba(124, 58, 237, 0), 0 0 45px 10px rgba(45, 212, 191, 0.35); }
    }
    @keyframes authSparkle {
        0%, 100% { opacity: .3; transform: scale(0.85) rotate(0deg); }
        50% { opacity: 1; transform: scale(1.15) rotate(15deg); }
    }

    .auth-bg { background: radial-gradient(ellipse at top, #0f1424 0%, #05070d 65%); }
    .auth-blob { position: absolute; border-radius: 9999px; filter: blur(60px); pointer-events: none; }
    .auth-blob-1 { background: radial-gradient(circle, rgba(124,58,237,.55), transparent 70%); animation: authFloat1 14s ease-in-out infinite; }
    .auth-blob-2 { background: radial-gradient(circle, rgba(45,212,191,.45), transparent 70%); animation: authFloat2 16s ease-in-out infinite; }
    .auth-blob-3 { background: radial-gradient(circle, rgba(53,104,247,.45), transparent 70%); animation: authFloat3 12s ease-in-out infinite; }

    .auth-card { animation: authCardIn .7s cubic-bezier(.22,1,.36,1) both; }
    .auth-logo-ring { animation: authLogoGlow 3.5s ease-in-out infinite; }
    .auth-sparkle { animation: authSparkle 3s ease-in-out infinite; }
    .auth-sparkle-delay { animation-delay: 1.2s; }

    .auth-input {
        transition: border-color .2s ease, box-shadow .2s ease, transform .15s ease;
    }
    .auth-input:focus {
        transform: translateY(-1px);
    }

    .auth-btn {
        transition: transform .15s ease, box-shadow .2s ease, background-position .4s ease;
        background-size: 160% 160%;
    }
    .auth-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 25px -5px rgba(37, 75, 234, .45); }
    .auth-btn:active { transform: translateY(0); }

    @media (prefers-reduced-motion: reduce) {
        .auth-blob-1, .auth-blob-2, .auth-blob-3, .auth-card, .auth-logo-ring, .auth-sparkle { animation: none !important; }
    }
</style>
