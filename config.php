<?php
session_start();
$host = 'localhost';
$dbname = 'fashion_store';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;port=3307;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isKasir() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'kasir';
}

function isStaff() {
    return isAdmin() || isKasir();
}

function redirect($url) {
    header("Location: $url");
    exit();
}

// ─── CSRF ───────────────────────────────────────────────────
function generateCsrfToken() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function validateCsrfToken($token) {
    return hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}

function csrfField() {
    return '<input type="hidden" name="_csrf_token" value="' . generateCsrfToken() . '">';
}

// ─── Status Badge ────────────────────────────────────────────
function statusBadge($status) {
    $map = [
        'pending'        => ['text' => 'Menunggu Pembayaran', 'class' => 'badge-warning'],
        'request_cancel' => ['text' => 'Menunggu Konfirmasi Batal', 'class' => 'badge-warning'],
        'processed'      => ['text' => 'Diproses', 'class' => 'badge-info'],
        'shipped'        => ['text' => 'Dikirim', 'class' => 'badge-primary'],
        'delivered'      => ['text' => 'Selesai', 'class' => 'badge-success'],
        'cancelled'      => ['text' => 'Dibatalkan', 'class' => 'badge-danger']
    ];
    $data = $map[$status] ?? ['text' => ucfirst($status), 'class' => 'badge-secondary'];
    return "<span class='badge {$data['class']}'>{$data['text']}</span>";
}

function statusIndonesia($status) {
    $map = [
        'pending'        => 'Menunggu',
        'request_cancel' => 'Menunggu Batal',
        'processed'      => 'Diproses',
        'shipped'        => 'Dikirim',
        'delivered'      => 'Selesai',
        'cancelled'      => 'Dibatalkan'
    ];
    return $map[$status] ?? ucfirst($status);
}

// ─── Shipping ────────────────────────────────────────────────
function getShippingCosts() {
    return [
        'JNE Reguler'    => 10000,
        'JNE YES'        => 20000,
        'SiCepat Reguler'=> 12000,
        'Grab Instant'   => 25000
    ];
}

function getShippingCost($method) {
    $costs = getShippingCosts();
    return $costs[$method] ?? 0;
}

// ─── Regenerate session ID after login ───────────────────────
function loginSession($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
}

// ─── Flash messages ──────────────────────────────────────────
function setFlash($key, $msg) {
    $_SESSION['_flash'][$key] = $msg;
}

function getFlash($key) {
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function flashAlert() {
    $msg = getFlash('message');
    $err = getFlash('error');
    if ($msg) echo "<div class='alert alert-success'>" . htmlspecialchars($msg) . "</div>";
    if ($err) echo "<div class='alert alert-error'>" . htmlspecialchars($err) . "</div>";
}
?>
