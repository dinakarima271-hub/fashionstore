<?php
require_once 'config.php';
if(!isLoggedIn() || isAdmin()) redirect('index.php');

$order_id = (int)($_GET['id'] ?? 0);
if(!$order_id) {
    $_SESSION['error'] = "ID pesanan tidak valid.";
    redirect('orders.php');
}

// Ambil detail pesanan
$stmt = $pdo->prepare("
    SELECT o.*, u.username 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if(!$order) {
    $_SESSION['error'] = "Pesanan tidak ditemukan.";
    redirect('orders.php');
}

// Ambil item pesanan
$stmt = $pdo->prepare("
    SELECT oi.*, p.name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

function statusBadge($status) {
    $map = [
        'pending'           => ['text' => 'Menunggu Pembayaran', 'class' => 'badge-warning'],
        'request_cancel'    => ['text' => 'Menunggu Konfirmasi Batal', 'class' => 'badge-warning'],
        'processed'         => ['text' => 'Diproses', 'class' => 'badge-info'],
        'shipped'           => ['text' => 'Dikirim', 'class' => 'badge-primary'],
        'delivered'         => ['text' => 'Selesai', 'class' => 'badge-success'],
        'cancelled'         => ['text' => 'Dibatalkan', 'class' => 'badge-danger']
    ];
    $data = $map[$status] ?? ['text' => ucfirst($status), 'class' => 'badge-secondary'];
    return "<span class='badge {$data['class']}'>{$data['text']}</span>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?= $order['id'] ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background: #f0f4f8; color: #1e2a3a; }
        
        .navbar { background: #1e40af; color: white; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .navbar a { color: white; text-decoration: none; margin-left: 16px; font-weight: 500; }
        .navbar a:hover { opacity: 0.8; }
        
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .page-title { font-size: 28px; font-weight: 600; color: #1e40af; margin-bottom: 24px; }
        
        .card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px; }
        .card-title { font-size: 18px; font-weight: 600; color: #1e40af; margin-bottom: 16px; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .info-item { display: flex; gap: 8px; }
        .info-label { font-weight: 600; color: #475569; min-width: 120px; }
        .info-value { color: #1e2a3a; }
        
        .badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #e0e7ff; color: #1e3a8a; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #e2e8f0; color: #475569; }
        
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #1e40af; }
        .total-row td { font-weight: 600; background: #f8fafc; }
        
        .btn { background: #1e40af; color: white; padding: 8px 20px; border-radius: 30px; text-decoration: none; font-size: 14px; font-weight: 500; display: inline-block; transition: 0.2s; border: none; cursor: pointer; }
        .btn-back { background: #64748b; }
        .btn-back:hover { background: #475569; }
        .btn:hover { transform: translateY(-1px); }
        
        .alert { padding: 12px 18px; border-radius: 20px; font-size: 13px; margin-bottom: 24px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        
        @media (max-width: 640px) {
            .info-grid { grid-template-columns: 1fr; }
            .page-title { font-size: 24px; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div>Berkah Fashion</div>
    <div>
        <a href="index.php">Beranda</a>
        <a href="cart.php">Keranjang</a>
        <a href="orders.php">Pesanan Saya</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">
    <div class="page-title">Detail Pesanan #<?= $order['id'] ?></div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <a href="orders.php" class="btn btn-back" style="margin-bottom: 16px;">← Kembali ke Pesanan</a>

    <!-- Informasi Pesanan -->
    <div class="card">
        <div class="card-title">Informasi Pesanan</div>
        <div class="info-grid">
            <div class="info-item"><span class="info-label">ID Pesanan:</span><span class="info-value">#<?= $order['id'] ?></span></div>
            <div class="info-item"><span class="info-label">Tanggal:</span><span class="info-value"><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></span></div>
            <div class="info-item"><span class="info-label">Pelanggan:</span><span class="info-value"><?= htmlspecialchars($order['username']) ?></span></div>
            <div class="info-item"><span class="info-label">Status:</span><span class="info-value"><?= statusBadge($order['status']) ?></span></div>
            <div class="info-item"><span class="info-label">Metode Pembayaran:</span><span class="info-value"><?= $order['payment_method'] == 'COD' ? 'COD' : 'Transfer Bank' ?></span></div>
            <div class="info-item"><span class="info-label">Total:</span><span class="info-value" style="font-weight: 600; color: #1e40af;">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span></div>
        </div>
    </div>

    <!-- Daftar Produk -->
    <div class="card">
        <div class="card-title">Daftar Produk</div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Harga</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td>Rp <?= number_format($item['price'], 0, ',', '.') ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td>Rp <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">Total</td>
                        <td>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>