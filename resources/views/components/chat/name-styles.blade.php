<style>
    .chat-name {
        color: var(--chat-name-color, inherit);
        text-shadow: var(--chat-glow, none);
    }
    .chat-name.chat-name-gradient {
        background: var(--chat-name-gradient);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    /* One block per message. The member's background is a translucent tint on a
       pseudo-element (the shorthand takes a hex or a gradient) at fixed opacity,
       so the text colour is never touched. */
    .chat-row { position: relative; isolation: isolate; }
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
</style>
