<?php
require_once __DIR__ . '/../config.php';
if (!isAdmin()) redirect('../index.php');

// Proses tambah pemasukan/pengeluaran manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    $type = $_POST['type'];
    $amount = (float)$_POST['amount'];
    $description = trim($_POST['description']);
    
    if ($amount <= 0) {
        $_SESSION['error'] = "Jumlah harus lebih dari 0.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO transactions (type, amount, description, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$type, $amount, $description, $_SESSION['user_id']]);
        $_SESSION['message'] = ucfirst($type) . " sebesar Rp " . number_format($amount,0,',','.') . " berhasil dicatat.";
    }
    header('Location: finance.php');
    exit;
}

// Ambil semua transaksi
$transactions = $pdo->query("SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON t.created_by = u.id ORDER BY t.created_at DESC")->fetchAll();

// Hitung total pemasukan & pengeluaran
$totalIncome = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income'")->fetchColumn();
$totalExpense = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense'")->fetchColumn();
$balance = ($totalIncome ?: 0) - ($totalExpense ?: 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keuangan - Berkah Fashion Admin</title>
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
        
        /* Statistik cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 20px; text-align: center; border: 1px solid #e2e8f0; }
        .stat-card .label { font-size: 14px; color: #475569; margin-bottom: 8px; }
        .stat-number { font-size: 28px; font-weight: 700; }
        .income { color: #166534; }
        .expense { color: #991b1b; }
        .balance { color: #1e40af; }
        
        /* Form card */
        .form-card { background: white; padding: 24px; border-radius: 20px; margin-bottom: 30px; border: 1px solid #e2e8f0; }
        .form-card h3 { margin-bottom: 16px; color: #1e40af; font-weight: 600; }
        .form-row { margin-bottom: 16px; }
        .form-row label { display: block; font-weight: 500; margin-bottom: 6px; color: #1e2a3a; }
        .form-row input, .form-row select, .form-row textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 20px; font-size: 14px; }
        .btn-group { display: flex; gap: 10px; }
        
        .btn { background: #1e40af; color: white; border: none; padding: 8px 20px; border-radius: 40px; cursor: pointer; font-size: 14px; font-weight: 500; transition: 0.2s; text-decoration: none; display: inline-block; }
        .btn:hover { background: #1e3a8a; transform: translateY(-1px); }
        
        /* Tabel */
        .table-wrapper { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #1e40af; }
        tr:hover td { background: #f8fafc; }
        
        .alert { padding: 12px 18px; border-radius: 24px; font-size: 13px; margin-bottom: 24px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        
        @media (max-width: 640px) {
            .navbar { flex-direction: column; align-items: flex-start; }
            .navbar .nav-links a { margin: 0 15px 0 0; }
            .stat-number { font-size: 22px; }
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
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card"><div class="label">💰 Total Pemasukan</div><div class="stat-number income">Rp <?= number_format($totalIncome ?: 0,0,',','.') ?></div></div>
        <div class="stat-card"><div class="label">💸 Total Pengeluaran</div><div class="stat-number expense">Rp <?= number_format($totalExpense ?: 0,0,',','.') ?></div></div>
        <div class="stat-card"><div class="label">📊 Saldo</div><div class="stat-number balance">Rp <?= number_format($balance,0,',','.') ?></div></div>
    </div>

    <div class="form-card">
        <h3>Tambah Transaksi Baru</h3>
        <form method="POST">
            <div class="form-row">
                <label>Jenis</label>
                <select name="type" required>
                    <option value="income">✅ Pemasukan</option>
                    <option value="expense">❌ Pengeluaran</option>
                </select>
            </div>
            <div class="form-row">
                <label>Jumlah (Rp)</label>
                <input type="number" step="1000" name="amount" required>
            </div>
            <div class="form-row">
                <label>Deskripsi</label>
                <textarea name="description" rows="2" placeholder="Contoh: Pembelian stok baju, Sewa tempat, dll"></textarea>
            </div>
            <button type="submit" name="add_transaction" class="btn">Simpan Transaksi</button>
        </form>
    </div>

    <h3 style="margin-bottom: 16px; color: #1e40af;">Riwayat Transaksi</h3>
    <?php if (count($transactions) == 0): ?>
        <div class="alert" style="background:#f8fafc; color:#475569;">Belum ada transaksi.</div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Tanggal</th><th>Jenis</th><th>Jumlah</th><th>Deskripsi</th><th>Dicatat oleh</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tr): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($tr['created_at'])) ?></td>
                        <td><?= $tr['type'] == 'income' ? '✅ Pemasukan' : '❌ Pengeluaran' ?></td>
                        <td class="<?= $tr['type'] == 'income' ? 'income' : 'expense' ?>">Rp <?= number_format($tr['amount'],0,',','.') ?></td>
                        <td><?= htmlspecialchars($tr['description']) ?></td>
                        <td><?= htmlspecialchars($tr['username'] ?? 'System') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>