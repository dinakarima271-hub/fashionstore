<?php
require_once 'config.php';

// Ambil semua produk
$stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berkah Fashion - Beranda</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f0f4f8;
            color: #1e2a3a;
        }

        /* Navbar biru - SAMA dengan halaman lain */
        .navbar {
            background: #1e40af;
            color: white;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-bottom: none;
        }

        .navbar .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 500;
        }

        .navbar .logo svg {
            width: 28px;
            height: 28px;
            stroke: white;
            stroke-width: 1.6;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            font-size: 14px;
            transition: opacity 0.2s;
        }

        .navbar a:hover {
            opacity: 0.8;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        h1 {
            font-size: 28px;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 24px;
        }

        /* Grid produk */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
        }

        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            border: 1px solid #e2e8f0;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.05);
        }

        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f8fafc;
        }

        .product-info {
            padding: 20px;
        }

        .product-info h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1e2a3a;
            margin-bottom: 8px;
        }

        .product-info p {
            font-size: 13px;
            color: #475569;
            line-height: 1.4;
            margin-bottom: 12px;
        }

        .price {
            font-size: 20px;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 8px;
        }

        .stock {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 16px;
        }

        .btn {
            display: inline-block;
            background: #1e40af;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn:hover {
            background: #1e3a8a;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: #eab308;
            color: #1e2a3a;
        }

        .btn-warning:hover {
            background: #ca8a04;
        }

        .product-info form {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .product-info input[type="number"] {
            width: 70px;
            padding: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 30px;
            text-align: center;
        }

        @media (max-width: 640px) {
            .navbar {
                flex-direction: column;
                align-items: flex-start;
            }
            .navbar a {
                margin-left: 0;
                margin-right: 16px;
            }
            h1 {
                font-size: 24px;
            }
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
        <?php if(isLoggedIn()): ?>
            <a href="cart.php">Keranjang</a>
            <a href="orders.php">Pesanan</a>
            <?php if(isAdmin()): ?>
                <a href="admin/">Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">
    <h1>Katalog Fashion Terbaru</h1>
    <div class="products-grid">
        <?php foreach($products as $product): ?>
            <div class="product-card">
                <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                <div class="product-info">
                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                    <p><?= substr(htmlspecialchars($product['description']), 0, 80) ?>...</p>
                    <div class="price">Rp <?= number_format($product['price'], 0, ',', '.') ?></div>
                    <div class="stock">Stok: <?= $product['stock'] ?></div>
                    <?php if(isLoggedIn() && !isAdmin()): ?>
                        <form method="POST" action="cart.php?action=add">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock'] ?>">
                            <button type="submit" class="btn">Tambah ke Keranjang</button>
                        </form>
                    <?php elseif(!isLoggedIn()): ?>
                        <a href="login.php" class="btn">Login untuk beli</a>
                    <?php elseif(isAdmin()): ?>
                        <a href="admin/products.php?edit=<?= $product['id'] ?>" class="btn btn-warning">Edit</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>