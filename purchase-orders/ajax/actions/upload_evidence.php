<?php
/**
 * Filename: upload_evidence.php
 * Action: po.upload_evidence
 * URL: https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php
 * Purpose: Handle evidence uploads for a PO; stores metadata in po_evidence. Assumes server-side upload dir security is already enforced.
 */
declare(strict_types=1);

$ctx   = $GLOBALS['__po_ctx'] ?? ['uid'=>0,'request_id'=>''];
$poId  = (int)($_POST['po_id'] ?? 0);
$etype = isset($_POST['evidence_type']) ? (string)$_POST['evidence_type'] : 'delivery';
$desc  = isset($_POST['description']) ? (string)$_POST['description'] : null;

if ($poId <= 0) po_jresp(false, ['code'=>'bad_request','message'=>'po_id required'], 422);
if (!isset($_FILES['file'])) po_jresp(false, ['code'=>'bad_request','message'=>'file required'], 422);

try {
    $pdo = po_pdo();
    if (!po_table_exists($pdo,'po_evidence')) po_jresp(false, ['code'=>'not_supported','message'=>'po_evidence table missing'], 400);

    // Basic upload handling — save to /uploads/po_evidence/YYYY/MM/ with unique name
    $base = $_SERVER['DOCUMENT_ROOT'] . '/uploads/po_evidence';
    $sub  = date('Y/m');
    $dir  = $base . '/' . $sub;
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $fname = 'po'.$poId.'_'.bin2hex(random_bytes(8));
    $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $safeExt = preg_replace('/[^a-zA-Z0-9]/','', $ext);
    $dest = $dir . '/' . $fname . ($safeExt ? ('.'.$safeExt) : '');

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
        po_jresp(false, ['code'=>'upload_failed','message'=>'Failed to move uploaded file'], 500);
    }

    $relPath = '/uploads/po_evidence/' . $sub . '/' . basename($dest);
    $ins = $pdo->prepare('INSERT INTO po_evidence (purchase_order_id, evidence_type, file_path, description, uploaded_by, uploaded_at) VALUES (?,?,?,?,?, NOW())');
    $ins->execute([$poId, $etype, $relPath, $desc, (int)$ctx['uid']]);

    po_insert_event($pdo, $poId, 'evidence.upload', [
        'path'=>$relPath,
        'type'=>$etype
    ], (int)$ctx['uid']);

    po_jresp(true, ['file'=>$relPath]);
} catch (Throwable $e) {
    error_log('[po.upload_evidence] '.$e->getMessage());
    po_jresp(false, ['code'=>'internal_error','message'=>'Failed to upload evidence'], 500);
}
<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$po_id = (int)($_POST['po_id'] ?? 0);
if ($po_id<=0) po_jresp(false, ['code'=>'invalid_po_id','message'=>'po_id required'], 400);
// TODO: handle file upload via $_FILES and persist
po_jresp(true, ['message'=>'upload accepted']);
