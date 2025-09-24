<?php
/**
 * https://staff.vapeshed.co.nz/modules/CIS_TEMPLATE.php
 * Shim to ensure PHP execution in environments without extensionless handler.
 * Delegates to the canonical CIS_TEMPLATE file.
 */
declare(strict_types=1);
// Bootstrap full application (sessions, config, autoloaders)
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
// Fallback only if DB constants not defined
if (!defined('DB_HOST')) {
  require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/config.php';
}
// Load lightweight shared template helpers (for asset stacks), if available
$__tpl_shared = $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
if (is_file($__tpl_shared)) { require_once $__tpl_shared; }

// -------- Resolve module/view early and load optional per-view metadata --------
$__cis_uri = $_SERVER['REQUEST_URI'] ?? '';
$__cis_module = '';
$__cis_view = '';
if (preg_match('#/module/([a-z0-9_\-/]+)/([a-z0-9_\-]+)#i', $__cis_uri, $m)) {
  $__cis_module = strtolower($m[1]);
  $__cis_view = strtolower($m[2]);
} else {
  if (!empty($_GET['module'])) { $__cis_module = preg_replace('/[^a-z0-9_\-\/]/i','', (string)$_GET['module']); }
  if (!empty($_GET['view'])) { $__cis_view = preg_replace('/[^a-z0-9_\-]/i','', (string)$_GET['view']); }
}

// Basic path hardening (disallow traversal)
if (strpos($__cis_module, '..') !== false) { $__cis_module = ''; }
if (strpos($__cis_view, '..') !== false) { $__cis_view = ''; }

$__cis_meta = [
  'title' => '',
  'subtitle' => '',
  'breadcrumb' => [], // [ [label, href?], ... ]
  'right' => '',
  'layout' => 'card', // card (default), plain, grid-2, grid-3, split, centered, full-bleed
  'hide_quick_search' => false,
  'suppress_breadcrumb' => false,
  // Simple asset declaration so views can stay lean
  // 'assets' => ['css' => ['https://.../file.css'], 'js' => [['https://.../file.js', ['defer'=>true]]]]
  'assets' => [ 'css' => [], 'js' => [] ],
  // Head meta (optional)
  'page_title' => '',
  'meta_description' => '',
  'meta_keywords' => '',
  'noindex' => false,
  // Tabs (optional): [ ['key'=>'overview','label'=>'Overview','href'=>'https://...','active'=>true], ... ]
  'tabs' => [],
  'active_tab' => '',
];
if ($__cis_module && $__cis_view) {
  $base = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . "/modules/{$__cis_module}";
  $metaCandidates = [
    $base . "/views/{$__cis_view}.meta.php",
    $base . "/{$__cis_view}.meta.php",
  ];
  foreach ($metaCandidates as $mf) {
    if (is_file($mf)) {
      $ret = include $mf;
      if (is_array($ret)) { $__cis_meta = array_merge($__cis_meta, $ret); }
      break;
    }
  }
}
// System-wide page view logging (GET only)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
  $telemetry = $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/telemetry.php';
  if (is_file($telemetry)) {
    require_once $telemetry;
    if (function_exists('cis_log_page_view') && $__cis_module && $__cis_view) {
      // include common IDs if present in query
      $ctx = [];
      foreach (['transfer','po_id','id'] as $k) {
        if (isset($_GET[$k])) { $ctx[$k] = $_GET[$k]; }
      }
      if (function_exists('cis_profiler_start')) { cis_profiler_start($__cis_module, $__cis_view, $ctx); }
      cis_log_page_view($__cis_module, $__cis_view, $ctx);
    }
  }
}
// If assets declared in meta, push them into the global asset stacks
if (!empty($__cis_meta['assets']) && is_array($__cis_meta['assets'])) {
  $a = $__cis_meta['assets'];
  if (!empty($a['css']) && function_exists('tpl_style')) {
    foreach ((array)$a['css'] as $css) {
      if (is_string($css)) { tpl_style($css); }
      elseif (is_array($css) && isset($css[0])) { tpl_style((string)$css[0], (array)($css[1] ?? [])); }
    }
  }
  if (!empty($a['js']) && function_exists('tpl_script')) {
    foreach ((array)$a['js'] as $js) {
      if (is_string($js)) { tpl_script($js, ['defer'=>true]); }
      elseif (is_array($js) && isset($js[0])) { tpl_script((string)$js[0], (array)($js[1] ?? ['defer'=>true])); }
    }
  }
}
// Defaults if missing
$__cis_human = static function(string $slug): string { return ucwords(str_replace(['-','_'], ' ', $slug)); };
if (empty($__cis_meta['title'])) { $__cis_meta['title'] = ($__cis_view ? $__cis_human($__cis_view) : ''); }
// Compose a smart default page title optimized for browser tabs:
// "{Title} — {Module} — CIS" and add env label (DEV/STAGE) when not production
// Determine environment label
$__cis_env = '';
if (defined('APP_ENV')) { $__cis_env = strtolower((string)APP_ENV); }
elseif (defined('ENV')) { $__cis_env = strtolower((string)ENV); }
elseif (!empty($_ENV['APP_ENV'])) { $__cis_env = strtolower((string)$_ENV['APP_ENV']); }
$__cis_env_label = (in_array($__cis_env, ['prod','production','live'], true) || $__cis_env === '') ? '' : strtoupper($__cis_env);

if (empty($__cis_meta['page_title'])) {
  $parts = [];
  $t = trim((string)($__cis_meta['title'] ?? ''));
  if ($t !== '') { $parts[] = $t; }
  if ($__cis_module) {
    $mh = $__cis_human($__cis_module);
    if ($mh !== '' && (!isset($t[0]) || stripos($t, $mh) === false)) { $parts[] = $mh; }
  }
  $parts[] = 'CIS';
  $__cis_meta['page_title'] = implode(' — ', $parts);
}
if (empty($__cis_meta['breadcrumb'])) {
  $bc = [];
  $bc[] = ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'];
  if ($__cis_module) { $bc[] = ['label' => $__cis_human($__cis_module)]; }
  if ($__cis_view) { $bc[] = ['label' => $__cis_human($__cis_view)]; }
  $__cis_meta['breadcrumb'] = $bc;
}


//######### AJAX BEGINS HERE #########
// Proxy ajax_action to module handler when hitting /module/{module}/{view}
try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $act = $_POST['ajax_action'] ?? ($_POST['action'] ?? null);
    if ($act) {
      $uri = $_SERVER['REQUEST_URI'] ?? '';
      $module = '';
      if (preg_match('#/module/([a-z0-9_\-]+)/#i', $uri, $m)) {
        $module = strtolower($m[1]);
      } else if (!empty($_GET['module'])) {
        $module = preg_replace('/[^a-z0-9_\-]/i','', $_GET['module']);
      }
      if ($module) {
  $handler = $_SERVER['DOCUMENT_ROOT'] . "/modules/{$module}/ajax/handler.php";
        if (is_file($handler)) { include $handler; exit; }
      }
  // Fallback to purchase-orders (hyphenated canonical path)
  $fallback = $_SERVER['DOCUMENT_ROOT'] . "/modules/purchase-orders/ajax/handler.php";
      if (is_file($fallback)) { include $fallback; exit; }
      http_response_code(200);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['success'=>false,'error'=>'invalid','request_id'=>bin2hex(random_bytes(8))]);
      exit;
    }
  }
} catch (Throwable $e) {
  http_response_code(200);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success'=>false,'error'=>'template_proxy_error']);
  exit;
}

//######### AJAX ENDS HERE #########

//######### HEADER BEGINS HERE ######### -->

// Expose head metadata to the html-header include (if it supports these globals)
$__cis_final_title = (string)($__cis_meta['page_title'] ?? 'CIS');
if (!empty($__cis_env_label)) { $__cis_final_title .= ' [' . $__cis_env_label . ']'; }
$GLOBALS['PAGE_TITLE'] = $__cis_final_title;
$GLOBALS['META_DESCRIPTION'] = (string)($__cis_meta['meta_description'] ?? '');
$GLOBALS['META_KEYWORDS'] = (string)($__cis_meta['meta_keywords'] ?? '');
$GLOBALS['NOINDEX'] = !empty($__cis_meta['noindex']);

include $_SERVER['DOCUMENT_ROOT'] . "/assets/template/html-header.php";
include $_SERVER['DOCUMENT_ROOT'] . "/assets/template/header.php";

//######### HEADER ENDS HERE ######### -->

?>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <?php
  // Global security headers for all public-facing pages rendered via CIS_TEMPLATE
  if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: no-referrer-when-downgrade');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
  }
  ?>
  <div class="app-body">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/assets/template/sidemenu.php"; ?>
    <main class="main">
      <!-- Breadcrumb (standardized, per-view configurable) -->
      <?php
        $GLOBALS['TPL_RENDERING_IN_CIS_TEMPLATE'] = true; // views can detect to avoid duplicate breadcrumb
        $items = (array)($__cis_meta['breadcrumb'] ?? []);
        $suppress = !empty($__cis_meta['suppress_breadcrumb']);
        if (!$suppress && !empty($items)) {
          echo '<ol class="breadcrumb">';
          $count = count($items);
          foreach ($items as $idx => $it) {
            $label = htmlspecialchars((string)($it['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $href  = isset($it['href']) ? htmlspecialchars((string)$it['href'], ENT_QUOTES, 'UTF-8') : '';
            $isLast = ($idx === $count - 1);
            if ($isLast) {
              echo '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
            } else {
              if ($href !== '') {
                echo '<li class="breadcrumb-item"><a href="' . $href . '">' . $label . '</a></li>';
              } else {
                echo '<li class="breadcrumb-item">' . $label . '</li>';
              }
            }
          }
          echo '<li class="breadcrumb-menu d-md-down-none">';
          // Optional quick search (can be hidden per-view)
          $hideQS = !empty($__cis_meta['hide_quick_search']);
          if (!$hideQS) {
            $qps = $_SERVER['DOCUMENT_ROOT'] . '/assets/template/quick-product-search.php';
            if (is_file($qps)) { include $qps; }
          }
          // Optional right-side actions/html slot from per-view meta
          if (!empty($__cis_meta['right'])) {
            // Intentionally trust internal HTML here; views should supply safe markup
            echo '<div class="cis-actions ml-3">' . (string)($__cis_meta['right']) . '</div>';
          }
          // Admin quick tools (admin/owner/director only): lightweight link to Audit Viewer
          $role = $_SESSION['role'] ?? ($_SESSION['userRole'] ?? '');
          if (in_array($role, ['admin','owner','director'], true)) {
        echo '<div class="cis-actions ml-3 d-none d-md-inline">'
          . '<a class="btn btn-sm btn-outline-dark" href="https://staff.vapeshed.co.nz/modules/module.php?module=_shared/admin/audit&view=viewer" title="Audit Viewer">'
           . '<i class="fa fa-shield"></i> Audit Viewer'
           . '</a>'
           . '</div>';
          }
          echo '</li></ol>';
        }
      ?>
      <div class="container-fluid">
        <div class="animated fadeIn">
          <div class="row">
            <div class="col ">
              <?php
                // include lightweight layout CSS
                echo '<link rel="stylesheet" href="https://staff.vapeshed.co.nz/modules/_shared/assets/css/cis-layouts.css">';
                $layout = (string)($__cis_meta['layout'] ?? 'card');
                // Build tabs HTML if provided
                $cis_tabs_html = '';
                $tabs = $__cis_meta['tabs'] ?? [];
                $activeKey = (string)($__cis_meta['active_tab'] ?? '');
                if (is_array($tabs) && count($tabs) > 0) {
                  $cis_tabs_html .= '<ul class="nav nav-tabs cis-tabs mb-3">';
                  foreach ($tabs as $t) {
                    $key = (string)($t['key'] ?? '');
                    $label = htmlspecialchars((string)($t['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $href = htmlspecialchars((string)($t['href'] ?? '#'), ENT_QUOTES, 'UTF-8');
                    $isActive = !empty($t['active']) || ($activeKey !== '' && $activeKey === $key);
                    $cis_tabs_html .= '<li class="nav-item"><a class="nav-link'.($isActive?' active':'').'" href="'.$href.'">'.$label.'</a></li>';
                  }
                  $cis_tabs_html .= '</ul>';
                }
                if ($layout === 'plain') {
                  echo '<div class="cis-layout cis-plain">';
                  $___t = trim((string)($__cis_meta['title'] ?? ''));
                  $___s = trim((string)($__cis_meta['subtitle'] ?? ''));
                  if ($___t !== '') { echo '<div class="cis-title h4 mb-0">' . htmlspecialchars($___t, ENT_QUOTES, 'UTF-8') . '</div>'; }
                  if ($___s !== '') { echo '<div class="cis-subtitle">' . htmlspecialchars($___s, ENT_QUOTES, 'UTF-8') . '</div>'; }
                  echo $cis_tabs_html;
                  echo '<div class="cis-content">';
                } elseif (strpos($layout, 'grid-') === 0) {
                  $gridClass = $layout === 'grid-3' ? 'cis-grid cis-grid-3' : 'cis-grid cis-grid-2';
                  echo '<div class="card"><div class="card-header">';
                  echo '<h4 class="card-title mb-0">' . htmlspecialchars((string)($__cis_meta['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</h4>';
                  echo '<div class="small text-muted">' . htmlspecialchars((string)($__cis_meta['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
                  echo '</div><div class="card-body ' . $gridClass . '">';
                  echo $cis_tabs_html;
                  echo '<div class="cis-content" style="grid-column: 1 / -1;">';
                } elseif ($layout === 'split') {
                  echo '<div class="card"><div class="card-header">';
                  echo '<h4 class="card-title mb-0">' . htmlspecialchars((string)($__cis_meta['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</h4>';
                  echo '<div class="small text-muted">' . htmlspecialchars((string)($__cis_meta['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
                  echo '</div><div class="card-body cis-split"><div class="cis-main">';
                  echo $cis_tabs_html;
                  echo '<div class="cis-content">';
                } elseif ($layout === 'centered') {
                  echo '<div class="card"><div class="card-header">';
                  echo '<h4 class="card-title mb-0">' . htmlspecialchars((string)($__cis_meta['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</h4>';
                  echo '<div class="small text-muted">' . htmlspecialchars((string)($__cis_meta['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
                  echo '</div><div class="card-body cis-centered"><div class="cis-center-box">';
                  echo $cis_tabs_html;
                  echo '<div class="cis-content">';
                } elseif ($layout === 'full-bleed') {
                  echo '<div class="card"><div class="card-header">';
                  echo '<h4 class="card-title mb-0">' . htmlspecialchars((string)($__cis_meta['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</h4>';
                  echo '<div class="small text-muted">' . htmlspecialchars((string)($__cis_meta['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
                  echo '</div><div class="card-body cis-full-bleed">';
                  echo $cis_tabs_html;
                  echo '<div class="cis-content">';
                } else { // default card
                  echo '<div class="card"><div class="card-header">';
                  echo '<h4 class="card-title mb-0">' . htmlspecialchars((string)($__cis_meta['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</h4>';
                  echo '<div class="small text-muted">' . htmlspecialchars((string)($__cis_meta['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
                  echo '</div><div class="card-body">';
                  echo $cis_tabs_html;
                  echo '<div class="cis-content">';
                }
              ?>
                  <?php if (function_exists('tpl_render_styles')) { tpl_render_styles(); } ?>
                      <?php
                      // Render module view inside the CIS template when visiting /module/{module}/{view}
                      $cis_module_output = '';
                      $cis_module = '';
                      $cis_view = '';
                      $uri = $_SERVER['REQUEST_URI'] ?? '';
                      if (preg_match('#/module/([a-z0-9_\-/]+)/([a-z0-9_\-]+)#i', $uri, $m)) {
                        $cis_module = strtolower($m[1]);
                        $cis_view = strtolower($m[2]);
                      } else {
                        if (!empty($_GET['module'])) { $cis_module = preg_replace('/[^a-z0-9_\-\/]/i','', $_GET['module']); }
                        if (!empty($_GET['view'])) { $cis_view = preg_replace('/[^a-z0-9_\-]/i','', $_GET['view']); }
                      }
                      if (strpos($cis_module, '..') !== false) { $cis_module = ''; }
                      if (strpos($cis_view, '..') !== false) { $cis_view = ''; }
                      if ($cis_module && $cis_view) {
                        $base = $_SERVER['DOCUMENT_ROOT'] . "/modules/{$cis_module}";
                        $candidates = [
                          $base . "/views/{$cis_view}.php",
                          $base . "/{$cis_view}.php",
                        ];
                        foreach ($candidates as $file) {
                          if (is_file($file)) { ob_start(); include $file; $cis_module_output = ob_get_clean(); break; }
                        }
                        if ($cis_module_output === '') {
                          $cis_module_output = '<div class="text-muted">Module view not found.</div>';
                        }
                        echo $cis_module_output; // Module views handle their own escaping/output
                      } else {
                        echo '<div class="text-muted">No module/view specified.</div>';
                      }
                      ?>
                  </div>
              <?php
                // Close wrappers for chosen layout
                if ($layout === 'plain') {
                  echo '</div></div>';
                } elseif (strpos($layout, 'grid-') === 0) {
                  echo '</div></div></div>';
                } elseif ($layout === 'split') {
                  // caller can place aside using $__cis_meta['right'] or view content block; we just close main
                  echo '</div></div></div>';
                } elseif ($layout === 'centered') {
                  echo '</div></div></div>';
                } elseif ($layout === 'full-bleed') {
                  echo '</div></div></div>';
                } else { // default card
                  echo '</div></div></div>';
                }
              ?>
            </div>
          </div>
          <!--/.row-->
        </div>
      </div>
    </main>
    <!-- ######### FOOTER BEGINS HERE ######### -->
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/assets/template/personalisation-menu.php"; ?>
  </div>

   <!-- ######### CSS BEGINS HERE ######### -->

   <!-- ######### CSS BEGINS HERE ######### -->


  <!-- ######### JAVASSCRIPT BEGINS HERE ######### -->
  <?php
    // Expose CSRF and telemetry consent to client
    $__csrf = '';
    if (function_exists('getCSRFToken')) { $__csrf = (string)getCSRFToken(); }
    elseif (!empty($_SESSION['csrf_token'])) { $__csrf = (string)$_SESSION['csrf_token']; }
    $__consent = !empty($_SESSION['telemetry_consent']);
  ?>
  <script>
    window.CSRF_TOKEN = <?php echo json_encode($__csrf); ?>;
    window.CIS_TELEMETRY_CONSENT = <?php echo json_encode($__consent); ?>;
  </script>
  <?php if (!empty($_SESSION['userID'])): ?>
  <script src="https://staff.vapeshed.co.nz/modules/_shared/assets/js/telemetry.beacon.js" defer></script>
  <?php endif; ?>
  <!-- ######### JAVASSCRIPT ENDS HERE ######### -->

  <?php include $_SERVER['DOCUMENT_ROOT'] . "/assets/template/html-footer.php"; ?>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/assets/template/footer.php"; ?>
  <?php if (function_exists('tpl_render_scripts')) { tpl_render_scripts(); } ?>
  <!-- ######### FOOTER ENDS HERE ######### -->
