<?php
declare(strict_types=1);

function boolenv(string $k): bool { $v = $_ENV[$k] ?? getenv($k); if ($v === false || $v === null) return false; $s = strtolower((string)$v); return !in_array($s, ['', '0', 'false', 'no', 'null'], true); }

$hasNzToken = boolenv('NZPOST_TOKEN') || boolenv('NZPOST_SUBSCRIPTION_KEY') || function_exists('createNzPostLabel_wrapped');
$hasGssToken = boolenv('GSS_TOKEN') || function_exists('createGssLabel_wrapped');

$default = 'none';
if ($hasNzToken && $hasGssToken) { $default = 'nzpost'; }
elseif ($hasNzToken) { $default = 'nzpost'; }
elseif ($hasGssToken) { $default = 'gss'; }

jresp(true, [
  'has_nzpost' => (bool)$hasNzToken,
  'has_gss' => (bool)$hasGssToken,
  'default' => $default,
]);
