<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class OtpNotificationService
{
    /**
     * Send OTP via Email
     * 
     * @param string $email
     * @param string $otp
     * @param string $mobile
     * @return bool
     */
    public function sendViaEmail(string $email, string $otp, string $mobile = null): bool
    {
        try {
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::error('Invalid email format', ['email' => $email]);
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
                    ->subject('Your OTP for ' . config('app.name') . ' Login')
                    ->from(
                        config('mail.from.address', 'noreply@insightechindia.in'),
                        config('mail.from.name', 'VDMS')
                    );
            });

            Log::info('OTP email sent successfully', [
                'email' => $email,
                'mobile' => $mobile,
                'timestamp' => now(),
                'mailer' => config('mail.mailer'),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send OTP via email', [
                'email' => $email,
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
     * 
     * @param string $mobile
     * @param string $otp
     * @return bool
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
                'mobile' => $mobile,
                'otp' => $otp,
                'timestamp' => now(),
                'note' => 'SMS provider not yet configured. Using log-only placeholder.',
            ]);

            // In development, also log to stack channel
            if (config('app.debug')) {
                \Log::channel('stack')->info("📱 SMS OTP to {$mobile}: {$otp}");
            }

            return true;
        } catch (Exception $e) {
            Log::error('Error in SMS placeholder', [
                'mobile' => $mobile,
                'error' => $e->getMessage(),
                'timestamp' => now(),
            ]);

            return false;
        }
    }

    /**
     * Send OTP via both Email and SMS
     * 
     * @param string $email
     * @param string $mobile
     * @param string $otp
     * @return array
     */
    public function sendViaEmailAndSms(string $email, string $mobile, string $otp): array
    {
        $emailSent = $this->sendViaEmail($email, $otp, $mobile);
        $smsSent = $this->sendViaSms($mobile, $otp);

        Log::info('OTP notification sent', [
            'email_sent' => $emailSent,
            'sms_sent' => $smsSent,
            'mobile' => $mobile,
        ]);

        return [
            'email_sent' => $emailSent,
            'sms_sent' => $smsSent,
            'both_sent' => $emailSent && $smsSent,
        ];
    }

    /**
     * Send verification success notification
     * 
     * @param string $email
     * @param string $mobile
     * @param string $device_name
     * @return bool
     */
    public function sendVerificationSuccessEmail(string $email, string $mobile, string $device_name): bool
    {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::error('Invalid email format', ['email' => $email]);
                return false;
            }

            $data = [
                'email' => $email,
                'mobile' => $mobile,
                'device_name' => $device_name,
                'timestamp' => now(),
                'app_name' => config('app.name'),
            ];

            Mail::send('emails.verification-success', $data, function ($message) use ($email) {
                $message
                    ->to($email)
                    ->subject('Login Successful - ' . config('app.name'))
                    ->from(
                        config('mail.from.address', 'noreply@insightechindia.in'),
                        config('mail.from.name', 'VDMS')
                    );
            });

            Log::info('Verification success email sent', [
                'email' => $email,
                'device' => $device_name,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send verification success email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send account locked notification
     * 
     * @param string $email
     * @param string $mobile
     * @param string $reason
     * @return bool
     */
    public function sendAccountLockedEmail(string $email, string $mobile, string $reason): bool
    {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::error('Invalid email format', ['email' => $email]);
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

            Mail::send('emails.account-locked', $data, function ($message) use ($email) {
                $message
                    ->to($email)
                    ->subject('Account Locked - ' . config('app.name'))
                    ->from(
                        config('mail.from.address', 'noreply@insightechindia.in'),
                        config('mail.from.name', 'VDMS')
                    );
            });

            Log::warning('Account locked notification sent', [
                'email' => $email,
                'reason' => $reason,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send account locked email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
