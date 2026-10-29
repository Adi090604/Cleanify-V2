<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }}</title>
</head>
<body style="margin:0; padding:0; background:#f3f6f4; color:#243029; font-family:Arial, Helvetica, sans-serif;">
  <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">{{ $preview ?? $title }}</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background:#f3f6f4;">
    <tr>
      <td align="center" style="padding:28px 12px;">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:600px; background:#ffffff; border:1px solid #dce5df; border-radius:12px; overflow:hidden;">
          <tr>
            <td style="padding:24px 30px; background:#17843f; color:#ffffff; text-align:center;">
              <div style="font-size:26px; line-height:32px; font-weight:800; letter-spacing:1px;">CLEANIFY</div>
              <div style="margin-top:4px; font-size:12px; line-height:18px; color:#dff5e6;">One Click. One Report. One Clean Community.</div>
            </td>
          </tr>
          <tr>
            <td style="padding:30px;">
              <h1 style="margin:0 0 22px; color:#153e26; font-size:24px; line-height:32px; font-weight:700;">{{ $title }}</h1>
              <p style="margin:0 0 18px; font-size:16px; line-height:25px;">{{ $greeting }}</p>

              @foreach((array) ($intro ?? []) as $paragraph)
                <p style="margin:0 0 18px; font-size:15px; line-height:24px;">{{ $paragraph }}</p>
              @endforeach

              @if(!empty($details))
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; margin:4px 0 22px; border:1px solid #dce5df; border-radius:8px; border-collapse:separate; overflow:hidden;">
                  @foreach($details as $detail)
                    <tr>
                      <td style="width:34%; padding:11px 13px; border-bottom:{{ $loop->last ? '0' : '1px solid #e8eeea' }}; background:#f7faf8; color:#4c6254; font-size:13px; line-height:20px; font-weight:700; vertical-align:top;">{{ $detail['label'] }}</td>
                      <td style="padding:11px 13px; border-bottom:{{ $loop->last ? '0' : '1px solid #e8eeea' }}; color:#243029; font-size:14px; line-height:21px; vertical-align:top; white-space:pre-line;">{{ $detail['value'] }}</td>
                    </tr>
                  @endforeach
                </table>
              @endif

              @foreach((array) ($additional ?? []) as $paragraph)
                <p style="margin:0 0 18px; font-size:15px; line-height:24px;">{{ $paragraph }}</p>
              @endforeach

              @if(!empty($actionText) && !empty($actionUrl))
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0;">
                  <tr>
                    <td style="border-radius:7px; background:#17843f;">
                      <a href="{{ $actionUrl }}" style="display:inline-block; padding:12px 20px; color:#ffffff; font-size:15px; line-height:20px; font-weight:700; text-decoration:none;">{{ $actionText }}</a>
                    </td>
                  </tr>
                </table>
              @endif

              @foreach((array) ($closing ?? []) as $paragraph)
                <p style="margin:0 0 18px; font-size:15px; line-height:24px;">{{ $paragraph }}</p>
              @endforeach

              <p style="margin:24px 0 0; font-size:15px; line-height:24px;">Thank you,<br><strong style="color:#17843f;">Cleanify</strong></p>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 30px; background:#eff7f1; color:#5b6f61; text-align:center; font-size:12px; line-height:18px;">
              This email was sent by Cleanify.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
