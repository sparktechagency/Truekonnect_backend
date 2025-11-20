<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Status Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f7;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0,0,0,0.05);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #333333;
            margin: 0;
        }
        .content {
            color: #555555;
            line-height: 1.6;
        }
        .status {
            font-weight: bold;
            color: #ffffff;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        .status.active {
            background-color: #28a745;
        }
        .status.banned {
            background-color: #dc3545;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #999999;
            text-align: center;
        }
        a.button {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
        }
        a.button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Account Status Notification</h1>
    </div>
    <div class="content">
        <p>Hi {{ $reviewer->name }},</p>

        <p>Your account on <strong>Truekonnect</strong> has been updated. The current status of your account is:</p>

        <p class="status {{ $status }}">
            {{ ucfirst($status) }}
        </p>

        @if($status == 'banned' && $adminMessage)
        <p>Reason provided by admin: {{ $adminMessage }}</p>
        @endif

        @if($status == 'active')
        <p>Welcome back! You can now continue using your account without restrictions.</p>
        @endif

        <p>If you have any questions, feel free to contact our support team.</p>
        {{-- <a href="{{ url('/') }}" class="button">Go to Dashboard</a> --}}
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} Truekonnect. All rights reserved.
    </div>
</div>
</body>
</html>
