<?php

namespace App\Contracts;

/**
 * Activity feeds for the admin dashboard home.
 */
interface AdminFeed
{
    /**
     * Newest comments, or null when the comment store is slow or down so the
     * page can show an empty state instead of failing.
     *
     * @return array<int, array{id: string, user_name: string, body: string, created_at: ?string, deleted: bool}>|null
     */
    public function latestComments(int $limit = 8): ?array;

    /**
     * @return array<int, array{id: int, room: ?string, member_name: ?string, body: string, created_at: ?string}>
     */
    public function recentChat(int $limit = 8): array;

    /**
     * @return array<int, array{action: string, target_member_name: ?string, created_at: ?string}>
     */
    public function modLog(int $limit = 8): array;
}
