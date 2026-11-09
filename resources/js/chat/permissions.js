/**
 * A UI hint that mirrors ChatPolicy. The server is the only authority: every
 * action is re-checked there and a stale hint just earns an inline 403.
 */
export const RANK_LEVEL = { member: 1, mod: 2, owner: 3, main_owner: 4 };

export function allowedActions(myRank, myUuid, canPost, line) {
    // Every moderation route needs a logged-in member.
    if (!canPost || !myUuid || !Object.hasOwn(RANK_LEVEL, myRank ?? '')) {
        return [];
    }

    const mine = line.uuid === myUuid;

    // A cached line from before ranks were shipped: author's rank unknown.
    if (!Object.hasOwn(RANK_LEVEL, line.rank ?? '')) {
        return mine ? ['delete'] : [];
    }

    const myLevel = RANK_LEVEL[myRank];
    const outranks = myLevel > RANK_LEVEL[line.rank];
    const actions = [];

    if (mine || outranks) {
        actions.push('delete');
    }

    if (!mine && outranks) {
        if (myLevel >= 2) {
            actions.push('gag', 'kick');
        }

        if (myLevel >= 3) {
            actions.push('ban');
        }
    }

    return actions;
}

/** A logged-in poster may report anyone's line but their own. Server re-checks. */
export function canReport(myUuid, canPost, line) {
    return !!canPost && !!myUuid && !!line.uuid && line.uuid !== myUuid;
}
