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

.shec-admin { direction: rtl; font-family: Shabnam, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; }
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
.shec-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:10px; margin:8px 0 18px; }
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
 *  1: gender+age+mobile
 *  2: + loss_pattern
 *  3: + uploads (>=1)
 *  4: + medical(has_medical & has_meds set)
 *  5: + contact(first_name,last_name,state,city,social)
 *  6: + ai.final exists
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

    // نزولی بر اساس آیدی
    $rows = $wpdb->get_results("SELECT id, data FROM {$wpdb->prefix}shec_users ORDER BY id DESC");

    shec_admin_wrap_open('data', 'داده‌های فرم هوشمند فخرایی');

    // جدول با ستون‌های دقیقاً مشخص‌شده
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

    // DataTables init (مرتب‌سازی روی ستون # که حالا ایندکسش 1 است)
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

    if (!$user_id) {
        echo '<p>اطلاعات کاربر پیدا نشد.</p>';
        shec_admin_wrap_close();
        return;
    }

    $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shec_users WHERE id = $user_id");
    if (!$row) {
        echo '<p>اطلاعات کاربر پیدا نشد.</p>';
        shec_admin_wrap_close();
        return;
    }

    $d    = json_decode($row->data, true);
    $step = shec_detect_progress($d);

    

    echo '<div class="shec-grid">';
    echo '  <div><strong>شناسه کاربر:</strong> '.intval($row->id).'</div>';
    echo '  <div><strong>مرحله:</strong> '.esc_html($step).'/6</div>';
    echo '  <div><strong>جنسیت:</strong> '.esc_html($d['gender'] ?? 'N/A').'</div>';
    echo '  <div><strong>سن:</strong> '.esc_html($d['age'] ?? 'N/A').'</div>';
    echo '  <div><strong>اعتماد به نفس:</strong> '.esc_html($d['confidence'] ?? 'N/A').'</div>';
    echo '  <div><strong>الگوی ریزش مو:</strong> '.esc_html($d['loss_pattern'] ?? 'N/A').'</div>';
    echo '  <div><strong>شماره تلفن:</strong> '.esc_html($d['mobile'] ?? ($d['contact']['mobile'] ?? 'N/A')).'</div>';
    echo '  <div><strong>نام:</strong> '.esc_html($d['contact']['first_name'] ?? 'N/A').'</div>';
    echo '  <div><strong>نام خانوادگی:</strong> '.esc_html($d['contact']['last_name'] ?? 'N/A').'</div>';
    echo '  <div><strong>سوشال:</strong> '.esc_html($d['contact']['social'] ?? 'N/A').'</div>';
    echo '</div>';

     // نمایش تصاویر آپلود شده
    $uploads = $d['uploads'] ?? [];
    if (!empty($uploads)) {
        echo "<h3>تصاویر آپلود شده:</h3>";
        foreach ($uploads as $image_url) {
            echo "<img src='{$image_url}' alt='Uploaded Image' style='max-width: 200px; height: 200px; margin-bottom: 10px;' />";
        }
    } else {
        echo "<p>هیچ تصویری برای این کاربر آپلود نشده است.</p>";
    }

    // سؤالات/پاسخ‌ها
    $qs  = $d['ai']['followups']['questions'] ?? [];
    $ans = $d['ai']['followups']['answers'] ?? [];

    echo '<h2 class="shec-subtitle">سوالات و پاسخ‌ها</h2>';
    if ($qs) {
        echo '<ol class="shec-qa">';
        foreach ($qs as $i => $q) {
            $a = $ans[$i] ?? '';
            echo '<li><strong>'.esc_html($q).'</strong><br><strong>پاسخ:</strong> '.esc_html($a ?: '—').'</li>';
        }
        echo '</ol>';
    } else {
        echo '<p>—</p>';
    }

    // نتیجه نهایی
    $final = $d['ai']['final'] ?? null;
    echo '<h2 class="shec-subtitle">نتیجه‌گیری هوش مصنوعی</h2>';
    if ($final) {
        echo '<div class="shec-result">';
        echo '  <div><strong>روش:</strong> '.esc_html($final['method'] ?? '—').'</div>';
        echo '  <div><strong>تخمین گرافت:</strong> '.esc_html($final['graft_count'] ?? '—').'</div>';
        echo '  <div><strong>تحلیل:</strong><br><div class="shec-analysis">'.esc_html($final['analysis'] ?? '—').'</div></div>';
        echo '</div>';
    } else {
        echo '<p>—</p>';
    }

    shec_admin_wrap_close();
}

/** =========================
 *  Settings page
 * ========================= */
function shec_display_settings() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['shec_api_key']))          update_option('shec_api_key', sanitize_text_field($_POST['shec_api_key']));
        if (isset($_POST['shec_sms_api']))          update_option('shec_sms_api', sanitize_text_field($_POST['shec_sms_api']));
        if (isset($_POST['shec_telegram_api']))     update_option('shec_telegram_api', sanitize_text_field($_POST['shec_telegram_api']));
        if (isset($_POST['shec_prompt_questions'])) update_option('shec_prompt_questions', shec_sanitize_prompt_text($_POST['shec_prompt_questions']));
        if (isset($_POST['shec_prompt_final']))     update_option('shec_prompt_final', shec_sanitize_prompt_text($_POST['shec_prompt_final']));
        echo '<div class="updated"><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
    }

    $api_key  = get_option('shec_api_key', '');
    $sms_api  = get_option('shec_sms_api', '');
    $telegram = get_option('shec_telegram_api', '');
    $p_q      = get_option('shec_prompt_questions', '');
    $p_f      = get_option('shec_prompt_final', '');

    if (!$p_q && function_exists('shec_prompt_questions_default')) $p_q = shec_prompt_questions_default();
    if (!$p_f && function_exists('shec_prompt_final_default'))     $p_f = shec_prompt_final_default();

    shec_admin_wrap_open('settings', 'تنظیمات افزونه');

    echo '<form method="POST" class="shec-form">';

    echo '<div class="shec-field"><label>API Key (OpenAI)</label>
          <input type="text" name="shec_api_key" value="'.esc_attr($api_key).'" /></div>';

    echo '<div class="shec-field"><label>پنل SMS</label>
          <input type="text" name="shec_sms_api" value="'.esc_attr($sms_api).'" /></div>';

    echo '<div class="shec-field"><label>ربات تلگرام</label>
          <input type="text" name="shec_telegram_api" value="'.esc_attr($telegram).'" /></div>';

    echo '<hr class="shec-sep"/>';

    echo '<div class="shec-field"><label>پرامپت سؤالات (بعد از استپ ۴)</label>
          <p class="shec-help">از <code>{{SUMMARY_JSON}}</code> استفاده کن. خروجی باید دقیقاً JSON با کلید <code>questions</code> و ۴ سؤال باشد.</p>
          <textarea name="shec_prompt_questions" rows="14">'.esc_textarea($p_q).'</textarea>
          <div class="shec-actions">
            <button type="button" class="button" data-restore="questions">بازگردانی پیش‌فرض</button>
          </div>
          </div>';

    echo '<div class="shec-field"><label>پرامپت نهایی (استپ ۵)</label>
          <p class="shec-help">از <code>{{PACK_JSON}}</code> استفاده کن. خروجی باید JSON با کلیدهای <code>method</code>، <code>graft_count</code>، <code>analysis</code> باشد.</p>
          <textarea name="shec_prompt_final" rows="14">'.esc_textarea($p_f).'</textarea>
          <div class="shec-actions">
            <button type="button" class="button" data-restore="final">بازگردانی پیش‌فرض</button>
          </div>
          </div>';

    echo '<p><input type="submit" value="ذخیره" class="button button-primary" /></p>';
    echo '</form>';

    // اتوسایز + بازگردانی پیش‌فرض
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
                ? wrap.querySelector('textarea[name=\"shec_prompt_questions\"]')
                : wrap.querySelector('textarea[name=\"shec_prompt_final\"]');
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
