export type ReportTargetType = 'comment' | 'chat_message';
export type ReportReason = 'spam' | 'abuse' | 'other';

function csrfToken(): string {
    const cookie = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    if (cookie) {
        return decodeURIComponent(cookie[1]);
    }

    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

/**
 * Report a comment or chat message. 201 means a new report, 200 means this
 * member had already reported it. Anything else throws with the server's
 * message so the caller can show it.
 */
export async function submitReport(
    type: ReportTargetType,
    id: string,
    reason: ReportReason,
    note: string,
): Promise<'created' | 'duplicate'> {
    const body: Record<string, string> = {
        target_type: type,
        target_id: id,
        reason,
    };

    if (note.trim() !== '') {
        body.note = note.trim();
    }

    const response = await fetch('/reports', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    if (response.status === 201) {
        return 'created';
    }

    if (response.status === 200) {
        return 'duplicate';
    }

    const payload = await response.json().catch(() => ({}));

    throw new Error(payload.message ?? 'ส่งรายงานไม่สำเร็จ');
}
