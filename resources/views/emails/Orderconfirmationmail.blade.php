<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Confirmation de commande — BRC Market</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#1a1a2e;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:40px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <!-- HEADER -->
      <tr>
        <td style="background:#274a82;border-radius:16px 16px 0 0;padding:28px 40px;text-align:center;">
          <h1 style="margin:0;color:#fff;font-size:26px;font-weight:900;letter-spacing:-0.5px;">
            BRC<span style="color:#e60012;">.</span>Market
          </h1>
          <p style="margin:6px 0 0;color:rgba(255,255,255,0.55);font-size:12px;">
            Votre partenaire informatique au Cameroun
          </p>
        </td>
      </tr>

      <!-- HERO -->
      <tr>
        <td style="background:#fff;padding:36px 40px 28px;text-align:center;border-bottom:1px solid #f0f0f0;">
          <div style="width:60px;height:60px;background:#d4edda;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">
            <span style="font-size:26px;">✅</span>
          </div>
          <h2 style="margin:0 0 8px;font-size:20px;font-weight:900;color:#1a1a2e;">Commande confirmée !</h2>
          <p style="margin:0;color:#666;font-size:14px;line-height:1.6;">
            Merci <strong>{{ $order['nom'] }}</strong> pour votre confiance.<br/>
            Commande <strong style="color:#274a82;">{{ $order['order_number'] }}</strong> enregistrée.
          </p>
        </td>
      </tr>

      <!-- ARTICLES -->
      <tr>
        <td style="background:#fff;padding:28px 40px;">
          <p style="margin:0 0 14px;font-size:11px;font-weight:900;color:#999;text-transform:uppercase;letter-spacing:2px;">
            Articles commandés
          </p>
          <table width="100%" cellpadding="0" cellspacing="0">
            @foreach($order['items'] as $item)
            <tr>
              <td style="padding:10px 0;border-bottom:1px solid #f5f5f5;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td width="52">
                      @if(!empty($item['image']))
                      <img src="{{ $item['image'] }}" width="44" height="44"
                        style="border-radius:8px;border:1px solid #eee;object-fit:contain;background:#fafafa;display:block;" />
                      @else
                      <div style="width:44px;height:44px;background:#f0f0f0;border-radius:8px;"></div>
                      @endif
                    </td>
                    <td style="padding-left:12px;">
                      <p style="margin:0;font-size:13px;font-weight:700;color:#1a1a2e;">{{ $item['name'] }}</p>
                      <p style="margin:2px 0 0;font-size:12px;color:#999;">Qté : {{ $item['quantity'] }}</p>
                    </td>
                    <td align="right" style="white-space:nowrap;vertical-align:middle;">
                      <p style="margin:0;font-size:14px;font-weight:900;color:#1a1a2e;">
                        {{ number_format($item['price'] * $item['quantity'], 0, ',', ' ') }} FCFA
                      </p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            @endforeach

            <!-- Totaux -->
            <tr>
              <td style="padding:18px 0 0;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="padding:5px 0;font-size:13px;color:#666;">Sous-total</td>
                    <td align="right" style="padding:5px 0;font-size:13px;font-weight:700;color:#1a1a2e;">
                      {{ number_format($order['subtotal'], 0, ',', ' ') }} FCFA
                    </td>
                  </tr>
                  <tr>
                    <td style="padding:5px 0;font-size:13px;color:#666;">Livraison ({{ $order['shipping'] === 'express' ? 'Express' : 'Standard' }})</td>
                    <td align="right" style="padding:5px 0;font-size:13px;font-weight:700;color:#274a82;">
                      + {{ number_format($order['livraison'], 0, ',', ' ') }} FCFA
                    </td>
                  </tr>
                  @if(!empty($order['discount']) && $order['discount'] > 0)
                  <tr>
                    <td style="padding:5px 0;font-size:13px;color:#16a34a;">Remise{{ !empty($order['promo_code']) ? ' ('.$order['promo_code'].')' : '' }}</td>
                    <td align="right" style="padding:5px 0;font-size:13px;font-weight:700;color:#16a34a;">
                      - {{ number_format($order['discount'], 0, ',', ' ') }} FCFA
                    </td>
                  </tr>
                  @endif
                  <tr>
                    <td style="padding:14px 0 0;font-size:15px;font-weight:900;color:#1a1a2e;border-top:2px solid #f0f0f0;">Total à payer</td>
                    <td align="right" style="padding:14px 0 0;font-size:20px;font-weight:900;color:#e60012;border-top:2px solid #f0f0f0;">
                      {{ number_format($order['total'], 0, ',', ' ') }} FCFA
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- LIVRAISON + PAIEMENT -->
      <tr>
        <td style="background:#fff;padding:0 40px 28px;">
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <!-- Adresse livraison -->
              <td width="48%" valign="top"
                style="background:#f8fafc;border-radius:12px;padding:18px;border:1px solid #e8edf2;">
                <p style="margin:0 0 10px;font-size:11px;font-weight:900;color:#999;text-transform:uppercase;letter-spacing:1.5px;">📦 Livraison</p>
                <p style="margin:0 0 3px;font-size:13px;font-weight:700;color:#1a1a2e;">{{ $order['nom'] }}</p>
                <p style="margin:0 0 3px;font-size:12px;color:#666;">{{ $order['phone'] }}</p>
                <p style="margin:0 0 6px;font-size:12px;color:#666;">{{ $order['adresse'] }}</p>
                <p style="margin:0;font-size:12px;color:#274a82;font-weight:700;">
                  {{ $order['shipping'] === 'express' ? '⚡ Express' : '🚚 Standard' }}
                </p>
              </td>
              <td width="4%"></td>
              <!-- Paiement -->
              <td width="48%" valign="top"
                style="background:#f8fafc;border-radius:12px;padding:18px;border:1px solid #e8edf2;">
                <p style="margin:0 0 10px;font-size:11px;font-weight:900;color:#999;text-transform:uppercase;letter-spacing:1.5px;">💳 Paiement</p>
                @if($order['payment'] === 'om')
                  <p style="margin:0 0 6px;font-size:13px;font-weight:900;color:#ea580c;">🟠 Orange Money</p>
                  <p style="margin:0 0 3px;font-size:12px;color:#666;">Envoyez <strong>{{ number_format($order['total'], 0, ',', ' ') }} FCFA</strong> au :</p>
                  <p style="margin:0 0 6px;font-size:20px;font-weight:900;color:#ea580c;letter-spacing:2px;">{{ $order['marchand'] }}</p>
                  <p style="margin:0;font-size:11px;color:#999;">Réf : <strong>{{ $order['order_number'] }}</strong> · Valable <strong>24h</strong></p>
                @elseif($order['payment'] === 'momo')
                  <p style="margin:0 0 6px;font-size:13px;font-weight:900;color:#ca8a04;">🟡 MTN Mobile Money</p>
                  <p style="margin:0 0 3px;font-size:12px;color:#666;">Envoyez <strong>{{ number_format($order['total'], 0, ',', ' ') }} FCFA</strong> au :</p>
                  <p style="margin:0 0 6px;font-size:20px;font-weight:900;color:#ca8a04;letter-spacing:2px;">{{ $order['marchand'] }}</p>
                  <p style="margin:0;font-size:11px;color:#999;">Réf : <strong>{{ $order['order_number'] }}</strong> · Valable <strong>24h</strong></p>
                @else
                  <p style="margin:0 0 6px;font-size:13px;font-weight:900;color:#274a82;">💵 Paiement à la livraison</p>
                  <p style="margin:0;font-size:12px;color:#666;line-height:1.6;">Préparez le montant exact.<br/>Notre livreur vous contactera avant passage.</p>
                @endif
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- ÉTAPES SUIVANTES -->
      <tr>
        <td style="background:#fff;padding:0 40px 28px;">
          <div style="background:linear-gradient(135deg,#274a82,#1a3460);border-radius:12px;padding:22px;">
            <p style="margin:0 0 14px;font-size:11px;font-weight:900;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:2px;">
              Prochaines étapes
            </p>
            @php
              $steps = [
                '1' => 'Votre commande est en cours de préparation par notre équipe.',
                '2' => $order['payment'] !== 'cash'
                  ? 'Effectuez votre paiement dans les 24h avec le numéro marchand ci-dessus.'
                  : 'Notre livreur vous contactera pour confirmer la livraison.',
                '3' => 'Livraison à votre adresse — surveillez votre téléphone.',
              ];
            @endphp
            @foreach($steps as $num => $text)
            <table cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
              <tr>
                <td width="26" valign="top">
                  <div style="width:20px;height:20px;background:rgba(255,255,255,0.12);border-radius:50%;text-align:center;line-height:20px;font-size:10px;font-weight:900;color:#fff;">{{ $num }}</div>
                </td>
                <td style="padding-left:8px;">
                  <p style="margin:0;font-size:12px;color:rgba(255,255,255,0.75);line-height:1.5;">{{ $text }}</p>
                </td>
              </tr>
            </table>
            @endforeach
          </div>
        </td>
      </tr>

      <!-- FOOTER -->
      <tr>
        <td style="background:#f8fafc;border-radius:0 0 16px 16px;padding:24px 40px;text-align:center;border:1px solid #e8edf2;border-top:none;">
          <p style="margin:0 0 4px;font-size:13px;font-weight:700;color:#1a1a2e;">BRC Market</p>
          <p style="margin:0 0 10px;font-size:12px;color:#999;">Yaoundé & Douala, Cameroun</p>
          <p style="margin:0;font-size:11px;color:#bbb;line-height:1.6;">
            Cet email a été envoyé suite à votre commande sur BRC Market.<br/>
            Des questions ? Contactez-nous par WhatsApp ou email.
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>