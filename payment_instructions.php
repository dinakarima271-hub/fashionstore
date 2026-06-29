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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f4f8; font-family: 'Segoe UI', sans-serif; color: #1e2a3a; }
        .navbar { background: #1e40af; color: white; padding: 14px 24px; display: flex; justify-content: center; font-size: 18px; font-weight: 500; }
        .payment-container { max-width: 600px; margin: 30px auto; background: white; border-radius: 28px; padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        h2 { color: #1e40af; margin-bottom: 12px; }
        .bank-info { background: #eff6ff; padding: 20px; border-radius: 20px; margin: 20px 0; border-left: 4px solid #1e40af; }
        .bank-info h3 { color: #1e40af; margin-bottom: 8px; }
        .bank-info li { margin-bottom: 6px; }
        .total { font-size: 24px; font-weight: bold; color: #1e40af; }
        .order-summary h3 { color: #1e40af; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; color: #1e40af; }
        .btn { background: #1e40af; color: white; padding: 10px 20px; border-radius: 40px; text-decoration: none; display: inline-block; font-weight: 500; transition: 0.2s; }
        .btn:hover { background: #1e3a8a; transform: translateY(-1px); }
        p { line-height: 1.6; color: #475569; }
    </style>
</head>
<body>
<nav class="navbar">Berkah Fashion - Instruksi Pembayaran</nav>
<div class="payment-container">
    <h2>Terima kasih telah berbelanja!</h2>
    <p>Pesanan Anda <strong>#<?= $order_id ?></strong> telah kami terima. Silakan lakukan pembayaran ke rekening berikut sesuai total yang harus dibayar.</p>
    
    <div class="bank-info">
        <h3>Transfer ke salah satu rekening:</h3>
        <ul style="list-style: none; margin-top: 10px;">
            <li>BCA: 1234567890 a.n. Berkah Fashion</li>
            <li>Mandiri: 9876543210 a.n. Berkah Fashion</li>
            <li>BNI: 5678901234 a.n. Berkah Fashion</li>
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
    
    <p style="margin: 20px 0;">Setelah transfer, harap upload bukti pembayaran di halaman <a href="orders.php">Pesanan Saya</a>.</p>
    
    <div style="text-align: center;">
        <a href="orders.php" class="btn">Lihat Pesanan Saya</a>
    </div>
</div>
</body>
</html>