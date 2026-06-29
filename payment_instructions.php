<?php
require_once 'config.php';
if(!isLoggedIn() || isAdmin()) redirect('index.php');

if(!isset($_SESSION['pending_order_id'])) {
    redirect('index.php');
}

$order_id = $_SESSION['pending_order_id'];
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();
if(!$order) {
    unset($_SESSION['pending_order_id']);
    redirect('index.php');
}

// Ambil item pesanan
$stmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// Hapus session pending setelah ditampilkan (opsional, biar tidak bisa diakses ulang)
// unset($_SESSION['pending_order_id']); // biarkan agar bisa refresh halaman
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Instruksi Pembayaran - Berkah Fashion</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #f5f5f7; font-family: 'Segoe UI', sans-serif; }
        .payment-container { max-width: 600px; margin: 30px auto; background: white; border-radius: 28px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .bank-info { background: #f0f9f4; padding: 20px; border-radius: 20px; margin: 20px 0; border-left: 4px solid #2c6e4f; }
        .total { font-size: 24px; font-weight: bold; color: #2c6e4f; }
        .btn { background: #2c6e4f; color: white; padding: 10px 20px; border-radius: 40px; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
<nav class="navbar">Berkah Fashion - Instruksi Pembayaran</nav>
<div class="payment-container">
    <h2>Terima kasih telah berbelanja! 🛍️</h2>
    <p>Pesanan Anda <strong>#<?= $order_id ?></strong> telah kami terima. Silakan lakukan pembayaran ke rekening berikut sesuai total yang harus dibayar.</p>
    
    <div class="bank-info">
        <h3>Transfer ke salah satu rekening:</h3>
        <ul style="list-style: none; margin-top: 10px;">
            <li>🏦 BCA: 1234567890 a.n. Berkah Fashion</li>
            <li>🏦 Mandiri: 9876543210 a.n. Berkah Fashion</li>
            <li>🏦 BNI: 5678901234 a.n. Berkah Fashion</li>
        </ul>
        <p style="margin-top: 15px;"><strong>QRIS / E-Wallet:</strong> +62 812-3456-7890 (Scan QR di toko)</p>
    </div>
    
    <div class="order-summary">
        <h3>Detail Pesanan</h3>
        <table width="100%">
            <tr><th>Produk</th><th>Jumlah</th><th>Subtotal</th></tr>
            <?php foreach($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td>Rp <?= number_format($item['price']*$item['quantity'],0,',','.') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr><td colspan="2"><strong>Total + Ongkir</strong></td><td><strong class="total">Rp <?= number_format($order['total_amount'],0,',','.') ?></strong></td></tr>
        </table>
    </div>
    
    <p style="margin: 20px 0;">Setelah transfer, harap konfirmasi ke WhatsApp <strong>0812-3456-7890</strong> atau upload bukti di halaman <a href="orders.php">Pesanan Saya</a> (Jika fitur upload tersedia).</p>
    
    <div style="text-align: center;">
        <a href="orders.php" class="btn">Lihat Pesanan Saya</a>
    </div>
</div>
</body>
</html>