<?php
/**
 * Filename: retry_request.php
 * Action: admin.retry_request
 * URL: https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php
 * Purpose: Set a failed/pending request back to pending to be retried by worker.
 */
declare(strict_types=1);

$id = (int)($_POST['request_id'] ?? 0);
if ($id <= 0) po_jresp(false, ['code'=>'bad_request','message'=>'request_id required'], 422);

try {
    $pdo = po_pdo();
    if (!po_table_exists($pdo,'inventory_adjust_requests')) po_jresp(false, ['code'=>'not_found','message'=>'Queue table missing'], 404);
    $st = $pdo->prepare("UPDATE inventory_adjust_requests SET status='pending', error_msg=NULL, processed_at=NULL WHERE request_id=?");
    $st->execute([$id]);
    po_insert_event($pdo, 0, 'queue.retry', ['request_id'=>$id], (int)($_SESSION['userID'] ?? 0));
    po_jresp(true, ['request_id'=>$id, 'status'=>'pending']);
} catch (Throwable $e) {
    error_log('[admin.retry_request] '.$e->getMessage());
    po_jresp(false, ['code'=>'internal_error','message'=>'Failed to retry request'], 500);
}
