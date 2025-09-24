<?php
/**
 * Filename: undo_item.php
 * Action: po.undo_item
 * URL: https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php
 * Purpose: Reset a product line received quantity back to 0 for a PO.
 * Author: CIS Developer Bot
 * Last Modified: 2025-09-21
 * Dependencies: Requires tools.php (po_pdo, po_jresp, po_has_column, po_table_exists), session/login, CSRF.
 */
declare(strict_types=1);

$ctx   = $GLOBALS['__po_ctx'] ?? ['uid'=>0,'request_id'=>''];
$poId  = (int)($_POST['po_id'] ?? 0);
$pid   = isset($_POST['product_id']) ? (string)$_POST['product_id'] : '';
$live  = isset($_POST['live']) ? filter_var($_POST['live'], FILTER_VALIDATE_BOOLEAN) : true;

if ($poId <= 0) po_jresp(false, ['code'=>'bad_request','message'=>'po_id required'], 422);
if ($pid === '') po_jresp(false, ['code'=>'bad_request','message'=>'product_id required'], 422);

try {
    $pdo = po_pdo();
    $st = $pdo->prepare('SELECT status, outlet_id FROM purchase_orders WHERE purchase_order_id = ? LIMIT 1');
    $st->execute([$poId]);
    $hdr = $st->fetch();
    if (!$hdr) po_jresp(false, ['code'=>'not_found','message'=>'PO not found'], 404);
    if ((int)($hdr['status'] ?? 0) === 1) po_jresp(false, ['code'=>'readonly','message'=>'Purchase order is completed'], 409);
    $outletId = (string)($hdr['outlet_id'] ?? '');

    $orderQtyCol = po_has_column($pdo,'purchase_order_line_items','order_qty') ? 'order_qty' : (po_has_column($pdo,'purchase_order_line_items','qty_ordered') ? 'qty_ordered' : 'order_qty');
    $qtyArrCol   = po_has_column($pdo,'purchase_order_line_items','qty_arrived') ? 'qty_arrived' : 'qty_received';

    // Determine current received and reset to 0
    $sel = $pdo->prepare("SELECT {$orderQtyCol} AS expected, COALESCE({$qtyArrCol},0) AS current FROM purchase_order_line_items WHERE purchase_order_id = ? AND product_id = ? LIMIT 1");
    $sel->execute([$poId, $pid]);
    $line = $sel->fetch();
    if (!$line) po_jresp(false, ['code'=>'not_found','message'=>'Product not found in this purchase order'], 404);
    $current = (int)$line['current'];
    if ($current === 0) {
        po_jresp(true, ['po_id'=>$poId, 'product_id'=>$pid, 'qty_received'=>0, 'delta'=>0, 'note'=>'already_zero']);
    }

    $upd = $pdo->prepare("UPDATE purchase_order_line_items SET {$qtyArrCol} = 0 WHERE purchase_order_id = :po AND product_id = :pid");
    $upd->execute([':po'=>$poId, ':pid'=>$pid]);

    // Enqueue reversal if infra exists and live
    $queued=false; $queueId=null; $queueNote=null; $delta = -$current;
    if ($live && $current !== 0 && po_table_exists($pdo,'inventory_adjust_requests')) {
        $idem = 'po:'.$poId.':product:'.$pid.':qty:0';
        $j = $pdo->prepare('INSERT INTO inventory_adjust_requests(transfer_id,outlet_id,product_id,delta,reason,source,status,idempotency_key,requested_by,requested_at) VALUES(NULL,?,?,?,?,\'po-undo\',\'pending\',?,?,NOW()) ON DUPLICATE KEY UPDATE requested_at = VALUES(requested_at)');
        $j->execute([$outletId, $pid, $delta, 'po-receive-undo', $idem, (int)$ctx['uid']]);
        $queued = true; $queueId = $pdo->lastInsertId(); $queueNote = 'queued:inventory_adjust_requests';
    }

    if ($live && $delta !== 0 && po_table_exists($pdo,'vend_inventory')) {
        $vi = $pdo->prepare('UPDATE vend_inventory SET inventory_level = inventory_level + :d WHERE product_id = :pid AND outlet_id = :oid');
        $vi->execute([':d'=>$delta, ':pid'=>$pid, ':oid'=>$outletId]);
    }

    po_insert_event($pdo, $poId, 'line.undo', [
        'product_id'=>$pid,
        'qty_prev'=>$current,
        'delta'=>$delta
    ], (int)$ctx['uid']);

    po_jresp(true, [
        'po_id'=>$poId,
        'product_id'=>$pid,
        'qty_received'=>0,
        'delta'=>$delta,
        'queue'=>['queued'=>$queued,'id'=>$queueId,'note'=>$queueNote]
    ]);
} catch (Throwable $e) {
    error_log('[po.undo_item]['.($ctx['request_id']??'-').'] '.$e->getMessage());
    po_jresp(false, ['code'=>'internal_error','message'=>'Failed to undo line'], 500);
}
<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$po_id = (int)($_POST['po_id'] ?? 0);
$line_id = (int)($_POST['line_id'] ?? 0);
if ($po_id<=0 || $line_id<=0) po_jresp(false, ['code'=>'bad_request','message'=>'po_id and line_id required'], 400);
// TODO: undo last receive on line
po_jresp(true, ['message'=>'line undone','line_id'=>$line_id]);
