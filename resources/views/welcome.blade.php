<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f5f5f5; }

    .wrapper {
      max-width: 600px;
      margin: 40px auto;
      background: #ffffff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    }

    /* Header bleu avec logo */
    .header {
      background: #274a82;
      padding: 36px 40px;
      text-align: center;
    }
    .header h1 {
      color: #ffffff;
      font-size: 28px;
      letter-spacing: 1px;
    }
    .header h1 span { color: #e60012; }
    .header p {
      color: rgba(255,255,255,0.7);
      font-size: 14px;
      margin-top: 6px;
    }

    /* Corps */
    .body { padding: 40px; }

    .greeting {
      font-size: 18px;
      color: #1a1a1a;
      font-weight: bold;
      margin-bottom: 16px;
    }

    p {
      color: #555555;
      font-size: 15px;
      line-height: 1.7;
      margin-bottom: 16px;
    }

    /* Bloc infos du compte */
    .account-box {
      background: #f0f4ff;
      border-left: 4px solid #274a82;
      border-radius: 4px;
      padding: 18px 20px;
      margin: 24px 0;
    }
    .account-box p {
      margin: 0;
      font-size: 14px;
      color: #274a82;
    }
    .account-box .label {
      font-size: 11px;
      color: #999;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }
    .account-box .value {
      font-weight: bold;
      font-size: 15px;
      color: #274a82;
    }
    .account-row {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }
    .account-item { flex: 1; min-width: 120px; }

    /* Avantages */
    .benefits {
      background: #fff8f8;
      border: 1px solid #fde0e0;
      border-radius: 6px;
      padding: 20px 24px;
      margin: 20px 0;
    }
    .benefits h3 {
      color: #e60012;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 12px;
    }
    .benefit-item {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
      font-size: 14px;
      color: #555;
    }
    .benefit-icon { font-size: 18px; }

    /* Bouton CTA */
    .cta-wrap { text-align: center; margin: 28px 0; }
    .cta {
      display: inline-block;
      background: #e60012;
      color: #ffffff !important;
      text-decoration: none;
      padding: 14px 40px;
      border-radius: 6px;
      font-weight: bold;
      font-size: 15px;
      letter-spacing: 0.5px;
    }
    .cta:hover { background: #c5000f; }

    /* Footer */
    .footer {
      background: #f0f0f0;
      padding: 20px 40px;
      text-align: center;
      color: #999999;
      font-size: 12px;
      line-height: 1.6;
    }
    .footer a { color: #274a82; text-decoration: none; }
  </style>
</head>
<body>
  <div class="wrapper">

    {{-- ── Header ── --}}
    <div class="header">
      <h1>BRC <span>MARKET</span></h1>
      <p>Votre boutique informatique de confiance au Cameroun</p>
    </div>

    {{-- ── Body ── --}}
    <div class="body">

      <p class="greeting">
        Bonjour {{ $user->first_name }} {{ $user->last_name }} 👋
      </p>

      <p>
        Votre compte a été créé avec succès sur <strong>BRC Market</strong>.
        Nous sommes ravis de vous accueillir dans notre communauté !
      </p>

      {{-- Infos du compte --}}
      <div class="account-box">
        <div class="account-row">
          <div class="account-item">
            <p class="label">Nom d'utilisateur</p>
            <p class="value">{{ $user->username }}</p>
          </div>
          <div class="account-item">
            <p class="label">Email</p>
            <p class="value">{{ $user->email }}</p>
          </div>
          <div class="account-item">
            <p class="label">Rôle</p>
            <p class="value">
              @if($user->role === 'super_admin') Super Admin
              @elseif($user->role === 'admin') Administrateur
              @else Client
              @endif
            </p>
          </div>
        </div>
      </div>

      {{-- Avantages --}}
      <div class="benefits">
        <h3>✨ Ce que vous pouvez faire maintenant</h3>
        <div class="benefit-item">
          <span class="benefit-icon">🛒</span>
          <span>Commander des milliers de produits informatiques</span>
        </div>
        <div class="benefit-item">
          <span class="benefit-icon">🚚</span>
          <span>Livraison rapide à Yaoundé &amp; Douala</span>
        </div>
        <div class="benefit-item">
          <span class="benefit-icon">❤️</span>
          <span>Sauvegarder vos produits favoris</span>
        </div>
        <div class="benefit-item">
          <span class="benefit-icon">📦</span>
          <span>Suivre vos commandes en temps réel</span>
        </div>
        <div class="benefit-item">
          <span class="benefit-icon">🎁</span>
          <span>Profiter des offres et codes promo exclusifs</span>
        </div>
      </div>

      <p>
        Utilisez votre code de bienvenue
        <strong style="color:#e60012;">BIENVENUE10</strong>
        pour obtenir <strong>10% de réduction</strong> sur votre première commande
        dès <strong>50 000 FCFA</strong> d'achat.
      </p>

      {{-- Bouton --}}
      <div class="cta-wrap">
        <a href="{{ config('app.url') }}/boutique" class="cta">
          Découvrir la boutique →
        </a>
      </div>

      <p style="font-size: 13px; color: #999;">
        Si vous n'avez pas créé ce compte, ignorez cet email
        ou contactez-nous à
        <a href="mailto:support@brcmarket.cm" style="color:#274a82;">
          support@brcmarket.cm
        </a>
      </p>

    </div>

    {{-- ── Footer ── --}}
    <div class="footer">
      © {{ date('Y') }} BRC Market · Douala, Cameroun<br>
      <a href="{{ config('app.url') }}">www.brcmarket.cm</a> ·
      <a href="mailto:businessrevcompany@gmail.com">businessrevcompany@gmail.com</a>
    </div>

  </div>
</body>
</html>