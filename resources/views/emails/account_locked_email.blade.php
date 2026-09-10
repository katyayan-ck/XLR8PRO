@extends('layouts.email')

@section('content')
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <!-- Header -->
        <div
            style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
            <h1 style="color: white; margin: 0; font-size: 28px;">⚠️ Account Locked</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0;">{{ $app_name }} Security Alert</p>
        </div>

        <!-- Body -->
        <div style="background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-top: none;">
            <h2 style="color: #333; font-size: 20px; margin-top: 0;">Your Account Has Been Locked</h2>

            <p style="color: #666; line-height: 1.6;">
                Your {{ $app_name }} account has been temporarily locked for security reasons.
            </p>

            <!-- Alert Box -->
            <div
                style="background: #fff3cd; padding: 20px; border-left: 4px solid #dc3545; border-radius: 8px; margin: 20px 0;">
                <p style="color: #856404; margin: 0 0 15px 0; font-weight: bold;">
                    🔒 Reason for Lock:
                </p>
                <p style="color: #856404; margin: 0;">
                    {{ $reason }}
                </p>
            </div>

            <!-- Lock Details -->
            <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <p style="color: #333; font-weight: bold; margin: 0 0 15px 0;">Lock Details:</p>

                <div style="background: #f0f0f0; padding: 15px; border-radius: 4px;">
                    <p style="color: #666; margin: 5px 0;">
                        <strong>Mobile:</strong> {{ substr($mobile, 0, 3) }}****{{ substr($mobile, -3) }}
                    </p>
                    <p style="color: #666; margin: 5px 0;">
                        <strong>Email:</strong> {{ substr($email, 0, 3) }}***@{{ substr(strrchr($email, '@'), 1) }}
                    </p>
                    <p style="color: #666; margin: 5px 0;">
                        <strong>Locked Until:</strong> {{ $locked_until->format('d M Y, h:i A IST') }}
                    </p>
                    <p style="color: #dc3545; margin: 5px 0; font-weight: bold;">
                        ⏰ Time Remaining: 30 minutes
                    </p>
                </div>
            </div>

            <!-- Security Notice -->
            <div
                style="background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; border-radius: 4px; margin: 20px 0;">
                <p style="color: #721c24; margin: 0; font-size: 13px;">
                    <strong>🛡️ Security Action:</strong>
                    For your security, we've temporarily locked your account to prevent unauthorized access.
                    Try again after 30 minutes or contact support if you believe this is a mistake.
                </p>
            </div>

            <!-- What You Can Do -->
            <div style="background: white; padding: 20px; border-radius: 4px; margin: 20px 0;">
                <p style="color: #333; font-weight: bold; margin: 0 0 15px 0;">What You Can Do:</p>

                <ul style="color: #666; margin: 0; padding-left: 20px;">
                    <li style="margin: 8px 0;">Wait 30 minutes and try logging in again</li>
                    <li style="margin: 8px 0;">Contact support to unlock your account immediately</li>
                    <li style="margin: 8px 0;">Change your password from security settings (after unlock)</li>
                    <li style="margin: 8px 0;">Review recent login activity in your account</li>
                </ul>
            </div>

            <!-- Support -->
            <div
                style="background: #e7f3ff; padding: 15px; border-left: 4px solid #0066cc; border-radius: 4px; margin: 20px 0; text-align: center;">
                <p style="color: #0066cc; margin: 0 0 10px 0; font-weight: bold;">
                    Need immediate help?
                </p>
                <p style="margin: 0;">
                    <a href="mailto:{{ $support_email }}" style="color: #0066cc; text-decoration: none; font-weight: bold;">
                        Contact Our Support Team
                    </a>
                </p>
            </div>

            <!-- Footer Info -->
            <div style="color: #999; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                <p style="margin: 0;">
                    If you didn't attempt to log in, your account is still secure and protected.
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
