<?php
/**
 * admin/ai-reply.php
 * AJAX endpoint — gera uma sugestão de resposta ao ticket via Claude (Anthropic).
 * Retorna JSON: { reply: "..." } ou { error: "..." }
 */
require_once __DIR__ . '/includes/auth.php';
require_admin_auth();

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$ticketId = (int)($_GET['id'] ?? 0);
if ($ticketId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de ticket inválido.']);
    exit;
}

$pdo  = get_db();
$stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = :id");
$stmt->execute([':id' => $ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    echo json_encode(['error' => 'Ticket não encontrado.']);
    exit;
}

$config = json_decode(file_get_contents(__DIR__ . '/../admin-config.json'), true);
$apiKey = $config['anthropic']['api_key'] ?? '';

if ($apiKey === '') {
    http_response_code(503);
    echo json_encode(['error' => 'Chave da API Anthropic não configurada. Adicione ANTHROPIC_API_KEY nas variáveis de ambiente.']);
    exit;
}

$systemPrompt = 'És um agente de suporte ao cliente da loja online LEGO World Cup 2026, que vende produtos LEGO temáticos do Campeonato do Mundo FIFA 2026. Responde sempre em Português de Portugal (PT-PT), de forma profissional, empática e cordial. As respostas devem ser concisas (2 a 4 parágrafos), resolver a dúvida ou problema do cliente e terminar com uma saudação cordial assinada como "Equipa de Suporte LEGO World Cup 2026". Não uses markdown, apenas texto simples.';

// Construir contexto completo: se veio de email, incluir o email original citado
$source = $ticket['source'] ?? 'form';
if ($source === 'email' && !empty($ticket['email_context'])) {
    // Extrair texto simples do HTML do email original (contexto do rastreio)
    $originalText = strip_tags($ticket['email_context']);
    $originalText = preg_replace('/\s{3,}/', "\n", $originalText);
    $originalText = trim(substr($originalText, 0, 800)); // limite razoável

    $userContent  = "Assunto: " . $ticket['subject'] . "\n\n";
    $userContent .= "=== EMAIL ORIGINAL (enviado pela nossa loja ao cliente) ===\n";
    $userContent .= $originalText . "\n";
    $userContent .= "=== RESPOSTA DO CLIENTE (" . $ticket['name'] . ") ===\n";
    $userContent .= $ticket['message'];
} else {
    $userContent = "Assunto: " . $ticket['subject'] . "\n\nMensagem do cliente (" . $ticket['name'] . "):\n" . $ticket['message'];
}

$payload = json_encode([
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 600,
    'system'     => $systemPrompt,
    'messages'   => [
        ['role' => 'user', 'content' => $userContent],
    ],
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr !== '') {
    http_response_code(502);
    echo json_encode(['error' => 'Erro de ligação à API: ' . $curlErr]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code(502);
    echo json_encode(['error' => 'A API respondeu com o código HTTP ' . $httpCode . '.']);
    exit;
}

$data = json_decode($response, true);
$text = $data['content'][0]['text'] ?? '';

if ($text === '') {
    http_response_code(502);
    echo json_encode(['error' => 'A API devolveu uma resposta vazia.']);
    exit;
}

echo json_encode(['reply' => $text], JSON_UNESCAPED_UNICODE);
