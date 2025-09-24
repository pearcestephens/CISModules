<?php
/**
 * Filename: list_evidence.php
 * Action: po.list_evidence
 * URL: https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php
 * Purpose: List evidence files associated with a PO.
 */
declare(strict_types=1);

$poId = (int)($_POST['po_id'] ?? 0);
if ($poId <= 0) po_jresp(false, ['code'=>'bad_request','message'=>'po_id required'], 422);

try {
	$pdo = po_pdo();
	if (!po_table_exists($pdo,'po_evidence')) po_jresp(true, ['rows'=>[]]);
	$q = $pdo->prepare('SELECT id, purchase_order_id, evidence_type, file_path, description, uploaded_by, uploaded_at FROM po_evidence WHERE purchase_order_id = ? ORDER BY id DESC');
	$q->execute([$poId]);
	$rows = $q->fetchAll();
	po_jresp(true, ['rows'=>$rows]);
} catch (Throwable $e) {
	error_log('[po.list_evidence] '.$e->getMessage());
	po_jresp(false, ['code'=>'internal_error','message'=>'Failed to list evidence'], 500);
}

<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$po_id = (int)($_POST['po_id'] ?? 0);
if ($po_id<=0) po_jresp(false, ['code'=>'invalid_po_id','message'=>'po_id required'], 400);
// TODO: fetch evidence list
po_jresp(true, ['items'=>[]]);
