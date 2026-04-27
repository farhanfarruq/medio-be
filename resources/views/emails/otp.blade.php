<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode Verifikasi - Optik Medio</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F5F2EE; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F5F2EE;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 480px; margin: 0 auto; background: white; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%); padding: 32px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #c19a51; font-size: 20px; font-weight: 800; letter-spacing: 2px;">
                                OPTIK MEDIO
                            </h1>
                            <p style="margin: 6px 0 0; color: rgba(255,255,255,0.6); font-size: 11px; letter-spacing: 3px; text-transform: uppercase;">
                                Premium Eyewear
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 8px; color: #1a1209; font-size: 22px; font-weight: 700;">
                                Halo, {{ $userName }}!
                            </p>
                            <p style="margin: 0 0 28px; color: #8a7a60; font-size: 14px; line-height: 1.6;">
                                Gunakan kode verifikasi berikut untuk mengaktifkan akun Optik Medio Anda. Kode ini berlaku selama <strong>10 menit</strong>.
                            </p>

                            <!-- OTP Code -->
                            <div style="background: linear-gradient(135deg, #faf9f7, #f0ece4); border: 2px dashed #c19a51; padding: 24px; text-align: center; margin: 0 0 28px;">
                                <p style="margin: 0 0 6px; color: #8a7a60; font-size: 10px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase;">
                                    Kode Verifikasi
                                </p>
                                <p style="margin: 0; color: #1a1209; font-size: 36px; font-weight: 900; letter-spacing: 10px; font-family: 'Courier New', monospace;">
                                    {{ $otpCode }}
                                </p>
                            </div>

                            <p style="margin: 0; color: #b0a590; font-size: 12px; line-height: 1.6;">
                                Jika Anda tidak merasa mendaftar di Optik Medio, abaikan email ini. Jangan bagikan kode ini kepada siapapun.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; border-top: 1px solid #f0ece4; text-align: center;">
                            <p style="margin: 0; color: #c19a51; font-size: 10px; font-weight: 700; letter-spacing: 2px;">
                                &copy; {{ date('Y') }} OPTIK MEDIO
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
