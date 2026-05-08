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

      <!-- ── Header ── -->
      <tr>
        <td style="background:#274a82;border-radius:16px 16px 0 0;padding:28px 40px;text-align:center;">
          <h1 style="margin:0;color:#fff;font-size:26px;font-weight:900;letter-spacing:-0.5px;">
            BRC<span style="color:#e60012;"> Market </span>
          </h1>
          <p style="margin:6px 0 0;color:rgba(255,255,255,0.55);font-size:12px;">
            Un Africain, Un Ordinateur
          </p>
        </td>
      </tr>

      <!-- ── Titre ── -->
      <tr>
        <td style="background:#fff;padding:36px 40px 28px;text-align:center;border-bottom:1px solid #f0f0f0;">
          <div style="width:60px;height:60px;background:#d4edda;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">
            @if(!empty($order['shipping_confirmed']) && $order['shipping_confirmed'])
              <span style="font-size:26px;">🚚</span>
            @else
              <span style="font-size:26px;">✅</span>
            @endif
          </div>

          @if(!empty($order['shipping_confirmed']) && $order['shipping_confirmed'])
            <h2 style="margin:0 0 8px;font-size:20px;font-weight:900;color:#1a1a2e;">Frais de livraison confirmés !</h2>
            <p style="margin:0;color:#666;font-size:14px;line-height:1.6;">
              Bonjour <strong>{{ $order['nom'] }}</strong>,<br/>
              Les frais de livraison de votre commande <strong style="color:#274a82;">{{ $order['order_number'] }}</strong> ont été calculés.<br/>
              Voici votre récapitulatif final.
            </p>
          @else
            <h2 style="margin:0 0 8px;font-size:20px;font-weight:900;color:#1a1a2e;">Commande confirmée !</h2>
            <p style="margin:0;color:#666;font-size:14px;line-height:1.6;">
              Merci <strong>{{ $order['nom'] }}</strong> pour votre confiance.<br/>
              Commande <strong style="color:#274a82;">{{ $order['order_number'] }}</strong> enregistrée.<br/>
              Notre équipe vous contactera sous <strong>10 minutes</strong> pour confirmer les frais de livraison.
            </p>
          @endif
        </td>
      </tr>

      <!-- ── Bannière frais confirmés ── -->
      @if(!empty($order['shipping_confirmed']) && $order['shipping_confirmed'])
      <tr>
        <td style="background:#f0fdf4;padding:16px 40px;border-bottom:1px solid #bbf7d0;">
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td>
                <p style="margin:0;font-size:13px;color:#166534;font-weight:700;">
                  Frais de livraison confirmés :
                  <strong style="font-size:16px;color:#16a34a;">
                    {{ number_format($order['livraison'], 0, ',', ' ') }} FCFA
                  </strong>
                </p>
                <p style="margin:4px 0 0;font-size:11px;color:#4ade80;">
                  Total final TTC : <strong>{{ number_format($order['subtotal'] + $order['livraison'], 0) }} FCFA</strong>
                </p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
      @endif

      <!-- ── Articles ── -->
      <tr>
        <td style="background:#fff;padding:28px 40px;">
          <p style="margin:0 0 14px;font-size:11px;font-weight:900;color:#999;letter-spacing:2px;">Articles commandés</p>
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
                    <td style="padding:5px 0;font-size:13px;color:#666;">Sous-total articles</td>
                    <td align="right" style="padding:5px 0;font-size:13px;font-weight:700;color:#1a1a2e;">
                      {{ number_format($order['subtotal'], 0, ',', ' ') }} FCFA
                    </td>
                  </tr>

                  @if(!empty($order['discount']) && $order['discount'] > 0)
                  <tr>
                    <td style="padding:5px 0;font-size:13px;color:#16a34a;">Réduction</td>
                    <td align="right" style="padding:5px 0;font-size:13px;font-weight:700;color:#16a34a;">
                      - {{ number_format($order['discount'], 0, ',', ' ') }} FCFA
                    </td>
                  </tr>
                  @endif

                  <tr>
                    <td style="padding:5px 0;font-size:13px;color:#666;">Frais de livraison</td>
                    <td align="right" style="padding:5px 0;font-size:13px;font-weight:700;">
                      @if(!empty($order['shipping_confirmed']) && $order['shipping_confirmed'])
                        <span style="color:#16a34a;">{{ number_format($order['livraison'], 0, ',', ' ') }} FCFA</span>
                      @else
                        <span style="color:#e60012;font-style:italic;font-size:11px;">En attente de confirmation</span>
                      @endif
                    </td>
                  </tr>

                  <tr>
                    <td style="padding:14px 0 0;font-size:15px;font-weight:900;color:#1a1a2e;border-top:2px solid #f0f0f0;">
                      @if(!empty($order['shipping_confirmed']) && $order['shipping_confirmed'])
                        Total final TTC
                      @else
                        Total articles
                      @endif
                    </td>
                    <td align="right" style="padding:14px 0 0;font-size:20px;font-weight:900;color:#e60012;border-top:2px solid #f0f0f0;">
                      @if(!empty($order['shipping_confirmed']) && $order['shipping_confirmed'])
                        {{ number_format($order['subtotal'] + $order['livraison'], 0, ',', ' ') }} FCFA
                      @else
                        {{ number_format($order['total'], 0, ',', ' ') }} FCFA
                      @endif
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- ── Livraison + Paiement ── -->
      <tr>
        <td style="background:#fff;padding:0 40px 28px;">
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <!-- Livraison -->
              <td width="48%" valign="top"
                style="background:#f8fafc;border-radius:12px;padding:18px;border:1px solid #e8edf2;">
                <p style="margin:0 0 10px;font-size:11px;font-weight:900;color:#999;letter-spacing:1.5px;">Livraison</p>
                <p style="margin:0 0 3px;font-size:13px;font-weight:700;color:#1a1a2e;">{{ $order['nom'] }}</p>
                <p style="margin:0 0 10px;font-size:12px;color:#666;">{{ $order['adresse'] }}</p>

                @if(!empty($order['shipping_confirmed']) && $order['shipping_confirmed'])
                  <div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:10px;border-radius:8px;">
                    <p style="margin:0;font-size:12px;font-weight:900;color:#166534;">
                      Frais confirmés : {{ number_format($order['livraison'], 0, ',', ' ') }} FCFA
                    </p>
                    <p style="margin:4px 0 0;font-size:11px;color:#4ade80;">Votre livreur vous contactera bientôt.</p>
                  </div>
                @else
                  <div style="background:#fff4f4;border:1px dashed #e60012;padding:10px;border-radius:8px;">
                    <p style="margin:0;font-size:11px;color:#e60012;font-weight:700;line-height:1.5;">
                      Frais en cours de calcul.<br/>
                      Notre équipe vous contactera sous <strong>10 minutes</strong>.
                    </p>
                  </div>
                @endif
              </td>

              <td width="4%"></td>

              <!-- Paiement -->
              <td width="48%" valign="top"
                style="background:#f8fafc;border-radius:12px;padding:18px;border:1px solid #e8edf2;">
                <p style="margin:0 0 10px;font-size:11px;font-weight:900;color:#999;letter-spacing:1.5px;">Paiement</p>
                @if($order['payment'] === 'om')
                  <p style="margin:0 0 6px;font-size:13px;font-weight:900;color:#ea580c;">Orange Money</p>
                  @if(!empty($order['shipping_confirmed']) && $order['shipping_confirmed'])
                    <p style="margin:0 0 3px;font-size:12px;color:#666;">
                      Montant total à payer : <strong>{{ number_format($order['subtotal'] + $order['livraison'], 0, ',', ' ') }} FCFA</strong>
                      Code Marchand : <strong>#150*14*427230*657905056*Montant#<strong>{{ number_format($order['total']) }}#<br></strong>
                      Nom : <strong>Ets YOMENI</strong>
                    </p>
                  @else
                    <p style="margin:0;font-size:11px;color:#999;line-height:1.4;">
                      Attendez la confirmation des frais de livraison avant d'effectuer le transfert.
                    </p>
                  @endif
                @elseif($order['payment'] === 'momo')
                  <p style="margin:0 0 6px;font-size:13px;font-weight:900;color:#ca8a04;">MTN Mobile Money</p>
                  @if(!empty($order['shipping_confirmed']) && $order['shipping_confirmed'])
                    <p style="margin:0 0 3px;font-size:12px;color:#666;">
                      Montant total à payer : <strong>{{ number_format($order['total'], 0,',') }} FCFA</strong><br>
                      Code Marchand : <strong>*126*16*925847*Montant# </strong><br>
                      Nom : <strong>Vanelle Yemdjeu<strong>
                    </p>
                  @else
                    <p style="margin:0;font-size:11px;color:#999;line-height:1.4;">
                      Attendez la confirmation des frais de livraison avant d'effectuer le transfert.
                    </p>
                  @endif
                @else
                  <p style="margin:0 0 6px;font-size:13px;font-weight:900;color:#274a82;">Paiement à la livraison</p>
                  @if(!empty($order['shipping_confirmed']) && $order['shipping_confirmed'])
                    <p style="margin:0;font-size:11px;color:#666;line-height:1.4;">
                      Préparez <strong>{{ number_format($order['subtotal'] + $order['livraison'], 0, ',', ' ') }} FCFA</strong> à la réception.
                    </p>
                  @else
                    <p style="margin:0;font-size:11px;color:#666;line-height:1.4;">
                      Payez articles + frais de livraison (communiqués sous 30min) à la réception.
                    </p>
                  @endif
                @endif
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- ── Prochaines étapes ── -->
      <tr>
        <td style="background:#fff;padding:0 40px 28px;">
          <div style="background:linear-gradient(135deg,#274a82,#1a3460);border-radius:12px;padding:22px;">
            <p style="margin:0 0 14px;font-size:11px;font-weight:900;color:rgba(255,255,255,0.45);letter-spacing:2px;">
              Prochaines étapes
            </p>

            <table cellpadding="0" cellspacing="0" style="margin-bottom:10px;width:100%;">
              <tr>
                <td width="28" valign="top">
                  <div style="width:22px;height:22px;background:rgba(255,255,255,0.15);border-radius:50%;text-align:center;line-height:22px;font-size:10px;font-weight:900;color:#fff;">1</div>
                </td>
                <td style="padding-left:10px;">
                  <p style="margin:0;font-size:12px;color:rgba(255,255,255,0.85);line-height:1.5;">
                    Validation de votre commande par notre équipe logistique.
                  </p>
                </td>
              </tr>
            </table>

            <table cellpadding="0" cellspacing="0" style="margin-bottom:10px;width:100%;">
              <tr>
                <td width="28" valign="top">
                  <div style="width:22px;height:22px;background:#e60012;border-radius:50%;text-align:center;line-height:22px;font-size:10px;font-weight:900;color:#fff;">2</div>
                </td>
                <td style="padding-left:10px;">
                  @if(!empty($order['shipping_confirmed']) && $order['shipping_confirmed'])
                    <p style="margin:0;font-size:12px;color:#fff;font-weight:700;line-height:1.5;">
                      Frais de livraison confirmés :
                      <strong>{{ number_format($order['livraison'], 0, ',', ' ') }} FCFA</strong>
                    </p>
                  @else
                    <p style="margin:0;font-size:12px;color:#fff;font-weight:700;line-height:1.5;">
                      Sous 30 minutes : notre équipe vous contacte pour confirmer les frais de livraison exacts.
                    </p>
                  @endif
                </td>
              </tr>
            </table>

            <table cellpadding="0" cellspacing="0" style="margin-bottom:14px;width:100%;">
              <tr>
                <td width="28" valign="top">
                  <div style="width:22px;height:22px;background:rgba(255,255,255,0.15);border-radius:50%;text-align:center;line-height:22px;font-size:10px;font-weight:900;color:#fff;">3</div>
                </td>
                <td style="padding-left:10px;">
                  <p style="margin:0;font-size:12px;color:rgba(255,255,255,0.85);line-height:1.5;">
                    Expédition de vos articles à votre domicile ou bureau.
                  </p>
                </td>
              </tr>
            </table>

            <!-- Cadeau clé USB -->
            <div style="background:rgba(230,0,18,0.18);border:1px solid rgba(230,0,18,0.4);border-radius:10px;padding:14px 16px;">
              <p style="margin:0;font-size:13px;color:#fff;font-weight:900;line-height:1.5;">
              Cadeau inclus dans votre colis !
              </p>
              <p style="margin:6px 0 0;font-size:12px;color:rgba(255,255,255,0.85);line-height:1.5;">
                Une <strong style="color:#fff;">clé USB 32GB</strong> vous sera remise à la livraison.<br/>
                Merci pour votre confiance chez BRC Market ! 🙏
              </p>
            </div>
          </div>
        </td>
      </tr>

      <!-- ── Footer ── -->
      <tr>
        <td style="background:#f8fafc;border-radius:0 0 16px 16px;padding:24px 40px;text-align:center;border:1px solid #e8edf2;border-top:none;">
          <p style="margin:0 0 4px;font-size:13px;font-weight:700;color:#1a1a2e;">BRC Market</p>
          <p style="margin:0 0 10px;font-size:12px;color:#999;">Douala & Yaoundé, Cameroun et toute l'Afrique</p>
          <p style="margin:0;font-size:11px;color:#bbb;line-height:1.6;">
            Cet email a été envoyé suite à votre commande sur BRC Market.<br/>
            Des questions ? Contactez-nous par WhatsApp au <strong>+237 689 205 751</strong>
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>