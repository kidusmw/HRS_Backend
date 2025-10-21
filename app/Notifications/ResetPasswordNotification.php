<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPasswordNotification
{
    /**
     * Build the mail representation of the notification.
     * 
     * Security: This notification does not log or expose reset tokens
     * to prevent information leakage.
     */
    public function toMail($notifiable)
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Reset Password Notification')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $url)
            ->line('This password reset link will expire in 60 minutes.')
            ->line('If you did not request a password reset, no further action is required.')
            ->line('For security reasons, this link can only be used once.');
    }

    /**
     * Get the reset URL for the given notifiable.
     * 
     * Security: The URL is generated securely and tokens are not logged.
     */
    protected function resetUrl($notifiable)
    {
        $frontend = rtrim(env('FRONTEND_BASE_URL', 'http://localhost:5173'), '/');
        
        return $frontend . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
