<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$product_id = (int)($_POST['product_id'] ?? 0);
$set = isset($_POST['stock']) ? (int)$_POST['stock'] : null;
if ($product_id<=0 || $set===null) po_jresp(false, ['code'=>'bad_request','message'=>'product_id and stock required'], 400);
// TODO: enqueue set inventory level contract
po_jresp(true, ['message'=>'stock update accepted']);
