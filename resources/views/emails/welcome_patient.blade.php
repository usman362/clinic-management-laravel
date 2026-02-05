<!DOCTYPE html>
<html>
<head>
    <title>Welcome to {{ config('app.name') }}</title>
</head>
<body>
    <h2>Hello {{ $name }},</h2>
    <p>Welcome to {{ config('app.name') }}! Your account has been created successfully.</p>

    <p><strong>Email:</strong> {{ $email }}</p>
    <p><strong>Password:</strong> {{ $password }}</p>

    <p>Please login and change your password after your first login for security reasons.</p>

    <p>Thank you,<br>{{ config('app.name') }} Team</p>
</body>
</html>
