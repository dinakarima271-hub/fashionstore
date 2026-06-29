<?php
require_once __DIR__ . '/../config.php';
if (!isAdmin()) redirect('../index.php');

// Filter periode
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Query laba
$stmt = $pdo->prepare("
    SELECT 
        o.id as order_id,
        o.order_date,
        o.total_amount as total_penjualan,
        SUM(oi.quantity * oi.cost_price) as total_hpp,
        SUM(oi.quantity * (oi.price - oi.cost_price)) as laba
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE o.status IN ('delivered', 'processed')
    AND o.order_date BETWEEN ? AND ?
    GROUP BY o.id
    ORDER BY o.order_date DESC
");
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
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
    <title>Laporan Laba - Berkah Fashion Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f0f4f8; color: #1e2a3a; }
        
        /* Navbar biru konsisten dengan logo */
        .navbar { background: #1e40af; color: white; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .navbar .logo { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 500; }
        .navbar .logo svg { width: 28px; height: 28px; stroke: white; stroke-width: 1.6; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .navbar .nav-links a { margin-left: 20px; text-decoration: none; color: white; font-size: 14px; transition: opacity 0.2s; }
        .navbar .nav-links a:hover { opacity: 0.8; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        h2 { font-size: 24px; font-weight: 600; color: #1e40af; margin-bottom: 20px; }
        
        /* Filter form */
        .filter-form { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 24px; background: white; padding: 20px; border-radius: 20px; border: 1px solid #e2e8f0; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 12px; font-weight: 500; color: #475569; }
        .filter-group input { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 20px; font-size: 14px; }
        .btn-filter { background: #64748b; color: white; border: none; padding: 8px 20px; border-radius: 30px; cursor: pointer; transition: 0.2s; }
        .btn-filter:hover { background: #475569; }
        .btn-export { background: #1e40af; color: white; text-decoration: none; padding: 8px 20px; border-radius: 30px; display: inline-block; transition: 0.2s; }
        .btn-export:hover { background: #1e3a8a; }
        
        /* Ringkasan cards */
        .summary-grid { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px; }
        .card { background: white; padding: 20px; border-radius: 20px; flex: 1; min-width: 180px; text-align: center; border: 1px solid #e2e8f0; }
        .card h3 { font-size: 13px; font-weight: 500; color: #64748b; margin-bottom: 10px; }
        .card .value { font-size: 28px; font-weight: 700; color: #1e40af; }
        
        /* Tabel */
        .table-wrapper { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #1e40af; }
        tr:hover td { background: #f8fafc; }
        .empty-message { text-align: center; padding: 40px; background: white; border-radius: 20px; border: 1px solid #e2e8f0; color: #64748b; }
        
        @media (max-width: 640px) {
            .navbar { flex-direction: column; align-items: flex-start; }
            .navbar .nav-links a { margin: 0 15px 0 0; }
            .card .value { font-size: 22px; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="logo">
        <!-- Logo SVG - Desain baju modern (sama dengan login/register) -->
        <svg viewBox="0 0 24 24" stroke="currentColor">
            <path d="M9 6L12 3L15 6" />
            <path d="M5 7L7 21H17L19 7" />
            <path d="M5 7L2 12L5 14" />
            <path d="M19 7L22 12L19 14" />
            <circle cx="12" cy="12" r="0.8" fill="white" stroke="none" />
            <circle cx="12" cy="16" r="0.8" fill="white" stroke="none" />
            <path d="M12 7L12 21" stroke-width="1" stroke-dasharray="1.5 1.5" opacity="0.5" />
        </svg>
        Berkah Fashion Admin
    </div>
    <div class="nav-links">
        <a href="index.php">Beranda</a>
        <a href="products.php">Kelola Produk</a>
        <a href="finance.php">Keuangan</a>
        <a href="profit_report.php">Laporan Laba</a>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container">
    <h2>Laporan Laba / Rugi</h2>

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
        <a href="export_profit.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn-export">📎 Ekspor Excel</a>
    </form>

    <div class="summary-grid">
        <div class="card"><h3>Total Penjualan</h3><div class="value">Rp <?= number_format($total_penjualan, 0, ',', '.') ?></div></div>
        <div class="card"><h3>Total HPP (Modal)</h3><div class="value">Rp <?= number_format($total_hpp, 0, ',', '.') ?></div></div>
        <div class="card"><h3>Total Laba </h3><div class="value">Rp <?= number_format($total_laba, 0, ',', '.') ?></div></div>
        <div class="card"><h3>Margin (%)</h3><div class="value"><?= $total_penjualan > 0 ? round(($total_laba/$total_penjualan)*100, 2) : 0 ?>%</div></div>
    </div>

    <?php if(empty($orders)): ?>
        <div class="empty-message">Tidak ada data pesanan selesai pada periode ini.</div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>ID Pesanan</th><th>Tanggal</th><th>Total Penjualan</th><th>Total HPP</th><th>Laba</th><th>Margin</th></tr>
                </thead>
                <tbody>
                    <?php foreach($orders as $o): 
                        $margin = $o['total_penjualan'] > 0 ? round(($o['laba']/$o['total_penjualan'])*100, 2) : 0;
                    ?>
                    <tr>
                        <td>#<?= $o['order_id'] ?></td>
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
</div>
</body>
</html>