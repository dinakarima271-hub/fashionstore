<?php
require_once '../config.php';
if (!isKasir() && !isAdmin()) redirect('../index.php');

$kasir_id = $_SESSION['user_id'];

// Filter tanggal
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Ambil ringkasan transaksi offline kasir ini
$stmt = $pdo->prepare("
    SELECT 
        COUNT(o.id) as total_transaksi,
        SUM(o.total_amount) as total_penjualan,
        SUM(oi.quantity * oi.cost_price) as total_hpp,
        SUM(oi.quantity * (oi.price - oi.cost_price)) as total_laba
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE o.kasir_id = ? 
        AND o.status = 'delivered'
        AND o.order_date BETWEEN ? AND ?
");
$stmt->execute([$kasir_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil detail transaksi
$stmt = $pdo->prepare("
    SELECT o.id, o.order_date, o.total_amount
    FROM orders o
    WHERE o.kasir_id = ? AND o.status = 'delivered' AND o.order_date BETWEEN ? AND ?
    ORDER BY o.order_date DESC
");
$stmt->execute([$kasir_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$transactions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - Berkah Kasir</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background: #f0f4f8; color: #1e2a3a; }
        .navbar { background: #1e40af; color: white; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .navbar a { color: white; text-decoration: none; margin-left: 16px; transition: opacity 0.2s; }
        .navbar a:hover { opacity: 0.8; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        h2 { font-size: 24px; font-weight: 600; color: #1e40af; margin-bottom: 20px; }
        .filter-form { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; background: white; padding: 20px; border-radius: 20px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .filter-group label { font-size: 12px; font-weight: 500; color: #475569; }
        .filter-group input { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 20px; }
        .btn { background: #1e40af; color: white; border: none; padding: 8px 20px; border-radius: 30px; cursor: pointer; transition: 0.2s; }
        .btn:hover { background: #1e3a8a; }
        .summary-grid { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px; }
        .card { background: white; padding: 20px; border-radius: 20px; flex: 1; text-align: center; border: 1px solid #e2e8f0; }
        .card h3 { font-size: 13px; font-weight: 500; color: #64748b; margin-bottom: 8px; }
        .card .value { font-size: 28px; font-weight: 700; color: #1e40af; }
        .table-wrapper { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #1e40af; }
        @media (max-width: 640px) {
            .navbar { flex-direction: column; align-items: flex-start; }
            .navbar a { margin: 0 15px 0 0; }
            .card .value { font-size: 22px; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div>Berkah Kasir</div>
    <div>
        <a href="index.php">Beranda</a>
        <a href="laporan_keuangan.php">Keuangan</a>
        <a href="profit_report.php">Laba</a>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container">
    <h2>Laporan Keuangan Saya (Kasir)</h2>
    <form method="GET" class="filter-form">
        <div class="filter-group"><label>Dari Tanggal</label><input type="date" name="start_date" value="<?= $start_date ?>"></div>
        <div class="filter-group"><label>Sampai Tanggal</label><input type="date" name="end_date" value="<?= $end_date ?>"></div>
        <button type="submit" class="btn">Filter</button>
    </form>

    <div class="summary-grid">
        <div class="card"><h3>Total Transaksi</h3><div class="value"><?= $summary['total_transaksi'] ?? 0 ?></div></div>
        <div class="card"><h3>Total Penjualan</h3><div class="value">Rp <?= number_format($summary['total_penjualan'] ?? 0,0,',','.') ?></div></div>
        <div class="card"><h3>Total HPP</h3><div class="value">Rp <?= number_format($summary['total_hpp'] ?? 0,0,',','.') ?></div></div>
        <div class="card"><h3>Total Laba</h3><div class="value">Rp <?= number_format($summary['total_laba'] ?? 0,0,',','.') ?></div></div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead><tr><th>ID Pesanan</th><th>Tanggal</th><th>Total</th></tr></thead>
            <tbody>
                <?php foreach($transactions as $tr): ?>
                <tr><td>#<?= $tr['id'] ?></td><td><?= date('d/m/Y H:i', strtotime($tr['order_date'])) ?></td><td>Rp <?= number_format($tr['total_amount'],0,',','.') ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>