<?php
if (!defined('ABSPATH')) exit;

/** =========================
 *  Sanitizers
 * ========================= */
function shec_sanitize_api_key( $val ) {
    $val = is_string($val) ? wp_unslash($val) : $val;
    return preg_replace('/[^A-Za-z0-9_\-\.\:]/', '', $val);
}
function shec_sanitize_prompt_text( $val ) {
    $val = is_string($val) ? wp_unslash($val) : $val;
    $val = wp_kses( $val, [] );
    return rtrim($val);
}
function shec_guess_step_progress(array $d){
    $step = 0;
    if (!empty($d['gender']) && !empty($d['age']) && (!empty($d['mobile']) || !empty($d['contact']['mobile']))) {
        $step = 1;
    } else { return 0; }
    if (!empty($d['loss_pattern'])) $step = 2;
    if (!empty($d['uploads']) && is_array($d['uploads']) && count($d['uploads'])>0) $step = 3;
    if (isset($d['medical']['has_medical']) && isset($d['medical']['has_meds'])) $step = 4;
    if (!empty($d['contact']['first_name']) && !empty($d['contact']['last_name']) && !empty($d['contact']['state']) && !empty($d['contact']['city']) && !empty($d['contact']['social'])) $step = 5;
    if (!empty($d['ai']['final'])) $step = 6;
    return $step;
}

function shec_sanitize_asst_id( $val ) {
    $val = is_string($val) ? wp_unslash($val) : $val;
    $val = trim($val);
    return preg_replace('/[^A-Za-z0-9_\-\.]/', '', $val); // asst_xxx
}

/** =========================
 *  Getters
 * ========================= */
function shec_openai_use_assistants(){
    return (bool) get_option('shec_asst_enable', false);
}
function shec_openai_asst_qs_id(){
    return trim((string) get_option('shec_asst_qs_id',''));
}
function shec_openai_asst_final_id(){
    return trim((string) get_option('shec_asst_final_id',''));
}

/** =========================
 *  Enqueue admin assets (only our pages)
 * ========================= */
add_action('admin_enqueue_scripts', function ($hook) {
    if (empty($_GET['page'])) return;
    $pages = ['shec-form','shec-settings','shec-form-data'];
    if (!in_array($_GET['page'], $pages, true)) return;

    // jQuery DataTables (فقط صفحه لیست)
    if ($_GET['page'] === 'shec-form') {
        wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', [], '1.13.6');
        wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], '1.13.6', true);
    }

    // استایل اختصاصی + فونت شبنم (از CDN)
    $inline_css = "
@import url('https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@v5.0.1/dist/font-face.css');

body{
    font-family: Shabnam, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
    direction: rtl;
} 

.shec-admin { direction: rtl; font-family: Shabnam; }
.shec-title { margin-bottom: 10px; }

.shec-admin-tabs { display:flex; gap:8px; margin:6px 0 16px; }
.shec-admin-tabs .shec-tab {
  background:#f3f4f6; border:1px solid #e5e7eb; padding:7px 12px; border-radius:10px;
  text-decoration:none; color:#111827; transition:background .2s, color .2s, transform .06s;
}
.shec-admin-tabs .shec-tab:hover { background:#eef2ff; transform:translateY(-1px); }
.shec-admin-tabs .shec-tab.is-active {
  background:#fff; border-color:#93c5fd; color:#1d4ed8; box-shadow:0 5px 18px rgba(29,78,216,.10);
}

.shec-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:16px; box-shadow:0 8px 28px rgba(17,24,39,.06); }
.shec-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(350px,1fr)); gap:10px; margin:8px 0 18px; }
.shec-subtitle { margin:18px 0 8px; }

.shec-table th, .shec-table td { vertical-align:middle; }
.shec-table td a.button.button-primary { border-radius:8px; }

.shec-form .shec-field { margin-bottom:16px; }
.shec-form .shec-field > label { display:inline-block; font-weight:700; margin-bottom:6px; }
.shec-form input[type='text'] {
  width:420px; border-radius:8px; border:1px solid #e5e7eb; padding:8px 10px; background:#fbfbfd; font-family:inherit;
}
.shec-form .shec-help { margin:0 0 6px; color:#6b7280; }
.shec-form textarea {
  width:100%; max-width:960px; min-height:180px; border-radius:10px; border:1px solid #e5e7eb; padding:12px 14px; background:#fbfbfd;
  font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; line-height:1.6;
  box-shadow:inset 0 1px 2px rgba(0,0,0,.03);
}
.shec-form .shec-actions { margin-top:6px; }
.shec-sep { border:none; border-top:1px solid #e5e7eb; margin:18px 0; }

.shec-qa { padding-right:20px; }
.shec-qa li { margin-bottom:8px; }
.shec-result { border:1px solid #e5e7eb; border-radius:12px; background:linear-gradient(180deg,#fff,#f9fbff); padding:12px; }
.shec-analysis { margin-top:6px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:10px; }

.wrap .button.button-primary { background:#1976d2; border:none; border-radius:10px; padding:8px 14px; box-shadow:0 4px 16px rgba(25,118,210,.18); }

/* DataTables فارسی‌تر */
.dataTables_wrapper .dataTables_filter input, .dataTables_wrapper .dataTables_length select {
  font-family: inherit;
  border:1px solid #e5e7eb; border-radius:8px; padding:6px 8px; background:#fbfbfd;
}
";
    wp_register_style('shec-admin-inline', false);
    wp_enqueue_style('shec-admin-inline');
    wp_add_inline_style('shec-admin-inline', $inline_css);

    // مقداردهی اولیهٔ DataTables (فقط صفحه داده‌ها)
    if ($_GET['page'] === 'shec-form') {
        $inline_js = "
jQuery(function($){
  var t = $('#shec-table');
  if (!t.length || !$.fn.DataTable) return;
  t.DataTable({
    pageLength: 20,
    order: [[0,'desc']], // ستون شناسه نزولی
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fa.json' }
  });
});
";
        wp_add_inline_script('datatables-js', $inline_js);
    }
});

/** =========================
 *  Admin tabs + wrappers
 * ========================= */
function shec_display_dashboard_stats() {
    global $wpdb;

    // تعداد کل فرم‌های ارسال شده
    $total_forms = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}shec_users");

    // تعداد فرم‌های کامل
    $completed_forms = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}shec_users WHERE data LIKE '%\"contact\"%'");

    // شماره‌های جدید (کاربرانی که در ابتدا ثبت‌نام کردند)
    $new_numbers = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}shec_users WHERE data LIKE '%\"mobile\"%'");

    // نرخ موفقیت (تعداد فرم‌های کامل / تعداد کل فرم‌ها)
    $success_rate = ($total_forms > 0) ? ($completed_forms / $total_forms) * 100 : 0;

    // نمایش باکس‌ها
    echo '<div class="shec-grid shec-dashboard-stats">';

    echo '<div class="shec-card"><h3>تعداد کل فرم‌های ارسال شده</h3><p>' . esc_html($total_forms) . '</p></div>';
    echo '<div class="shec-card"><h3>فرم‌های کامل شده</h3><p>' . esc_html($completed_forms) . '</p></div>';
    echo '<div class="shec-card"><h3>شماره‌های جدید</h3><p>' . esc_html($new_numbers) . '</p></div>';
    echo '<div class="shec-card"><h3>نرخ موفقیت</h3><p>' . esc_html(number_format($success_rate, 2)) . '%</p></div>';

    echo '</div>';
}
add_action('shec_display_data', 'shec_display_dashboard_stats', 10);

function shec_admin_tabs($active = 'data') {
    $base = admin_url('admin.php');
    $items = [
        'data'     => ['label'=>'داده‌ها',    'url'=> add_query_arg(['page'=>'shec-form'],      $base)],
        'settings' => ['label'=>'تنظیمات',    'url'=> add_query_arg(['page'=>'shec-settings'],  $base)],
        'detail'   => ['label'=>'جزئیات',     'url'=> add_query_arg(['page'=>'shec-form-data'], $base)],
    ];
    echo '<div class="shec-admin-tabs">';
    foreach ($items as $key => $it) {
        $class = 'shec-tab'. ($active === $key ? ' is-active' : '');
        echo '<a class="'.esc_attr($class).'" href="'.esc_url($it['url']).'">'.esc_html($it['label']).'</a>';
    }
    echo '</div>';
}
function shec_admin_wrap_open($activeTab = 'data', $title = '') {
    echo '<div class="wrap shec-admin">';
    if ($title) echo '<h1 class="shec-title">'.esc_html($title).'</h1>';
    shec_admin_tabs($activeTab);
    echo '<div class="shec-card">';
}
function shec_admin_wrap_close() { echo '</div></div>'; }

/** =========================
 *  Progress detector (0..6)
 * ========================= */
function shec_detect_progress(array $d) {
    $step = 0;
    if (!empty($d['gender']) && !empty($d['age']) && !empty($d['mobile'])) $step = 1;
    if (!empty($d['loss_pattern'])) $step = max($step, 2);
    if (!empty($d['uploads']) && is_array($d['uploads'])) {
        $hasUploads = count(array_filter($d['uploads'])) > 0;
        if ($hasUploads) $step = max($step, 3);
    }
    if (!empty($d['medical']) && is_array($d['medical'])) {
        if (array_key_exists('has_medical',$d['medical']) && array_key_exists('has_meds',$d['medical'])) {
            $step = max($step, 4);
        }
    }
    if (!empty($d['contact']) && is_array($d['contact'])) {
        $c = $d['contact'];
        $ok = !empty($c['first_name']) && !empty($c['last_name']) && !empty($c['state']) && !empty($c['city']) && !empty($c['social']);
        if ($ok) $step = max($step, 5);
    }
    if (!empty($d['ai']['final'])) $step = max($step, 6);
    return $step;
}

/** =========================
 *  Menus
 * ========================= */
function shec_add_admin_menu() {
    add_menu_page('هوش مصنوعی', 'هوش مصنوعی', 'manage_options', 'shec-form', 'shec_display_data', 'dashicons-chart-pie', 6);
    add_submenu_page('shec-form', 'تنظیمات', 'تنظیمات', 'manage_options', 'shec-settings', 'shec_display_settings');
}
add_action('admin_menu', 'shec_add_admin_menu');
add_action('admin_menu', function () {
    add_submenu_page('shec-form', 'جزئیات داده‌ها', 'جزئیات داده‌ها', 'manage_options', 'shec-form-data', 'shec_display_user_details');
});

/** =========================
 *  List page (Data + DataTables)
 * ========================= */
function shec_display_data() {
    global $wpdb;

    $rows = $wpdb->get_results("SELECT id, data FROM {$wpdb->prefix}shec_users WHERE data LIKE '%\"contact\"%' ORDER BY id DESC");

    shec_admin_wrap_open('data', 'داده‌های فرم هوشمند فخرایی');

    // کارت‌های آمار
    $total_forms   = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}shec_users");
    $completed     = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}shec_users WHERE data LIKE '%\"contact\"%'");
    $new_numbers   = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}shec_users WHERE data LIKE '%\"mobile\"%'");
    $success_rate  = ($total_forms > 0) ? ($completed / $total_forms) * 100 : 0;

    echo '<div class="shec-grid shec-dashboard-stats">';
    echo '<div class="shec-card"><h3>تعداد کل فرم‌های ارسال شده</h3><p>'.esc_html($total_forms).'</p></div>';
    echo '<div class="shec-card"><h3>فرم‌های کامل شده</h3><p>'.esc_html($completed).'</p></div>';
    echo '<div class="shec-card"><h3>شماره‌های جدید</h3><p>'.esc_html($new_numbers).'</p></div>';
    echo '<div class="shec-card"><h3>نرخ موفقیت</h3><p>'.esc_html(number_format($success_rate, 2)).'%</p></div>';
    echo '</div>';

    // جدول
    echo '<table id="shec-data-table" class="display shec-table" style="width:100%; text-align:right; direction:rtl; float:right;">';
    echo '  <thead>
              <tr style="text-align:right;">
                <th>#</th>
                <th>نام</th>
                <th>نام خانوادگی</th>
                <th>سن</th>
                <th>موبایل</th>
                <th>مرحله</th>
                <th>مشاهده</th>
              </tr>
            </thead>
            <tbody>';

    if (!empty($rows)) {
        foreach ($rows as $row) {
            $d = json_decode($row->data, true) ?: [];

            $first  = $d['contact']['first_name'] ?? '';
            $last   = $d['contact']['last_name']  ?? '';
            $age    = $d['age'] ?? '';
            $mobile = $d['mobile'] ?? ($d['contact']['mobile'] ?? '');
            $stage  = shec_guess_step_progress($d) . '/6';

            $detail_url = admin_url('admin.php?page=shec-form-data&user_id=' . intval($row->id));

            echo '<tr>';
            echo '  <td><a href="'.esc_url($detail_url).'" class="row-id-link">'.intval($row->id).'</a></td>';
            echo '  <td>'.esc_html($first ?: '—').'</td>';
            echo '  <td>'.esc_html($last  ?: '—').'</td>';
            echo '  <td>'.esc_html($age   ?: '—').'</td>';
            echo '  <td>'.esc_html($mobile?: '—').'</td>';
            echo '  <td>'.esc_html($stage).'</td>';
            echo '  <td><a href="'.esc_url($detail_url).'" class="button button-primary">مشاهده</a></td>';
            echo '</tr>';
        }
    }

    echo '  </tbody></table>';

    ?>
    <script>
    jQuery(function($){
      $('#shec-data-table').DataTable({
        order: [[1,'desc']],
        pageLength: 10,
        language: {
          search: "جستجو:",
          lengthMenu: "نمایش _MENU_",
          info: "نمایش _START_ تا _END_ از _TOTAL_",
          infoEmpty: "رکوردی یافت نشد",
          zeroRecords: "چیزی پیدا نشد",
          paginate: { first:"اول", last:"آخر", next:"بعدی", previous:"قبلی" }
        }
      });
    });
    </script>
    <?php

    shec_admin_wrap_close();
}

/** =========================
 *  Detail page
 * ========================= */
function shec_display_user_details() {
  global $wpdb;

  $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
  shec_admin_wrap_open('detail', 'جزئیات داده‌ها');

  if (!$user_id) { echo '<p>اطلاعات کاربر پیدا نشد.</p>'; shec_admin_wrap_close(); return; }

  $table = $wpdb->prefix . 'shec_users';
  $row   = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $user_id) );
  if (!$row) { echo '<p>اطلاعات کاربر پیدا نشد.</p>'; shec_admin_wrap_close(); return; }

  $d    = json_decode($row->data ?? '[]', true);
  $step = function_exists('shec_detect_progress') ? shec_detect_progress($d) : '-';

  // Helpers
  $esc  = 'esc_html';
  $ynFa = function($v){
    $t = is_bool($v) ? ($v ? 'yes' : 'no') : strtolower(trim((string)$v));
    if (in_array($t, ['yes','true','1'], true)) return 'بله';
    if (in_array($t, ['no','false','0'], true))  return 'خیر';
    $v = trim((string)$v);
    return ($v === '') ? '—' : $v;
  };
  $stageFromPattern = function($p){
    if (!$p) return null;
    if (preg_match('~(\d+)~', (string)$p, $m)) {
      $s = (int)$m[1]; if ($s<1) $s=1; if ($s>7) $s=7; return $s;
    }
    return null;
  };
  $graftByTable = function($gender, $stage){
    if (!$stage) return null;
    $gender = strtolower((string)$gender);
    $male   = [1=>8000, 2=>10000, 3=>12000, 4=>14000, 5=>16000, 6=>18000, 7=>20000];
    $female = [1=>4000, 2=>8000,  3=>10000, 4=>12000, 5=>14000, 6=>16000];
    $tbl = ($gender==='female') ? $female : $male;
    return $tbl[$stage] ?? null;
  };
  $fmt_dt = function($ts){
    if (!$ts) return '—';
    return date_i18n( get_option('date_format').' H:i', is_numeric($ts) ? (int)$ts : strtotime($ts) );
  };

  // تاریخ/ساعت
  $created_at = $row->created_at ?? ($row->inserted_at ?? null);
  if (!$created_at) {
    $created_at = $d['ai']['final']['generated_at'] ?? ($d['ai']['followups']['generated_at'] ?? null);
  }

  // اطلاعات پایه
  $gender   = $d['gender'] ?? ($d['contact']['gender'] ?? '');
  $age      = $d['age'] ?? ($d['contact']['age'] ?? '');
  $mobile   = $d['mobile'] ?? ($d['contact']['mobile'] ?? '');
  $fname    = $d['contact']['first_name'] ?? '';
  $lname    = $d['contact']['last_name']  ?? '';
  $fullName = trim($fname.' '.($lname?:''));
  $pattern  = $d['loss_pattern'] ?? ($d['pattern'] ?? '');
  $confidence = $d['confidence'] ?? '';
  $social   = $d['contact']['social'] ?? '';
  $concern  = $d['medical']['concern'] ?? '';

  $stage    = $stageFromPattern($pattern);
  $graftTbl = $graftByTable($gender, $stage);

  $uploads = $d['uploads'] ?? [];

  // سؤالات/پاسخ‌ها
  $fu      = $d['ai']['followups'] ?? [];
  $qaList  = [];
  if (!empty($fu['qa']) && is_array($fu['qa'])) {
    $qaList = $fu['qa'];
  } elseif (!empty($fu['questions']) && is_array($fu['questions'])) {
    $ans = is_array($fu['answers'] ?? null) ? $fu['answers'] : [];
    foreach ($fu['questions'] as $i=>$q) { $qaList[] = ['q'=>$q, 'a'=>$ans[$i] ?? '']; }
  }

  // خروجی AI
  $final     = $d['ai']['final'] ?? [];
  $method    = $final['method'] ?? '';
  $graft_ai  = $final['graft_count'] ?? '';
  $analysis  = $final['analysis'] ?? '';

  $concern_box = $final['concern_box'] ?? '';
  $pat_ex      = is_array($final['pattern_explain'] ?? null) ? $final['pattern_explain'] : [];
  $fu_ai       = is_array($final['followups'] ?? null) ? $final['followups'] : [];
  $fu_sum      = $final['followup_summary'] ?? '';

  // نقشهٔ Q/A ← کامنت AI
  $aiCoachMap = [];
  foreach ($fu_ai as $item) {
    $q = trim((string)($item['q'] ?? ''));
    if ($q === '') continue;
    $aiCoachMap[$q] = trim((string)($item['coach'] ?? $item['tip'] ?? ''));
  }

  // استایل مختصر
  echo '<style>
    .shec-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:12px 0}
    .shec-card{background:#fff;border:1px solid #e6e6e6;border-radius:10px;padding:14px}
    .shec-badge{display:flex;gap:6px;align-items:center;font-size:13px;color:#666}
    .shec-badge b{color:#111}
    .shec-title{margin:18px 0 8px;font-weight:700}
    .shec-chips{display:flex;flex-wrap:wrap;gap:8px}
    .shec-chip{background:#f6f7f9;border:1px solid #edf0f4;border-radius:999px;padding:6px 10px;font-size:12px}
    .shec-uploads{display:flex;flex-wrap:wrap;gap:10px}
    .shec-uploads figure{width:160px;margin:0}
    .shec-uploads img{width:160px;height:160px;object-fit:cover;border-radius:10px;border:1px solid #eee}
    .shec-uploads figcaption{font-size:12px;text-align:center;color:#666;margin-top:6px}
    .shec-qa{list-style:none;padding:0;margin:0}
    .shec-qa li{border:1px solid #edf0f4;background:#fafbfc;border-radius:10px;padding:10px 12px;margin-bottom:8px}
    .shec-qa .q{font-weight:700;margin-bottom:6px}
    .shec-qa .a{margin-bottom:8px}
    .shec-qa .coach{display:flex;gap:6px;align-items:flex-start}
    .shec-qa .coach .bot{font-size:16px;line-height:1}
    .shec-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .shec-stat{background:#fff;border:1px solid #e6e6e6;border-radius:10px;padding:12px}
    .shec-stat .label{font-size:12px;color:#666}
    .shec-stat .val{font-weight:700;font-size:16px}
    .shec-note{background:#f7f9ff;border:1px solid #e6eeff;border-radius:10px;padding:12px}
  </style>';

  // هدر
  echo '<div class="shec-grid shec-card" style="grid-template-columns:repeat(5,minmax(0,1fr))">';
  echo '  <div class="shec-badge"><b>شناسه کاربر:</b> '.intval($row->id).'</div>';
  echo '  <div class="shec-badge"><b>مرحله:</b> '.$esc($step).'/6</div>';
  echo '  <div class="shec-badge"><b>تاریخ ثبت:</b> '.$esc( $fmt_dt($created_at) ).'</div>';
  echo '  <div class="shec-badge"><b>وضعیت AI (سوالات):</b> '.($esc(isset($fu['generated_at']) ? $fmt_dt($fu['generated_at']) : '—')).'</div>';
  echo '  <div class="shec-badge"><b>وضعیت AI (نهایی):</b> '.($esc(isset($final['generated_at']) ? $fmt_dt($final['generated_at']) : '—')).'</div>';
  echo '</div>';

  // اطلاعات فردی
  echo '<h3 class="shec-title">اطلاعات کاربر</h3>';
  echo '<div class="shec-grid">';
  echo '  <div class="shec-card"><div class="shec-badge"><b>نام و نام خانوادگی:</b> '.$esc($fullName ?: '—').'</div></div>';
  echo '  <div class="shec-card"><div class="shec-badge"><b>جنسیت:</b> '.$esc($gender ?: '—').'</div></div>';
  echo '  <div class="shec-card"><div class="shec-badge"><b>سن:</b> '.$esc($age ?: '—').'</div></div>';
  echo '  <div class="shec-card"><div class="shec-badge"><b>شماره تلفن:</b> '.$esc($mobile ?: '—').'</div></div>';
  echo '  <div class="shec-card"><div class="shec-badge"><b>الگوی ریزش مو:</b> '.$esc($pattern ?: '—').'</div></div>';
  echo '  <div class="shec-card"><div class="shec-badge"><b>اعتماد به نفس/توضیح کاربر:</b> '.$esc($confidence ?: '—').'</div></div>';
  echo '  <div class="shec-card"><div class="shec-badge"><b>سوشال:</b> '.$esc($social ?: '—').'</div></div>';
  echo '  <div class="shec-card"><div class="shec-badge"><b>مهم‌ترین دغدغه:</b> '.$esc($concern ?: '—').'</div></div>';
  echo '</div>';

  // گالری آپلود
  echo '<h3 class="shec-title">تصاویر آپلود شده</h3>';
  if ($uploads && is_array($uploads)) {
    echo '<div class="shec-uploads">';
    foreach ($uploads as $pos => $url) {
      echo '<figure><img src="'.esc_url($url).'" alt=""><figcaption>'. $esc($pos) .'</figcaption></figure>';
    }
    echo '</div>';
  } else {
    echo '<div class="shec-card">هیچ تصویری برای این کاربر آپلود نشده است.</div>';
  }

  // توضیح الگو + دغدغه
  if ($concern_box || $pat_ex) {
    echo '<h3 class="shec-title">توضیح هوش مصنوعی (الگو و دغدغه)</h3>';
    echo '<div class="shec-grid">';
    if ($pat_ex) {
      $p_label = $pat_ex['label']       ?? '—';
      $p_what  = $pat_ex['what_it_is']  ?? '';
      $p_why   = $pat_ex['why_happens'] ?? '';
      $p_note  = $pat_ex['note']        ?? '';
      echo '<div class="shec-card shec-note">';
      echo '  <div><b>الگوی شما:</b> '.$esc($p_label).'</div>';
      if ($p_what) echo '<div style="margin-top:6px">'.$esc($p_what).'</div>';
      if ($p_why)  echo '<div style="margin-top:6px">'.$esc($p_why).'</div>';
      if ($p_note) echo '<div style="margin-top:6px">'.$esc($p_note).'</div>';
      echo '</div>';
    }
    if ($concern_box) {
      echo '<div class="shec-card shec-note"><div>🤖 '.$esc($concern_box).'</div></div>';
    }
    echo '</div>';
  }

  // جمع‌بندی درمانی
  echo '<h3 class="shec-title">جمع‌بندی درمانی</h3>';
  echo '<div class="shec-stats">';
  echo '  <div class="shec-stat"><div class="label">روش پیشنهادی</div><div class="val">'. $esc($method ?: '—') .'</div></div>';
  echo '  <div class="shec-stat"><div class="label">گرافت پیشنهادی (AI)</div><div class="val">'. $esc($graft_ai ?: '—') .'</div></div>';
  echo '  <div class="shec-stat"><div class="label">گرافت تقریبی از جدول کلینیک</div><div class="val">'. ($graftTbl ? number_format_i18n($graftTbl) : '—') .'</div></div>';
  echo '</div>';

  if ($analysis) {
    echo '<div class="shec-card" style="margin-top:10px"><div class="shec-badge" style="margin-bottom:6px"><b>تحلیل AI:</b></div><div>'. $esc($analysis) .'</div></div>';
  }

  // سؤالات پیگیری
  echo '<h3 class="shec-title">سؤالات پیگیری و پاسخ‌های کاربر</h3>';
  if ($qaList) {
    echo '<ol class="shec-qa">';
    foreach ($qaList as $i=>$qa) {
      $q = trim((string)($qa['q'] ?? ''));
      $a = $qa['a'] ?? '';
      $coach = $aiCoachMap[$q] ?? '';
      echo '<li>';
      echo '  <div class="q">'.($i+1).'. '.$esc($q ?: '—').'</div>';
      echo '  <div class="a"><b>پاسخ:</b> '.$esc($ynFa($a)).'</div>';
      if ($coach) {
        echo '  <div class="coach"><span class="bot">🤖</span><div>'. $esc($coach) .'</div></div>';
      }
      echo '</li>';
    }
    echo '</ol>';
  } else {
    echo '<div class="shec-card">—</div>';
  }

  // خلاصهٔ جمع‌بندی پاسخ‌ها
  if ($fu_sum) {
    echo '<h3 class="shec-title">جمع‌بندی پاسخ‌ها و توصیه‌های اختصاصی</h3>';
    echo '<div class="shec-card shec-note">'. $esc($fu_sum) .'</div>';
  }

  shec_admin_wrap_close();
}

/** =========================
 *  Settings page
 * ========================= */
function shec_display_settings() {
    $tg_msg = ''; // پیام وضعیت وبهوک

    //TEST TELEGRAM
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shec_test_telegram'])) {
        $token = get_option('shec_telegram_api', '');
        $chat  = get_option('shec_admin_chat_id', '');
        if ($token && $chat) {
            $res = wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", [
                'headers' => ['Content-Type'=>'application/json'],
                'body'    => wp_json_encode([
                    'chat_id' => $chat,
                    'text'    => "✅ تست موفق! افزونه به تلگرام وصل است.",
                ], JSON_UNESCAPED_UNICODE),
            ]);
            if (is_wp_error($res)) {
                echo '<div class="error"><p>❌ خطا در ارسال: '.esc_html($res->get_error_message()).'</p></div>';
            } else {
                echo '<div class="updated"><p>پیام تست با موفقیت ارسال شد.</p></div>';
            }
        } else {
            echo '<div class="error"><p>⚠️ ابتدا توکن ربات و Chat ID ادمین را ذخیره کنید.</p></div>';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // ذخیره تنظیمات
        if (isset($_POST['shec_api_key']))          update_option('shec_api_key',          sanitize_text_field($_POST['shec_api_key']));
        if (isset($_POST['shec_sms_api']))          update_option('shec_sms_api',          sanitize_text_field($_POST['shec_sms_api']));
        if (isset($_POST['shec_telegram_api']))     update_option('shec_telegram_api',     sanitize_text_field($_POST['shec_telegram_api']));
        if (isset($_POST['shec_admin_chat_id']))    update_option('shec_admin_chat_id',    sanitize_text_field($_POST['shec_admin_chat_id']));
        if (isset($_POST['shec_tg_secret']))        update_option('shec_tg_secret',        sanitize_text_field($_POST['shec_tg_secret']));
        if (isset($_POST['shec_prompt_questions'])) update_option('shec_prompt_questions', shec_sanitize_prompt_text($_POST['shec_prompt_questions']));
        if (isset($_POST['shec_prompt_final']))     update_option('shec_prompt_final',     shec_sanitize_prompt_text($_POST['shec_prompt_final']));
        // Assistants
        update_option('shec_asst_enable',   isset($_POST['shec_asst_enable']) ? 1 : 0);
        if (isset($_POST['shec_asst_final_id'])) update_option('shec_asst_final_id', shec_sanitize_asst_id($_POST['shec_asst_final_id']));

        // اکشن‌های وبهوک (در همان فرم)
        if (isset($_POST['shec_action'])) {
            $token  = trim((string) get_option('shec_telegram_api', ''));
            $url    = home_url('/wp-json/shec/v1/telegram/webhook');
            $secret = get_option('shec_tg_secret', '');
            if ($token) {
                if ($_POST['shec_action'] === 'set_webhook') {
                    $args = array(
                        'timeout' => 15,
                        'body'    => array('url' => $url) + ($secret ? array('secret_token' => $secret) : array()),
                    );
                    $res = wp_remote_post("https://api.telegram.org/bot{$token}/setWebhook", $args);
                    $tg_msg = 'نتیجه setWebhook: ' . wp_kses_post(wp_remote_retrieve_body($res));
                } elseif ($_POST['shec_action'] === 'delete_webhook') {
                    $res = wp_remote_post("https://api.telegram.org/bot{$token}/deleteWebhook", array('timeout'=>15));
                    $tg_msg = 'نتیجه deleteWebhook: ' . wp_kses_post(wp_remote_retrieve_body($res));
                } elseif ($_POST['shec_action'] === 'info_webhook') {
                    $res = wp_remote_get("https://api.telegram.org/bot{$token}/getWebhookInfo", array('timeout'=>15));
                    $tg_msg = 'وضعیت getWebhookInfo: ' . wp_kses_post(wp_remote_retrieve_body($res));
                }
            } else {
                $tg_msg = 'توکن ربات خالی است.';
            }
        }

        echo '<div class="updated"><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
        if ($tg_msg) {
            echo '<div class="notice notice-info"><pre style="white-space:pre-wrap;margin:8px 0;padding:8px;border:1px solid #ddd;background:#fff;">'.esc_html($tg_msg).'</pre></div>';
        }
    }

    // مقادیر فعلی
    $api_key       = get_option('shec_api_key', '');
    $asst_enable   = (bool) get_option('shec_asst_enable', 0);
    $asst_final_id = get_option('shec_asst_final_id', '');
    $sms_api  = get_option('shec_sms_api', '');
    $admin_id = get_option('shec_admin_chat_id', '');
    $telegram = get_option('shec_telegram_api', '');
    $p_q      = get_option('shec_prompt_questions', '');
    $p_f      = get_option('shec_prompt_final', '');

    if (!$p_q && function_exists('shec_prompt_questions_default')) $p_q = shec_prompt_questions_default();
    if (!$p_f && function_exists('shec_prompt_final_default'))     $p_f = shec_prompt_final_default();

    shec_admin_wrap_open('settings', 'تنظیمات افزونه');

    echo '<form method="POST" class="shec-form" id="shec-settings-form">';

    // --- SMS ---
    echo '<h3 class="shec-title" style="margin:10px 0 10px;">تنظیمات پیامک</h3>';
    echo '<div class="shec-field"><label>پنل SMS</label>
          <input type="text" name="shec_sms_api" value="'.esc_attr($sms_api).'" /></div>';

    echo '<div class="shec-field">
        <button type="button" id="shec-sms-test-btn" class="button">ارسال پیام تست پیامک</button>
        <span class="shec-help">برای تست، شمارهٔ گیرنده را از شما می‌پرسد و «سلام دنیا» ارسال می‌شود.</span>
      </div>';
    ?>
    <script>
    (function(){
      const btn = document.getElementById('shec-sms-test-btn');
      if(!btn) return;
      btn.addEventListener('click', function(){
        const to = prompt('شماره گیرنده برای تست SMS را وارد کنید (مثل 09xxxxxxxxx)');
        if(!to) return;
        btn.disabled = true;
        fetch(ajaxurl, {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
          body: 'action=shec_sms_test&_wpnonce=<?php echo wp_create_nonce('shec_nonce'); ?>&to='+encodeURIComponent(to)
        }).then(r=>r.json()).then(j=>{
          btn.disabled = false;
          if(j && j.success){
            alert('✅ پیامک ارسال شد.');
          }else{
            const msg = (j && j.data && j.data.message) ? j.data.message : 'خطای نامشخص';
            alert('❌ خطا در ارسال: ' + msg);
          }
        }).catch(()=>{
          btn.disabled = false;
          alert('❌ خطا در ارتباط با سرور');
        });
      });
    })();
    </script>
    <?php

    // --- Telegram ---
    echo '<h3 class="shec-title" style="margin:30px 0 10px;">تنظیمات تلگرام</h3>';

    echo '<div class="shec-field"><label>ربات تلگرام (توکن)</label>
          <input type="text" name="shec_telegram_api" value="'.esc_attr($telegram).'" /></div>';

    echo '<div class="shec-field"><label>Chat ID ادمین</label>
          <input type="text" name="shec_admin_chat_id" value="'.esc_attr($admin_id).'" /></div>';

    echo '<div class="shec-actions" style="margin-top:10px">';
    echo '<button type="submit" name="shec_test_telegram" value="1" class="button button-secondary">📨 ارسال پیام تست تلگرام</button>';
    echo '</div>';

    // وبهوک تلگرام
    if (function_exists('shec_admin_render_telegram_webhook_box')) {
        shec_admin_render_telegram_webhook_box();
    }

    echo '<hr class="shec-sep"/>';

    // --- OpenAI ---
    echo '<h3 class="shec-title" style="margin:30px 0 10px;">تنظیمات OPENAI</h3>';

    echo '<div class="shec-field" style="margin:30px 0 10px;"><label>API Key (OpenAI)</label>
          <input type="text" name="shec_api_key" value="'.esc_attr($api_key).'" /></div>';

    // Assistants UI
    echo '<div class="shec-field">';
    echo '  <label style="display:flex;align-items:center;gap:8px">';
    echo '      <input type="checkbox" name="shec_asst_enable" id="shec_asst_enable" '. checked($asst_enable, true, false) .' />';
    echo '      استفاده از Assistant برای خروجی نهایی (Step 5)';
    echo '  </label>';
    echo '  <div class="shec-help">توجه: برای <b>سؤالات</b> (Step 4) از Assistant استفاده نمی‌شود؛ فقط برای پاسخ نهایی در Step 5 در صورت فعال‌بودن.</div>';
    echo '</div>';

    echo '<div class="shec-field" id="shec_asst_final_wrap" style="'. ($asst_enable ? '' : 'display:none;') .'">';
    echo '  <label>Assistant ID (Final) — مثل: <code>asst_abc123...</code></label>';
    echo '  <input type="text" name="shec_asst_final_id" value="'. esc_attr($asst_final_id) .'" />';
    echo '  <div class="shec-help">Assistant شما باید طوری پیکربندی شود که فقط JSON معتبر برگرداند.</div>';
    echo '</div>';

    ?>
    <script>
    (function(){
      const chk = document.getElementById('shec_asst_enable');
      const wrap = document.getElementById('shec_asst_final_wrap');
      if (chk && wrap) {
        const sync = ()=> wrap.style.display = chk.checked ? '' : 'none';
        chk.addEventListener('change', sync); sync();
      }
    })();
    </script>
    <?php

    // Prompts
    echo '<div class="shec-field"><label>پرامپت سؤالات (بعد از استپ ۴)</label>
          <p class="shec-help">از <code>{{SUMMARY_JSON}}</code> استفاده کن. خروجی باید دقیقاً JSON با کلید <code>questions</code> و ۴ سؤال باشد.</p>
          <textarea style="width:100%;" name="shec_prompt_questions" rows="14">'.esc_textarea($p_q).'</textarea>
          <div class="shec-actions">
            <button type="button" class="button" data-restore="questions">بازگردانی پیش‌فرض</button>
          </div>
          </div>';

    echo '<div class="shec-field"><label>پرامپت نهایی (استپ ۵)</label>
          <p class="shec-help">از <code>{{PACK_JSON}}</code> استفاده کن. خروجی باید JSON با کلیدهای <code>method</code>، <code>graft_count</code>، <code>analysis</code> و ... باشد.</p>
          <textarea style="width:100%;" name="shec_prompt_final" rows="14">'.esc_textarea($p_f).'</textarea>
          <div class="shec-actions">
            <button type="button" class="button" data-restore="final">بازگردانی پیش‌فرض</button>
          </div>
          </div>';

    echo '<p><input type="submit" value="ذخیره" class="button button-primary" /></p>';
    echo '</form>';

    ?>
    <script>
    (function(){
      const wrap = document.querySelector('.shec-admin');
      if (!wrap) return;
      wrap.querySelectorAll('textarea').forEach(t=>{
        const fit=()=>{ t.style.height='auto'; t.style.height=(t.scrollHeight+6)+'px'; };
        fit(); t.addEventListener('input', fit);
      });
      wrap.querySelectorAll('[data-restore]').forEach(btn=>{
        btn.addEventListener('click', ()=> {
          const type = btn.getAttribute('data-restore');
          fetch(ajaxurl, {
            method:'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
            body: 'action=shec_get_default_prompt&_wpnonce=<?php echo wp_create_nonce('shec_nonce'); ?>&kind='+encodeURIComponent(type)
          }).then(r=>r.json()).then(json=>{
            if (json && json.success && json.data && json.data.prompt) {
              const txt = (type==='questions')
                ? wrap.querySelector('textarea[name="shec_prompt_questions"]')
                : wrap.querySelector('textarea[name="shec_prompt_final"]');
              txt.value = json.data.prompt;
              txt.dispatchEvent(new Event('input'));
            }
          });
        });
      });
    })();
    </script>
    <?php

    shec_admin_wrap_close();
}

/** =========================
 *  Restore default prompts (AJAX)
 * ========================= */
add_action('wp_ajax_shec_get_default_prompt', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'forbidden'], 403);
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'shec_nonce')) wp_send_json_error(['message'=>'bad nonce'], 403);

    $kind = sanitize_text_field($_POST['kind'] ?? '');
    if ($kind === 'questions' && function_exists('shec_prompt_questions_default')) {
        wp_send_json_success(['prompt'=> shec_prompt_questions_default()]);
    } elseif ($kind === 'final' && function_exists('shec_prompt_final_default')) {
        wp_send_json_success(['prompt'=> shec_prompt_final_default()]);
    }
    wp_send_json_error(['message'=>'unknown kind']);
});

/** =========================
 *  One-off patch for slashes
 *  /wp-admin/?shec_fix_prompts=1
 * ========================= */
add_action('admin_init', function(){
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['shec_fix_prompts'])) return;
    foreach (['shec_prompt_questions','shec_prompt_final'] as $k) {
        $v = get_option($k, '');
        if ($v !== '') update_option($k, wp_unslash($v));
    }
    wp_die('Prompts unslashed. حالا می‌توانید این بلاک را از فایل حذف کنید.');
});
