<?php
// pagamento.php - MangoFy API
header('Content-Type: application/json');

// =====================================
// FUNÇÕES PARA GERAR DADOS ALEATÓRIOS
// =====================================

// Gera um CPF válido (apenas números)
function gerarCPF()
{
    $n = [];
    for ($i = 0; $i < 9; $i++) {
        $n[$i] = rand(0, 9);
    }

    // 1º dígito
    $soma = 0;
    for ($i = 0, $peso = 10; $i < 9; $i++, $peso--) {
        $soma += $n[$i] * $peso;
    }
    $resto = $soma % 11;
    $dv1 = ($resto < 2) ? 0 : 11 - $resto;
    $n[9] = $dv1;

    // 2º dígito
    $soma = 0;
    for ($i = 0, $peso = 11; $i < 10; $i++, $peso--) {
        $soma += $n[$i] * $peso;
    }
    $resto = $soma % 11;
    $dv2 = ($resto < 2) ? 0 : 11 - $resto;
    $n[10] = $dv2;

    return implode('', $n); // só números
}

// Gera um nome completo
function gerarNome()
{
    $nomes = ['João', 'Maria', 'Pedro', 'Ana', 'Carlos', 'Mariana', 'Lucas', 'Juliana', 'Fernando', 'Patrícia'];
    $sobrenomes = ['Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves', 'Pereira', 'Gomes', 'Martins'];

    $nome = $nomes[array_rand($nomes)];
    $sob1 = $sobrenomes[array_rand($sobrenomes)];
    $sob2 = $sobrenomes[array_rand($sobrenomes)];

    return $nome . ' ' . $sob1 . ' ' . $sob2;
}

// Gera telefone no formato DDD + 9 + número
function gerarTelefone()
{
    $ddds = ['11', '21', '31', '41', '51', '61', '71', '81', '91'];
    $ddd = $ddds[array_rand($ddds)];

    // 8 dígitos, começando com 9 (celular)
    $num = rand(10000000, 99999999);
    $numStr = '9' . substr((string) $num, 1); // garante 9XXXXXXXX

    return $ddd . $numStr;
}

// Gera e-mail a partir do nome
function gerarEmail($nome)
{
    $provedores = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.br', 'icloud.com'];
    $nomeFormatado = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $nome));
    $nomeFormatado = preg_replace('/[^a-z0-9]+/i', '.', $nomeFormatado);
    $nomeFormatado = trim($nomeFormatado, '.');

    $dominio = $provedores[array_rand($provedores)];

    return $nomeFormatado . '@' . $dominio;
}

// =====================================
// Lê dados do front
// =====================================
$input = json_decode(file_get_contents('php://input'), true);

// Valor da oferta: 2874 (R$ 21,67)
$amountFromFront = isset($input['amount']) ? (int) $input['amount'] : 0;
$amount = $amountFromFront > 0 ? $amountFromFront : 2874;

$utm = [];
if (!empty($input['utmQuery'])) {
    $utmDecoded = json_decode($input['utmQuery'], true);
    if (is_array($utmDecoded)) {
        $utm = $utmDecoded;
    }
}

// =====================================
// CONFIG MANGOFY
// =====================================

// COLE SUAS CREDENCIAIS MANGOFY AQUI
$MANGOFY_API_TOKEN = '22a13f6f48064863af2f412e8c1877b2blegxppxapplkmktuswuo58iyb5gu6m'; // Authorization header
$MANGOFY_STORE_CODE = '5cc977b5311cbbd3cb5cb2809912444e'; // Store-Code header

$API_URL = 'https://checkout.mangofy.com.br/api/v1/payment';

// =====================================
// Validação
// =====================================
if ($amount <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Valor inválido.'
    ]);
    exit;
}

// =====================================
// Gera dados aleatórios do "cliente"
// =====================================
// Se o nome vier do frontend, usa ele. Senão gera aleatório.
$nomeDoFront = isset($input['customerName']) ? trim($input['customerName']) : '';
$nomeCliente = !empty($nomeDoFront) ? $nomeDoFront : gerarNome();
$cpfCliente = gerarCPF();          // só números
$telCliente = gerarTelefone();
$emailCliente = gerarEmail($nomeCliente);

// Pega o IP do usuário
$userIP = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// =====================================
// Monta payload para MangoFy API
// =====================================
$payload = [
    "store_code" => $MANGOFY_STORE_CODE,
    "external_code" => uniqid('txn_'), // identificador único interno
    "payment_method" => "pix",
    "payment_format" => "regular",
    "installments" => 1,
    "payment_amount" => $amount, // em centavos
    "shipping_amount" => 0,
    "postback_url" => "https://seusite.com/webhook/mangofy", // troque depois

    "items" => [
        [
            "name" => "Licença Vitalícia ZapEspião",
            "quantity" => 1,
            "price" => $amount
        ]
    ],

    "customer" => [
        "email" => $emailCliente,
        "name" => $nomeCliente,
        "document" => $cpfCliente, // CPF sem máscara
        "phone" => $telCliente,
        "ip" => $userIP
    ],

    "pix" => [
        "expires_in_days" => 1 // expira em 1 dia
    ],

    "extra" => [
        "metadata" => [
            "utm_source" => $utm['utm_source'] ?? '',
            "utm_medium" => $utm['utm_medium'] ?? '',
            "utm_campaign" => $utm['utm_campaign'] ?? '',
            "utm_term" => $utm['utm_term'] ?? '',
            "utm_content" => $utm['utm_content'] ?? ''
        ]
    ]
];

// =====================================
// Chamada à API MangoFy
// =====================================
$ch = curl_init($API_URL);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: ' . $MANGOFY_API_TOKEN,
    'Store-Code: ' . $MANGOFY_STORE_CODE,
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

// =====================================
// Erro de comunicação
// =====================================
if ($response === false || $httpCode === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao comunicar com a MangoFy.'
    ]);
    exit;
}

// =====================================
// Trata erros HTTP
// =====================================
if ($httpCode < 200 || $httpCode >= 300 || !is_array($data)) {
    $errorMsg = "Erro na API MangoFy (HTTP {$httpCode})";

    // Tenta extrair mensagem de erro da resposta
    if (is_array($data) && isset($data['message'])) {
        $errorMsg .= ": " . $data['message'];
    }

    echo json_encode([
        'success' => false,
        'error' => $errorMsg,
        'raw' => $data ?? $response
    ]);
    exit;
}

// =====================================
// Extrai dados do pagamento
// =====================================
$paymentCode = $data['payment_code'] ?? null;
$paymentStatus = $data['payment_status'] ?? null;

// Para PIX, procura pelo QR code em diferentes campos possíveis
$pixQrCode = null;
if (isset($data['pix']['pix_qrcode_text'])) {
    $pixQrCode = $data['pix']['pix_qrcode_text']; // MangoFy usa este campo
} elseif (isset($data['pix']['qr_code'])) {
    $pixQrCode = $data['pix']['qr_code'];
} elseif (isset($data['pix']['pix_qr_code'])) {
    $pixQrCode = $data['pix']['pix_qr_code'];
}

// Validação
if (!$paymentCode || !$pixQrCode) {
    echo json_encode([
        'success' => false,
        'error' => 'MangoFy não retornou payment_code ou código PIX.',
        'raw' => $data
    ]);
    exit;
}

// =====================================
// Resposta no formato que seu JS espera
// =====================================
echo json_encode([
    'success' => true,
    'amount' => $amount,
    'data' => [
        'transactionId' => $paymentCode,
        'pix' => [
            'pix_qr_code' => $pixQrCode
        ]
    ]
]);