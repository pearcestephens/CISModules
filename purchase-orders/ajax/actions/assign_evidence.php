<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$po_id = (int)($_POST['po_id'] ?? 0);
$evidence_id = (int)($_POST['evidence_id'] ?? 0);
if ($po_id<=0 || $evidence_id<=0) po_jresp(false, ['code'=>'bad_request','message'=>'po_id and evidence_id required'], 400);
// TODO: assign evidence to PO or line
po_jresp(true, ['message'=>'evidence assigned']);
