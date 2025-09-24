<?php
/**
 * Filename: list_receipts.php
 * Action: admin.list_receipts
 * URL: https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php
 * Purpose: Admin listing of PO receipts with pagination and filters.
 */
declare(strict_types=1);

$poId = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
$page = max(1, (int)($_POST['page'] ?? 1));
$size = min(200, max(10, (int)($_POST['size'] ?? 50)));
$off  = ($page - 1) * $size;

try {
    $pdo = po_pdo();
    if (!po_table_exists($pdo,'po_receipts')) po_jresp(true, ['rows'=>[], 'total'=>0, 'page'=>$page, 'size'=>$size]);
    $where = '';
    $args = [];
    if ($poId > 0) { $where = 'WHERE r.purchase_order_id = ?'; $args[] = $poId; }
    $sql = "SELECT r.receipt_id, r.purchase_order_id, r.outlet_id, r.is_final, r.created_by, r.created_at,
                   COUNT(ri.id) AS items
            FROM po_receipts r
            LEFT JOIN po_receipt_items ri ON ri.receipt_id = r.receipt_id
            $where
            GROUP BY r.receipt_id
            ORDER BY r.receipt_id DESC
            LIMIT $size OFFSET $off";
    $rows = $pdo->prepare($sql); $rows->execute($args);
    $data = $rows->fetchAll();
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM po_receipts r " . ($where ?: ''));
    $cnt->execute($args);
    $total = (int)$cnt->fetchColumn();
    po_jresp(true, ['rows'=>$data, 'total'=>$total, 'page'=>$page, 'size'=>$size]);
} catch (Throwable $e) {
    error_log('[admin.list_receipts] '.$e->getMessage());
    po_jresp(false, ['code'=>'internal_error','message'=>'Failed to list receipts'], 500);
}
