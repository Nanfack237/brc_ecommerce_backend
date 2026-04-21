<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <style>
    /* ... (CSS existant) ... */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f5f5f5; }
    .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
    .header { background: #274a82; padding: 36px 40px; text-align: center; }
    .header h1 { color: #ffffff; font-size: 28px; letter-spacing: 1px; }
    .header h1 span { color: #e60012; }
    .header p { color: rgba(255,255,255,0.7); font-size: 14px; margin-top: 6px; }
    .body { padding: 40px; }
    .greeting { font-size: 18px; color: #1a1a1a; font-weight: bold; margin-bottom: 16px; }
    p { color: #555555; font-size: 15px; line-height: 1.7; margin-bottom: 16px; }
    .account-box { background: #f0f4ff; border-left: 4px solid #274a82; border-radius: 4px; padding: 18px 20px; margin: 24px 0; }
    .account-box .label { font-size: 11px; color: #999; text-transform: uppercase; margin-bottom: 4px; }
    .account-box .value { font-weight: bold; font-size: 15px; color: #274a82; }
    .account-row { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .account-item { flex: 1; min-width: 120px; }
    .benefits { background: #fff8f8; border: 1px solid #fde0e0; border-radius: 6px; padding: 20px 24px; margin: 20px 0; }
    .benefits h3 { color: #e60012; font-size: 14px; text-transform: uppercase; margin-bottom: 12px; }
    .benefit-item { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 14px; color: #555; }
    .cta-wrap { text-align: center; margin: 28px 0; }
    .cta { display: inline-block; background: #e60012; color: #ffffff !important; text-decoration: none; padding: 14px 40px; border-radius: 6px; font-weight: bold; font-size: 15px; }
    .footer { background: #f0f0f0; padding: 20px 40px; text-align: center; color: #999999; font-size: 12px; }
    .footer a { color: #274a82; text-decoration: none; }
    
    .gift-box {
      border: 2px dashed #e60012;
      padding: 15px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      gap: 15px;
      background: #fffafa;
    }
    .gift-icon { font-size: 30px; }
  </style>
</head>
<body>
  <div class="wrapper">

    <div class="header">
      <h1>BRC <span>MARKET</span></h1>
      <p>Un Africain, Un Ordinateur</p>
    </div>

    <div class="body">

      <p class="greeting">
        Ravi de vous accueillir, {{ $user->first_name }} 👋
      </p>

      <p>
        Votre compte a été créé avec succès sur <strong>BRC Market</strong>. 
        Toute l'équipe de Business Revolution Company est heureuse de vous compter parmi ses clients !
      </p>

      <div class="account-box">
        <div class="account-row">
          <div class="account-item">
            <p class="label">Utilisateur</p>
            <p class="value">{{ $user->username }}</p>
          </div>
          <div class="account-item">
            <p class="label">Email</p>
            <p class="value">{{ $user->email }}</p>
          </div>
        </div>
      </div>

      <div class="benefits">
        <h3>✨ Vos privilèges membre</h3>
        <div class="benefit-item"><span>🛒</span> <span>Accès prioritaire aux stocks informatiques</span></div>
        <div class="benefit-item"><span>🚚</span> <span>Livraison express à domicile</span></div>
        <div class="benefit-item"><span>📦</span> <span>Historique et suivi de commandes</span></div>
      </div>

      {{-- Section Cadeau USB --}}
      <div class="gift-box">
        <div>
          <p style="margin: 0; font-weight: bold; color: #e60012;">Cadeau de bienvenue !</p>
          <p style="margin: 0; font-size: 14px;">
            Une <strong>clé USB de 32GB vous sera offerte</strong> gratuitement lors de votre toute première livraison chez nous.
          </p>
        </div>
      </div>

      <div class="cta-wrap">
        <a href="{{ config('app.url') }}/boutique" class="cta">
          Faire mon premier achat →
        </a>
      </div>

      <p style="font-size: 13px; color: #999; text-align: center;">
        Besoin d'un conseil ? Contactez-nous sur WhatsApp au <strong>6 89 20 57 51</strong>.
      </p>

    </div>

    <div class="footer">
      © {{ date('Y') }} BRC Market · Douala & Yaoundé, Cameroun<br>
      <a href="{{ config('app.url') }}">www.brcmarket.cm</a>
    </div>

  </div>
</body>
</html>