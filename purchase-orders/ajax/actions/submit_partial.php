<?php
/**
 * Filename: submit_partial.php
 * Action: po.submit_partial
 * URL: https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php
 * Purpose: Mark PO as partially received and enqueue inventory adjustments for all received lines.
 * Author: CIS Developer Bot
 * Last Modified: 2025-09-21
 * Dependencies: Requires tools.php (po_pdo, po_jresp, po_has_column, po_table_exists), session/login, CSRF.
 */
declare(strict_types=1);

$ctx   = $GLOBALS['__po_ctx'] ?? ['uid'=>0,'request_id'=>''];
$poId  = (int)($_POST['po_id'] ?? 0);
$live  = isset($_POST['live']) ? filter_var($_POST['live'], FILTER_VALIDATE_BOOLEAN) : true;

if ($poId <= 0) po_jresp(false, ['code'=>'bad_request','message'=>'po_id required'], 422);

try {
    $pdo = po_pdo();
    $st = $pdo->prepare('SELECT status, outlet_id FROM purchase_orders WHERE purchase_order_id = ? LIMIT 1');
    $st->execute([$poId]);
    $hdr = $st->fetch();
    if (!$hdr) po_jresp(false, ['code'=>'not_found','message'=>'PO not found'], 404);
    if ((int)($hdr['status'] ?? 0) === 1) po_jresp(false, ['code'=>'readonly','message'=>'Already completed'], 409);
    $outletId = (string)($hdr['outlet_id'] ?? '');

    $orderQtyCol = po_has_column($pdo,'purchase_order_line_items','order_qty') ? 'order_qty' : (po_has_column($pdo,'purchase_order_line_items','qty_ordered') ? 'qty_ordered' : 'order_qty');
    $qtyArrCol   = po_has_column($pdo,'purchase_order_line_items','qty_arrived') ? 'qty_arrived' : 'qty_received';

    // Iterate lines and enqueue deltas where appropriate
    $q = $pdo->prepare("SELECT product_id, {$orderQtyCol} AS expected, COALESCE({$qtyArrCol},0) AS received FROM purchase_order_line_items WHERE purchase_order_id = ?");
    $q->execute([$poId]);
    $items = $q->fetchAll();

    $enqueued = 0;
    foreach ($items as $it) {
        $pid = (string)$it['product_id'];
        $recv = (int)$it['received'];
        if ($recv <= 0) continue;
        if ($live && po_table_exists($pdo,'inventory_adjust_requests')) {
            $idem = 'po:'.$poId.':product:'.$pid.':qty:'.$recv;
            $j = $pdo->prepare('INSERT INTO inventory_adjust_requests(transfer_id,outlet_id,product_id,delta,reason,source,status,idempotency_key,requested_by,requested_at) VALUES(NULL,?,?,?,?,\'po-partial\',\'pending\',?,?,NOW()) ON DUPLICATE KEY UPDATE requested_at = VALUES(requested_at)');
            $j->execute([$outletId, $pid, $recv, 'po-partial-commit', $idem, (int)$ctx['uid']]);
            $enqueued++;
        }
    }

    // Snapshot receipt (optional tables)
    $rid = po_create_receipt($pdo, $poId, $outletId, false, (int)$ctx['uid'], $items ?? []);

    // Mark header as partial (status 2) if such a column exists; else store timestamp
    if (po_has_column($pdo,'purchase_orders','status')) {
        $pdo->prepare('UPDATE purchase_orders SET status = 2, updated_at = NOW() WHERE purchase_order_id = ?')->execute([$poId]);
    } else if (po_has_column($pdo,'purchase_orders','partial_received_at')) {
        $pdo->prepare('UPDATE purchase_orders SET partial_received_at = NOW() WHERE purchase_order_id = ?')->execute([$poId]);
    }

    // Event
    po_insert_event($pdo, $poId, 'submit.partial', [
        'receipt_id'=>$rid,
        'enqueued'=>$enqueued
    ], (int)$ctx['uid']);

    po_jresp(true, [
        'po_id'=>$poId,
        'actions'=>['enqueued'=>$enqueued, 'receipt_id'=>$rid],
        'status'=>'partial'
    ]);
} catch (Throwable $e) {
    error_log('[po.submit_partial]['.($ctx['request_id']??'-').'] '.$e->getMessage());
    po_jresp(false, ['code'=>'internal_error','message'=>'Failed to submit partial'], 500);
}
<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$po_id = (int)($_POST['po_id'] ?? 0);
if ($po_id <= 0) po_jresp(false, ['code'=>'invalid_po_id','message'=>'po_id required'], 400);
// TODO: create receipt event with status PARTIAL and queue inventory deltas
po_jresp(true, ['message'=>'partial submitted']);
