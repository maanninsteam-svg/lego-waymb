<?php
function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function format_money(float $amount): string {
    return number_format($amount, 2, ',', '.') . ' €';
}

function format_date(?string $dt): string {
    if (!$dt) return '—';
    try {
        $d = new DateTime($dt);
        return $d->format('d/m/Y H:i');
    } catch (Exception $e) {
        return $dt;
    }
}

function status_badge(string $status): string {
    $map = [
        'waiting_payment' => ['label' => 'Aguardando', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
        'paid'            => ['label' => 'Pago',       'color' => '#059669', 'bg' => '#d1fae5'],
        'shipped'         => ['label' => 'Enviado',    'color' => '#2563eb', 'bg' => '#dbeafe'],
        'refunded'        => ['label' => 'Reembolsado','color' => '#dc2626', 'bg' => '#fee2e2'],
    ];
    $s = $map[$status] ?? ['label' => h($status), 'color' => '#6b7280', 'bg' => '#f3f4f6'];
    return sprintf(
        '<span style="background:%s;color:%s;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">%s</span>',
        $s['bg'], $s['color'], $s['label']
    );
}

function method_label(string $method): string {
    $map = [
        'mbway'       => 'MB WAY',
        'multibanco'  => 'Multibanco',
        'credit_card' => 'Cartão',
        'pix'         => 'PIX',
        'boleto'      => 'Boleto',
        'paypal'      => 'PayPal',
    ];
    return $map[strtolower($method)] ?? strtoupper($method);
}

/**
 * Vai buscar emails recebidos ao Resend e cria tickets para os novos.
 * Retorna o número de tickets criados.
 */
function sync_received_emails(PDO $pdo): array {
    $config = json_decode(file_get_contents(__DIR__ . '/../../admin-config.json'), true);
    $apiKey = $config['resend']['api_key'] ?? '';
    if (!$apiKey) return ['synced' => 0, 'error' => 'API key não configurada'];

    // Throttle: só sincroniza se passaram mais de 60 segundos
    $flagFile = '/var/www/html/db/.last_email_sync';
    if (file_exists($flagFile) && (time() - (int)file_get_contents($flagFile)) < 60) {
        return ['synced' => 0, 'error' => null, 'skipped' => true];
    }

    // Buscar lista de emails recebidos
    $ch = curl_init('https://api.resend.com/emails/receiving');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    file_put_contents($flagFile, time()); // atualizar throttle mesmo em erro

    if ($httpCode !== 200) {
        error_log('[sync_received_emails] API list error: HTTP ' . $httpCode . ' ' . $response);
        return ['synced' => 0, 'error' => 'Resend API devolveu HTTP ' . $httpCode];
    }

    $list   = json_decode($response, true);
    $emails = $list['data'] ?? [];
    $synced = 0;

    foreach ($emails as $meta) {
        $emailId = $meta['id'] ?? '';
        if (!$emailId) continue;

        // Já processado?
        $dup = $pdo->prepare("SELECT id FROM support_tickets WHERE email_message_id = :mid");
        $dup->execute([':mid' => $emailId]);
        if ($dup->fetch()) continue;

        // Buscar email completo (com corpo)
        $ch2 = curl_init('https://api.resend.com/emails/receiving/' . $emailId);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $full     = curl_exec($ch2);
        $httpCode2 = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if ($httpCode2 !== 200) continue;

        $email = json_decode($full, true);

        // Normalizar "from"
        $fromRaw = $email['from'] ?? '';
        if (is_array($fromRaw)) {
            $fromName  = $fromRaw['name']  ?? '';
            $fromEmail = strtolower($fromRaw['email'] ?? '');
        } elseif (preg_match('/^(.*?)\s*<([^>]+)>$/', trim($fromRaw), $m)) {
            $fromName  = trim($m[1], ' "\'');
            $fromEmail = strtolower(trim($m[2]));
        } else {
            $fromName  = '';
            $fromEmail = strtolower(trim($fromRaw));
        }

        // Ignorar emails enviados por nós mesmos
        if (str_contains($fromEmail, 'legoworld2026.com')) continue;

        $subject = $email['subject'] ?? '(sem assunto)';

        // Extrair corpo — o Resend pode ter 'text', 'plain_text' ou só 'html'
        $rawText = $email['text'] ?? $email['plain_text'] ?? null;
        $rawHtml = $email['html'] ?? $email['body_html']  ?? null;

        if ($rawText !== null && trim($rawText) !== '') {
            $body = trim($rawText);
        } elseif ($rawHtml !== null && trim($rawHtml) !== '') {
            $body = trim(strip_tags((string)$rawHtml));
        } else {
            // Nenhum campo de corpo encontrado — logar as chaves para diagnóstico
            error_log('[sync_received_emails] sem body para ' . $emailId . '. Chaves: ' . implode(',', array_keys($email)));
            $body = '(sem conteúdo de texto)';
        }

        // Remover texto citado (quoted reply) — padrão alargado para datas longas
        // "Em seg., 20 de abr. de 2026 às 00:14, LEGO World Cup 2026 <...> escreveu:"
        $body = preg_replace('/\r?\n[-]{2,}\s*$.*$/su',                     '', $body);
        $body = preg_replace('/\r?\nEm .{5,300} escreveu\s*:.*$/su',        '', $body);
        $body = preg_replace('/\r?\nOn .{5,300} wrote\s*:.*$/su',           '', $body);
        $body = preg_replace('/\r?\n_{3,}.*$/su',                           '', $body);
        $body = trim($body);
        if ($body === '') $body = '(sem conteúdo de texto)';

        // Tentar extrair order_id do assunto ("Código: XXXXXXXX")
        $orderId = null;
        if (preg_match('/[Cc][oó]digo[:\s]+([A-Z0-9\-]{6,30})/u', $subject, $m)) {
            $orderId = $m[1];
        }

        $emailContext = $email['html'] ?? $email['text'] ?? '';

        $stmt = $pdo->prepare("
            INSERT INTO support_tickets
                (order_id, name, email, subject, message, status, source, email_message_id, email_context, created_at)
            VALUES
                (:order_id, :name, :email, :subject, :message, 'open', 'email', :msg_id, :context, datetime('now'))
        ");
        $stmt->execute([
            ':order_id' => $orderId,
            ':name'     => $fromName ?: $fromEmail,
            ':email'    => $fromEmail,
            ':subject'  => $subject,
            ':message'  => $body,
            ':msg_id'   => $emailId,
            ':context'  => $emailContext,
        ]);
        $synced++;
    }

    return ['synced' => $synced, 'error' => null];
}

function send_tracking_email(
    string $toEmail,
    string $toName,
    string $trackingCode,
    array  $items,
    float  $amount
): bool {
    $config = json_decode(file_get_contents(__DIR__ . '/../../admin-config.json'), true);
    $apiKey    = $config['resend']['api_key']    ?? '';
    $fromName  = $config['resend']['from_name']  ?? 'LEGO World Cup 2026';
    $fromEmail = $config['resend']['from_email'] ?? 'support@legoworld2026.com';

    if ($apiKey === '') {
        error_log('send_tracking_email: Resend API key not configured');
        return false;
    }

    // Build items rows
    $itemsRows = '';
    foreach ($items as $item) {
        $name  = htmlspecialchars((string)($item['name']     ?? 'Produto'),   ENT_QUOTES, 'UTF-8');
        $qty   = (int)($item['quantity'] ?? 1);
        $price = (float)($item['price'] ?? 0);
        $line  = number_format($price * $qty, 2, ',', '.') . ' €';
        $itemsRows .= "
            <tr>
                <td style=\"padding:10px 12px;border-bottom:1px solid #e5e7eb;\">{$name}</td>
                <td style=\"padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:center;\">{$qty}</td>
                <td style=\"padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:right;\">{$line}</td>
            </tr>";
    }

    $totalFormatted = number_format($amount, 2, ',', '.') . ' €';
    $nameSafe       = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $codeSafe       = htmlspecialchars($trackingCode, ENT_QUOTES, 'UTF-8');
    $trackingUrl    = 'https://t.17track.net/en?nums=' . urlencode($trackingCode);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:600px;">
        <!-- Header -->
        <tr>
          <td style="background:#1e293b;padding:28px 32px;text-align:center;">
            <h1 style="margin:0;color:#f59e0b;font-size:24px;font-weight:700;">⚽ LEGO World Cup 2026</h1>
            <p style="margin:8px 0 0;color:#94a3b8;font-size:14px;">Encomenda Despachada</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:32px;">
            <p style="margin:0 0 16px;font-size:16px;color:#1e293b;">Olá, <strong>{$nameSafe}</strong>!</p>
            <p style="margin:0 0 24px;font-size:15px;color:#374151;line-height:1.6;">
              A sua encomenda foi despachada e está a caminho. Pode acompanhar a entrega com o código de rastreio abaixo.
            </p>

            <!-- Tracking code box -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
              <tr>
                <td style="background:#fef3c7;border:2px solid #f59e0b;border-radius:10px;padding:20px 24px;text-align:center;">
                  <p style="margin:0 0 8px;font-size:13px;color:#92400e;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Código de Rastreio</p>
                  <p style="margin:0;font-size:28px;font-weight:700;color:#1e293b;letter-spacing:3px;">{$codeSafe}</p>
                  <p style="margin:16px 0 0;">
                    <a href="{$trackingUrl}" target="_blank"
                       style="display:inline-block;background:#f59e0b;color:#1e293b;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px;text-decoration:none;letter-spacing:0.5px;">
                      Rastrear Encomenda →
                    </a>
                  </p>
                </td>
              </tr>
            </table>

            <!-- Items table -->
            <h3 style="margin:0 0 12px;font-size:15px;color:#1e293b;font-weight:600;">Resumo da Encomenda</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:24px;">
              <thead>
                <tr style="background:#f8fafc;">
                  <th style="padding:10px 12px;text-align:left;font-size:12px;color:#6b7280;text-transform:uppercase;font-weight:600;border-bottom:1px solid #e5e7eb;">Produto</th>
                  <th style="padding:10px 12px;text-align:center;font-size:12px;color:#6b7280;text-transform:uppercase;font-weight:600;border-bottom:1px solid #e5e7eb;">Qtd</th>
                  <th style="padding:10px 12px;text-align:right;font-size:12px;color:#6b7280;text-transform:uppercase;font-weight:600;border-bottom:1px solid #e5e7eb;">Valor</th>
                </tr>
              </thead>
              <tbody>
                {$itemsRows}
              </tbody>
              <tfoot>
                <tr style="background:#f8fafc;">
                  <td colspan="2" style="padding:12px;font-weight:700;color:#1e293b;font-size:14px;">Total</td>
                  <td style="padding:12px;font-weight:700;color:#1e293b;font-size:14px;text-align:right;">{$totalFormatted}</td>
                </tr>
              </tfoot>
            </table>

            <p style="margin:0 0 8px;font-size:14px;color:#374151;line-height:1.6;">
              Se tiver alguma dúvida sobre a sua encomenda, não hesite em contactar-nos.
            </p>
            <p style="margin:0;font-size:14px;color:#374151;">
              Com os melhores cumprimentos,<br>
              <strong>Equipa LEGO World Cup 2026</strong>
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f8fafc;padding:20px 32px;border-top:1px solid #e5e7eb;text-align:center;">
            <p style="margin:0;font-size:12px;color:#9ca3af;">
              © 2026 LEGO World Cup 2026. Todos os direitos reservados.<br>
              Este email foi enviado automaticamente — por favor não responda diretamente.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

    $payload = json_encode([
        'from'    => "{$fromName} <{$fromEmail}>",
        'to'      => ["{$toName} <{$toEmail}>"],
        'subject' => "O seu pedido foi despachado — Código: {$trackingCode}",
        'html'    => $html,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr !== '') {
        error_log('send_tracking_email curl error: ' . $curlErr);
        return false;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('send_tracking_email HTTP ' . $httpCode . ': ' . $response);
        return false;
    }
    return true;
}
