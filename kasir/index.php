<?php
require_once '../config.php';
if (!isKasir() && !isAdmin()) redirect('../index.php');

// Ambil statistik transaksi offline hari ini
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as total_transaksi, COALESCE(SUM(total_amount), 0) as total_omset FROM orders WHERE user_id IS NULL AND DATE(order_date) = ?");
$stmt->execute([$today]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$totalTransaksiHariIni = $stats['total_transaksi'] ?? 0;
$totalOmsetHariIni = $stats['total_omset'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kasir - Berkah Fashion</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f0f4f8;
            color: #1e2a3a;
        }

        /* Navbar biru konsisten */
        .navbar {
            background: #1e40af;
            color: white;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar .logo {
            font-size: 18px;
            font-weight: 600;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 16px;
            font-weight: 500;
            transition: opacity 0.2s;
        }

        .navbar a:hover {
            opacity: 0.8;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Welcome Card */
        .welcome-card {
            background: white;
            border-radius: 24px;
            padding: 28px 32px;
            margin-bottom: 32px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        .welcome-card h1 {
            font-size: 26px;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 8px;
        }

        .welcome-card p {
            color: #475569;
            font-size: 14px;
        }

        /* Statistik Cards */
        .stats-grid {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            flex: 1;
            min-width: 180px;
            padding: 24px 16px;
            text-align: center;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 13px;
            color: #475569;
            font-weight: 500;
        }

        /* Tombol Transaksi */
        .btn-transaksi {
            display: inline-block;
            background: #1e40af;
            color: white;
            padding: 14px 32px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(30,64,175,0.2);
        }

        .btn-transaksi:hover {
            background: #1e3a8a;
            transform: scale(1.02);
        }

        /* Info Box */
        .info-box {
            background: #f8fafc;
            border-radius: 20px;
            padding: 24px;
            text-align: center;
            margin-top: 32px;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 14px;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 24px;
        }

        @media (max-width: 640px) {
            .welcome-card h1 { font-size: 22px; }
            .stat-number { font-size: 26px; }
            .btn-transaksi { padding: 12px 24px; font-size: 14px; }
            .stats-grid { gap: 12px; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="logo">🏪 Berkah Kasir</div>
    <div>
        <a href="index.php">Beranda</a>
        <a href="profit_report.php">Keuangan</a>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container">
    <div class="welcome-card">
        <h1>Selamat datang, <?= htmlspecialchars($_SESSION['username']) ?> 👋</h1>
        <p>Anda login sebagai <strong>Kasir</strong> | 📅 <?= date('d F Y') ?></p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $totalTransaksiHariIni ?></div>
            <div class="stat-label">Transaksi Hari Ini</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">Rp <?= number_format($totalOmsetHariIni, 0, ',', '.') ?></div>
            <div class="stat-label">Omset Hari Ini</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">⭐</div>
            <div class="stat-label">Mode Offline</div>
        </div>
    </div>

    <div style="text-align: center;">
        <a href="../kasir.php" class="btn-transaksi">🛒 Mulai Transaksi Penjualan</a>
    </div>

    <div class="info-box">
        💡 <strong>Tips:</strong> Gunakan halaman ini untuk transaksi penjualan di toko fisik.<br>
        Setiap transaksi akan langsung mengurangi stok produk dan tercatat sebagai penjualan offline.
    </div>

    <div class="footer">
        &copy; <?= date('Y') ?> Berkah Fashion Store - Sistem Kasir Offline
    </div>
</div>
</body>
</html>