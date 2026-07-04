@extends('emails.layouts.email_layout')

@section('content')
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <!-- Header -->
        <div
            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="color: white; margin: 0; font-size: 28px;">{{ $app_name }}</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0;">OTP Verification</p>
        </div>

        <!-- Body -->
        <div style="background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-top: none;">
            <h2 style="color: #333; font-size: 20px; margin-top: 0;">Your Login OTP</h2>

            <p style="color: #666; line-height: 1.6;">
                Hello,<br><br>
                You requested a One-Time Password (OTP) to securely log in to your {{ $app_name }} account.
            </p>

            <!-- OTP Box -->
            <div
                style="background: white; padding: 25px; text-align: center; border: 2px dashed #667eea; border-radius: 8px; margin: 25px 0;">
                <p style="color: #999; font-size: 12px; margin: 0 0 10px 0; text-transform: uppercase;">Your OTP Code</p>
                <p
                    style="color: #667eea; font-size: 36px; font-weight: bold; letter-spacing: 8px; margin: 10px 0; font-family: 'Courier New', monospace;">
                    {{ $otp }}</p>
                <p style="color: #999; font-size: 12px; margin: 10px 0 0 0;">Valid for {{ $expires_in }} minutes</p>
            </div>

            <!-- Security Info -->
            <div
                style="background: #fffbea; padding: 15px; border-left: 4px solid #ffc107; border-radius: 4px; margin: 20px 0;">
                <p style="color: #856404; margin: 0; font-size: 13px;">
                    <strong>⚠️ Security Notice:</strong>
                    Never share this OTP with anyone. We will never ask you for this code via email or phone.
                </p>
            </div>

            <!-- Device Info -->
            @if ($mobile)
                <div style="background: #f0f0f0; padding: 15px; border-radius: 4px; margin: 20px 0; font-size: 13px;">
                    <p style="color: #666; margin: 0 0 8px 0;"><strong>Account Details:</strong></p>
                    <p style="color: #666; margin: 5px 0;">📱 Mobile:
                        {{ substr($mobile, 0, 3) }}****{{ substr($mobile, -3) }}</p>
                    <p style="color: #666; margin: 5px 0;">📧 Email: {{ substr($email, 0, 3) }}***{{ substr($email, -8) }}
                    </p>
                </div>
            @endif

            <!-- Action Box -->
            <div style="background: white; padding: 20px; border-radius: 4px; margin: 20px 0; text-align: center;">
                <p style="color: #333; margin: 0 0 15px 0;">Enter this OTP in the login form to continue:</p>
                <a href="{{ $app_url }}"
                    style="display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                    Go to Login
                </a>
            </div>

            <!-- Footer Info -->
            <div style="color: #999; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                <p style="margin: 0 0 8px 0;">
                    ⏰ If you didn't request this OTP, please ignore this email and your account will remain secure.
                </p>
                <p style="margin: 0;">
                    For support, contact: <a href="mailto:support@bmpl.com"
                        style="color: #667eea; text-decoration: none;">support@bmpl.com</a>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div
            style="background: #333; color: #999; padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px;">
            <p style="margin: 0;">
                {{ $app_name }} • <a href="https://waba.insightechindia.in"
                    style="color: #667eea; text-decoration: none;">Visit us</a>
            </p>
            <p style="margin: 5px 0 0 0;">
                © {{ date('Y') }} InsightEch India. All rights reserved.
            </p>
        </div>
    </div>
@endsection
