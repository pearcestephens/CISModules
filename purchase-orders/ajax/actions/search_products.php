<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$q = trim((string)($_POST['q'] ?? ''));
if ($q === '') { po_jresp(true, ['results'=>[]]); }
// TODO: implement product search
po_jresp(true, ['results'=>[]]);
