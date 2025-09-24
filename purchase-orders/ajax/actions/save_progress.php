<?php
/**
 * Filename: save_progress.php
 * Action: po.save_progress
 * URL: https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php
 * Purpose: Update a single PO line's received quantity (qty_arrived) idempotently and enqueue inventory adjustments (optional).
 * Author: CIS Developer Bot
 * Last Modified: 2025-09-21
 * Dependencies: Requires tools.php (po_pdo, po_jresp, po_has_column, po_table_exists), session/login, CSRF.
 */
declare(strict_types=1);

// tools.php already loaded by handler; provides po_pdo(), po_jresp()

$ctx   = $GLOBALS['__po_ctx'] ?? ['uid'=>0,'request_id'=>''];
$poId  = (int)($_POST['po_id'] ?? 0);
$pid   = isset($_POST['product_id']) ? (string)$_POST['product_id'] : '';
$qty   = isset($_POST['qty_received']) ? (int)$_POST['qty_received'] : null; // required for line-level save
$live  = isset($_POST['live']) ? filter_var($_POST['live'], FILTER_VALIDATE_BOOLEAN) : true; // default live queue

if ($poId <= 0) po_jresp(false, ['code'=>'bad_request','message'=>'po_id required'], 422);
if ($pid === '') po_jresp(false, ['code'=>'bad_request','message'=>'product_id required'], 422);
if ($qty === null || $qty < 0) po_jresp(false, ['code'=>'bad_request','message'=>'qty_received must be >= 0'], 422);

try {
    $pdo = po_pdo();
    // Reject edits on completed PO
    $st = $pdo->prepare('SELECT status, outlet_id FROM purchase_orders WHERE purchase_order_id = ? LIMIT 1');
    $st->execute([$poId]);
    $hdr = $st->fetch();
    if (!$hdr) po_jresp(false, ['code'=>'not_found','message'=>'PO not found'], 404);
    if ((int)($hdr['status'] ?? 0) === 1) po_jresp(false, ['code'=>'readonly','message'=>'Purchase order is completed'], 409);
    $outletId = (string)($hdr['outlet_id'] ?? '');

    // Detect schema
    $orderQtyCol = po_has_column($pdo,'purchase_order_line_items','order_qty') ? 'order_qty' : (po_has_column($pdo,'purchase_order_line_items','qty_ordered') ? 'qty_ordered' : 'order_qty');
    $qtyArrCol   = po_has_column($pdo,'purchase_order_line_items','qty_arrived') ? 'qty_arrived' : 'qty_received';
    $recvAtCol   = po_has_column($pdo,'purchase_order_line_items','received_at') ? 'received_at' : null;

    // Fetch current line
    $sel = $pdo->prepare("SELECT {$orderQtyCol} AS expected, COALESCE({$qtyArrCol},0) AS current FROM purchase_order_line_items WHERE purchase_order_id = ? AND product_id = ? LIMIT 1");
    $sel->execute([$poId, $pid]);
    $line = $sel->fetch();
    if (!$line) po_jresp(false, ['code'=>'not_found','message'=>'Product not found in this purchase order'], 404);

    $expected = (int)$line['expected'];
    $current  = (int)$line['current'];
    $newQty   = $qty;
    if ($newQty > $expected) { $newQty = $expected; $capped = true; } else { $capped = false; }
    $delta = $newQty - $current; // positive adds stock; negative removes

    // Update line
    $set = "{$qtyArrCol} = :q" . ($recvAtCol ? ", {$recvAtCol} = NOW()" : '');
    $upd = $pdo->prepare("UPDATE purchase_order_line_items SET {$set} WHERE purchase_order_id = :po AND product_id = :pid");
    $upd->execute([':q'=>$newQty, ':po'=>$poId, ':pid'=>$pid]);

    // Optional: enqueue inventory adjustment if infrastructure exists and live==true and delta!=0
    $queued = false; $queueId = null; $queueNote = null;
    if ($delta !== 0 && $live && po_table_exists($pdo,'inventory_adjust_requests')) {
        // Create idempotency key to avoid dupes for same (po, product, newQty)
        $idem = 'po:'.$poId.':product:'.$pid.':qty:'.$newQty;
        $j = $pdo->prepare('INSERT INTO inventory_adjust_requests(transfer_id,outlet_id,product_id,delta,reason,source,status,idempotency_key,requested_by,requested_at) VALUES(NULL,?,?,?,?,\'purchase-order\',\'pending\',?,?,NOW()) ON DUPLICATE KEY UPDATE requested_at = VALUES(requested_at)');
        $j->execute([$outletId, $pid, $delta, ($delta>=0?'po-receive':'po-correction'), $idem, (int)$ctx['uid']]);
        $queued = true; $queueId = $pdo->lastInsertId(); $queueNote = 'queued:inventory_adjust_requests';
    }

    // Try to update local vend_inventory mirror if present and live
    if ($delta !== 0 && $live && po_table_exists($pdo,'vend_inventory')) {
        $vi = $pdo->prepare('UPDATE vend_inventory SET inventory_level = inventory_level + :d WHERE product_id = :pid AND outlet_id = :oid');
        $vi->execute([':d'=>$delta, ':pid'=>$pid, ':oid'=>$outletId]);
    }

    // Event log
    po_insert_event($pdo, $poId, 'line.update', [
        'product_id'=>$pid,
        'qty_new'=>$newQty,
        'qty_prev'=>$current,
        'delta'=>$delta,
        'capped'=>$capped
    ], (int)$ctx['uid']);

    po_jresp(true, [
        'po_id'=>$poId,
        'product_id'=>$pid,
        'qty_received'=>$newQty,
        'delta'=>$delta,
        'capped'=>$capped,
        'queue'=>['queued'=>$queued, 'id'=>$queueId, 'note'=>$queueNote]
    ]);
} catch (Throwable $e) {
    error_log('[po.save_progress]['.($ctx['request_id']??'-').'] '.$e->getMessage());
    po_jresp(false, ['code'=>'internal_error','message'=>'Failed to save line'], 500);
}
<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$po_id = (int)($_POST['po_id'] ?? 0);
if ($po_id <= 0) po_jresp(false, ['code'=>'invalid_po_id','message'=>'po_id required'], 400);
// TODO: persist draft receipt progress
po_jresp(true, ['message'=>'progress saved']);
