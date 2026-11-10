<style>
    /* Name: member colour, or an animated gradient, plus optional glow. */
    .chat-name {
        color: var(--chat-name-color, inherit);
        text-shadow: var(--chat-glow, none);
        font-weight: 700;
        letter-spacing: 0.01em;
    }
    .chat-name.chat-name-gradient {
        background: var(--chat-name-gradient);
        background-size: 200% 100%;
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        animation: chat-shimmer 4s ease-in-out infinite alternate;
        /* text-shadow would paint over transparent glyphs; glow via filter. */
        text-shadow: none;
        filter: drop-shadow(var(--chat-glow, 0 0 0 transparent));
    }
    @keyframes chat-shimmer {
        from { background-position: 0% 50%; }
        to { background-position: 100% 50%; }
    }
    @media (prefers-reduced-motion: reduce) {
        .chat-name.chat-name-gradient { animation: none; }
    }

    /* Role badges. */
    .chat-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 4px;
        padding: 1px 6px;
        font-size: 10px;
        font-weight: 700;
        line-height: 1.4;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #fff;
    }
    .chat-badge[data-rank='main_owner'] { background: linear-gradient(135deg, #f59e0b, #dc2626); }
    .chat-badge[data-rank='owner'] { background: #dc2626; }
    .chat-badge[data-rank='mod'] { background: #16a34a; }

    .chat-avatar { box-shadow: 0 0 0 2px transparent; background: rgb(255 255 255 / 0.1); }
    .chat-avatar[data-rank='main_owner'] { box-shadow: 0 0 0 2px #f59e0b; }
    .chat-avatar[data-rank='owner'] { box-shadow: 0 0 0 2px #dc2626; }
    .chat-avatar[data-rank='mod'] { box-shadow: 0 0 0 2px #16a34a; }

    /* One block per message. The member's background is a translucent tint on a
       pseudo-element (the shorthand takes a hex or a gradient) at fixed opacity,
       so the text colour is never touched. */
    .chat-row { position: relative; isolation: isolate; }
    .chat-row:hover { background: rgb(255 255 255 / 0.04); }
    .chat-row::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        border-radius: inherit;
        background: var(--chat-background, transparent);
        opacity: 0.3;
        pointer-events: none;
    }

    /* Roster names: xat-style name effects. Rank effects first, then six stock
       effects for members without an equipped style. */
    .chat-online-name { font-size: 14px; line-height: 1.25; }
    .chat-fx-rank-main_owner,
    .chat-fx-rank-owner,
    .chat-fx-rank-mod { background-size: 200% 100%; -webkit-background-clip: text; background-clip: text; color: transparent; animation: chat-shimmer 3s ease-in-out infinite alternate; }
    .chat-fx-rank-main_owner { background-image: linear-gradient(90deg, #fde68a, #f59e0b, #ef4444, #fde68a); filter: drop-shadow(0 0 4px rgb(245 158 11 / 0.7)); }
    .chat-fx-rank-owner { background-image: linear-gradient(90deg, #fecaca, #ef4444, #fecaca); filter: drop-shadow(0 0 4px rgb(239 68 68 / 0.7)); }
    .chat-fx-rank-mod { background-image: linear-gradient(90deg, #bbf7d0, #22c55e, #bbf7d0); filter: drop-shadow(0 0 3px rgb(34 197 94 / 0.7)); }
    .chat-fx-0 { color: #fff; text-shadow: 0 0 6px #38bdf8, 0 0 2px #38bdf8; }
    .chat-fx-1 { color: #fde047; text-shadow: 0 0 6px #f59e0b, 1px 1px 0 #92400e; }
    .chat-fx-2 { background: linear-gradient(90deg, #f472b6, #a78bfa, #60a5fa, #f472b6); background-size: 200% 100%; -webkit-background-clip: text; background-clip: text; color: transparent; animation: chat-shimmer 3s linear infinite alternate; }
    .chat-fx-3 { background: linear-gradient(90deg, #fb923c, #ef4444, #fde047, #fb923c); background-size: 200% 100%; -webkit-background-clip: text; background-clip: text; color: transparent; animation: chat-shimmer 2s ease-in-out infinite alternate; }
    .chat-fx-4 { color: #86efac; text-shadow: 0 0 5px #16a34a; animation: chat-blink 2.4s ease-in-out infinite; }
    .chat-fx-5 { color: #f5d0fe; text-shadow: 1px 1px 0 #a21caf, -1px -1px 0 #7e22ce; }
    @keyframes chat-blink { 50% { opacity: 0.55; } }
    @media (prefers-reduced-motion: reduce) {
        .chat-online-name, .chat-fx-2, .chat-fx-3, .chat-fx-4 { animation: none !important; }
    }

    /* [spoiler]: hidden until clicked. */
    .chat-mention { font-weight: 600; color: #38bdf8; background: rgb(56 189 248 / 0.12); border-radius: 3px; padding: 0 2px; }
    .chat-row[data-mention='true'] { background: rgb(56 189 248 / 0.08); box-shadow: inset 2px 0 0 #38bdf8; }
    .chat-reply { border-left: 2px solid rgb(255 255 255 / 0.25); padding-left: 6px; }
    .chat-spoiler-img { filter: blur(18px); cursor: pointer; }
    .chat-spoiler { background: currentColor; color: transparent !important; border-radius: 3px; cursor: pointer; user-select: none; }
</style>
