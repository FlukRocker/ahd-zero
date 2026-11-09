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

    /* [spoiler]: hidden until clicked. */
    .chat-spoiler-img { filter: blur(18px); cursor: pointer; }
    .chat-spoiler { background: currentColor; color: transparent !important; border-radius: 3px; cursor: pointer; user-select: none; }
</style>
