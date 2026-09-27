<?php

namespace App\Services;

use App\Services\Platform\Comms\ContactService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Legacy mobile-login notifications. BUG-189 (DEC-070): logs never carry the OTP, and phone numbers / emails
 are masked with ContactService::mask().
 */
class OtpNotificationService
{
    private function mask(?string $address): string
    {
        return app(ContactService::class)->mask($address);
    }

    /**
     * Send OTP via Email
     */
    public function sendViaEmail(string $email, string $otp, ?string $mobile = null): bool
    {
        try {
            // Validate email
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::error('Invalid email format', ['email' => $this->mask($email)]);

                return false;
            }

            $data = [
                'email' => $email,
                'otp' => $otp,
                'mobile' => $mobile,
                'expires_in' => 10, // minutes
                'app_name' => config('app.name', 'VDMS'),
                'app_url' => config('app.url'),
            ];

            // Send email using Mailable class (more reliable)
            Mail::send('emails.otp_notification_email', $data, function ($message) use ($email) {
                $message
                    ->to($email)
                    ->subject('Your OTP for '.config('app.name').' Login')
                    ->from(
                        config('mail.from.address', 'noreply@insightechindia.in'),
                        config('mail.from.name', 'VDMS')
                    );
            });

            Log::info('OTP email sent successfully', [
                'email' => $this->mask($email),
                'mobile' => $this->mask($mobile),
                'timestamp' => now(),
                'mailer' => config('mail.mailer'),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send OTP via email', [
                'email' => $this->mask($email),
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'timestamp' => now(),
            ]);

            return false;
        }
    }

    /**
     * Send OTP via SMS (Placeholder for future SMS provider integration)
     *
     * Currently logs to console and database.
     * Will be replaced with actual SMS provider (Twilio, AWS SNS, etc.)
     */
    public function sendViaSms(string $mobile, string $otp): bool
    {
        try {
            // TODO: Integrate actual SMS provider here
            // Supported providers:
            // - Twilio (recommended)
            // - AWS SNS
            // - Nexmo/Vonage
            // - MSG91
            // - Kaleyra

            Log::info('SMS would be sent (placeholder)', [
                'mobile' => $this->mask($mobile),
                'timestamp' => now(),
                'note' => 'SMS provider not yet configured. Using log-only placeholder.',
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Error in SMS placeholder', [
                'mobile' => $this->mask($mobile),
                'error' => $e->getMessage(),
                'timestamp' => now(),
            ]);

            return false;
        }
    }

    /**
     * Send OTP via both Email and SMS
     */
    public function sendViaEmailAndSms(string $email, string $mobile, string $otp): array
    {
        $emailSent = $this->sendViaEmail($email, $otp, $mobile);
        $smsSent = $this->sendViaSms($mobile, $otp);

        Log::info('OTP notification sent', [
            'email_sent' => $emailSent,
            'sms_sent' => $smsSent,
            'mobile' => $this->mask($mobile),
        ]);

        return [
            'email_sent' => $emailSent,
            'sms_sent' => $smsSent,
            'both_sent' => $emailSent && $smsSent,
        ];
    }

    /**
     * Send verification success notification
     */
    public function sendVerificationSuccessEmail(string $email, string $mobile, string $device_name): bool
    {
        try {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::error('Invalid email format', ['email' => $this->mask($email)]);

                return false;
            }

            $data = [
                'email' => $email,
                'mobile' => $mobile,
                'device_name' => $device_name,
                'timestamp' => now(),
                'app_name' => config('app.name'),
            ];

            Mail::send('emails.verification_success_email', $data, function ($message) use ($email) {
                $message
                    ->to($email)
                    ->subject('Login Successful - '.config('app.name'))
                    ->from(
                        config('mail.from.address', 'noreply@insightechindia.in'),
                        config('mail.from.name', 'VDMS')
                    );
            });

            Log::info('Verification success email sent', [
                'email' => $this->mask($email),
                'device' => $device_name,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send verification success email', [
                'email' => $this->mask($email),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send account locked notification
     */
    public function sendAccountLockedEmail(string $email, string $mobile, string $reason): bool
    {
        try {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::error('Invalid email format', ['email' => $this->mask($email)]);

                return false;
            }

            $data = [
                'email' => $email,
                'mobile' => $mobile,
                'reason' => $reason,
                'locked_until' => now()->addMinutes(30),
                'support_email' => config('mail.from.address', 'support@insightechindia.in'),
                'app_name' => config('app.name'),
            ];

            Mail::send('emails.account_locked_email', $data, function ($message) use ($email) {
                $message
                    ->to($email)
                    ->subject('Account Locked - '.config('app.name'))
                    ->from(
                        config('mail.from.address', 'noreply@insightechindia.in'),
                        config('mail.from.name', 'VDMS')
                    );
            });

            Log::warning('Account locked notification sent', [
                'email' => $this->mask($email),
                'reason' => $reason,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send account locked email', [
                'email' => $this->mask($email),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
