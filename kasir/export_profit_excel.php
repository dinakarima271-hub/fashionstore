<?php
require_once '../config.php';

if (!isKasir() && !isAdmin()) {
    exit('Akses ditolak');
}

$start_date = isset($_GET['start_date']) && $_GET['start_date'] ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) && $_GET['end_date'] ? $_GET['end_date'] : date('Y-m-t');
$kasir_id   = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT 
        o.id as order_id,
        o.order_date,
        o.total_amount as total_penjualan,
        COALESCE(SUM(oi.quantity * oi.cost_price), 0) as total_hpp,
        COALESCE(SUM(oi.quantity * (oi.price - oi.cost_price)), 0) as laba
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.kasir_id = :kasir_id 
        AND o.status = 'delivered'
        AND o.order_date BETWEEN :start AND :end
    GROUP BY o.id
    ORDER BY o.order_date DESC
");
$stmt->execute([
    ':kasir_id' => $kasir_id,
    ':start'    => $start_date . ' 00:00:00',
    ':end'      => $end_date . ' 23:59:59'
]);
$orders = $stmt->fetchAll();

$total_penjualan = array_sum(array_column($orders, 'total_penjualan'));
$total_hpp       = array_sum(array_column($orders, 'total_hpp'));
$total_laba      = array_sum(array_column($orders, 'laba'));

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="laporan_laba_kasir_' . date('Y-m-d') . '.xls"');

echo '<table border="1">';
echo '<caption style="font-size:16px; font-weight:bold; margin-bottom:10px;">Laporan Laba Kasir</caption>';
echo '<tr><th>ID Pesanan</th><th>Tanggal</th><th>Total Penjualan</th><th>Total HPP</th><th>Laba</th><th>Margin (%)</th></tr>';

foreach ($orders as $o) {
    $margin = ($o['total_penjualan'] > 0) ? round(($o['laba'] / $o['total_penjualan']) * 100, 2) : 0;
    echo '<tr>';
    echo '<td>' . $o['order_id'] . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($o['order_date'])) . '</td>';
    echo '<td>Rp ' . number_format($o['total_penjualan'], 0, ',', '.') . '</td>';
    echo '<td>Rp ' . number_format($o['total_hpp'], 0, ',', '.') . '</td>';
    echo '<td>Rp ' . number_format($o['laba'], 0, ',', '.') . '</td>';
    echo '<td>' . $margin . '%</td>';
    echo '</tr>';
}
echo '<tr style="background:#f0f0f0; font-weight:bold;">';
echo '<td colspan="2">Total</td>';
echo '<td>Rp ' . number_format($total_penjualan, 0, ',', '.') . '</td>';
echo '<td>Rp ' . number_format($total_hpp, 0, ',', '.') . '</td>';
echo '<td>Rp ' . number_format($total_laba, 0, ',', '.') . '</td>';
echo '<td></td>';
echo '</tr>';
echo '</table>';
exit;
?>