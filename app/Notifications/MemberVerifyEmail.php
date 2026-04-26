<?php

namespace App\Notifications;

use App\Models\Member;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

use function config;
use function sha1;

/**
 * Email verification mail for the `member` guard. Mirrors Laravel's built-in
 * `Illuminate\Auth\Notifications\VerifyEmail` but signs URLs against the
 * member-specific route (`member.verification.verify`) instead of the
 * default `verification.verify` (which lives on the admin/web guard).
 */
class MemberVerifyEmail extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $appName = (string) config('app.name', 'Anime HD Zero');

        return (new MailMessage)
            ->subject("ยืนยันอีเมลของคุณ - {$appName}")
            ->greeting("สวัสดี {$notifiable->name}")
            ->line('ขอบคุณที่สมัครสมาชิก กรุณายืนยันอีเมลของคุณโดยกดปุ่มด้านล่าง')
            ->action('ยืนยันอีเมล', $url)
            ->line('หากคุณไม่ได้สมัครสมาชิก กรุณาเพิกเฉยต่ออีเมลฉบับนี้')
            ->salutation('ขอบคุณ');
    }

    private function verificationUrl(Member $notifiable): string
    {
        $expireMinutes = (int) config('auth.verification.expire', 60);

        return URL::temporarySignedRoute(
            'member.verification.verify',
            Carbon::now()->addMinutes($expireMinutes),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
