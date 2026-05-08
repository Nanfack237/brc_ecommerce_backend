<!-- resources/views/emails/reset-password-code.blade.php -->
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f5f7; margin: 0; padding: 20px; }
    .card { background: white; border-radius: 12px; padding: 40px; max-width: 480px; margin: 0 auto; }
    .logo { color: #274a82; font-size: 24px; font-weight: 900; margin-bottom: 24px; }
    .code { font-size: 42px; font-weight: 900; letter-spacing: 12px; color: #e60012;
            background: #fff5f5; border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0; }
    .footer { color: #9ca3af; font-size: 12px; margin-top: 24px; }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">BRC Market</div>
    <h2 style="color:#1f2937">Bonjour {{ $firstName }} </h2>
    <p style="color:#6b7280">Vous avez demandé la réinitialisation de votre mot de passe. Voici votre code :</p>
    <div class="code">{{ $code }}</div>
    <p style="color:#6b7280">Ce code expire dans <strong>15 minutes</strong>. Si vous n'avez pas fait cette demande, ignorez cet email.</p>
    <div class="footer">© {{ date('Y') }} BRC Market — Yaoundé, Cameroun</div>
  </div>
</body>
</html>