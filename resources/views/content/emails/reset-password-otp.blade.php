<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $systemName }} - Password Reset OTP</title>

  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: #f2f4f8;
      font-family: Arial, Helvetica, sans-serif;
    }

    .wrapper {
      width: 100%;
      padding: 30px 0;
      background-color: #f2f4f8;
    }

    .email-container {
      max-width: 600px;
      margin: 0 auto;
      background-color: #ffffff;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    }

    .email-header {
      background: linear-gradient(135deg, #7c2d12, #9a3412);
      padding: 30px;
      text-align: center;
    }

    .email-header img {
      max-width: 160px;
      height: auto;
    }

    .email-content {
      padding: 35px 30px;
      text-align: center;
      color: #374151;
    }

    .email-content h1 {
      font-size: 22px;
      margin-bottom: 12px;
      color: #111827;
    }

    .email-content p {
      font-size: 15px;
      line-height: 1.6;
      margin-bottom: 18px;
      color: #4b5563;
    }

    .otp-box {
      margin: 25px auto;
      display: inline-block;
      background-color: #dc2626;
      color: #ffffff;
      font-size: 36px;
      letter-spacing: 6px;
      padding: 14px 32px;
      border-radius: 10px;
      font-weight: bold;
    }

    .otp-validity {
      font-size: 14px;
      color: #6b7280;
      margin-top: 10px;
    }

    .cta-button {
      display: inline-block;
      margin-top: 25px;
      padding: 12px 28px;
      background-color: #111827;
      color: #ffffff !important;
      font-size: 15px;
      font-weight: 600;
      text-decoration: none;
      border-radius: 8px;
    }

    .security-note {
      margin-top: 25px;
      font-size: 13px;
      color: #9ca3af;
    }

    .email-footer {
      background-color: #f9fafb;
      padding: 20px;
      text-align: center;
      font-size: 13px;
      color: #6b7280;
      border-top: 1px solid #e5e7eb;
    }

    .email-footer a {
      color: #dc2626;
      text-decoration: none;
    }

    @media only screen and (max-width: 600px) {
      .email-content {
        padding: 25px 20px;
      }

      .otp-box {
        font-size: 30px;
        padding: 12px 24px;
      }
    }
  </style>
</head>

<body>
  <div class="wrapper">
    <div class="email-container">

      <!-- Header -->
      <div class="email-header">
        <img src="{{ $imageUrl }}" alt="{{ $systemName }} Logo">
      </div>

      <!-- Content -->
      <div class="email-content">
        <h1>Password Reset Request</h1>

        <p>
          Hello {{ $name }},<br><br>
          We received a request to reset the password for your
          <strong>{{ $systemName }}</strong> account.
        </p>

        <p>Please use the OTP below to reset your password:</p>

        <div class="otp-box">{{ $otp }}</div>

        <div class="otp-validity">
          This OTP is valid for <strong>10 minutes</strong>.
        </div>

        <a href="https://finsova.org/" class="cta-button">
          Go to {{ $systemName }}
        </a>

        <div class="security-note">
          If you did not request a password reset, please ignore this email.
          Your account will remain secure.
        </div>
      </div>

      <!-- Footer -->
      <div class="email-footer">
        <p>
          Need help?
          <a href="https://finsova.org/contact/">Contact Support</a>
        </p>
        <p>
          &copy; {{ date('Y') }} {{ $systemName }}. All rights reserved.
        </p>
      </div>

    </div>
  </div>
</body>
</html>
