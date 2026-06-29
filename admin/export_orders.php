<?php
require_once __DIR__ . '/../config.php';
if (!isAdmin()) exit('Akses ditolak');

// Ambil semua pesanan online
$stmt = $pdo->query("
    SELECT o.id, u.username, o.order_date, o.total_amount, o.status, 
           o.shipping_method, o.payment_method, o.address, o.phone
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.user_id IS NOT NULL AND o.user_id != 0 
    ORDER BY o.order_date DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Header untuk download file Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="laporan_pesanan_'.date('Y-m-d').'.xls"');

// Tampilkan tabel
echo '<table border="1">';
echo '<tr>
        <th>ID Pesanan</th>
        <th>Pelanggan</th>
        <th>Tanggal</th>
        <th>Total (Rp)</th>
        <th>Status</th>
        <th>Pengiriman</th>
        <th>Pembayaran</th>
        <th>Alamat</th>
        <th>No. HP</th>
      </tr>';

foreach ($orders as $o) {
    echo '<tr>';
    echo '<td>' . $o['id'] . '</td>';
    echo '<td>' . htmlspecialchars($o['username']) . '</td>';
    echo '<td>' . $o['order_date'] . '</td>';
    echo '<td>' . number_format($o['total_amount'], 0, ',', '.') . '</td>';
    echo '<td>' . $o['status'] . '</td>';
    echo '<td>' . htmlspecialchars($o['shipping_method'] ?? '-') . '</td>';
    echo '<td>' . htmlspecialchars($o['payment_method'] ?? '-') . '</td>';
    echo '<td>' . htmlspecialchars($o['address'] ?? '-') . '</td>';
    echo '<td>' . htmlspecialchars($o['phone'] ?? '-') . '</td>';
    echo '</tr>';
}
echo '</table>';
exit;
?>