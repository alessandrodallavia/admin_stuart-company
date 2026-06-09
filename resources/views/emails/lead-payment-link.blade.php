<!DOCTYPE html>
<html lang="it">
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#1f1f21;font-size:15px;line-height:1.6;">
    <p style="margin:0 0 16px;">Importo preventivo: <strong>€ {{ $amount }}</strong></p>
    <p style="margin:0 0 20px;">Clicca sul pulsante “Paga ora” per procedere al pagamento del preventivo.</p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 20px;">
        <tr>
            <td style="background:#206ae9;border-radius:6px;">
                <a href="{{ $paymentLink }}" style="display:inline-block;padding:12px 22px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Paga ora</a>
            </td>
        </tr>
    </table>
    <p style="margin:0;">Se preferisci pagare tramite bonifico bancario, rispondi a questa e-mail e ti invieremo la proforma con tutti i dettagli per il pagamento.</p>
</body>
</html>
