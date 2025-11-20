<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password OTP</title>
<style>
  body { font-family: Arial, sans-serif; background-color: #f4f4f7; margin:0; padding:0; color:#333; }
  .container { max-width:600px; margin:40px auto; background:#fff; border-radius:8px; padding:30px; box-shadow:0 4px 10px rgba(0,0,0,0.05); }
  .header { background-color:#4A90E2; color:#fff; padding:20px; text-align:center; border-radius:8px 8px 0 0; }
  .header h1 { margin:0; font-size:24px; }
  .content { padding:20px; line-height:1.6; }
  .otp-box { display:block; background:#f1f1f1; padding:15px; text-align:center; font-size:20px; font-weight:bold; letter-spacing:2px; margin:20px 0; border-radius:5px; }
  .footer { text-align:center; font-size:12px; color:#777; padding:20px; }
</style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>Password Reset OTP</h1>
    </div>
    <div class="content">
      <p>Hello, {{ $userName }}</p>
      <p>Use the OTP below to reset your password. This OTP is valid for 10 minutes only.</p>
      <span class="otp-box">{{ $otp }}</span>
      <p>If you did not request a password reset, please ignore this email.</p>
      <p>Thanks,<br>The Truekonnect Team</p>
    </div>
    <div class="footer">
      &copy; {{ date('Y') }} Truekonnect. All rights reserved.
    </div>
  </div>
</body>
</html>
