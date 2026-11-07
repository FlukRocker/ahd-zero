// @vitest-environment happy-dom
import { describe, expect, it } from 'vitest';
import { allowedActions } from '../../resources/js/chat/permissions';

const line = (uuid: string, rank?: string) => (rank === undefined ? { uuid } : { uuid, rank });

describe('allowedActions', () => {
    it('test_guest_and_unknown_rank_get_no_actions', () => {
        expect(allowedActions(null, null, false, line('b', 'member'))).toEqual([]);
        expect(allowedActions(null, 'a', true, line('b', 'member'))).toEqual([]);
        expect(allowedActions('wizard', 'a', true, line('b', 'member'))).toEqual([]);
    });

    it('test_member_may_only_delete_own_message', () => {
        expect(allowedActions('member', 'a', true, line('a', 'member'))).toEqual(['delete']);
        expect(allowedActions('member', 'a', true, line('b', 'member'))).toEqual([]);
    });

    it('test_mod_may_delete_gag_kick_a_member_but_not_ban', () => {
        expect(allowedActions('mod', 'a', true, line('b', 'member'))).toEqual(['delete', 'gag', 'kick']);
    });

    it('test_mod_cannot_touch_another_mod_or_higher', () => {
        expect(allowedActions('mod', 'a', true, line('b', 'mod'))).toEqual([]);
        expect(allowedActions('mod', 'a', true, line('b', 'owner'))).toEqual([]);
    });

    it('test_owner_may_ban_a_mod_but_not_an_owner', () => {
        expect(allowedActions('owner', 'a', true, line('b', 'mod'))).toEqual(['delete', 'gag', 'kick', 'ban']);
        expect(allowedActions('owner', 'a', true, line('b', 'owner'))).toEqual([]);
    });

    it('test_nobody_gets_actions_on_their_own_message_except_delete', () => {
        expect(allowedActions('main_owner', 'a', true, line('a', 'main_owner'))).toEqual(['delete']);
        expect(allowedActions('mod', 'a', true, line('a', 'mod'))).toEqual(['delete']);
    });

    it('test_unverified_viewer_gets_no_actions', () => {
        expect(allowedActions('owner', 'a', false, line('b', 'member'))).toEqual([]);
    });

    it('test_line_without_a_rank_key_only_allows_deleting_my_own_message', () => {
        expect(allowedActions('owner', 'a', true, line('a'))).toEqual(['delete']);
        expect(allowedActions('owner', 'a', true, line('b'))).toEqual([]);
    });
});
