<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class ResetPasswordNotification extends Notification
{
    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    /**
     * The callback that should be used to create the reset password URL.
     *
     * @var \Closure|null
     */
    public static $createUrlCallback;

    /**
     * The callback that should be used to build the mail message.
     *
     * @var \Closure|null
     */
    public static $toMailCallback;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        return $this->buildMailMessage($notifiable, $this->token);
    }

    protected function buildMailMessage($notifiable, $token)
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $url = $frontendUrl.'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset());
        $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject(Lang::get('Reset Your SLMS Password'))
            ->greeting(Lang::get('Hello, :name!', ['name' => $notifiable->name ?? 'there']))
            ->line(Lang::get('We received a request to reset the password for your SLMS account.'))
            ->action(Lang::get('Reset Password'), $url)
            ->line(Lang::get('This link will expire in :count minutes for your security.', ['count' => $expireMinutes]))
            ->line(Lang::get("If you didn't request a password reset, no action is needed — your account is still secure."))
            ->salutation(Lang::get("Regards,\nThe SLMS Team"));
    }

    public static function createUrlUsing($callback)
    {
        static::$createUrlCallback = $callback;
    }

    public static function toMailUsing($callback)
    {
        static::$toMailCallback = $callback;
    }
}
