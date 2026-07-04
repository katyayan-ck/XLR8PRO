@extends('layouts.email')

@section('content')
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <!-- Header -->
        <div
            style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="color: white; margin: 0; font-size: 28px;">✓ Login Successful</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0;">{{ $app_name }}</p>
        </div>

        <!-- Body -->
        <div style="background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-top: none;">
            <h2 style="color: #333; font-size: 20px; margin-top: 0;">Welcome Back!</h2>

            <p style="color: #666; line-height: 1.6;">
                Your account has been successfully authenticated. You're now logged in.
            </p>

            <!-- Device Info Box -->
            <div
                style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;">
                <p style="color: #333; font-weight: bold; margin: 0 0 15px 0;">📱 Login Device Details:</p>

                <div style="background: #f0f0f0; padding: 15px; border-radius: 4px;">
                    <p style="color: #666; margin: 5px 0;">
                        <strong>Device Name:</strong> {{ $device_name }}
                    </p>
                    <p style="color: #666; margin: 5px 0;">
                        <strong>Mobile:</strong> {{ substr($mobile, 0, 3) }}****{{ substr($mobile, -3) }}
                    </p>
                    <p style="color: #666; margin: 5px 0;">
                        <strong>Email:</strong> {{ substr($email, 0, 3) }}***@{{ substr(strrchr($email, '@'), 1) }}
                    </p>
                    <p style="color: #666; margin: 5px 0;">
                        <strong>Login Time:</strong> {{ $timestamp->format('d M Y, h:i A') }}
                    </p>
                </div>
            </div>

            <!-- Security Notice -->
            <div
                style="background: #e8f5e9; padding: 15px; border-left: 4px solid #28a745; border-radius: 4px; margin: 20px 0;">
                <p style="color: #2e7d32; margin: 0; font-size: 13px;">
                    <strong>✓ Security:</strong>
                    This login was completed from a registered device. If this wasn't you, please contact support
                    immediately.
                </p>
            </div>

            <!-- Quick Actions -->
            <div style="background: white; padding: 20px; border-radius: 4px; margin: 20px 0; text-align: center;">
                <p style="color: #333; margin: 0 0 15px 0;">Quick Actions:</p>
                <div style="display: inline-block; margin: 0 10px;">
                    <a href="{{ $app_url }}"
                        style="display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                        Go to Dashboard
                    </a>
                </div>
                <div style="display: inline-block; margin: 0 10px;">
                    <a href="{{ $app_url }}/settings/security"
                        style="display: inline-block; background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                        Security Settings
                    </a>
                </div>
            </div>

            <!-- Footer Info -->
            <div style="color: #999; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                <p style="margin: 0 0 8px 0;">
                    <strong>💡 Tip:</strong> Keep your device information updated in security settings to stay secure.
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
