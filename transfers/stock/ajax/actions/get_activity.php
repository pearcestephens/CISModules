<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/DevState.php';

$all = DevState::loadAll();
$items = [];
$outletIds = [];
foreach ($all as $tid => $row) {
  $id = (int)$tid;
  $times = [
    (string)($row['last_touched_at'] ?? ''),
    (string)($row['last_edited_at'] ?? ''),
    (string)($row['last_opened_at'] ?? ''),
    (string)($row['updated_at'] ?? ''),
  ];
  $latest = '';
  foreach ($times as $t) { if ($t !== '' && $t > $latest) { $latest = $t; } }
  $from = (string)($row['outlet_from'] ?? '');
  $to = (string)($row['outlet_to'] ?? '');
  if ($from !== '') $outletIds[$from] = true;
  if ($to !== '') $outletIds[$to] = true;
  $items[] = [
    'transfer_id' => $id,
    'state' => (string)($row['state'] ?? ''),
    'latest_at' => $latest,
    'from' => $from,
    'to' => $to,
    'flag_count' => (int)(is_array($row['inaccuracies'] ?? null) ? count($row['inaccuracies']) : (int)($row['inaccuracies'] ?? 0)),
  ];
}
usort($items, function($a,$b){ return strcmp($b['latest_at'], $a['latest_at']); });
if (count($items) > 20) { $items = array_slice($items, 0, 20); }

// Optional enrichment for outlet names
try {
  if (function_exists('cis_pdo') && !empty($items)) {
    $idList = array_keys($outletIds);
    if (count($idList) > 0) {
      $chunk = array_slice($idList, 0, 500);
      $placeholders = implode(',', array_fill(0, count($chunk), '?'));
      $pdo = cis_pdo();
      $stmt = $pdo->prepare("SELECT id, name FROM vend_outlets WHERE id IN ($placeholders)");
      $stmt->execute($chunk);
      $map = [];
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $map[(string)$row['id']] = (string)($row['name'] ?? ''); }
      foreach ($items as &$it) {
        $it['from_name'] = isset($map[(string)($it['from'] ?? '')]) ? $map[(string)$it['from']] : '';
        $it['to_name'] = isset($map[(string)($it['to'] ?? '')]) ? $map[(string)$it['to']] : '';
      }
      unset($it);
    }
  }
} catch (Throwable $e) { /* soft-fail */ }

jresp(true, ['items' => $items], 200);
