<?php
require_once 'config.php';

if(isLoggedIn()) redirect('index.php');

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if(empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } elseif(strlen($username) < 3) {
        $error = 'Username minimal 3 karakter!';
    } elseif(strlen($password) < 4) {
        $error = 'Password minimal 4 karakter!';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if($stmt->fetch()) {
            $error = 'Username sudah digunakan! Silakan pilih username lain.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
                $stmt->execute([$username, $hashedPassword]);
                $success = 'Registrasi berhasil! Silakan login.';
            } catch(PDOException $e) {
                $error = 'Gagal mendaftar: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Berkah Fashion</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, 'Inter', -apple-system, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .register-card {
            max-width: 400px;
            width: 100%;
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            padding: 40px 32px;
        }

        .brand {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
            border-radius: 35px;
            margin-bottom: 18px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.05);
        }

        .brand-icon svg {
            width: 36px;
            height: 36px;
            stroke: #ffffff;
            stroke-width: 1.6;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .brand-name {
            font-size: 26px;
            font-weight: 600;
            color: #1e40af;
            letter-spacing: -0.3px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .brand-tagline {
            font-size: 13px;
            color: #475569;
            margin-top: 6px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #1e2a3a;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            font-size: 15px;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            background: #ffffff;
            transition: 0.2s;
            font-family: inherit;
        }

        input:focus {
            outline: none;
            border-color: #1e40af;
            box-shadow: 0 0 0 2px rgba(30,64,175,0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .checkbox-group input {
            width: auto;
            margin: 0;
        }

        .checkbox-group label {
            margin: 0;
            font-size: 13px;
            font-weight: normal;
            color: #475569;
            cursor: pointer;
        }

        button[type="submit"] {
            width: 100%;
            background: #1e40af;
            color: white;
            border: none;
            padding: 12px;
            font-size: 15px;
            font-weight: 500;
            border-radius: 40px;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 8px;
        }

        button[type="submit"]:hover {
            background: #1e3a8a;
            transform: translateY(-1px);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 20px;
            font-size: 13px;
            margin-bottom: 24px;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 3px solid #ef4444;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 3px solid #22c55e;
        }

        .login-link {
            text-align: center;
            margin-top: 28px;
            font-size: 13px;
            color: #64748b;
        }

        .login-link a {
            color: #1e40af;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .register-card {
                padding: 32px 24px;
                border-radius: 24px;
            }
            .brand-name {
                font-size: 22px;
            }
            .brand-icon {
                width: 60px;
                height: 60px;
            }
            .brand-icon svg {
                width: 30px;
                height: 30px;
            }
        }
    </style>
</head>
<body>
<div class="register-card">
    <div class="brand">
        <div class="brand-icon">
            <!-- Logo SVG - Desain baju modern dengan sentuhan fashion (SAMA PERSIS DENGAN LOGIN) -->
            <svg viewBox="0 0 24 24" stroke="currentColor">
                <!-- Kerah bundar -->
                <path d="M9 6L12 3L15 6" />
                <!-- Badan baju -->
                <path d="M5 7L7 21H17L19 7" />
                <!-- Lengan kiri -->
                <path d="M5 7L2 12L5 14" />
                <!-- Lengan kanan -->
                <path d="M19 7L22 12L19 14" />
                <!-- Detail kancing -->
                <circle cx="12" cy="12" r="0.8" fill="white" stroke="none" />
                <circle cx="12" cy="16" r="0.8" fill="white" stroke="none" />
                <!-- Detail lipatan -->
                <path d="M12 7L12 21" stroke-width="1" stroke-dasharray="1.5 1.5" opacity="0.5" />
            </svg>
        </div>
        <div class="brand-name">Berkah Fashion</div>
        <div class="brand-tagline">Daftar member baru</div>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <script>
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 2000);
        </script>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="Pilih username" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" id="password" placeholder="Minimal 4 karakter" required>
            <div class="checkbox-group">
                <input type="checkbox" id="showPassword">
                <label for="showPassword">Tampilkan password</label>
            </div>
        </div>
        <button type="submit">Daftar Sekarang</button>
    </form>

    <div class="login-link">
        Sudah punya akun? <a href="login.php">Login di sini</a>
    </div>
</div>

<script>
    const toggle = document.getElementById('showPassword');
    const password = document.getElementById('password');
    toggle.addEventListener('change', function() {
        password.type = this.checked ? 'text' : 'password';
    });
</script>
</body>
</html>