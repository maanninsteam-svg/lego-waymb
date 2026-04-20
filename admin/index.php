<?php
ini_set('session.gc_maxlifetime', 3600);
session_start();

// Already logged in → go to dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

// CSRF token for login form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $postToken)) {
        $error = 'Token de segurança inválido. Recarregue a página.';
    } else {
        $configPath = __DIR__ . '/../admin-config.json';
        $config = json_decode(file_get_contents($configPath), true);

        $inputUser = trim($_POST['username'] ?? '');
        $inputPass = $_POST['password'] ?? '';

        $storedUser = $config['admin']['username'] ?? '';
        $storedHash = $config['admin']['password_hash'] ?? '';
        $storedPlain = $config['admin']['password'] ?? '';

        $userOk = ($inputUser === $storedUser);
        $passOk = false;

        if ($userOk) {
            if ($storedHash !== '') {
                // Hash already set — use secure verify
                $passOk = password_verify($inputPass, $storedHash);
            } else {
                // First login: compare plaintext, then hash and save
                if ($inputPass === $storedPlain) {
                    $passOk = true;
                    $newHash = password_hash($inputPass, PASSWORD_BCRYPT);
                    $config['admin']['password_hash'] = $newHash;
                    file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        }

        if ($userOk && $passOk) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $storedUser;
            header('Location: /admin/dashboard.php');
            exit;
        } else {
            $error = 'Utilizador ou palavra-passe incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — LEGO World Cup 2026</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-wrapper {
            width: 100%;
            max-width: 400px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-logo h1 {
            font-size: 26px;
            font-weight: 700;
            color: #f59e0b;
        }
        .login-logo p {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 6px;
        }
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-card h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            transition: border-color 0.15s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #f59e0b;
        }
        .btn-login {
            width: 100%;
            padding: 11px;
            background: #f59e0b;
            color: #1e293b;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: background 0.15s;
            margin-top: 4px;
        }
        .btn-login:hover { background: #d97706; }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 18px;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-logo">
            <h1>&#9917; Admin Panel</h1>
            <p>LEGO World Cup 2026</p>
        </div>
        <div class="login-card">
            <h2>Iniciar Sessão</h2>
            <?php if ($error !== ''): ?>
                <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="POST" action="/admin/index.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="username">Utilizador</label>
                    <input type="text" id="username" name="username" autocomplete="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Palavra-passe</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-login">Entrar</button>
            </form>
        </div>
    </div>
</body>
</html>
