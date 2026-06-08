<table role="presentation" width="520" cellspacing="0" cellpadding="0" style="width:520px;max-width:100%;margin-top:44px;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;">
    <tr>
        <td width="108" style="width:108px;padding:0 12px 14px 0;vertical-align:middle;">
            <img src="{{ $logoUrl }}" alt="Stuart Company" width="82" style="display:block;width:82px;height:auto;border:0;">
        </td>
        <td style="padding:0 0 12px;vertical-align:middle;">
            <p style="margin:0 0 2px;color:#111111;font-size:18px;line-height:1.3;font-weight:700;">{{ $name }}</p>
            <p style="margin:0 0 12px;color:#a8a8a8;font-size:16px;line-height:1.3;font-weight:700;">{{ $company }}</p>
            <p style="margin:0;color:#a8a8a8;font-size:14px;line-height:1.55;">
                <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" style="color:#a8a8a8;text-decoration:none;">{{ $phone }}</a><br>
                <a href="mailto:{{ $email }}" style="color:#a8a8a8;text-decoration:none;">{{ $email }}</a>
                <span style="color:#c3c3c3;"> | </span>
                <a href="{{ $website }}" style="color:#206ae9;text-decoration:underline;">stuart-company.com</a>
            </p>
        </td>
    </tr>
    <tr>
        <td colspan="2" width="520" style="width:520px;padding-top:10px;border-top:1px solid #eeeeee;">
            <p style="margin:0;color:#b0b0b0;font-size:10px;line-height:1.45;">{{ $disclaimer }}</p>
        </td>
    </tr>
</table>
