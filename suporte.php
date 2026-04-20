<?php
require_once __DIR__ . '/admin/includes/db.php';

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot check
    if (!empty($_POST['website'])) {
        // Bot detected — silently succeed
        $success = true;
    } else {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $orderId  = trim($_POST['order_id'] ?? '');
        $subject  = trim($_POST['subject']  ?? '');
        $message  = trim($_POST['message']  ?? '');

        // Validation
        if ($name === '')                    $errors['name']    = 'O nome é obrigatório.';
        if ($email === '')                   $errors['email']   = 'O email é obrigatório.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email inválido.';
        if ($subject === '')                 $errors['subject'] = 'Selecione um assunto.';
        if (strlen($message) < 10)           $errors['message'] = 'A mensagem deve ter pelo menos 10 caracteres.';

        if (empty($errors)) {
            try {
                $pdo = get_db();
                $stmt = $pdo->prepare("
                    INSERT INTO support_tickets (order_id, name, email, subject, message, status, created_at)
                    VALUES (:order_id, :name, :email, :subject, :message, 'open', datetime('now'))
                ");
                $stmt->execute([
                    ':order_id' => $orderId !== '' ? $orderId : null,
                    ':name'     => $name,
                    ':email'    => $email,
                    ':subject'  => $subject,
                    ':message'  => $message,
                ]);
                $success = true;
            } catch (Throwable $e) {
                error_log('suporte.php DB error: ' . $e->getMessage());
                $errors['general'] = 'Ocorreu um erro ao enviar a mensagem. Por favor tente novamente.';
            }
        }
    }
}

function sv(string $field): string {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return '';
    return htmlspecialchars(trim($_POST[$field] ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suporte — LEGO World Cup 2026</title>
    <meta name="description" content="Contacte a nossa equipa de suporte para qualquer questão sobre o seu pedido.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #FFF9E6;
            --card: #ffffff;
            --yellow: #f5c518;
            --yellow-dark: #EAB308;
            --yellow-soft: #FFFBEB;
            --text: #1a1d26;
            --text-soft: #6B7280;
            --border: #E5E7EB;
            --success-bg: #d1fae5;
            --success-text: #065f46;
            --success-border: #6ee7b7;
            --error-bg: #fee2e2;
            --error-text: #991b1b;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            color: var(--text);
        }

        /* ── Header ── */
        .site-header {
            background: #1e293b;
            padding: 16px 20px;
            text-align: center;
        }

        .site-header a {
            font-family: 'Fredoka', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--yellow);
            text-decoration: none;
            letter-spacing: 0.5px;
        }

        /* ── Page ── */
        .page-wrapper {
            max-width: 680px;
            margin: 0 auto;
            padding: 48px 20px 64px;
        }

        .page-hero {
            text-align: center;
            margin-bottom: 36px;
        }

        .page-hero .icon {
            font-size: 48px;
            margin-bottom: 12px;
            display: block;
        }

        .page-hero h1 {
            font-family: 'Fredoka', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 10px;
        }

        .page-hero p {
            font-size: 16px;
            color: var(--text-soft);
            line-height: 1.6;
        }

        /* ── Card ── */
        .form-card {
            background: var(--card);
            border-radius: 20px;
            border: 1.5px solid var(--border);
            padding: 36px 40px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }

        @media (max-width: 560px) {
            .form-card { padding: 28px 20px; }
        }

        /* ── Form elements ── */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        @media (max-width: 480px) { .form-row { grid-template-columns: 1fr; } }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 7px;
        }

        .form-group .optional {
            font-weight: 500;
            color: var(--text-soft);
            font-size: 12px;
            margin-left: 4px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 11px 15px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Nunito', sans-serif;
            color: var(--text);
            background: #fff;
            transition: border-color 0.15s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--yellow-dark);
        }

        .form-group input.has-error,
        .form-group select.has-error,
        .form-group textarea.has-error {
            border-color: #dc2626;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 130px;
        }

        .field-error {
            color: #dc2626;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }

        /* ── Honeypot ── */
        .hp-field { display: none !important; visibility: hidden; }

        /* ── Submit button ── */
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--yellow);
            color: var(--text);
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 800;
            font-family: 'Nunito', sans-serif;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover  { background: var(--yellow-dark); }
        .btn-submit:active { transform: scale(0.98); }

        /* ── Alerts ── */
        .alert {
            border-radius: 12px;
            padding: 16px 20px;
            font-size: 15px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .alert-success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1.5px solid var(--success-border);
        }

        .alert-danger {
            background: var(--error-bg);
            color: var(--error-text);
            border: 1.5px solid #fca5a5;
        }

        .alert .alert-icon { font-size: 20px; flex-shrink: 0; }

        /* ── Footer note ── */
        .footer-note {
            text-align: center;
            margin-top: 28px;
            font-size: 13px;
            color: var(--text-soft);
        }

        .footer-note a {
            color: var(--text);
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <header class="site-header">
        <a href="/">&#9917; LEGO World Cup 2026</a>
    </header>

    <div class="page-wrapper">

        <div class="page-hero">
            <span class="icon">&#128172;</span>
            <h1>Suporte ao Cliente</h1>
            <p>Tem alguma dúvida ou problema com o seu pedido?<br>
               Preencha o formulário e a nossa equipa responderá em breve.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <span class="alert-icon">&#10003;</span>
                <div>
                    <strong>Mensagem enviada com sucesso!</strong><br>
                    A nossa equipa irá responder em breve.
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">&#9888;</span>
                <div><?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <div class="form-card">
            <form method="POST" action="/suporte.php" novalidate>

                <!-- Honeypot -->
                <div class="hp-field">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Nome completo</label>
                        <input type="text" id="name" name="name"
                               class="<?= isset($errors['name']) ? 'has-error' : '' ?>"
                               value="<?= sv('name') ?>"
                               placeholder="O seu nome"
                               autocomplete="name">
                        <?php if (isset($errors['name'])): ?>
                            <div class="field-error"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email"
                               class="<?= isset($errors['email']) ? 'has-error' : '' ?>"
                               value="<?= sv('email') ?>"
                               placeholder="email@exemplo.com"
                               autocomplete="email">
                        <?php if (isset($errors['email'])): ?>
                            <div class="field-error"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="order_id">
                        Número do Pedido
                        <span class="optional">(opcional)</span>
                    </label>
                    <input type="text" id="order_id" name="order_id"
                           value="<?= sv('order_id') ?>"
                           placeholder="Ex: s2Dx8KR4YPf6zw7HfgTZ">
                </div>

                <div class="form-group">
                    <label for="subject">Assunto</label>
                    <select id="subject" name="subject"
                            class="<?= isset($errors['subject']) ? 'has-error' : '' ?>">
                        <option value="">Selecione um assunto...</option>
                        <option value="Problema com pedido"       <?= sv('subject') === 'Problema com pedido'       ? 'selected' : '' ?>>Problema com pedido</option>
                        <option value="Produto não recebido"      <?= sv('subject') === 'Produto não recebido'      ? 'selected' : '' ?>>Produto não recebido</option>
                        <option value="Questão sobre pagamento"   <?= sv('subject') === 'Questão sobre pagamento'   ? 'selected' : '' ?>>Questão sobre pagamento</option>
                        <option value="Outro"                     <?= sv('subject') === 'Outro'                     ? 'selected' : '' ?>>Outro</option>
                    </select>
                    <?php if (isset($errors['subject'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['subject'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="message">Mensagem</label>
                    <textarea id="message" name="message"
                              class="<?= isset($errors['message']) ? 'has-error' : '' ?>"
                              placeholder="Descreva o seu problema ou questão com o máximo de detalhe possível..."><?= sv('message') ?></textarea>
                    <?php if (isset($errors['message'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['message'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i>
                    Enviar Mensagem
                </button>
            </form>
        </div>

        <div class="footer-note">
            Respondemos em até 24 horas nos dias úteis.<br>
            Em alternativa, pode contactar-nos diretamente por email:
            <a href="mailto:support@legoworld2026.com">support@legoworld2026.com</a><br><br>
            <a href="/">&#8592; Voltar à loja</a>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>
