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

// Fungsi untuk mengecek apakah user adalah admin ATAU kasir (bisa akses halaman tertentu)
function isStaff() {
    return isAdmin() || isKasir();
}

function redirect($url) {
    header("Location: $url");
    exit();
}
?>