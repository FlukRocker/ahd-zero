import '../css/app.css';

import Alpine from 'alpinejs';

// Expose Alpine for inline Blade usage and debugging.
window.Alpine = Alpine;

// NOTE: Motion (`motion` package, ~130KB) is intentionally NOT imported here.
// The Blade site is server-rendered and static per the SEO decision — no
// scroll-reveal / tilt / parallax. Alpine alone drives the interactive chrome
// (theme toggle, search overlay, drawers, dropdowns). If a future page needs
// animation, lazy-load `motion` on that page only rather than in this global
// entry, so it never lands on the critical path of read pages.

// Appearance store. The <head> FOUC script (layouts/app.blade.php) already
// applied data-theme / .dark before paint from localStorage; this store reads
// that initial state into a reactive `isDark` and keeps it in sync so header
// controls (theme toggle icon) update reactively.
Alpine.store('appearance', {
    isDark: false,
    init() {
        this.isDark = document.documentElement.classList.contains('dark');
    },
    apply(theme) {
        const resolved =
            theme === 'system'
                ? window.matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'dark'
                    : 'light'
                : theme;
        document.documentElement.setAttribute('data-theme', resolved);
        document.documentElement.classList.toggle('dark', resolved === 'dark');
        this.isDark = resolved === 'dark';
        try {
            localStorage.setItem('appearance', theme);
        } catch {
            /* ignore — private mode / storage disabled */
        }
    },
    toggle() {
        this.apply(this.isDark ? 'light' : 'dark');
    },
});

// Bookmark toggle. Optimistic: flip immediately, revert if the write fails,
// so a slow network never makes the button feel dead.
Alpine.data('bookmarkToggle', (catId, initial) => ({
    on: initial,
    busy: false,
    async toggle() {
        if (this.busy) return;
        this.busy = true;
        const next = !this.on;
        this.on = next;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const headers = {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            };
            const res = next
                ? await fetch('/member/bookmarks', {
                      method: 'POST',
                      headers,
                      body: JSON.stringify({ cat_id: catId }),
                  })
                : await fetch(`/member/bookmarks/${catId}`, { method: 'DELETE', headers });
            if (!res.ok) throw new Error(String(res.status));
        } catch {
            this.on = !next;
        } finally {
            this.busy = false;
        }
    },
}));

// Comment section. The list is public — guests read it without signing in —
// so the fetch runs regardless of auth state; only the composer is gated.
Alpine.data('commentSection', (type, id, canPost) => ({
    comments: [],
    page: 1,
    lastPage: 1,
    total: 0,
    loading: true,
    posting: false,
    body: '',
    error: '',
    canPost,

    init() {
        this.load(1);
    },

    async load(page) {
        this.loading = true;
        try {
            const res = await fetch(`/api/comments/${type}/${id}?page=${page}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            const rows = data.data ?? [];
            this.comments = page === 1 ? rows : [...this.comments, ...rows];
            this.page = data.current_page ?? 1;
            this.lastPage = data.last_page ?? 1;
            this.total = data.total ?? 0;
        } catch {
            // Network error or Mongo down — leave the list empty rather than
            // breaking the page around it.
        } finally {
            this.loading = false;
        }
    },

    loadMore() {
        if (this.page < this.lastPage) this.load(this.page + 1);
    },

    async submit() {
        const body = this.body.trim();
        if (!body || this.posting) return;
        this.posting = true;
        this.error = '';
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            // Turnstile renders a hidden input into the form when it is enabled;
            // absent means it is switched off server-side and the field is ignored.
            const token = this.$el.querySelector('[name="cf-turnstile-response"]')?.value ?? '';
            const res = await fetch('/api/comments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    body,
                    commentable_type: type,
                    commentable_id: id,
                    parent_id: null,
                    'cf-turnstile-response': token,
                }),
            });
            if (!res.ok) throw new Error(String(res.status));
            this.body = '';
            window.turnstile?.reset();
            await this.load(1);
        } catch {
            this.error = 'ส่งความคิดเห็นไม่สำเร็จ ลองใหม่อีกครั้ง';
        } finally {
            this.posting = false;
        }
    },

    // Thai relative time. Keeps the markup free of a date library.
    when(iso) {
        if (!iso) return '';
        const then = new Date(iso).getTime();
        if (Number.isNaN(then)) return '';
        const mins = Math.floor((Date.now() - then) / 60000);
        if (mins < 1) return 'เมื่อสักครู่';
        if (mins < 60) return `${mins} นาทีที่แล้ว`;
        const hrs = Math.floor(mins / 60);
        if (hrs < 24) return `${hrs} ชั่วโมงที่แล้ว`;
        const days = Math.floor(hrs / 24);
        if (days < 30) return `${days} วันที่แล้ว`;
        return new Date(then).toLocaleDateString('th-TH');
    },
}));

Alpine.start();
