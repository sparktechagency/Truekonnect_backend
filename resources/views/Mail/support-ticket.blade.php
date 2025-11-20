<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Ticket Answer</title>
    <style>
      /* Keep styles inline-friendly and minimal */
      body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial; margin:0; padding:0; background:#f5f7fa; color:#333; }
      .wrapper { width:100%; table-layout:fixed; background:#f5f7fa; padding:30px 0; }
      .container { max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,.06); }
      .header { padding:18px 24px; background:#0b67ff; color:#fff; }
      .header h1 { margin:0; font-size:18px; font-weight:600; }
      .body { padding:20px 24px; font-size:15px; line-height:1.45; color:#1f2937; }
      .ticket-meta { font-size:13px; color:#6b7280; margin-bottom:12px; }
      .reply { background:#f8fafc; border:1px solid #e6eefc; padding:14px; border-radius:6px; margin:12px 0; white-space:pre-wrap; }
      .cta { display:inline-block; margin-top:12px; padding:10px 14px; background:#0b67ff; color:#fff; border-radius:6px; text-decoration:none; font-weight:600; }
      .footer { font-size:12px; color:#9ca3af; padding:14px 24px 20px; text-align:center; }
      @media (max-width:420px) {
        .body, .header, .footer { padding-left:16px; padding-right:16px; }
      }
    </style>
  </head>
  <body>
    <table class="wrapper" cellpadding="0" cellspacing="0" role="presentation">
      <tr>
        <td align="center">
          <table class="container" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
              <td class="header">
                <h1>Support — Ticket - {{ $subject }}</h1>
              </td>
            </tr>

            <tr>
              <td class="body">
                <p style="margin:0 0 8px 0">Hi {{ $username }},</p>

                <p style="margin:0 0 12px 0">
                  We’ve posted an answer to your ticket <strong>"{{ $subject }}"</strong>.<br>
                  You mention about the issue is:  <b>"{{ $issue }}"</b>
                </p>
                <div class="reply">{{ $reply }}</div>
                <p style="margin:8px 0 0 0">
                  If this doesn’t resolve your issue, reply to this email or click the button below to view the ticket in your account.
                </p>
                <p style="margin:18px 0 0 0; color:#6b7280; font-size:13px">
                  Thanks,<br>
                  The Support Team TrueKonncet
                </p>
              </td>
            </tr>
            <tr>
              <td class="footer">
                This is an automated message — please do not reply to this address.
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
