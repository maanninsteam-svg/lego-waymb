<?php
// pagamento.php - Paradise API Integration
header('Content-Type: application/json');

// =====================================
// FUNÇÕES PARA GERAR DADOS ALEATÓRIOS
// =====================================

function gerarCPF()
{
    $n = [];
    for ($i = 0; $i < 9; $i++) {
        $n[$i] = rand(0, 9);
    }
    $soma = 0;
    for ($i = 0, $peso = 10; $i < 9; $i++, $peso--) {
        $soma += $n[$i] * $peso;
    }
    $resto = $soma % 11;
    $dv1 = ($resto < 2) ? 0 : 11 - $resto;
    $n[9] = $dv1;
    $soma = 0;
    for ($i = 0, $peso = 11; $i < 10; $i++, $peso--) {
        $soma += $n[$i] * $peso;
    }
    $resto = $soma % 11;
    $dv2 = ($resto < 2) ? 0 : 11 - $resto;
    $n[10] = $dv2;
    return implode('', $n);
}

function gerarNome()
{
    $nomes = ['João', 'Maria', 'Pedro', 'Ana', 'Carlos', 'Mariana', 'Lucas', 'Juliana', 'Fernando', 'Patrícia'];
    $sobrenomes = ['Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves', 'Pereira', 'Gomes', 'Martins'];
    return $nomes[array_rand($nomes)] . ' ' . $sobrenomes[array_rand($sobrenomes)] . ' ' . $sobrenomes[array_rand($sobrenomes)];
}

function gerarTelefone()
{
    $ddds = ['11', '21', '31', '41', '51', '61', '71', '81', '91'];
    return $ddds[array_rand($ddds)] . '9' . rand(10000000, 99999999);
}

function gerarEmail($nome)
{
    // Garantir unicidade: Date.now() + random string (regra Paradise)
    $nomeFormatado = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $nome));
    $nomeFormatado = preg_replace('/[^a-z0-9]+/i', '.', $nomeFormatado);
    $nomeFormatado = trim($nomeFormatado, '.');
    
    $timestamp = time();
    $randomStr = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 6);
    
    return "{$nomeFormatado}_{$timestamp}_{$randomStr}@gmail.com";
}

// =====================================
// Lê dados do front
// =====================================
$input = json_decode(file_get_contents('php://input'), true);

$amount = isset($input['amount']) ? (int) $input['amount'] : 0500; // Default 05,00
if ($amount <= 0)
    $amount = 0500;

$utmParams = [];
if (!empty($input['utmQuery'])) {
    $decodedUtm = json_decode($input['utmQuery'], true);
    if (is_array($decodedUtm)) {
        $utmParams = $decodedUtm;
    }
}

// =====================================
// CONFIG PARADISE API
// =====================================
$API_URL = 'https://multi.paradisepags.com/api/v1/transaction.php';
$API_KEY = 'sk_8a30d556e8cb4f1920f10e829d8b5485f38ad860bf7f4b6609c19af3d90c1964';
$PRODUCT_HASH = 'prod_79c2b95c42cb6822';

// =====================================
// Gera dados do cliente
// =====================================
$nomeCliente = gerarNome();
$cpfCliente = gerarCPF();
$telCliente = gerarTelefone();
$emailCliente = gerarEmail($nomeCliente);

$reference = 'REF-' . time() . '-' . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 6);

// =====================================
// Monta payload Paradise API
// =====================================
$payload = [
    'amount'      => $amount,
    'description' => 'Taxa de Emissão',
    'reference'   => $reference,
    'productHash' => $PRODUCT_HASH,
    'customer'    => [
        'name'     => $nomeCliente,
        'email'    => $emailCliente,
        'document' => $cpfCliente,
        'phone'    => $telCliente
    ]
];

// Tracking
// Campos permitidos pela Paradise: utm_source, utm_medium, utm_campaign, utm_content, utm_term, src, sck
$tracking = [];
$camposSuportados = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'src', 'sck'];
foreach ($camposSuportados as $campo) {
    if (isset($utmParams[$campo]) && !empty($utmParams[$campo])) {
        $tracking[$campo] = $utmParams[$campo];
    }
}

if (!empty($tracking)) {
    $payload['tracking'] = $tracking;
}

// =====================================
// Chamada à API Paradise
// =====================================
$ch = curl_init($API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "X-API-Key: {$API_KEY}",
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

$data = json_decode($response, true);

if ($error || $httpCode < 200 || $httpCode >= 300 || (!empty($data['status']) && $data['status'] !== 'success')) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao gerar PIX: ' . ($data['message'] ?? 'Erro desconhecido'),
        'raw' => $data,
        'curl_error' => $error
    ]);
    exit;
}

// =====================================
// Extrai dados e retorna para o Frontend JS
// =====================================
// A API retorna qr_code (texto), transaction_id, etc.
$pixCode = $data['qr_code'] ?? null;
$transactionId = $data['transaction_id'] ?? null;

if (!$pixCode || !$transactionId) {
    echo json_encode([
        'success' => false,
        'error' => 'Resposta incompleta da API Paradise',
        'raw' => $data
    ]);
    exit;
}

// Mantendo a estrutura compatível esperada pelo JavaScript (handlePixSuccess)
echo json_encode([
    'success' => true,
    'amount' => $amount,
    'id' => $transactionId,
    'data' => [
        'transactionId' => $transactionId,
        'pix' => [
            'pix_qr_code' => $pixCode // Aqui mandamos o texto do qr_code
        ]
    ]
]);