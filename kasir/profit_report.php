<?php
require_once '../config.php';
if (!isKasir() && !isAdmin()) redirect('../index.php');

// Filter tanggal - default bulan ini
$start_date = isset($_GET['start_date']) && $_GET['start_date'] ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) && $_GET['end_date'] ? $_GET['end_date'] : date('Y-m-t');

$kasir_id = $_SESSION['user_id'];

// Gunakan LEFT JOIN agar pesanan tanpa item pun tetap muncul (walaupun tidak mungkin)
$stmt = $pdo->prepare("
    SELECT 
        o.id as order_id,
        o.order_date,
        o.total_amount as total_penjualan,
        COALESCE(SUM(oi.quantity * oi.cost_price), 0) as total_hpp,
        COALESCE(SUM(oi.quantity * (oi.price - oi.cost_price)), 0) as laba
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.kasir_id = ? 
        AND o.status = 'delivered'
        AND o.order_date BETWEEN ? AND ?
    GROUP BY o.id
    ORDER BY o.order_date DESC
");
$stmt->execute([$kasir_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$orders = $stmt->fetchAll();

$total_penjualan = array_sum(array_column($orders, 'total_penjualan'));
$total_hpp = array_sum(array_column($orders, 'total_hpp'));
$total_laba = array_sum(array_column($orders, 'laba'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Laba - Kasir</title>
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

        /* Navbar biru */
        .navbar {
            background: #1e40af;
            color: white;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
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
            max-width: 1100px;
            margin: 30px auto;
            background: white;
            border-radius: 24px;
            padding: 28px;
            border: 1px solid #e2e8f0;
        }

        h2 {
            color: #1e40af;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #475569;
            margin-bottom: 24px;
            font-size: 14px;
        }

        /* Filter dan tombol */
        .filter-form {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            font-size: 14px;
        }
        .btn-filter {
            background: #64748b;
            color: white;
            border: none;
            padding: 8px 24px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 500;
            transition: 0.2s;
        }
        .btn-filter:hover {
            background: #475569;
        }
        .btn-export {
            background: #1e40af;
            color: white;
            border: none;
            padding: 8px 24px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: 0.2s;
        }
        .btn-export:hover {
            background: #1e3a8a;
        }

        /* Ringkasan cards */
        .summary-grid {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }
        .summary-card {
            background: #ffffff;
            flex: 1;
            min-width: 160px;
            padding: 20px 12px;
            text-align: center;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }
        .summary-card h3 {
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 10px;
        }
        .summary-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e40af;
        }

        /* Tabel */
        .table-wrapper {
            overflow-x: auto;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            padding: 12px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f8fafc;
            font-weight: 600;
            color: #1e2a3a;
        }

        .btn-back {
            display: inline-block;
            background: #1e40af;
            color: white;
            padding: 8px 24px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
            margin-top: 24px;
        }
        .btn-back:hover {
            background: #1e3a8a;
            transform: translateY(-1px);
        }

        .empty-message {
            text-align: center;
            padding: 40px;
            color: #64748b;
            background: #f8fafc;
            border-radius: 20px;
            margin: 20px 0;
        }

        @media (max-width: 640px) {
            .container { padding: 20px; margin: 20px; }
            .summary-value { font-size: 22px; }
            .navbar { flex-direction: column; align-items: flex-start; }
            .navbar a { margin: 0 15px 0 0; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div>🏪Berkah Kasir</div>
    <div>
        <a href="index.php">Beranda</a>
        <a href="profit_report.php">Keuangan</a>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container">
    <h2> Laporan Keuangan</h2>
    <div class="subtitle">Transaksi offline yang Anda lakukan sebagai kasir (status Selesai)</div>

    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label>Dari Tanggal</label>
            <input type="date" name="start_date" value="<?= $start_date ?>">
        </div>
        <div class="filter-group">
            <label>Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?= $end_date ?>">
        </div>
        <button type="submit" class="btn-filter">Filter</button>
        <a href="export_profit_excel.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn-export">📎 Ekspor Excel</a>
    </form>

    <div class="summary-grid">
        <div class="summary-card"><h3>Total Penjualan</h3><div class="summary-value">Rp <?= number_format($total_penjualan,0,',','.') ?></div></div>
        <div class="summary-card"><h3>Total HPP (Modal)</h3><div class="summary-value">Rp <?= number_format($total_hpp,0,',','.') ?></div></div>
        <div class="summary-card"><h3>Total Laba</h3><div class="summary-value">Rp <?= number_format($total_laba,0,',','.') ?></div></div>
        <div class="summary-card"><h3>Margin Rata-rata</h3><div class="summary-value"><?= $total_penjualan > 0 ? round(($total_laba/$total_penjualan)*100,2) : 0 ?>%</div></div>
    </div>

    <?php if(empty($orders)): ?>
        <div class="empty-message">
            Belum ada transaksi offline yang selesai pada periode ini.
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID Pesanan</th>
                        <th>Tanggal</th>
                        <th>Total Penjualan</th>
                        <th>Total HPP</th>
                        <th>Laba</th>
                        <th>Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): 
                        $margin = ($o['total_penjualan'] > 0) ? round(($o['laba'] / $o['total_penjualan']) * 100, 2) : 0;
                    ?>
                    <tr>
                        <td>#<?= htmlspecialchars($o['order_id']) ?></td>
                        <td><?= date('d/m/Y', strtotime($o['order_date'])) ?></td>
                        <td>Rp <?= number_format($o['total_penjualan'], 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($o['total_hpp'], 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($o['laba'], 0, ',', '.') ?></td>
                        <td><?= $margin ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div style="text-align: center;">
    </div>
</div>
</body>
</html>