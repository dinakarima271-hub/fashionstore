<?php
require_once 'config.php';
if(!isLoggedIn() || isAdmin()) redirect('index.php');

if(!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Tambah ke keranjang
if(isset($_GET['action']) && $_GET['action'] === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    if($product && $product['stock'] >= $quantity) {
        if(isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'name' => $product['name'],
                'price' => $product['price'],
                'cost_price' => $product['cost_price'],
                'quantity' => $quantity,
                'stock' => $product['stock'],
                'image_url' => $product['image_url']
            ];
        }
    }
    redirect('cart.php');
}

// Update jumlah
if(isset($_POST['update_cart'])) {
    $error = false;
    foreach($_POST['quantity'] as $id => $qty) {
        $qty = max(1, (int)$qty);
        if(isset($_SESSION['cart'][$id])) {
            $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $currentStock = $stmt->fetchColumn();
            if($currentStock === false) {
                unset($_SESSION['cart'][$id]);
                continue;
            }
            $newQty = min($qty, $currentStock);
            $_SESSION['cart'][$id]['quantity'] = $newQty;
            $_SESSION['cart'][$id]['stock'] = $currentStock;
            if($qty > $currentStock) {
                $error = "Stok untuk produk {$_SESSION['cart'][$id]['name']} tersisa $currentStock. Jumlah disesuaikan.";
            }
        }
    }
    if($error) $_SESSION['error'] = $error;
    redirect('cart.php');
}

// Hapus item
if(isset($_GET['remove'])) {
    $id = $_GET['remove'];
    unset($_SESSION['cart'][$id]);
    redirect('cart.php');
}

// Proses checkout
if(isset($_POST['checkout'])) {
    if(empty($_SESSION['cart'])) redirect('cart.php');
    
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $shipping_method = $_POST['shipping_method'];
    $payment_method = $_POST['payment_method'];
    $notes = trim($_POST['notes']);
    
    if(empty($address) || empty($phone)) {
        $_SESSION['error'] = "Alamat dan nomor HP wajib diisi!";
        redirect('cart.php');
    }
    
    $subtotal = 0;
    foreach($_SESSION['cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $shipping_cost = getShippingCost($shipping_method);
    $total = $subtotal + $shipping_cost;
    
    try {
        $pdo->beginTransaction();
        foreach($_SESSION['cart'] as $product_id => $item) {
            $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ? FOR UPDATE");
            $stmt->execute([$product_id]);
            $currentStock = $stmt->fetchColumn();
            if($currentStock < $item['quantity']) {
                throw new Exception("Stok {$item['name']} tidak mencukupi. Tersisa $currentStock.");
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, shipping_cost, address, phone, shipping_method, payment_method, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$_SESSION['user_id'], $total, $shipping_cost, $address, $phone, $shipping_method, $payment_method, $notes]);
        $order_id = $pdo->lastInsertId();
        
        foreach($_SESSION['cart'] as $product_id => $item) {
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, cost_price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$order_id, $product_id, $item['quantity'], $item['price'], $item['cost_price']]);
            $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $product_id]);
        }
        
        if($payment_method === 'COD') {
            $stmtInc = $pdo->prepare("INSERT INTO transactions (type, amount, description, reference_id, created_by) VALUES ('income', ?, CONCAT('Pesanan online #', ?), ?, ?)");
            $stmtInc->execute([$total, $order_id, $order_id, $_SESSION['user_id']]);
        }
        
        $pdo->commit();
        unset($_SESSION['cart']);
        
        if($payment_method === 'COD') {
            $_SESSION['success'] = "Pesanan berhasil dibuat! Total termasuk ongkir Rp ".number_format($shipping_cost,0,',','.');
            redirect('orders.php');
        } else {
            $_SESSION['pending_order_id'] = $order_id;
            redirect('payment_instructions.php');
        }
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Gagal checkout: " . $e->getMessage();
        redirect('cart.php');
    }
}

// Hitung grand total untuk keperluan tampilan dan JavaScript
$grand_total = 0;
foreach($_SESSION['cart'] as $item) {
    $grand_total += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - Berkah Fashion</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: #f0f4f8; color: #1e2a3a; }
        
        /* Navbar - SAMA dengan halaman lain */
        .navbar { background: #1e40af; color: white; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-bottom: none; }
        .navbar .logo { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 500; }
        .navbar .logo svg { width: 28px; height: 28px; stroke: white; stroke-width: 1.6; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; font-size: 14px; transition: opacity 0.2s; }
        .navbar a:hover { opacity: 0.8; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .page-title { font-size: 22px; font-weight: 600; color: #1e40af; margin-bottom: 20px; }
        
        .cart-grid { display: flex; flex-wrap: wrap; gap: 30px; }
        .cart-items { flex: 2; background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; }
        .cart-header { display: grid; grid-template-columns: 3fr 1fr 1fr 1fr 0.5fr; background: #f8fafc; padding: 15px 20px; font-weight: 600; color: #1e2a3a; border-bottom: 1px solid #e2e8f0; }
        .cart-item { display: grid; grid-template-columns: 3fr 1fr 1fr 1fr 0.5fr; align-items: center; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; }
        .product-cell { display: flex; align-items: center; gap: 15px; }
        .product-img { width: 60px; height: 60px; object-fit: cover; border-radius: 12px; background: #f1f5f9; }
        .product-name { font-weight: 600; color: #1e2a3a; }
        .quantity-cell input { width: 70px; padding: 6px; border: 1px solid #cbd5e1; border-radius: 20px; text-align: center; }
        .remove-cell a { color: #dc2626; font-size: 20px; text-decoration: none; }
        .cart-summary { flex: 1.2; background: white; border-radius: 20px; border: 1px solid #e2e8f0; padding: 25px; align-self: start; }
        .summary-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; color: #1e40af; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 16px; }
        .summary-total { font-size: 22px; font-weight: 700; color: #1e40af; border-top: 1px dashed #cbd5e1; padding-top: 15px; margin-top: 10px; }
        .btn-update { background: #64748b; width: 100%; margin-bottom: 15px; }
        .btn { background: #1e40af; color: white; border: none; padding: 8px 16px; border-radius: 40px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 500; }
        .btn-success { background: #1e40af; width: 100%; font-size: 16px; padding: 12px; }
        .btn-success:hover { background: #1e3a8a; }
        .checkout-form { background: white; border-radius: 20px; border: 1px solid #e2e8f0; padding: 25px; margin-top: 30px; }
        .form-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; color: #1e40af; }
        .form-row { margin-bottom: 20px; }
        .form-row label { display: block; font-weight: 600; margin-bottom: 8px; color: #1e2a3a; }
        .form-row input, .form-row select, .form-row textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 20px; font-size: 14px; }
        .two-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .empty-cart { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; border: 1px solid #e2e8f0; }
        .alert { padding: 12px; border-radius: 20px; margin-bottom: 20px; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .alert-success { background: #dcfce7; color: #166534; }
        
        @media (max-width: 768px) {
            .cart-header { display: none; }
            .cart-item { grid-template-columns: 1fr; gap: 10px; }
            .product-cell { justify-content: space-between; }
            .price-cell, .quantity-cell, .subtotal-cell, .remove-cell { display: flex; justify-content: space-between; align-items: center; }
            .price-cell:before { content: "Harga: "; font-weight: 600; }
            .quantity-cell:before { content: "Jumlah: "; font-weight: 600; }
            .subtotal-cell:before { content: "Subtotal: "; font-weight: 600; }
            .remove-cell:before { content: "Hapus: "; font-weight: 600; }
            .two-cols { grid-template-columns: 1fr; }
            .cart-grid { flex-direction: column; }
            .navbar { flex-direction: column; align-items: flex-start; }
            .navbar a { margin-left: 0; margin-right: 16px; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="logo">
        <svg viewBox="0 0 24 24" stroke="currentColor">
            <path d="M9 6L12 3L15 6" />
            <path d="M5 7L7 21H17L19 7" />
            <path d="M5 7L2 12L5 14" />
            <path d="M19 7L22 12L19 14" />
            <circle cx="12" cy="12" r="0.8" fill="white" stroke="none" />
            <circle cx="12" cy="16" r="0.8" fill="white" stroke="none" />
            <path d="M12 7L12 21" stroke-width="1" stroke-dasharray="1.5 1.5" opacity="0.5" />
        </svg>
        Berkah Fashion
    </div>
    <div>
        <a href="index.php">Beranda</a>
        <a href="cart.php">Keranjang</a>
        <a href="orders.php">Pesanan</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container cart-container">
    <div class="page-title">Keranjang Belanja</div>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if(empty($_SESSION['cart'])): ?>
        <div class="empty-cart">
            <h3>Keranjang kosong</h3>
            <p>Yuk, tambahkan produk fashion favoritmu!</p>
            <a href="index.php" class="btn">Belanja Sekarang</a>
        </div>
    <?php else: ?>
        <form method="POST" id="cartForm">
            <div class="cart-grid">
                <div class="cart-items">
                    <div class="cart-header">
                        <div>Produk</div><div>Harga</div><div>Jumlah</div><div>Subtotal</div><div></div>
                    </div>
                    <?php foreach($_SESSION['cart'] as $id => $item): 
                        $subtotal = $item['price'] * $item['quantity'];
                    ?>
                    <div class="cart-item">
                        <div class="product-cell">
                            <img src="<?= htmlspecialchars($item['image_url'] ?? 'https://picsum.photos/60/60') ?>" class="product-img">
                            <span class="product-name"><?= htmlspecialchars($item['name']) ?></span>
                        </div>
                        <div class="price-cell">Rp <?= number_format($item['price'],0,',','.') ?></div>
                        <div class="quantity-cell">
                            <input type="number" name="quantity[<?= $id ?>]" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock'] ?>">
                        </div>
                        <div class="subtotal-cell">Rp <?= number_format($subtotal,0,',','.') ?></div>
                        <div class="remove-cell">
                            <a href="?remove=<?= $id ?>" onclick="return confirm('Hapus item ini?')">🗑️</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <div class="summary-title">Ringkasan Belanja</div>
                    <div class="summary-row">
                        <span>Total Item</span>
                        <span><?= count($_SESSION['cart']) ?> produk</span>
                    </div>
                    <div class="summary-row">
                        <span>Subtotal Produk</span>
                        <span>Rp <?= number_format($grand_total,0,',','.') ?></span>
                    </div>
                    <div class="summary-row" id="shippingCostRow" style="display:none;">
                        <span>Ongkos Kirim</span>
                        <span id="shippingCostDisplay">Rp 0</span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>Total Belanja</span>
                        <span id="grandTotalDisplay">Rp <?= number_format($grand_total,0,',','.') ?></span>
                    </div>
                    <button type="submit" name="update_cart" class="btn btn-update" style="background:#64748b; width:100%; margin-bottom:15px;">Update Keranjang</button>
                </div>
            </div>

            <div class="checkout-form">
                <div class="form-title">Informasi Pengiriman & Pembayaran</div>
                <div class="two-cols">
                    <div class="form-row">
                        <label>Alamat Lengkap *</label>
                        <textarea name="address" rows="3" required placeholder="Jl. Contoh No. 123, Kecamatan, Kota, Kode Pos"></textarea>
                    </div>
                    <div class="form-row">
                        <label>Nomor HP *</label>
                        <input type="tel" name="phone" required placeholder="08123456789">
                    </div>
                </div>
                <div class="two-cols">
                    <div class="form-row">
                        <label>Metode Pengiriman</label>
                        <select name="shipping_method" id="shippingMethod">
                            <option value="JNE Reguler">JNE Reguler (3-5 hari) + Rp 10.000</option>
                            <option value="JNE YES">JNE YES (1-2 hari) + Rp 20.000</option>
                            <option value="SiCepat Reguler">SiCepat Reguler + Rp 12.000</option>
                            <option value="Grab Instant">Grab Instant (kota besar) + Rp 25.000</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Metode Pembayaran</label>
                        <select name="payment_method">
                            <option value="Transfer Bank">Transfer Bank (BCA/Mandiri/BNI)</option>
                            <option value="COD">COD (Bayar di Tempat)</option>
                            <option value="QRIS">QRIS (Scan QR)</option>
                            <option value="E-Wallet">E-Wallet (OVO/GoPay)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label>Catatan (opsional)</label>
                    <textarea name="notes" rows="2" placeholder="Contoh: Tolong dibungkus rapi, atau jam pengiriman..."></textarea>
                </div>
                <button type="submit" name="checkout" class="btn btn-success" style="width:100%;">Konfirmasi & Checkout</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
    const shippingSelect = document.getElementById('shippingMethod');
    const shippingCostRow = document.getElementById('shippingCostRow');
    const shippingCostDisplay = document.getElementById('shippingCostDisplay');
    const grandTotalDisplay = document.getElementById('grandTotalDisplay');
    const subtotal = <?= $grand_total ?? 0 ?>;
    
    const shippingCosts = {
        'JNE Reguler': 10000,
        'JNE YES': 20000,
        'SiCepat Reguler': 12000,
        'Grab Instant': 25000
    };
    
    function updateTotal() {
        const method = shippingSelect.value;
        const cost = shippingCosts[method] || 0;
        if(cost > 0) {
            shippingCostRow.style.display = 'flex';
            shippingCostDisplay.innerText = 'Rp ' + cost.toLocaleString('id-ID');
        } else {
            shippingCostRow.style.display = 'none';
        }
        const total = subtotal + cost;
        grandTotalDisplay.innerText = 'Rp ' + total.toLocaleString('id-ID');
    }
    
    if(shippingSelect) {
        shippingSelect.addEventListener('change', updateTotal);
        updateTotal();
    }
</script>
</body>
</html>