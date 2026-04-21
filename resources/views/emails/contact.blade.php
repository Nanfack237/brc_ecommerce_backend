<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #ffffff; }
    .email-container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; }
    
    /* Marque simple */
    .brand { font-size: 18px; font-weight: 800; color: #274a82; border-bottom: 2px solid #e60012; display: inline-block; margin-bottom: 30px; }
    
    .title { font-size: 22px; font-weight: 700; margin-bottom: 20px; color: #111; }
    
    /* Grille d'infos */
    .details { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px; }
    .details-row { margin-bottom: 10px; font-size: 14px; }
    .label { font-weight: 600; color: #666; width: 100px; display: inline-block; }
    
    /* Zone message */
    .message-content { font-size: 16px; color: #333; border-left: 3px solid #e60012; padding-left: 15px; margin: 30px 0; }
    
    /* Bouton Pro */
    .button { background: #274a82; color: #ffffff !important; text-decoration: none; padding: 12px 25px; border-radius: 5px; font-weight: 600; display: inline-block; font-size: 14px; }
    
    .footer { margin-top: 40px; font-size: 12px; color: #999; border-top: 1px solid #eee; padding-top: 20px; }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="brand">BRC MARKET</div>
    
    <div class="title">Nouveau message client</div>
    
    <div class="details">
      <div class="details-row"><span class="label">De :</span> {{ $senderName }}</div>
      <div class="details-row"><span class="label">Email :</span> {{ $senderEmail }}</div>
      <div class="details-row"><span class="label">Tél :</span> {{ $senderPhone }}</div>
    </div>

    <div class="message-content">
      {{ $senderMessage }}
    </div>

    <a href="mailto:{{ $senderEmail }}?subject=Re: {{ $senderSubject }}" class="button">
      Répondre au client
    </a>

    <div class="footer">
      <strong>Business Revolution Company</strong><br>
      Akwa, Douala, Cameroun<br>
      Ceci est une notification automatique de votre site e-commerce.
    </div>
  </div>
</body>
</html>