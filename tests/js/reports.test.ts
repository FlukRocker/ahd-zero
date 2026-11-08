// @vitest-environment happy-dom
import { submitReport } from '@/lib/reports';
import { afterEach, describe, expect, it, vi } from 'vitest';

const respond = (status: number, body: unknown = {}) =>
    vi.fn(async () => new Response(JSON.stringify(body), { status }));

describe('submitReport', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('posts the right JSON and maps 201 to created', async () => {
        const fetchMock = respond(201, { ok: true });
        vi.stubGlobal('fetch', fetchMock);

        await expect(
            submitReport('chat_message', '7', 'spam', 'ads'),
        ).resolves.toBe('created');

        const [url, init] = fetchMock.mock.calls[0] as unknown as [
            string,
            RequestInit,
        ];
        expect(url).toBe('/reports');
        expect(init.method).toBe('POST');
        expect(JSON.parse(init.body as string)).toEqual({
            target_type: 'chat_message',
            target_id: '7',
            reason: 'spam',
            note: 'ads',
        });
    });

    it('maps 200 to duplicate', async () => {
        vi.stubGlobal('fetch', respond(200, { ok: true }));

        await expect(submitReport('comment', 'abc', 'abuse', '')).resolves.toBe(
            'duplicate',
        );
    });

    it('omits an empty note', async () => {
        const fetchMock = respond(201);
        vi.stubGlobal('fetch', fetchMock);

        await submitReport('comment', 'abc', 'other', '  ');

        const init = (
            fetchMock.mock.calls[0] as unknown as [string, RequestInit]
        )[1];
        expect(JSON.parse(init.body as string)).not.toHaveProperty('note');
    });

    it('throws with the server message on other statuses', async () => {
        vi.stubGlobal('fetch', respond(422, { message: 'nope' }));

        await expect(
            submitReport('comment', 'abc', 'spam', ''),
        ).rejects.toThrow('nope');
    });
});
