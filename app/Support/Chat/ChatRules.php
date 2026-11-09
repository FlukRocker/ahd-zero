<?php

namespace App\Support\Chat;

use App\Support\SiteSettings;

/**
 * The house rules a member accepts before their first message. The text is
 * editable from the dashboard and lives in the site settings file; an empty
 * saved text switches the gate off. The version is a hash of the text, so
 * editing the rules asks everyone to accept again.
 */
class ChatRules
{
    public const DEFAULT = "1. สุภาพ ไม่ด่าทอ ไม่คุกคามผู้อื่น\n2. ห้ามแนบลิงก์ ห้ามโฆษณา ห้ามสแปม\n3. ห้ามโพสต์เนื้อหาผิดกฎหมาย ลามกอนาจาร หรือละเมิดลิขสิทธิ์\n4. ห้ามเปิดเผยข้อมูลส่วนตัวของผู้อื่น\n5. ทีมงานอาจลบข้อความหรือระงับการใช้แชทได้โดยไม่ต้องแจ้งล่วงหน้า";

    public const MAX = 2000;

    public static function text(): string
    {
        $stored = SiteSettings::read();

        return array_key_exists('chat_rules', $stored) && is_string($stored['chat_rules'])
            ? $stored['chat_rules']
            : self::DEFAULT;
    }

    public static function set(string $text): void
    {
        // Keep newlines, drop every other control character.
        $clean = (string) preg_replace('/[^\P{C}\n]/u', '', str_replace("\r", '', $text));

        SiteSettings::set('chat_rules', trim($clean));
    }

    /**
     * @return array{text: string, version: string}|null
     */
    public static function forClient(): ?array
    {
        $text = trim(self::text());

        return $text === '' ? null : ['text' => $text, 'version' => substr(sha1($text), 0, 12)];
    }
}
