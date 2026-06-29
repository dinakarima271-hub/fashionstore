<?php
require_once 'config.php';
if(!isLoggedIn() || isAdmin()) redirect('index.php');

// Proses ajukan pembatalan
if(isset($_GET['cancel_request'])) {
    $order_id = (int)$_GET['cancel_request'];
    
    $stmt = $pdo->prepare("SELECT status, user_id FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if($order && $order['status'] == 'pending') {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'request_cancel' WHERE id = ?");
        $stmt->execute([$order_id]);
        $_SESSION['success'] = "Permintaan pembatalan pesanan #$order_id telah dikirim. Menunggu konfirmasi admin.";
    } else {
        $_SESSION['error'] = "Pesanan tidak dapat dibatalkan karena sudah diproses.";
    }
    redirect('orders.php');
}

// Proses ulasan (review)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $order_id = (int)$_POST['order_id'];
    $product_id = (int)$_POST['product_id'];
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    
    if($rating < 1 || $rating > 5) {
        $_SESSION['error'] = "Rating harus 1-5.";
        redirect('orders.php');
    }
    
    if(empty($comment)) {
        $_SESSION['error'] = "Komentar tidak boleh kosong.";
        redirect('orders.php');
    }
    
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if(!$order || $order['status'] != 'delivered') {
        $_SESSION['error'] = "Anda hanya bisa memberi ulasan untuk pesanan yang sudah selesai.";
        redirect('orders.php');
    }
    
    $stmt = $pdo->prepare("SELECT id FROM order_reviews WHERE order_id = ? AND product_id = ?");
    $stmt->execute([$order_id, $product_id]);
    if($stmt->fetch()) {
        $_SESSION['error'] = "Anda sudah memberikan ulasan untuk produk ini.";
        redirect('orders.php');
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO order_reviews (order_id, product_id, user_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$order_id, $product_id, $_SESSION['user_id'], $rating, $comment]);
        
        // Update rating produk
        $stmt = $pdo->prepare("
            UPDATE products p 
            SET p.rating = (
                SELECT AVG(r.rating) 
                FROM order_reviews r 
                WHERE r.product_id = p.id
            ),
            p.review_count = (
                SELECT COUNT(*) 
                FROM order_reviews r 
                WHERE r.product_id = p.id
            )
            WHERE p.id = ?
        ");
        $stmt->execute([$product_id]);
        
        $_SESSION['success'] = "Terima kasih atas ulasannya!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal menyimpan review: " . $e->getMessage();
    }
    
    redirect('orders.php');
}

// Fungsi status badge
function statusBadge($status) {
    $map = [
        'pending'           => ['text' => 'Menunggu Pembayaran', 'class' => 'badge-warning'],
        'request_cancel'    => ['text' => 'Menunggu Konfirmasi Admin', 'class' => 'badge-warning'],
        'processed'         => ['text' => 'Diproses', 'class' => 'badge-info'],
        'shipped'           => ['text' => 'Dikirim', 'class' => 'badge-primary'],
        'delivered'         => ['text' => 'Selesai', 'class' => 'badge-success'],
        'cancelled'         => ['text' => 'Dibatalkan', 'class' => 'badge-danger']
    ];
    $data = $map[$status] ?? ['text' => ucfirst($status), 'class' => 'badge-secondary'];
    return "<span class='badge {$data['class']}'>{$data['text']}</span>";
}

// Ambil daftar pesanan user
$stmt = $pdo->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_reviews WHERE order_id = o.id) as review_count,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as total_items
    FROM orders o 
    WHERE o.user_id = ? 
    ORDER BY o.order_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

// Ambil item setiap pesanan
foreach($orders as &$order) {
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.image 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $order['items'] = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT product_id FROM order_reviews WHERE order_id = ?");
    $stmt->execute([$order['id']]);
    $order['reviewed_products'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Berkah Fashion</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; color: #1e2a3a; }
        
        .navbar { background: #1e40af; color: white; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .navbar .logo { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 500; }
        .navbar .logo svg { width: 28px; height: 28px; stroke: white; stroke-width: 1.6; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; font-size: 14px; transition: opacity 0.2s; }
        .navbar a:hover { opacity: 0.8; }
        
        .container { max-width: 700px; margin: 20px auto; padding: 0 16px; }
        .page-title { font-size: 22px; font-weight: 600; color: #1e40af; margin-bottom: 20px; }
        
        .order-card { background: white; border-radius: 12px; margin-bottom: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .order-header { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .order-header .order-id { font-size: 14px; font-weight: 600; color: #1e2a3a; }
        .order-header .order-id span { color: #64748b; font-weight: 400; }
        
        .badge { display: inline-block; padding: 2px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #e0e7ff; color: #1e3a8a; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #e2e8f0; color: #475569; }
        
        .product-list { padding: 8px 16px; }
        .product-item { display: flex; gap: 12px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; align-items: center; }
        .product-item:last-child { border-bottom: none; }
        .product-item .product-image { width: 56px; height: 56px; border-radius: 8px; object-fit: cover; background: #e2e8f0; flex-shrink: 0; }
        .product-item .product-info { flex: 1; }
        .product-item .product-name { font-size: 13px; font-weight: 500; color: #1e2a3a; }
        .product-item .product-qty { font-size: 12px; color: #64748b; }
        .product-item .product-price { font-size: 14px; font-weight: 600; color: #1e40af; }
        
        .order-footer { padding: 10px 16px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
        .summary-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; color: #475569; }
        .summary-row.total { border-top: 2px solid #e2e8f0; padding-top: 10px; margin-top: 4px; font-size: 15px; font-weight: 700; color: #1e2a3a; }
        .summary-row.total .amount { color: #1e40af; font-size: 17px; }
        .summary-row .amount { font-weight: 600; }
        
        .review-section { margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e8f0; }
        .btn-review { display: inline-block; padding: 6px 20px; background: #f59e0b; color: white; border: none; border-radius: 30px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; }
        .btn-review:hover { background: #d97706; transform: translateY(-1px); }
        .btn-success-small { display: inline-block; padding: 6px 20px; background: #16a34a; color: white; border: none; border-radius: 30px; font-size: 13px; font-weight: 600; cursor: default; }
        .btn-cancel { display: inline-block; padding: 6px 16px; background: #dc2626; color: white; border: none; border-radius: 30px; font-size: 12px; font-weight: 500; cursor: pointer; text-decoration: none; transition: 0.2s; }
        .btn-cancel:hover { background: #b91c1c; }
        .btn-disabled-small { display: inline-block; padding: 6px 16px; background: #e2e8f0; color: #94a3b8; border-radius: 30px; font-size: 12px; font-weight: 500; cursor: not-allowed; }
        
        .review-form-inline { margin-top: 10px; padding: 12px; background: #f1f5f9; border-radius: 10px; border: 1px solid #e2e8f0; }
        .review-form-inline textarea { width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; margin-bottom: 10px; font-family: inherit; min-height: 60px; resize: vertical; }
        
        /* RATING SELECT - PASTI BISA */
        .rating-select {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            width: 100%;
            max-width: 200px;
            margin-bottom: 10px;
        }
        
        .submit-review-btn { padding: 8px 24px; background: #1e40af; color: white; border: none; border-radius: 30px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .submit-review-btn:hover { background: #1e3a8a; }
        
        .alert { padding: 12px 18px; border-radius: 12px; font-size: 14px; margin-bottom: 16px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; border: 1px solid #e2e8f0; color: #64748b; }
        .empty-state .empty-icon { font-size: 48px; margin-bottom: 16px; }
        .empty-state h3 { color: #1e2a3a; margin-bottom: 8px; }
        
        .order-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
        .request-cancel-badge { background: #fef3c7; color: #b45309; padding: 2px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; animation: blink 1s infinite; }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        
        .review-product-name { font-size: 13px; font-weight: 500; color: #1e2a3a; padding: 8px 12px; background: #e2e8f0; border-radius: 8px; margin-bottom: 10px; display: inline-block; }
        
        .btn-review-simple { display: inline-block; padding: 4px 14px; background: #f59e0b; color: white; border: none; border-radius: 30px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; }
        .btn-review-simple:hover { background: #d97706; transform: translateY(-1px); }
        
        @media (max-width: 640px) {
            .navbar { flex-direction: column; align-items: flex-start; }
            .navbar a { margin: 0 15px 0 0; }
            .order-header { flex-wrap: wrap; gap: 6px; }
            .product-item .product-image { width: 48px; height: 48px; }
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
        <a href="index.php">🏠 Beranda</a>
        <a href="cart.php">🛒 Keranjang</a>
        <a href="orders.php">📦 Pesanan</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
</nav>

<div class="container">
    <div class="page-title">📦 Pesanan Saya</div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success">✅ <?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-error">❌ <?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if(empty($orders)): ?>
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <h3>Belum Ada Pesanan</h3>
            <p>Yuk, mulai belanja sekarang!</p>
            <a href="index.php" class="btn-review" style="margin-top: 12px;">Mulai Belanja</a>
        </div>
    <?php else: ?>
        <?php foreach($orders as $order): 
            $subtotal = 0;
            foreach($order['items'] as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            
            $ongkir = 10000;
            $total = $subtotal + $ongkir;
            
            $unreviewed = [];
            foreach($order['items'] as $item) {
                if(!in_array($item['product_id'], $order['reviewed_products'])) {
                    $unreviewed[] = $item;
                }
            }
            
            $allReviewed = empty($unreviewed);
        ?>
        <div class="order-card">
            <div class="order-header">
                <div class="order-id">
                    <span>Pesanan #</span><?= $order['id'] ?>
                    <span style="font-weight:400;color:#94a3b8;font-size:12px;margin-left:6px;">
                        <?= date('d/m/Y', strtotime($order['order_date'])) ?>
                    </span>
                </div>
                <div>
                    <?php if($order['status'] == 'request_cancel'): ?>
                        <span class="request-cancel-badge">⏳ Menunggu Konfirmasi Admin</span>
                    <?php else: ?>
                        <?= statusBadge($order['status']) ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="product-list">
                <?php foreach($order['items'] as $item): ?>
                <div class="product-item">
                    <?php if(!empty($item['image']) && file_exists('uploads/produk/' . $item['image'])): ?>
                        <img src="uploads/produk/<?= $item['image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="product-image">
                    <?php else: ?>
                        <div class="product-image" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #94a3b8;">No Image</div>
                    <?php endif; ?>
                    <div class="product-info">
                        <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="product-qty"><?= $item['quantity'] ?> pcs</div>
                    </div>
                    <div class="product-price">Rp <?= number_format($item['price'], 0, ',', '.') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="order-footer">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span class="amount">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                </div>
                <div class="summary-row">
                    <span>Ongkos Kirim</span>
                    <span class="amount">Rp <?= number_format($ongkir, 0, ',', '.') ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span class="amount">Rp <?= number_format($total, 0, ',', '.') ?></span>
                </div>
                
                <div class="review-section">
                    <?php if($order['status'] == 'delivered'): ?>
                        <?php if($allReviewed): ?>
                            <div class="order-actions">
                                <span class="btn-success-small">⭐ Semua Sudah Diulas</span>
                            </div>
                        <?php else: ?>
                            <div class="order-actions" style="flex-wrap:wrap; gap:8px;">
                                <?php foreach($unreviewed as $item): ?>
                                <button class="btn-review-simple" onclick="toggleReview(<?= $order['id'] ?>, <?= $item['product_id'] ?>)">
                                    ⭐ Beri Nilai
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php foreach($unreviewed as $item): ?>
                            <div id="reviewForm_<?= $order['id'] ?>_<?= $item['product_id'] ?>" class="review-form-inline" style="display: none;">
                                <form method="POST" action="">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                    
                                    <div class="review-product-name">
                                        📝 <?= htmlspecialchars($item['name']) ?>
                                    </div>
                                    
                                    <div>
                                        <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Rating</label>
                                        <select name="rating" class="rating-select" required>
                                            <option value="">Pilih Rating</option>
                                            <option value="1">⭐ 1 - Sangat Buruk</option>
                                            <option value="2">⭐⭐ 2 - Buruk</option>
                                            <option value="3">⭐⭐⭐ 3 - Cukup</option>
                                            <option value="4">⭐⭐⭐⭐ 4 - Baik</option>
                                            <option value="5">⭐⭐⭐⭐⭐ 5 - Sangat Baik</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Komentar</label>
                                        <textarea name="comment" placeholder="Tulis pengalaman Anda..." required></textarea>
                                    </div>
                                    
                                    <button type="submit" name="submit_review" class="submit-review-btn">Kirim Ulasan</button>
                                    <button type="button" class="btn-cancel" onclick="toggleReview(<?= $order['id'] ?>, <?= $item['product_id'] ?>)" style="background:#94a3b8;">Batal</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                    <?php elseif($order['status'] == 'pending'): ?>
                        <div class="order-actions">
                            <span class="btn-review" style="background:#f59e0b;cursor:default;">⏳ Menunggu Pembayaran</span>
                            <a href="?cancel_request=<?= $order['id'] ?>" class="btn-cancel" onclick="return confirm('Ajukan pembatalan pesanan ini? Permintaan akan dikonfirmasi admin.')">Ajukan Batal</a>
                        </div>
                    <?php elseif($order['status'] == 'request_cancel'): ?>
                        <div class="order-actions">
                            <span class="btn-disabled-small">⏳ Menunggu Konfirmasi Admin</span>
                        </div>
                    <?php elseif($order['status'] == 'cancelled'): ?>
                        <div class="order-actions">
                            <span class="btn-disabled-small">❌ Dibatalkan</span>
                        </div>
                    <?php elseif($order['status'] == 'processed'): ?>
                        <div class="order-actions">
                            <span class="btn-review" style="background:#1e40af;cursor:default;">📦 Diproses</span>
                        </div>
                    <?php elseif($order['status'] == 'shipped'): ?>
                        <div class="order-actions">
                            <span class="btn-review" style="background:#1e40af;cursor:default;">🚚 Dikirim</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleReview(orderId, productId) {
    var form = document.getElementById('reviewForm_' + orderId + '_' + productId);
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}
</script>
</body>
</html>