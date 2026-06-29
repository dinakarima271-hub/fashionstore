<?php
require_once 'config.php';

// Ganti dengan username & password yang diinginkan (pastikan username belum ada)
$username = 'kasir_toko';   // username baru, ganti jika masih terdaftar
$password = 'kasir123';     // password bebas
$role = 'kasir';

// Hash password
$hash = password_hash($password, PASSWORD_DEFAULT);

// Cek apakah username sudah ada (perbaiki query)
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);

if ($stmt->fetch()) {
    die("❌ Username <strong>$username</strong> sudah terdaftar. Silakan ganti username lain.");
}

// Insert user baru
$stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
if ($stmt->execute([$username, $hash, $role])) {
    echo "✅ Akun kasir berhasil dibuat!<br>";
    echo "Username: <strong>$username</strong><br>";
    echo "Password: <strong>$password</strong><br>";
    echo "Role: $role<br>";
    echo "Silakan <a href='login.php'>login di sini</a>.";
} else {
    echo "❌ Gagal membuat akun. Detail error:<br>";
    print_r($stmt->errorInfo());
}
?>