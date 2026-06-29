<?php
require_once 'config.php';

$id = (int)$_GET['id'];
if ($id <= 0) redirect('index.php');

$stmt = $pdo->prepare("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) redirect('index.php');

$isAdmin = isAdmin();
if (!$isAdmin && ($order['user_id'] != $_SESSION['user_id'])) redirect('index.php');

$stmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= $order['id'] ?> - Berkah Fashion</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Roboto', sans-serif;
            background: #eef2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 24px;
        }

        /* Kartu invoice */
        .invoice-card {
            max-width: 540px;
            width: 100%;
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: all 0.2s;
        }

        /* Header hijau toko (soft) */
        .invoice-header {
            background: #2c6e4f;
            color: white;
            text-align: center;
            padding: 24px 20px;
        }

        .invoice-header h2 {
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .invoice-header p {
            font-size: 11px;
            opacity: 0.85;
            margin-top: 4px;
        }

        /* Body invoice */
        .invoice-body {
            padding: 24px 26px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #e2e8f0;
            padding: 8px 0;
            flex-wrap: wrap;
        }

        .info-label {
            font-size: 11px;
            color: #5b6e8c;
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .info-value {
            font-weight: 600;
            font-size: 14px;
            color: #2c3e50;
        }

        /* Tabel item */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0 16px;
            font-size: 13px;
        }

        .items-table th,
        .items-table td {
            padding: 10px 0;
            text-align: left;
            border-bottom: 1px dotted #e2e8f0;
        }

        .items-table th {
            border-bottom: 1px solid #cbd5e1;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
        }

        .text-right {
            text-align: right;
        }

        /* Total */
        .total-row {
            display: flex;
            justify-content: flex-end;
            border-top: 2px solid #2c6e4f;
            margin-top: 12px;
            padding-top: 12px;
            font-weight: 700;
            font-size: 18px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .total-label {
            color: #2c6e4f;
        }

        .total-amount {
            color: #2c6e4f;
        }

        /* Footer */
        .invoice-footer {
            background: #f8fafc;
            padding: 14px 20px;
            text-align: center;
            font-size: 10px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }

        /* Tombol cetak */
        .print-button {
            display: block;
            background: #2c6e4f;
            color: white;
            text-align: center;
            text-decoration: none;
            padding: 12px;
            margin: 16px 26px 26px;
            border-radius: 60px;
            font-weight: 500;
            font-size: 14px;
            transition: 0.2s;
        }

        .print-button:hover {
            background: #1f5a41;
        }

        /* Gaya untuk cetak */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .invoice-card {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }
            .print-button {
                display: none;
            }
            .invoice-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .info-row, .items-table th, .items-table td, .total-row {
                break-inside: avoid;
            }
        }

        /* Responsive */
        @media (max-width: 500px) {
            .invoice-body {
                padding: 20px;
            }
            .info-row {
                flex-direction: column;
                gap: 4px;
            }
            .total-row {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
<div class="invoice-card">
    <div class="invoice-header">
        <h2>BERKAH FASHION STORE</h2>
        <p>Jl. Salakbrojo No.23, Pekalongan | Telp. 0812-3456-7890</p>
    </div>

    <div class="invoice-body">
        <div class="info-row">
            <span class="info-label">INVOICE <?= $order['id'] ?></span>
            <span class="info-value"><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></span>
        </div>

        <?php if ($order['user_id'] == 0 || $order['user_id'] == null): ?>
            <div class="info-row">
                <span class="info-label">Transaksi</span>
                <span class="info-value">Pembelian Langsung (Toko)</span>
            </div>
        <?php else: ?>
            <div class="info-row">
                <span class="info-label">Pelanggan</span>
                <span class="info-value"><?= htmlspecialchars($order['username'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Alamat</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($order['address'] ?? '-')) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">No. HP</span>
                <span class="info-value"><?= htmlspecialchars($order['phone'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value"><?= statusIndonesia($order['status']) ?></span>
            </div>
        <?php endif; ?>

        <table class="items-table">
            <thead>
                <tr><th>Produk</th><th class="text-right">Harga</th><th class="text-right">Jml</th><th class="text-right">Subtotal</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td class="text-right">Rp <?= number_format($item['price'],0,',','.') ?></td>
                    <td class="text-right"><?= $item['quantity'] ?></td>
                    <td class="text-right">Rp <?= number_format($item['price'] * $item['quantity'],0,',','.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-row">
            <span class="total-label">Total</span>
            <span class="total-amount">Rp <?= number_format($order['total_amount'],0,',','.') ?></span>
        </div>

        <?php if ($order['user_id'] == 0 || $order['user_id'] == null): ?>
            <div class="info-row">
                <span class="info-label">Metode Bayar</span>
                <span class="info-value">Tunai</span>
            </div>
        <?php else: ?>
            <div class="info-row">
                <span class="info-label">Pengiriman</span>
                <span class="info-value"><?= htmlspecialchars($order['shipping_method'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Pembayaran</span>
                <span class="info-value"><?= htmlspecialchars($order['payment_method'] ?? '-') ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($order['notes']) && $order['user_id'] != 0 && $order['user_id'] != null): ?>
            <div class="info-row">
                <span class="info-label">Catatan</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($order['notes'])) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="invoice-footer">
        Terima kasih telah berbelanja di Berkah Fashion Store.<br>
        Simpan invoice ini sebagai bukti transaksi.
    </div>

    <a href="#" class="print-button" onclick="window.print();return false;">🖨️ Cetak / Simpan PDF</a>
</div>
</body>
</html>