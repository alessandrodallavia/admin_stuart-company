<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;background:#f4f5f7;color:#101214;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5f7;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #d9dde3;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:22px 24px 8px;">
                            <p style="margin:0 0 8px;color:#747b85;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0;">Stuart Admin</p>
                            <h1 style="margin:0;color:#101214;font-size:22px;line-height:1.25;font-weight:800;">{{ $title }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 24px 20px;">
                            <p style="margin:0;color:#33383f;font-size:15px;line-height:1.55;">{{ $body }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 24px;">
                            <a href="{{ $actionUrl }}" style="display:inline-block;background:#111111;color:#ffffff;text-decoration:none;border-radius:6px;padding:12px 16px;font-size:13px;font-weight:800;text-transform:uppercase;">
                                {{ $actionLabel }}
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
