<?php
require_once '../config.php';
if(!isAdmin()) redirect('../index.php');

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$order) redirect('index.php');

$items = $pdo->prepare("SELECT oi.*, p.name, p.image_url FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$items->execute([$id]);
$orderItems = $items->fetchAll(PDO::FETCH_ASSOC);

// Fungsi badge status warna
function statusBadge($status) {
    $classes = [
        'pending' => 'badge-warning',
        'processed' => 'badge-info',
        'shipped' => 'badge-primary',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    $class = $classes[$status] ?? 'badge-secondary';
    $labels = [
        'pending' => 'Menunggu',
        'processed' => 'Diproses',
        'shipped' => 'Dikirim',
        'delivered' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];
    $label = $labels[$status] ?? ucfirst($status);
    return "<span class='badge $class'>$label</span>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?= $id ?> - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .detail-container {
            max-width: 1100px;
            margin: 30px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .detail-header {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            padding: 25px 30px;
        }
        .detail-header h2 {
            margin: 0 0 5px;
            font-size: 26px;
        }
        .detail-header p {
            margin: 0;
            opacity: 0.8;
        }
        .order-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            background: #f8f9fa;
            padding: 20px 30px;
            border-bottom: 1px solid #e9ecef;
        }
        .info-card {
            background: white;
            padding: 12px 20px;
            border-radius: 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            min-width: 160px;
        }
        .info-card .label {
            font-size: 12px;
            text-transform: uppercase;
            color: #6c757d;
        }
        .info-card .value {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-top: 5px;
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-primary { background: #cce5ff; color: #004085; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }

        .shipping-section {
            background: #f1f5f9;
            margin: 0;
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
        }
        .shipping-section h3 {
            margin: 0 0 15px;
            color: #1e293b;
        }
        .shipping-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
        }
        .shipping-col {
            flex: 1;
            min-width: 200px;
            background: white;
            padding: 12px 18px;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .shipping-col p {
            margin: 8px 0;
        }
        .items-section {
            padding: 20px 30px;
        }
        .items-section h3 {
            margin: 0 0 20px;
            color: #1e293b;
            border-left: 4px solid #2c3e50;
            padding-left: 15px;
        }
        .product-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .product-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 2;
        }
        .product-img {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 12px;
            background: #f1f3f5;
        }
        .product-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .product-price, .product-qty, .product-subtotal {
            min-width: 100px;
            text-align: right;
        }
        .product-subtotal {
            font-weight: 700;
            color: #2c3e50;
        }
        .total-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed #dee2e6;
        }
        .total-box {
            background: #f1f5f9;
            padding: 15px 25px;
            border-radius: 16px;
            text-align: right;
        }
        .total-label {
            font-size: 14px;
            color: #475569;
        }
        .total-amount {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #64748b;
            color: white;
            padding: 10px 22px;
            border-radius: 40px;
            text-decoration: none;
            margin: 0 30px 30px;
            transition: background 0.2s;
        }
        .back-button:hover {
            background: #475569;
            text-decoration: none;
        }
        @media (max-width: 700px) {
            .order-info { flex-direction: column; }
            .product-info { flex-wrap: wrap; }
            .product-price, .product-qty, .product-subtotal { text-align: left; width: auto; }
            .product-item { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div>Admin Panel - Detail Pesanan</div>
    <div><a href="index.php">← Kembali ke Daftar</a></div>
</nav>

<div class="detail-container">
    <div class="detail-header">
        <h2>Pesanan #<?= $order['id'] ?></h2>
        <p>Dipesan oleh: <?= htmlspecialchars($order['username']) ?></p>
    </div>

    <div class="order-info">
        <div class="info-card">
            <div class="label">Tanggal Pemesanan</div>
            <div class="value"><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></div>
        </div>
        <div class="info-card">
            <div class="label">Status</div>
            <div class="value"><?= statusBadge($order['status']) ?></div>
        </div>
        <div class="info-card">
            <div class="label">Total</div>
            <div class="value">Rp <?= number_format($order['total_amount'],0,',','.') ?></div>
        </div>
    </div>

    <!-- Informasi Pengiriman & Pembayaran -->
    <div class="shipping-section">
        <h3>📦 Informasi Pengiriman & Pembayaran</h3>
        <div class="shipping-grid">
            <div class="shipping-col">
                <p><strong>📍 Alamat Lengkap</strong><br><?= nl2br(htmlspecialchars($order['address'] ?? '-')) ?></p>
                <p><strong>📞 Nomor HP</strong><br><?= htmlspecialchars($order['phone'] ?? '-') ?></p>
            </div>
            <div class="shipping-col">
                <p><strong>🚚 Metode Pengiriman</strong><br><?= htmlspecialchars($order['shipping_method'] ?? '-') ?></p>
                <p><strong>💳 Metode Pembayaran</strong><br><?= htmlspecialchars($order['payment_method'] ?? '-') ?></p>
            </div>
            <?php if(!empty($order['notes'])): ?>
            <div class="shipping-col">
                <p><strong>📝 Catatan Pelanggan</strong><br><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Daftar Item -->
    <div class="items-section">
        <h3>🛍️ Item yang Dipesan</h3>
        <?php foreach($orderItems as $item): ?>
        <div class="product-item">
            <div class="product-info">
                <img src="<?= htmlspecialchars($item['image_url'] ?? 'https://picsum.photos/55/55') ?>" class="product-img">
                <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
            </div>
            <div class="product-price">Rp <?= number_format($item['price'],0,',','.') ?></div>
            <div class="product-qty">x <?= $item['quantity'] ?></div>
            <div class="product-subtotal">Rp <?= number_format($item['price'] * $item['quantity'],0,',','.') ?></div>
        </div>
        <?php endforeach; ?>
        <div class="total-row">
            <div class="total-box">
                <div class="total-label">Total Seluruhnya</div>
                <div class="total-amount">Rp <?= number_format($order['total_amount'],0,',','.') ?></div>
            </div>
        </div>
    </div>

    <p style="margin-top: 20px;">
    <a href="../invoice.php?id=<?= $order['id'] ?>" target="_blank" class="btn" style="background:#2c3e50;">🧾 Cetak Struk (Invoice)</a>
    <a href="index.php" class="btn">← Kembali</a>
</p>

    <a href="index.php" class="back-button">← Kembali ke Daftar Pesanan</a>
</div>
</body>
</html>