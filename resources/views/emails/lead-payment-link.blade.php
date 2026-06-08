<!DOCTYPE html>
<html lang="it">
<body style="margin:0;padding:0;background:#f8f8f8;font-family:Arial,sans-serif;color:#1f1f21;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8f8f8;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #dcdcdc;">
                    <tr>
                        <td style="padding:28px 32px;">
                            <p style="margin:0 0 8px;font-size:12px;font-weight:700;text-transform:uppercase;color:#206ae9;">Stuart Company</p>
                            <h1 style="margin:0 0 24px;font-size:24px;line-height:1.25;">Pagamento preventivo {{ $quoteNumber }}</h1>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Importo preventivo: <strong>€ {{ $amount }}</strong></p>
                            <p style="margin:0 0 24px;font-size:16px;line-height:1.6;">Clicca sul pulsante “Paga ora” per procedere al pagamento del preventivo.</p>
                            <table role="presentation" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="background:#206ae9;border-radius:6px;">
                                        <a href="{{ $paymentLink }}" style="display:inline-block;padding:14px 24px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Paga ora</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
