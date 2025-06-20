<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Verifikasi TSTH</title>
</head>

<body style="font-family: 'Segoe UI', sans-serif; background-color: #f9f9f9; padding: 24px; margin: 0;">
    <table width="100%" cellspacing="0" cellpadding="0"
        style="max-width: 600px; margin: auto; background-color: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);">
        <tr>
            <td style="text-align: center; padding-bottom: 24px;">
                <img src="https://drive.google.com/uc?id=11uPQ87UT5Gn2-HKtxGMWAhN-MQrtZBMz" alt="Logo"
                    style="width: 120px; height: auto; border-radius: 12px;">
            </td>
        </tr>
        <tr>
            <td>
                <h2 style="text-align: center; color: #2E7D32; font-size: 26px; margin-bottom: 16px;">
                    Verifikasi TSTH untuk Gudang
                </h2>
                <p style="font-size: 17px; color: #444; line-height: 1.7; text-align: center; margin-bottom: 32px;">
                    Kami ingin menginformasikan bahwa proses verifikasi TSTH sedang berlangsung untuk gudang yang
                    terdaftar. Mohon untuk melakukan pengecekan dan validasi data agar dapat diproses lebih lanjut
                    sesuai dengan ketentuan yang berlaku.
                </p>
                <div style="text-align: center; margin-bottom: 40px;">
                    <a href="{{ $actionUrl }}"
                        style="background-color: #2E7D32; color: #ffffff; font-size: 16px; font-weight: 600; padding: 14px 28px; border-radius: 8px; text-decoration: none; display: inline-block;">
                        Lihat Detail Verifikasi
                    </a>
                </div>
                <p style="font-size: 17px; color: #444; line-height: 1.7; text-align: center;">
                    Jika ada pertanyaan lebih lanjut, silakan hubungi tim kami. Terima kasih atas perhatian dan kerja
                    samanya.
                </p>
                <p style="text-align: center; font-size: 17px; color: #333; margin-top: 32px;">
                    Salam hormat,<br>{{ config('app.name') }}
                </p>
            </td>
        </tr>
    </table>
</body>

</html>