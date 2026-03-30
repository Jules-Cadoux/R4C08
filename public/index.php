<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


session_start();

require_once '../config/database.php'; 
require_once '../src/auth.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($action === 'register') {
        if (!empty($username) && !empty($password)) {
            $result = registerUser($pdo, $username, $password);
            $message = $result['message'];
        } else {
            $message = "Tous les champs sont obligatoires.";
        }
    } elseif ($action === 'login') {
        if (!empty($username) && !empty($password)) {
            $result = loginUser($pdo, $username, $password);
            
            if ($result['success']) {
                header("Location: dashboard.php");
                exit;
            } else {
                $message = $result['message'];
            }
        } else {
            $message = "Tous les champs sont obligatoires.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - SecureCloud</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --error-bg: #fef2f2;
            --error-text: #991b1b;
            --success-bg: #f0fdf4;
            --success-text: #166534;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg); 
            color: var(--text-main); 
            margin: 0; 
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 900px;
            padding: 20px;
        }

        .brand {
            text-align: center;
            margin-bottom: 40px;
        }

        .brand h1 {
            font-size: 2rem;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .auth-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .auth-grid { grid-template-columns: 1fr; }
        }

        .auth-card {
            background: var(--surface);
            padding: 40px;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .auth-card h2 {
            margin-top: 0;
            font-size: 1.25rem;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-muted);
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        button {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: var(--primary-hover);
        }

        .register-btn {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .register-btn:hover {
            background: #eff6ff;
        }

        .alert {
            max-width: 900px;
            width: 100%;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            text-align: center;
            box-sizing: border-box;
        }

        .alert-error { background: var(--error-bg); color: var(--error-text); border: 1px solid #fee2e2; }
        .alert-success { background: var(--success-bg); color: var(--success-text); border: 1px solid #dcfce7; }
    </style>
</head>
<body>

    <div class="container">
        <div class="brand">
            <h1><span style="color: var(--primary);">🛡️</span> SecureCloud</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $isError ? 'alert-error' : 'alert-success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="auth-grid">
            <div class="auth-card">
                <h2>Connexion</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="log_username">Nom d'utilisateur</label>
                        <input type="text" id="log_username" name="username" required placeholder="test123">
                    </div>
                    <div class="form-group">
                        <label for="log_password">Mot de passe</label>
                        <input type="password" id="log_password" name="password" required placeholder="••••••••">
                    </div>
                    <button type="submit">Se connecter</button>
                </form>
            </div>

            <div class="auth-card">
                <h2>Créer un compte</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label for="reg_username">Nom d'utilisateur</label>
                        <input type="text" id="reg_username" name="username" required placeholder="test321">
                    </div>
                    <div class="form-group">
                        <label for="reg_password">Mot de passe</label>
                        <input type="password" id="reg_password" name="password" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="register-btn">S'inscrire</button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>