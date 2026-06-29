<?php
require_once __DIR__ . '/../config.php';
if (!isAdmin()) redirect('../index.php');

// Ambil semua pesanan (online + offline kasir)
$stmt = $pdo->query("
    SELECT o.*, COALESCE(u.username, CONCAT('Kasir: ', k.username), 'Offline') as username 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id AND o.user_id IS NOT NULL AND o.user_id != 0
    LEFT JOIN users k ON o.kasir_id = k.id
    ORDER BY o.order_date DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOrders = count($orders);
$pendingCount = count(array_filter($orders, fn($o) => $o['status'] == 'pending'));
$requestCancelCount = count(array_filter($orders, fn($o) => $o['status'] == 'request_cancel'));
$processedCount = count(array_filter($orders, fn($o) => $o['status'] == 'processed'));
$shippedCount = count(array_filter($orders, fn($o) => $o['status'] == 'shipped'));
$deliveredCount = count(array_filter($orders, fn($o) => $o['status'] == 'delivered'));
$cancelledCount = count(array_filter($orders, fn($o) => $o['status'] == 'cancelled'));

// Update status biasa (dengan restok jika dibatalkan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $status = $_POST['status'];
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $oldStatus = $stmt->fetchColumn();
    
    // Jika diubah ke cancelled, kembalikan stok
    if ($status === 'cancelled' && $oldStatus !== 'cancelled') {
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll();
        foreach ($items as $item) {
            $stmt2 = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $stmt2->execute([$item['quantity'], $item['product_id']]);
        }
    }
    
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);
    $_SESSION['message'] = "Status pesanan #$order_id berhasil diupdate.";
    header('Location: index.php');
    exit;
}

// Verifikasi pembayaran (non-COD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $order_id = (int)$_POST['order_id'];
    $stmt = $pdo->prepare("UPDATE orders SET status = 'processed' WHERE id = ? AND payment_method != 'COD'");
    $stmt->execute([$order_id]);
    $stmt = $pdo->prepare("SELECT total_amount FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $total = $stmt->fetchColumn();
    if ($total) {
        $stmtInc = $pdo->prepare("INSERT INTO transactions (type, amount, description, reference_id, created_by) VALUES ('income', ?, CONCAT('Pesanan online #', ?), ?, ?)");
        $stmtInc->execute([$total, $order_id, $order_id, $_SESSION['user_id']]);
    }
    $_SESSION['message'] = "Pembayaran pesanan #$order_id telah diverifikasi. Status diubah menjadi Diproses.";
    header('Location: index.php');
    exit;
}

// KONFIRMASI PEMBATALAN OLEH ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel'])) {
    $order_id = (int)$_POST['order_id'];
    
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if ($order && $order['status'] == 'request_cancel') {
        // Ubah status menjadi cancelled
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$order_id]);
        
        // Kembalikan stok produk
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll();
        foreach ($items as $item) {
            $stmt2 = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $stmt2->execute([$item['quantity'], $item['product_id']]);
        }
        
        $_SESSION['message'] = "✅ Pesanan #$order_id telah dibatalkan. Stok dikembalikan.";
    } else {
        $_SESSION['error'] = "❌ Pesanan tidak dalam status permintaan pembatalan.";
    }
    header('Location: index.php');
    exit;
}

// TOLAK PEMBATALAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_cancel'])) {
    $order_id = (int)$_POST['order_id'];
    
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if ($order && $order['status'] == 'request_cancel') {
        // Kembalikan ke status pending
        $stmt = $pdo->prepare("UPDATE orders SET status = 'pending' WHERE id = ?");
        $stmt->execute([$order_id]);
        $_SESSION['message'] = "❌ Permintaan pembatalan pesanan #$order_id ditolak. Kembali ke status Menunggu.";
    } else {
        $_SESSION['error'] = "Pesanan tidak dalam status permintaan pembatalan.";
    }
    header('Location: index.php');
    exit;
}

// Ambil detail pesanan untuk modal
$detail_order = null;
$detail_items = [];
if (isset($_GET['detail'])) {
    $order_id = (int)$_GET['detail'];
    $stmt = $pdo->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $detail_order = $stmt->fetch();
    
    if ($detail_order) {
        $stmt = $pdo->prepare("SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $stmt->execute([$order_id]);
        $detail_items = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Berkah Fashion</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f0f4f8; color: #1e2a3a; }
        
        .navbar { background: #1e40af; color: white; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: none; flex-wrap: wrap; gap: 10px; }
        .navbar .logo { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 500; }
        .navbar .logo svg { width: 28px; height: 28px; stroke: white; stroke-width: 1.6; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .navbar .nav-links a { margin-left: 20px; text-decoration: none; color: white; font-size: 14px; transition: opacity 0.2s; }
        .navbar .nav-links a:hover { opacity: 0.8; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; }
        h2 { font-size: 22px; font-weight: 600; color: #1e40af; margin: 0; }
        
        .btn-export { background: #1e40af; color: white; text-decoration: none; padding: 8px 18px; border-radius: 40px; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
        .btn-export:hover { background: #1e3a8a; }
        
        .stats-grid { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 16px 12px; border-radius: 20px; text-align: center; flex: 1; min-width: 100px; border: 1px solid #e2e8f0; }
        .stat-number { font-size: 28px; font-weight: 600; color: #1e40af; }
        .stat-label { font-size: 12px; color: #475569; margin-top: 6px; }
        .stat-card-warning { background: #fffbeb; border-color: #f59e0b; }
        .stat-card-warning .stat-number { color: #b45309; }
        
        .table-wrapper { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background: #f8fafc; font-weight: 600; color: #1e40af; }
        
        .badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #e0e7ff; color: #1e3a8a; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #e2e8f0; color: #475569; }
        
        .btn { background: #1e40af; color: white; border: none; padding: 6px 14px; border-radius: 30px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block; transition: 0.2s; }
        .btn-outline { background: #e2e8f0; color: #1e40af; border: none; }
        .btn-outline:hover { background: #cbd5e1; }
        .btn:hover { background: #1e3a8a; transform: translateY(-1px); }
        .btn-detail { background: #64748b; color: white; border: none; padding: 6px 12px; border-radius: 30px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block; transition: 0.2s; }
        .btn-detail:hover { background: #475569; }
        
        .btn-verify { background: #e67e22; color: white; border: none; padding: 6px 12px; border-radius: 30px; font-size: 12px; cursor: pointer; transition: 0.2s; }
        .btn-verify:hover { background: #d35400; }
        
        .btn-cancel-confirm { background: #dc2626; color: white; border: none; padding: 6px 12px; border-radius: 30px; font-size: 12px; cursor: pointer; transition: 0.2s; }
        .btn-cancel-confirm:hover { background: #b91c1c; }
        
        .btn-reject-cancel { background: #64748b; color: white; border: none; padding: 6px 12px; border-radius: 30px; font-size: 12px; cursor: pointer; transition: 0.2s; }
        .btn-reject-cancel:hover { background: #475569; }
        
        select { padding: 5px 10px; border-radius: 20px; border: 1px solid #cbd5e1; background: white; font-size: 12px; margin-right: 8px; }
        .action-group { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .alert { padding: 12px 18px; border-radius: 24px; font-size: 13px; margin-bottom: 24px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .empty-message { text-align: center; padding: 50px; background: white; border-radius: 20px; border: 1px solid #e2e8f0; color: #64748b; }
        
        .no-column { text-align: center; color: #64748b; font-weight: 500; width: 60px; }
        
        .request-cancel-row {
            background: #fef3c7 !important;
        }
        .request-cancel-row td {
            border-color: #fcd34d !important;
        }
        
        .badge-request {
            background: #fef3c7;
            color: #b45309;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 500;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: white;
            border-radius: 20px;
            max-width: 700px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px;
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 12px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #94a3b8;
            transition: 0.2s;
            background: none;
            border: none;
        }
        .modal-close:hover { color: #dc2626; }
        .modal-title { font-size: 20px; font-weight: 600; color: #1e40af; margin-bottom: 16px; }
        .modal-info { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; margin-bottom: 16px; padding: 12px; background: #f8fafc; border-radius: 12px; }
        .modal-info .label { font-size: 12px; color: #64748b; }
        .modal-info .value { font-size: 14px; font-weight: 500; color: #1e2a3a; }
        .modal-item { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; align-items: center; }
        .modal-item:last-child { border-bottom: none; }
        .modal-item img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; background: #e2e8f0; }
        .modal-item .info { flex: 1; }
        .modal-item .name { font-size: 13px; font-weight: 500; }
        .modal-item .qty { font-size: 12px; color: #64748b; }
        .modal-item .price { font-size: 14px; font-weight: 600; color: #1e40af; }
        .modal-total { display: flex; justify-content: space-between; padding-top: 12px; margin-top: 12px; border-top: 2px solid #e2e8f0; font-size: 16px; font-weight: 700; }
        .modal-total .amount { color: #1e40af; font-size: 18px; }
        
        @media (max-width: 700px) {
            .navbar { flex-direction: column; align-items: flex-start; }
            .navbar .nav-links a { margin: 0 15px 0 0; }
            .stat-number { font-size: 22px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .modal-info { grid-template-columns: 1fr; }
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
    <?php if(isset($_SESSION['message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="page-header">
        <h2>📋 Pesanan Online</h2>
        <a href="export_orders.php" class="btn-export">📎 Ekspor ke Excel</a>
    </div>

    <!-- Statistik -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?= $totalOrders ?></div><div class="stat-label">Total Pesanan</div></div>
        <div class="stat-card"><div class="stat-number"><?= $pendingCount ?></div><div class="stat-label">Menunggu</div></div>
        <div class="stat-card stat-card-warning">
            <div class="stat-number"><?= $requestCancelCount ?></div>
            <div class="stat-label">⚠️ Menunggu Batal</div>
        </div>
        <div class="stat-card"><div class="stat-number"><?= $processedCount ?></div><div class="stat-label">Diproses</div></div>
        <div class="stat-card"><div class="stat-number"><?= $shippedCount ?></div><div class="stat-label">Dikirim</div></div>
        <div class="stat-card"><div class="stat-number"><?= $deliveredCount ?></div><div class="stat-label">Selesai</div></div>
        <div class="stat-card"><div class="stat-number"><?= $cancelledCount ?></div><div class="stat-label">Dibatalkan</div></div>
    </div>

    <?php if(count($orders) == 0): ?>
        <div class="empty-message">Belum ada pesanan online.</div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th class="no-column">No</th>
                        <th>Pelanggan</th>
                        <th>Tanggal</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Bukti Bayar</th>
                        <th>Verifikasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach($orders as $order): 
                        $isRequestCancel = $order['status'] == 'request_cancel';
                    ?>
                    <tr class="<?= $isRequestCancel ? 'request-cancel-row' : '' ?>">
                        <td class="no-column"><?= $no++ ?></td>
                        <td><?= htmlspecialchars($order['username']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></td>
                        <td>Rp <?= number_format($order['total_amount'],0,',','.') ?></td>
                        <td>
                            <?php if($isRequestCancel): ?>
                                <span class="badge-request">⚠️ <?= statusIndonesia($order['status']) ?></span>
                            <?php else: ?>
                                <?= statusBadge($order['status']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($order['payment_method'] != 'COD' && !empty($order['payment_proof'])): ?>
                                <a href="../uploads/bukti/<?= $order['payment_proof'] ?>" target="_blank" class="btn-outline" style="padding: 4px 10px; text-decoration: none;">Lihat Bukti</a>
                            <?php elseif($order['payment_method'] == 'COD'): ?>
                                <span style="font-size:12px;">COD</span>
                            <?php else: ?>
                                <span style="color:#94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($order['payment_method'] != 'COD' && $order['status'] == 'pending' && !empty($order['payment_proof'])): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <button type="submit" name="verify_payment" class="btn-verify">Verifikasi Bayar</button>
                                </form>
                            <?php elseif($order['payment_method'] == 'COD' && $order['status'] == 'pending'): ?>
                                <span style="color:#d97706;">Menunggu COD</span>
                            <?php else: ?>
                                <span style="color:#94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="action-group">
                            <!-- Tombol Detail -->
                            <a href="?detail=<?= $order['id'] ?>" class="btn-detail">📋 Detail</a>
                            
                            <!-- KONFIRMASI BATAL - KHUSUS UNTUK STATUS request_cancel -->
                            <?php if($order['status'] == 'request_cancel'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ Yakin ingin membatalkan pesanan #<?= $order['id'] ?>? Stok akan dikembalikan.')">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <button type="submit" name="confirm_cancel" class="btn-cancel-confirm" style="padding: 6px 16px; font-weight: 600;">
                                        ✅ Konfirmasi Batal
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menolak pembatalan pesanan #<?= $order['id'] ?>?')">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <button type="submit" name="reject_cancel" class="btn-reject-cancel" style="padding: 6px 16px;">
                                        ❌ Tolak
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <!-- Form update status (sembunyikan untuk request_cancel karena ditangani di atas) -->
                            <?php if($order['status'] != 'request_cancel'): ?>
                            <form method="POST" style="display:inline-flex; gap:5px; align-items:center;">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <select name="status">
                                    <option value="pending" <?= $order['status']=='pending'?'selected':'' ?>>Menunggu</option>
                                    <option value="processed" <?= $order['status']=='processed'?'selected':'' ?>>Diproses</option>
                                    <option value="shipped" <?= $order['status']=='shipped'?'selected':'' ?>>Dikirim</option>
                                    <option value="delivered" <?= $order['status']=='delivered'?'selected':'' ?>>Selesai</option>
                                    <option value="cancelled" <?= $order['status']=='cancelled'?'selected':'' ?>>Dibatalkan</option>
                                </select>
                                <button type="submit" name="update_status" class="btn">Update</button>
                            </form>
                            <?php endif; ?>
                            
                            <a href="../invoice.php?id=<?= $order['id'] ?>" class="btn btn-outline">Invoice</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Detail Pesanan -->
<div class="modal-overlay <?= $detail_order ? 'active' : '' ?>" id="detailModal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        
        <?php if($detail_order): ?>
        <div class="modal-title">📋 Detail Pesanan #<?= $detail_order['id'] ?></div>
        
        <div class="modal-info">
            <div>
                <div class="label">Pelanggan</div>
                <div class="value"><?= htmlspecialchars($detail_order['username']) ?></div>
            </div>
            <div>
                <div class="label">Tanggal</div>
                <div class="value"><?= date('d/m/Y H:i', strtotime($detail_order['order_date'])) ?></div>
            </div>
            <div>
                <div class="label">Status</div>
                <div class="value"><?= statusBadge($detail_order['status']) ?></div>
            </div>
            <div>
                <div class="label">Metode Pembayaran</div>
                <div class="value"><?= $detail_order['payment_method'] == 'COD' ? 'COD' : 'Transfer Bank' ?></div>
            </div>
        </div>
        
        <div style="font-weight:600;margin-bottom:10px;font-size:14px;">Daftar Produk</div>
        
        <?php foreach($detail_items as $item): ?>
        <div class="modal-item">
            <?php if(!empty($item['image']) && file_exists('../uploads/produk/' . $item['image'])): ?>
                <img src="../uploads/produk/<?= $item['image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>">
            <?php else: ?>
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='50' height='50'%3E%3Crect width='50' height='50' fill='%23e2e8f0'/%3E%3Ctext x='25' y='30' font-size='10' text-anchor='middle' fill='%2394a3b8'%3ENo%20Image%3C/text%3E%3C/svg%3E" alt="No Image">
            <?php endif; ?>
            <div class="info">
                <div class="name"><?= htmlspecialchars($item['name']) ?></div>
                <div class="qty"><?= $item['quantity'] ?> pcs</div>
            </div>
            <div class="price">Rp <?= number_format($item['price'],0,',','.') ?></div>
        </div>
        <?php endforeach; ?>
        
        <div class="modal-total">
            <span>Total</span>
            <span class="amount">Rp <?= number_format($detail_order['total_amount'],0,',','.') ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function closeModal() {
    document.getElementById('detailModal').classList.remove('active');
    if (window.history && window.history.pushState) {
        window.history.pushState({}, '', 'index.php');
    }
}

document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>
</body>
</html>