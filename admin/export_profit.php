<?php
require_once __DIR__ . '/../config.php';
if (!isAdmin()) exit('Akses ditolak');

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$stmt = $pdo->prepare("
    SELECT 
        o.id as order_id,
        o.order_date,
        o.total_amount as total_penjualan,
        SUM(oi.quantity * oi.cost_price) as total_hpp,
        SUM(oi.quantity * (oi.price - oi.cost_price)) as laba
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE o.status IN ('delivered', 'processed')
    AND o.order_date BETWEEN ? AND ?
    GROUP BY o.id
    ORDER BY o.order_date DESC
");
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$orders = $stmt->fetchAll();

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="laporan_laba_'.date('Y-m-d').'.xls"');

echo '<table border="1">';
echo '<tr><th>ID Pesanan</th><th>Tanggal</th><th>Total Penjualan</th><th>Total HPP</th><th>Laba</th><th>Margin (%)</th></tr>';
foreach($orders as $o) {
    $margin = $o['total_penjualan'] > 0 ? round(($o['laba']/$o['total_penjualan'])*100,2) : 0;
    echo '<tr>';
    echo '<td>'.$o['order_id'].'</td>';
    echo '<td>'.$o['order_date'].'</td>';
    echo '<td>'.number_format($o['total_penjualan'],0,',','.').'</td>';
    echo '<td>'.number_format($o['total_hpp'],0,',','.').'</td>';
    echo '<td>'.number_format($o['laba'],0,',','.').'</td>';
    echo '<td>'.$margin.'%</td>';
    echo '</tr>';
}
echo '</table>';
exit;