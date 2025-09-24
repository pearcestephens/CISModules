<?php
/**
 * Filename: force_resend.php
 * Action: admin.force_resend
 * URL: https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php
 * Purpose: Clone an existing inventory request with a fresh idempotency key to force resend.
 */
declare(strict_types=1);

$id = (int)($_POST['request_id'] ?? 0);
if ($id <= 0) po_jresp(false, ['code'=>'bad_request','message'=>'request_id required'], 422);

try {
    $pdo = po_pdo();
    if (!po_table_exists($pdo,'inventory_adjust_requests')) po_jresp(false, ['code'=>'not_found','message'=>'Queue table missing'], 404);
    $sel = $pdo->prepare('SELECT * FROM inventory_adjust_requests WHERE request_id = ?');
    $sel->execute([$id]);
    $r = $sel->fetch();
    if (!$r) po_jresp(false, ['code'=>'not_found','message'=>'Request not found'], 404);
    $idem = $r['idempotency_key'].'#'.bin2hex(random_bytes(4));
    $ins = $pdo->prepare("INSERT INTO inventory_adjust_requests(transfer_id,outlet_id,product_id,delta,reason,source,status,idempotency_key,requested_by,requested_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())");
    $ins->execute([
        $r['transfer_id'], $r['outlet_id'], $r['product_id'], $r['delta'], $r['reason'], $r['source'], 'pending', $idem, (int)($_SESSION['userID'] ?? 0)
    ]);
    $newId = (int)$pdo->lastInsertId();
    po_insert_event($pdo, 0, 'queue.force_resend', ['from'=>$id, 'to'=>$newId], (int)($_SESSION['userID'] ?? 0));
    po_jresp(true, ['from'=>$id, 'to'=>$newId]);
} catch (Throwable $e) {
    error_log('[admin.force_resend] '.$e->getMessage());
    po_jresp(false, ['code'=>'internal_error','message'=>'Failed to force resend'], 500);
}
