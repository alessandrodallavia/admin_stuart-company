<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
@php
    $theme = match ($kind ?? null) {
        'payment-completed' => ['label' => 'Pagamento', 'color' => '#168447', 'background' => '#e9f7ef'],
        'bank-transfer-proforma-requested' => ['label' => 'Bonifico', 'color' => '#b45d00', 'background' => '#fff3e0'],
        'whatsapp-message' => ['label' => 'WhatsApp', 'color' => '#128c7e', 'background' => '#e7f7f4'],
        'new-lead' => ['label' => 'Lead', 'color' => '#111111', 'background' => '#eeeeee'],
        default => ['label' => 'Stuart Admin', 'color' => '#111111', 'background' => '#eeeeee'],
    };
@endphp
<body style="margin:0;background:#f3f4f6;color:#101214;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #d9dde3;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#101214;padding:18px 24px;">
                            <p style="margin:0;color:#ffffff;font-size:15px;line-height:1.2;font-weight:800;">Stuart Admin</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 24px 8px;">
                            <span style="display:inline-block;background:{{ $theme['background'] }};color:{{ $theme['color'] }};border-radius:999px;padding:6px 10px;font-size:11px;line-height:1;font-weight:800;text-transform:uppercase;">
                                {{ $theme['label'] }}
                            </span>
                            <h1 style="margin:14px 0 0;color:#101214;font-size:24px;line-height:1.25;font-weight:800;">{{ $title }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 24px 18px;">
                            <p style="margin:0;color:#33383f;font-size:16px;line-height:1.55;">{{ $body }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 24px;">
                            <a href="{{ $actionUrl }}" style="display:inline-block;background:#101214;color:#ffffff;text-decoration:none;border-radius:6px;padding:13px 18px;font-size:13px;font-weight:800;text-transform:uppercase;">
                                {{ $actionLabel }}
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #eceff3;padding:16px 24px 20px;">
                            <p style="margin:0;color:#747b85;font-size:12px;line-height:1.5;">
                                Ricevi questa email perche sei destinatario delle notifiche operative di Stuart Admin.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
