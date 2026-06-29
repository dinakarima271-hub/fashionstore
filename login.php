<?php
require_once 'config.php';

if (!function_exists('isKasir')) {
    function isKasir() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'kasir';
    }
}

if(isLoggedIn()) {
    if(isAdmin()) {
        redirect('admin/');
    } elseif(isKasir()) {
        redirect('kasir/');
    } else {
        redirect('index.php');
    }
}

$error = '';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        if($user['role'] === 'admin') {
            redirect('admin/');
        } elseif($user['role'] === 'kasir') {
            redirect('kasir/');
        } else {
            redirect('index.php');
        }
    } else {
        $error = 'Username atau password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Berkah Fashion</title>
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

        .login-card {
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
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 20px;
            font-size: 13px;
            margin-bottom: 24px;
            border-left: 3px solid #ef4444;
        }

        .footer-links {
            text-align: center;
            margin-top: 28px;
            font-size: 13px;
            color: #64748b;
        }

        .footer-links a {
            color: #1e40af;
            text-decoration: none;
            font-weight: 500;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-card {
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
    <div class="login-card">
        <div class="brand">
            <div class="brand-icon">
                <!-- Logo SVG - Desain baju modern dengan sentuhan fashion -->
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
            <div class="brand-tagline">Masuk ke akun Anda</div>
        </div>

        <?php if($error): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Masukkan username" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" id="password" placeholder="Masukkan password" required>
                <div class="checkbox-group">
                    <input type="checkbox" id="showPassword">
                    <label for="showPassword">Tampilkan password</label>
                </div>
            </div>
            <button type="submit">Masuk</button>
        </form>

        <div class="footer-links">
            Belum punya akun? <a href="register.php">Daftar</a>
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