// Small JSON helper for the chat admin tools. Unlike the page's own send(), it
// does not reload Inertia props, so lazy tabs stay cheap.

export interface CallResult<T> {
    ok: boolean;
    status: number;
    data: T | null;
    error: string;
}

export async function adminCall<T = Record<string, unknown>>(
    method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
    url: string,
    body?: object,
): Promise<CallResult<T>> {
    try {
        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = (await res.json().catch(() => null)) as
            | (T & { message?: string })
            | null;

        return {
            ok: res.ok,
            status: res.status,
            data,
            error: res.ok
                ? ''
                : (data?.message ?? `Request failed (${res.status})`),
        };
    } catch {
        return { ok: false, status: 0, data: null, error: 'Network error' };
    }
}

export const ui = {
    field: 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
    btn: 'inline-flex h-8 items-center justify-center rounded-md border border-input bg-background px-3 text-xs font-medium hover:bg-accent disabled:opacity-50',
    btnPrimary:
        'inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50',
    btnDanger:
        'inline-flex h-8 items-center justify-center rounded-md border border-destructive/40 px-3 text-xs font-medium text-destructive hover:bg-destructive/10 disabled:opacity-50',
    card: 'rounded-xl border bg-card text-card-foreground shadow-sm',
};
