<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="supported-color-schemes" content="dark">
    <title>@yield('title')</title>
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,600" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background-color: #181115; }
        @media (max-width: 600px) {
            .shell { padding: 24px 12px 40px !important; }
            .band { padding: 28px 24px 24px !important; }
            .panel { padding: 32px 24px 36px !important; }
            .aside { padding-left: 12px !important; padding-right: 12px !important; }
            .headline { font-size: 30px !important; }
            .button-table { width: 100% !important; }
            .button-link { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #181115;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; color: #181115;">
        @yield('preheader')
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#181115" style="background-color: #181115;">
        <tr>
            <td class="shell" align="center" style="padding: 56px 24px 64px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px; font-family: 'Instrument Sans', -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif;">
                    <tr>
                        <td class="band" bgcolor="#a31621" style="background-color: #a31621; padding: 36px 44px 30px; border-radius: 8px 8px 0 0; font-size: 44px; line-height: 1; font-weight: 600; letter-spacing: -0.035em; color: #ffffff;">
                            Chill
                        </td>
                    </tr>

                    <tr>
                        <td class="panel" bgcolor="#241a20" style="background-color: #241a20; padding: 44px 44px 48px; border-radius: 0 0 8px 8px;">
                            <h1 class="headline" style="margin: 0 0 20px 0; font-size: 36px; line-height: 1.06; font-weight: 600; letter-spacing: -0.025em; color: #ffffff;">
                                @yield('headline')
                            </h1>

                            <p style="margin: 0 0 32px 0; font-size: 17px; line-height: 1.55; color: #ffffff;">
                                @yield('lede')
                            </p>

                            <table class="button-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td bgcolor="#a31621" style="background-color: #a31621; border-radius: 4px;">
                                        <a class="button-link" href="{{ $url }}" target="_blank" style="display: inline-block; padding: 16px 32px; font-size: 16px; line-height: 1; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 4px;">
                                            @yield('action')
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 18px 0 0 0; font-size: 14px; line-height: 1.5; color: #b3abb0;">
                                The link works for {{ $expiresInMinutes }} minutes.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td class="aside" style="padding: 36px 44px 0; font-size: 13px; line-height: 1.55; color: #a39ba0;">
                            Button not working? Paste this link into your browser:
                            <br>
                            <a href="{{ $url }}" target="_blank" style="color: #e6dfe3; text-decoration: underline; word-break: break-all;">{{ $url }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="aside" style="padding: 24px 44px 0; font-size: 13px; line-height: 1.55; color: #a39ba0;">
                            @yield('disclaimer')
                        </td>
                    </tr>
                    <tr>
                        <td class="aside" style="padding: 24px 44px 0; font-size: 13px; line-height: 1.55; color: #a39ba0;">
                            <span style="color: #ffffff; font-weight: 600;">Chill</span> &middot; Find what to watch tonight.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
