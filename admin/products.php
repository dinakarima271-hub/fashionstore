<?php
require_once dirname(__DIR__) . '/config.php';

if (!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

// Folder upload (di luar folder admin, sejajar dengan index.php)
$uploadDir = dirname(__DIR__) . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Hapus file gambar
function deleteImage($filename) {
    global $uploadDir;
    if ($filename && file_exists($uploadDir . $filename)) {
        unlink($uploadDir . $filename);
    }
}

// Hapus produk
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Cek apakah produk sudah digunakan dalam pesanan (order_items)
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
    $checkStmt->execute([$id]);
    $count = $checkStmt->fetchColumn();

    if ($count > 0) {
        $_SESSION['error'] = "Produk tidak dapat dihapus karena sudah ada di pesanan.";
        header('Location: products.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($prod && $prod['image_url'] && strpos($prod['image_url'], 'uploads/') === 0) {
        $oldFile = basename($prod['image_url']);
        deleteImage($oldFile);
    }
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['message'] = "Produk berhasil dihapus.";
    header('Location: products.php');
    exit;
}

// Tambah / Edit produk dengan upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $cost_price = (float)$_POST['cost_price'];
    $stock = (int)$_POST['stock'];
    $isEdit = isset($_POST['edit_id']) && !empty($_POST['edit_id']);
    $imagePath = null;

    if ($cost_price > $price) {
        $_SESSION['error'] = "Harga modal tidak boleh lebih besar dari harga jual.";
        header('Location: products.php' . ($isEdit ? "?edit={$_POST['edit_id']}" : ''));
        exit;
    }

    // Proses upload file
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['image_file']['tmp_name'];
        $fileName = basename($_FILES['image_file']['name']);
        $fileSize = $_FILES['image_file']['size'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($fileExt, $allowed)) {
            $_SESSION['error'] = "Format file tidak didukung. Gunakan JPG, PNG, GIF, WEBP.";
            header('Location: products.php' . ($isEdit ? "?edit={$_POST['edit_id']}" : ''));
            exit;
        }
        if ($fileSize > 2 * 1024 * 1024) {
            $_SESSION['error'] = "Ukuran file maksimal 2MB.";
            header('Location: products.php' . ($isEdit ? "?edit={$_POST['edit_id']}" : ''));
            exit;
        }

        $newName = time() . '_' . uniqid() . '.' . $fileExt;
        $destination = $uploadDir . $newName;
        if (move_uploaded_file($fileTmp, $destination)) {
            $imagePath = 'uploads/' . $newName;
            if ($isEdit) {
                $oldId = (int)$_POST['edit_id'];
                $stmtOld = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
                $stmtOld->execute([$oldId]);
                $oldProd = $stmtOld->fetch(PDO::FETCH_ASSOC);
                if ($oldProd && $oldProd['image_url'] && strpos($oldProd['image_url'], 'uploads/') === 0) {
                    deleteImage(basename($oldProd['image_url']));
                }
            }
        } else {
            $_SESSION['error'] = "Gagal mengupload file.";
            header('Location: products.php' . ($isEdit ? "?edit={$_POST['edit_id']}" : ''));
            exit;
        }
    } else {
        if ($isEdit) {
            $oldId = (int)$_POST['edit_id'];
            $stmtOld = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
            $stmtOld->execute([$oldId]);
            $oldProd = $stmtOld->fetch(PDO::FETCH_ASSOC);
            $imagePath = $oldProd['image_url'];
        } else {
            $imagePath = 'https://picsum.photos/300/200';
        }
    }

    if ($isEdit) {
        $id = (int)$_POST['edit_id'];
        $stmt = $pdo->prepare("UPDATE products SET name=?, description=?, price=?, cost_price=?, stock=?, image_url=? WHERE id=?");
        $stmt->execute([$name, $desc, $price, $cost_price, $stock, $imagePath, $id]);
        $_SESSION['message'] = "Produk berhasil diupdate.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, cost_price, stock, image_url) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$name, $desc, $price, $cost_price, $stock, $imagePath]);
        $_SESSION['message'] = "Produk berhasil ditambahkan.";
    }
    header('Location: products.php');
    exit;
}

$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Berkah Fashion Admin</title>
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
        h3 { font-size: 20px; font-weight: 600; color: #1e40af; margin: 30px 0 20px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 6px; color: #1e2a3a; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 20px; font-size: 14px; }
        .form-group textarea { border-radius: 16px; }
        .form-group small { display: block; margin-top: 4px; color: #64748b; font-size: 12px; }
        
        .btn { background: #1e40af; color: white; border: none; padding: 8px 20px; border-radius: 40px; cursor: pointer; font-size: 14px; font-weight: 500; transition: 0.2s; text-decoration: none; display: inline-block; }
        .btn:hover { background: #1e3a8a; transform: translateY(-1px); }
        .btn-warning { background: #eab308; color: #1e2a3a; }
        .btn-warning:hover { background: #ca8a04; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-small { padding: 4px 10px; font-size: 12px; }
        
        .alert { padding: 12px 18px; border-radius: 24px; font-size: 13px; margin-bottom: 24px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        
        .preview-img { max-width: 100px; max-height: 100px; margin-top: 10px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .product-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 10px; }
        
        .table-wrapper { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow-x: auto; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background: #f8fafc; font-weight: 600; color: #1e40af; }
        tr:hover td { background: #f8fafc; }
        
        @media (max-width: 640px) {
            .navbar { flex-direction: column; align-items: flex-start; }
            .navbar .nav-links a { margin: 0 15px 0 0; }
            th, td { padding: 8px 10px; }
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
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <h2><?= $edit ? 'Edit Produk' : 'Tambah Produk Baru' ?></h2>
    <form method="POST" enctype="multipart/form-data" style="background: white; padding: 24px; border-radius: 20px; border: 1px solid #e2e8f0;">
        <?php if ($edit): ?>
            <input type="hidden" name="edit_id" value="<?= $edit['id'] ?>">
        <?php endif; ?>
        <div class="form-group">
            <label>Nama Produk</label>
            <input type="text" name="name" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Deskripsi</label>
            <textarea name="description" rows="4"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>Harga Jual (Rp)</label>
            <input type="number" step="1000" name="price" value="<?= $edit['price'] ?? '' ?>" required>
        </div>
        <div class="form-group">
            <label>Harga Modal / HPP (Rp)</label>
            <input type="number" step="1000" name="cost_price" value="<?= $edit['cost_price'] ?? '' ?>" required>
            <small>Harga beli / modal produk. Tidak boleh lebih besar dari harga jual.</small>
        </div>
        <div class="form-group">
            <label>Stok</label>
            <input type="number" name="stock" value="<?= $edit['stock'] ?? '' ?>" required>
        </div>
        <div class="form-group">
            <label>Gambar Produk</label>
            <input type="file" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp">
            <small>Maksimal 2MB. Format: JPG, PNG, GIF, WEBP.</small>
            <?php if ($edit && $edit['image_url'] && strpos($edit['image_url'], 'uploads/') === 0): ?>
                <br><img src="../<?= $edit['image_url'] ?>" class="preview-img"><br>
                <small>Gambar saat ini. Upload baru untuk mengganti.</small>
            <?php elseif ($edit && $edit['image_url']): ?>
                <br><img src="<?= $edit['image_url'] ?>" class="preview-img"><br>
                <small>Gambar dari URL (tidak bisa dihapus).</small>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn"><?= $edit ? 'Update Produk' : 'Simpan Produk' ?></button>
        <?php if ($edit): ?>
            <a href="products.php" class="btn" style="background:#64748b;">Batal</a>
        <?php endif; ?>
    </form>

    <h3>Daftar Produk</h3>
    <?php if (count($products) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>ID</th><th>Gambar</th><th>Nama</th><th>Harga Jual</th><th>Harga Modal</th><th>Stok</th><th>Laba per Item</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): 
                        $profit = $p['price'] - $p['cost_price'];
                    ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td>
                            <?php if ($p['image_url'] && strpos($p['image_url'], 'uploads/') === 0): ?>
                                <img src="../<?= $p['image_url'] ?>" class="product-thumb">
                            <?php elseif ($p['image_url']): ?>
                                <img src="<?= $p['image_url'] ?>" class="product-thumb">
                            <?php else: ?>
                                <img src="https://picsum.photos/50/50" class="product-thumb">
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td>Rp <?= number_format($p['price'], 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($p['cost_price'], 0, ',', '.') ?></td>
                        <td><?= $p['stock'] ?></td>
                        <td><span style="color:#1e40af; font-weight:500;">Rp <?= number_format($profit, 0, ',', '.') ?></span></td>
                        <td>
                            <a href="?edit=<?= $p['id'] ?>" class="btn btn-warning btn-small">Edit</a>
                            <a href="?delete=<?= $p['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm('Yakin hapus produk ini?')">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert" style="background:#f8fafc; color:#475569;">Belum ada produk. Silakan tambah produk di atas.</div>
    <?php endif; ?>
</div>
</body>
</html>