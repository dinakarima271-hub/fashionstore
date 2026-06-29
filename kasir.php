<?php
require_once 'config.php';

// Hanya admin atau kasir yang boleh mengakses halaman ini
if (!isStaff()) redirect('index.php');

// Ambil produk dengan cost_price juga
$products = $pdo->query("SELECT id, name, price, cost_price, stock FROM products ORDER BY name")->fetchAll();
$message = '';
$error = '';
$lastOrder = null;
$lastOrderItems = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $items = $_POST['items'] ?? [];
    $validItems = [];

    foreach ($items as $product_id => $qty) {
        $qty = (int)$qty;
        if ($qty > 0) $validItems[$product_id] = $qty;
    }

    if (empty($validItems)) {
        $error = "Pilih minimal satu produk.";
    } else {
        $total = 0;
        $cart = [];
        $stockError = false;

        foreach ($validItems as $product_id => $qty) {
            $stmt = $pdo->prepare("SELECT name, price, cost_price, stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            if (!$product) continue;
            if ($product['stock'] < $qty) {
                $error = "Stok '{$product['name']}' tidak cukup (sisa {$product['stock']}).";
                $stockError = true;
                break;
            }
            $subtotal = $product['price'] * $qty;
            $total += $subtotal;
            $cart[] = [
                'id' => $product_id,
                'name' => $product['name'],
                'price' => $product['price'],
                'cost_price' => $product['cost_price'],
                'quantity' => $qty
            ];
        }

        if (!$stockError && $total > 0) {
            try {
                $pdo->beginTransaction();
                // Insert order dengan kasir_id (offline)
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, kasir_id, total_amount, shipping_cost, address, phone, shipping_method, payment_method, notes, status) VALUES (0, ?, ?, 0, 'Toko Fisik', '-', 'Ambil di Toko', 'Tunai', 'Transaksi kasir', 'delivered')");
                $stmt->execute([$_SESSION['user_id'], $total]);
                $order_id = $pdo->lastInsertId();

                foreach ($cart as $item) {
                    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, cost_price) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$order_id, $item['id'], $item['quantity'], $item['price'], $item['cost_price']]);
                    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                    $stmt->execute([$item['quantity'], $item['id']]);
                }

                // Catat transaksi offline ke tabel transactions
                $stmtInc = $pdo->prepare("INSERT INTO transactions (type, amount, description, reference_id, created_by) VALUES ('income', ?, CONCAT('Transaksi kasir #', ?), ?, ?)");
                $stmtInc->execute([$total, $order_id, $order_id, $_SESSION['user_id']]);

                $pdo->commit();
                $message = "Transaksi berhasil! Pesanan #$order_id";
                // Ambil data order untuk struk
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);
                $lastOrder = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmtItems = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
                $stmtItems->execute([$order_id]);
                $lastOrderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Gagal simpan: " . $e->getMessage();
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Offline - Berkah Fashion</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f4f8; font-family: 'Segoe UI', Roboto, sans-serif; color: #1e2a3a; }
        
        /* Navbar biru konsisten */
        .navbar { background: #1e40af; color: white; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; font-weight: 500; transition: opacity 0.2s; }
        .navbar a:hover { opacity: 0.8; }
        
        .kasir-wrapper { max-width: 1400px; margin: 30px auto; padding: 0 20px; display: flex; gap: 30px; flex-wrap: wrap; }
        .form-panel { flex: 2; min-width: 300px; background: white; border-radius: 24px; border: 1px solid #e2e8f0; overflow: hidden; }
        .struk-panel { flex: 1.5; min-width: 350px; background: white; border-radius: 24px; border: 1px solid #e2e8f0; overflow: hidden; position: sticky; top: 20px; align-self: flex-start; }
        .panel-header { background: #1e40af; color: white; padding: 16px 20px; }
        .panel-header h3 { margin: 0; font-weight: 500; }
        .product-list { padding: 20px; }
        .product-table { width: 100%; border-collapse: collapse; }
        .product-table th, .product-table td { padding: 12px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        .product-table th { background: #f8fafc; font-weight: 600; color: #1e40af; }
        .qty-input { width: 80px; padding: 8px; text-align: center; border: 1px solid #cbd5e1; border-radius: 20px; }
        .total-area { background: #f8fafc; padding: 16px 20px; text-align: right; border-top: 2px solid #e2e8f0; }
        .btn-submit { background: #1e40af; color: white; border: none; padding: 10px 28px; border-radius: 40px; font-size: 16px; font-weight: 500; cursor: pointer; transition: 0.2s; margin-top: 10px; }
        .btn-submit:hover { background: #1e3a8a; transform: translateY(-1px); }
        .struk-content { padding: 20px; font-family: 'Courier New', monospace; font-size: 13px; }
        .struk-header { text-align: center; border-bottom: 1px dashed #aaa; margin-bottom: 15px; }
        .struk-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .struk-items { margin: 15px 0; border-top: 1px dotted #aaa; border-bottom: 1px dotted #aaa; padding: 10px 0; }
        .struk-total { font-weight: bold; border-top: 1px solid #aaa; margin-top: 10px; padding-top: 10px; text-align: right; }
        .print-btn { background: #1e40af; color: white; border: none; padding: 8px 15px; border-radius: 30px; cursor: pointer; width: 100%; margin-top: 10px; font-weight: 500; transition: 0.2s; }
        .print-btn:hover { background: #1e3a8a; }
        .alert { margin: 15px 20px; padding: 10px 16px; border-radius: 24px; font-size: 13px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        
        @media print {
            .form-panel, .navbar, .btn-submit, .print-btn { display: none; }
            .struk-panel { box-shadow: none; margin: 0; width: 100%; }
            .struk-content { padding: 0; }
        }
        @media (max-width: 768px) {
            .kasir-wrapper { flex-direction: column; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div>🏪 Berkah Kasir Offline</div>
    <div>
        <a href="kasir/index.php">Beranda</a>
        <a href="kasir/profit_report.php"> Keuangan</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="kasir-wrapper">
    <!-- Panel Form Input -->
    <div class="form-panel">
        <div class="panel-header">
            <h3>🛒 Input Transaksi Baru</h3>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="product-list">
                <table class="product-table">
                    <thead>
                        <tr><th>Produk</th><th>Harga</th><th>Stok</th><th>Jumlah</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td>Rp <?= number_format($p['price'],0,',','.') ?></td>
                            <td><?= $p['stock'] ?></td>
                            <td><input type="number" name="items[<?= $p['id'] ?>]" class="qty-input" min="0" max="<?= $p['stock'] ?>" value="0"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="total-area">
                <h3 style="color:#1e40af;">Total: Rp <span id="totalDisplay">0</span></h3>
                <button type="submit" name="submit_order" class="btn-submit">✅ Proses Transaksi</button>
            </div>
        </form>
    </div>

    <!-- Panel Struk (Hasil Transaksi) -->
    <div class="struk-panel" id="strukPanel">
        <div class="panel-header">
            <h3>🧾 Struk Transaksi</h3>
        </div>
        <div class="struk-content" id="strukContent">
            <?php if ($lastOrder && !empty($lastOrderItems)): ?>
                <div class="struk-header">
                    <strong>BERKAH FASHION STORE</strong><br>
                    Jl. Salakbrojo No.23, Pekalongan<br>
                    Telp. 0812-3456-7890
                </div>
                <div class="struk-row"><span>Invoice #<?= $lastOrder['id'] ?></span><span><?= date('d/m/Y H:i', strtotime($lastOrder['order_date'])) ?></span></div>
                <div class="struk-row"><span>Kasir</span><span><?= htmlspecialchars($_SESSION['username']) ?></span></div>
                <div class="struk-items">
                    <?php foreach ($lastOrderItems as $item): ?>
                        <div class="struk-row">
                            <span><?= htmlspecialchars($item['name']) ?> x <?= $item['quantity'] ?></span>
                            <span>Rp <?= number_format($item['price'] * $item['quantity'],0,',','.') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="struk-total">
                    Total: Rp <?= number_format($lastOrder['total_amount'],0,',','.') ?>
                </div>
                <div class="struk-row"><span>Metode Bayar</span><span>Tunai</span></div>
                <div style="text-align:center; margin-top:15px; font-size:11px;">Terima kasih! Simpan struk ini.</div>
                <button class="print-btn" onclick="printStruk()">🖨️ Cetak Struk / Simpan PDF</button>
            <?php else: ?>
                <div style="text-align:center; color:#94a3b8; padding:40px;">
                    Belum ada transaksi.<br>Silakan input produk di samping.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const inputs = document.querySelectorAll('.qty-input');
    const totalSpan = document.getElementById('totalDisplay');
    function updateTotal() {
        let total = 0;
        inputs.forEach(input => {
            const row = input.closest('tr');
            const priceText = row.cells[1].innerText.replace('Rp ', '').replace(/\./g, '');
            const price = parseInt(priceText) || 0;
            const qty = parseInt(input.value) || 0;
            total += price * qty;
        });
        totalSpan.innerText = total.toLocaleString('id-ID');
    }
    inputs.forEach(i => i.addEventListener('input', updateTotal));
    updateTotal();

    function printStruk() {
        const strukContent = document.getElementById('strukContent').cloneNode(true);
        const printBtn = strukContent.querySelector('.print-btn');
        if (printBtn) printBtn.remove();
        const win = window.open('', '_blank');
        win.document.write('<html><head><title>Struk Berkah Fashion</title><style>body{font-family:"Courier New",monospace;padding:20px;}</style></head><body>');
        win.document.write(strukContent.innerHTML);
        win.document.write('</body></html>');
        win.document.close();
        win.print();
    }
</script>
</body>
</html>