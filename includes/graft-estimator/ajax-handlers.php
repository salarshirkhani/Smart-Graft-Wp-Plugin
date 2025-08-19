<?php
/**
 * Smart Hair Graft Calculator — AJAX Handlers
 * Version: 1.2.4 (guarded helpers to avoid redeclare)
 */
if (!defined('ABSPATH')) exit;

/* ---------------------------------
 * Helpers (guarded)
 * --------------------------------- */
if (!function_exists('shec_table')) {
  function shec_table(){ global $wpdb; return $wpdb->prefix.'shec_users'; }
}

if (!function_exists('shec_check_nonce_or_bypass')) {
  function shec_check_nonce_or_bypass() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host==='localhost' || $host==='127.0.0.1') return; // local dev
    $nonce = $_POST['_nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'shec_nonce')) {
      wp_send_json_error(['message'=>'Invalid nonce'], 403);
    }
  }
}

/** تمام دسترسی‌ها با wp_user_id (شناسه‌ی یکتای فرم) */
if (!function_exists('shec_get_data')) {
  function shec_get_data($uid){
    global $wpdb;
    $json = $wpdb->get_var($wpdb->prepare(
      "SELECT data FROM ".shec_table()." WHERE wp_user_id=%d",
      (int)$uid
    ));
    return $json ? json_decode($json, true) : [];
  }
}
if (!function_exists('shec_update_data')) {
  function shec_update_data($uid, array $data){
    global $wpdb;
    return $wpdb->update(
      shec_table(),
      ['data'=>wp_json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)],
      ['wp_user_id'=>(int)$uid],
      ['%s'], ['%d']
    );
  }
}
/** تولید شناسه‌ی یکتا مثل قبل: max(wp_user_id)+1 */
if (!function_exists('shec_generate_form_uid')) {
  function shec_generate_form_uid(){
    global $wpdb;
    $max = (int)$wpdb->get_var("SELECT MAX(wp_user_id) FROM ".shec_table());
    return $max>0 ? ($max+1) : 1;
  }
}

if (!function_exists('shec_openai_api_key')) {
  function shec_openai_api_key(){ return trim((string)get_option('shec_api_key','')); }
}
if (!function_exists('shec_openai_chat')) {
  function shec_openai_chat(array $messages, array $opts=[]){
    $api_key = shec_openai_api_key();
    if (!$api_key) return ['ok'=>false,'error'=>'OpenAI API key not set','http_code'=>0];

    $model = $opts['model'] ?? 'gpt-4o-mini';
    $body = [
      'model' => $model,
      'temperature' => $opts['temperature'] ?? 0.2,
      'response_format' => ['type'=>'json_object'],
      'messages' => $messages,
    ];
    $res = wp_remote_post('https://api.openai.com/v1/chat/completions', [
      'headers' => [
        'Authorization' => 'Bearer '.$api_key,
        'Content-Type'  => 'application/json',
      ],
      'body' => wp_json_encode($body),
      'timeout' => 45,
    ]);
    if (is_wp_error($res)) return ['ok'=>false,'error'=>$res->get_error_message(),'http_code'=>0];
    $code = wp_remote_retrieve_response_code($res);
    $json = json_decode(wp_remote_retrieve_body($res), true);
    $content = $json['choices'][0]['message']['content'] ?? '';
    $out = ['ok'=> $code<400, 'content'=>$content, 'http_code'=>$code, 'model'=>$model];
    if ($code>=400) $out['error'] = $json['error']['message'] ?? ("OpenAI HTTP ".$code);
    return $out;
  }
}
if (!function_exists('shec_json_decode_safe')) {
  function shec_json_decode_safe($str){
    if (!is_string($str)) return null;
    $str = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($str));
    $data = json_decode($str, true);
    return is_array($data) ? $data : null;
  }
}
if (!function_exists('shec_set_rate_limit_block')) {
  function shec_set_rate_limit_block($seconds=180){
    $until = time() + max(60, min((int)$seconds, 600));
    set_transient('shec_ai_block_until', $until, $until - time());
    return $until;
  }
}

/* ===== Dynamic prompts (guarded) ===== */
if (!function_exists('shec_prompt_questions_default')) {
  function shec_prompt_questions_default() {
    return <<<EOT
# نقش شما
شما دستیار پذیرش کلینیک کاشت مو هستید. فقط JSON برگردان.

# خروجی لازم
{"questions": ["...", "...", "...", "..."]}

# قواعد
- دقیقاً ۴ سؤال بله/خیر، کوتاه، ساده و غیرتخصصی، فارسی.
- سؤال‌ها را بر مبنای خلاصه‌ی بیمار تنظیم کن (جنسیت/سن/الگوی ریزش/پرونده پزشکی/مصرف دارو/شدت و روند).
- از تکرار چیزی که قبلاً معلوم است خودداری کن؛ نکات تکمیلیِ مهم را بپرس.
- هیچ متن اضافه‌ای نزن؛ فقط JSON معتبر.

# ورودی
خلاصه‌ی بیمار (JSON):
{{SUMMARY_JSON}}
EOT;
  }
}
if (!function_exists('shec_prompt_final_default')) {
  function shec_prompt_final_default() {
    return <<<EOT
# نقش شما
شما دستیار متخصص کاشت مو در کلینیک فخرائی هستید. فقط و فقط JSON معتبر برگردان.

# خروجی لازم (دقیقاً همین کلیدها)
{
  "method": "FIT",
  "graft_count": 0,
  "analysis": "<تحلیل کوتاه 100 تا 160 کلمه فارسی، دوستانه و همدلانه>",
  "pattern_explain": {
    "label": "Norwood 5 | Ludwig II | …",
    "what_it_is": "<توضیح خیلی کوتاه و قابل‌فهم از الگو>",
    "why_happens": "<علت‌های معمول: ژنتیک/هورمون/سبک زندگی؛ خیلی کوتاه>",
    "fit_ok": true,
    "note": "<اگر Norwood 1 یا Ludwig I است: معمولاً کاشت لازم نیست و درمان نگه‌دارنده پیشنهاد می‌شود؛ در غیر این صورت خالی>"
  },
  "concern_box": "<پاسخ همدلانه و آرام‌کننده دقیقاً متناسب با دغدغه‌ی کاربر؛ از دعوت مستقیم به مراجعه خودداری کن مگر در یک جمله‌ی پایانی اختیاری>",
  "followups": [
    {"q":"<سؤال 1>","a":"بله|خیر","tip":"<یک توصیه‌ی عملی و کوتاه بر اساس پاسخ>"},
    {"q":"<سؤال 2>","a":"بله|خیر","tip":"<…>"},
    {"q":"<سؤال 3>","a":"بله|خیر","tip":"<…>"},
    {"q":"<سؤال 4>","a":"بله|خیر","tip":"<…>"}
  ],
  "pre_op": ["<۰ تا ۳ توصیه‌ی کوتاه پیش از کاشت کاملاً متناسب با پاسخ‌ها>"],
  "post_op":["<۰ تا ۳ توصیه‌ی کوتاه پس از کاشت کاملاً متناسب با پاسخ‌ها>"]
}

# قواعد مهم
- تمام پاسخ‌ها فارسی و محاوره‌ای-مودبانه باشد؛ لحن حتماً همدلانه و مطمئن‌کننده.
- method همیشه "FIT" باشد. (کلینیک فقط FUE/FIT انجام می‌دهد؛ از FUT یا روش‌های دیگر نام نبر.)
- graft_count را 0 بگذار؛ این عدد را سیستم محاسبه می‌کند.
- analysis: 100 تا 160 کلمه، دوستانه، بدون اصطلاحات پیچیده؛ حتماً شامل:
  1) اشاره‌ی ساده به علت احتمالی ریزش (ژنتیک/هورمونی/استرس…)
  2) اطمینان‌بخشی درباره‌ی نتیجه‌ی طبیعی و تراکم مناسب در کلینیک فخرائی
  3) 2–3 پیشنهاد ساده تا زمان کاشت (مثال: شامپوی ملایم، پرهیز از سیگار/قلیان، خواب کافی)
  4) جمع‌بندی روشن که مسیر درمان مشخص و قابل پیگیری است
- concern_box: دقیقاً به دغدغه‌ی ثبت‌شده‌ی کاربر واکنش نشان بده و همدلی کن. نمونه‌ی برخورد:
  * «هزینه»: اطمینان بده برآورد شفاف و برنامه‌ی مالی منطقی داریم و کیفیت اولویت است.
  * «درد»: بی‌حسی موضعی و پایش مداوم؛ تجربه‌ی درد ناچیز.
  * «نقاهت»: کوتاه و قابل‌مدیریت با راهنمای مرحله‌به‌مرحله.
  * «طول کشیدن نتیجه»: رشد مو مرحله‌ای است و از ماه‌های اول تغییرات شروع می‌شود.
  * اگر دغدغه‌ی دیگری بود، باز هم همدلانه، کوتاه و مشخص پاسخ بده.
- pattern_explain:
  * اگر gender=male → از loss_pattern مثل "pattern-5" به Norwood 5 تبدیل کن؛ بازه را 1..6 در نظر بگیر.
  * اگر gender=female → "pattern-x" را تقریبی به Ludwig I (x∈{1,2})، Ludwig II (x∈{3,4})، Ludwig III (x∈{5,6}) نگاشت کن.
  * اگر Norwood 1 یا Ludwig I بود: در note بگو «معمولاً کاشت لازم نیست و درمان نگه‌دارنده پیشنهاد می‌شود».
  * fit_ok برای تمام موارد بجز Norwood 1/Ludwig I true باشد؛ برای آن‌ها true بماند ولی در note توضیح احتیاطی بده.
- followups:
  * ورودی شامل followups با جفت‌های q/a است. همان سؤال‌ها را تکرار کن، مقدار a را به «بله/خیر» نگاشت کن (نه yes/no).
  * برای هر مورد یک "tip" کاملاً مرتبط و کوتاه بنویس (مثلاً برای سیگار: پرهیز ۱۰ روز قبل و ۷ روز بعد؛ برای استرس/خواب: روتین آرام‌سازی و خواب منظم؛ برای التهاب/عفونت: ابتدا درمان سپس کاشت؛ برای بدترشدن روند: درمان نگه‌دارنده قبل از کاشت).
- pre_op و post_op:
  * عمومی ننویس؛ ۰ تا ۳ توصیه‌ی خیلی کوتاه، دقیقاً مبتنی بر پاسخ‌های همان کاربر (اگر سیگار=بله → پرهیز دخانیات؛ اگر خواب ناکافی=بله → بهداشت خواب؛ اگر التهاب=بله → اول کنترل التهاب).
- از جملات کلی مثل «برای نتیجه دقیق‌تر حضوری بیا» خودداری کن؛ فقط در یک جملهٔ پایانیِ اختیاری و مودبانه می‌توانی پیشنهاد مشاوره بدهی.
- هیچ متن اضافه، توضیح، Markdown یا کدبلاک نده. فقط JSON شیء واحد.

# ورودی
اطلاعات بیمار (JSON):
{{PACK_JSON}}

# فقط JSON

EOT;
  }
}

if (!function_exists('shec_get_prompt_questions')) {
  function shec_get_prompt_questions(){ $p=get_option('shec_prompt_questions',''); return $p ?: shec_prompt_questions_default(); }
}
if (!function_exists('shec_get_prompt_final')) {
  function shec_get_prompt_final(){ $p=get_option('shec_prompt_final',''); return $p ?: shec_prompt_final_default(); }
}
if (!function_exists('shec_render_template')) {
  function shec_render_template($tpl, array $vars){
    foreach ($vars as $k=>$v) {
      if (is_array($v) || is_object($v)) {
        $v = wp_json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      }
      $tpl = str_replace('{{'.$k.'}}', (string)$v, $tpl);
    }
    return $tpl;
  }
}

/* ---------------------------------
 * STEP 1  (INSERT new row, return unique uid)
 * --------------------------------- */
if (!function_exists('shec_handle_step1')) {
  function shec_handle_step1(){
    shec_check_nonce_or_bypass();
    global $wpdb;

    $normalize = function($m){
      $m = preg_replace('/\D+/', '', (string)$m);
      if (strpos($m,'0098')===0) $m = substr($m,4);
      if (strpos($m,'98')===0)   $m = substr($m,2);
      if (strpos($m,'9')===0)    $m = '0'.$m;
      return $m;
    };

    $gender     = sanitize_text_field($_POST['gender'] ?? '');
    $age        = sanitize_text_field($_POST['age'] ?? '');
    $confidence = sanitize_text_field($_POST['confidence'] ?? '');
    $mobile     = $normalize(sanitize_text_field($_POST['mobile'] ?? ''));

    $valid_ages = ['18-23','24-29','30-35','36-43','44-56','+56'];
    if (!$gender || !in_array($age,$valid_ages,true)) {
      wp_send_json_error(['message'=>'لطفاً جنسیت و بازه سنی معتبر وارد کنید.']);
    }
    if (!preg_match('/^09\d{9}$/',$mobile)) {
      wp_send_json_error(['message'=>'شماره موبایل معتبر نیست. مثال: 09xxxxxxxxx']);
    }

    // ✅ شناسه یکتای فرم (max+1)
    $form_uid = shec_generate_form_uid();

    $data = [
      'gender'=>$gender,
      'age'=>$age,
      'mobile'=>$mobile,
      'confidence'=>$confidence
    ];

    $wpdb->insert(shec_table(), [
      'wp_user_id'=>$form_uid,
      'data'=>wp_json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
    ], ['%d','%s']);

    if (!$wpdb->insert_id) {
      wp_send_json_error(['message'=>'در ذخیره‌سازی اولیه خطا رخ داد.']);
    }

    wp_send_json_success(['user_id'=>$form_uid]);
  }
}
add_action('wp_ajax_shec_step1','shec_handle_step1');
add_action('wp_ajax_nopriv_shec_step1','shec_handle_step1');

/* ---------------------------------
 * STEP 2  (UPDATE)
 * --------------------------------- */
if (!function_exists('shec_handle_step2')) {
  function shec_handle_step2(){
    shec_check_nonce_or_bypass();

    $uid     = intval($_POST['user_id'] ?? 0);
    $pattern = sanitize_text_field($_POST['loss_pattern'] ?? '');
    if ($uid<=0 || !$pattern) wp_send_json_error(['message'=>'اطلاعات مرحله ۲ ناقص است']);

    $data = shec_get_data($uid);
    if (!$data) wp_send_json_error(['message'=>'شناسه فرم معتبر نیست.']);

    $data['loss_pattern'] = $pattern;
    shec_update_data($uid, $data);

    wp_send_json_success();
  }
}
add_action('wp_ajax_shec_step2','shec_handle_step2');
add_action('wp_ajax_nopriv_shec_step2','shec_handle_step2');

/* ---------------------------------
 * STEP 3 (upload)
 * --------------------------------- */
if (!function_exists('shec_handle_step3')) {
  function shec_handle_step3(){
    shec_check_nonce_or_bypass();

    $uid      = intval($_POST['user_id'] ?? 0);
    $position = sanitize_text_field($_POST['position'] ?? '');
    if ($uid<=0 || empty($_FILES)) wp_send_json_error(['message'=>'فایل یا کاربر معتبر نیست.']);

    $data = shec_get_data($uid);
    if (!$data) wp_send_json_error(['message'=>'شناسه فرم معتبر نیست.']);

    require_once ABSPATH.'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload($_FILES[array_key_first($_FILES)], ['test_form'=>false]);
    if (isset($uploaded['error'])) wp_send_json_error(['message'=>$uploaded['error']]);

    if (!isset($data['uploads'])) $data['uploads'] = [];
    $data['uploads'][$position] = $uploaded['url'];
    shec_update_data($uid, $data);

    wp_send_json_success(['file'=>$uploaded['url']]);
  }
}
add_action('wp_ajax_shec_step3','shec_handle_step3');
add_action('wp_ajax_nopriv_shec_step3','shec_handle_step3');

/* ---------------------------------
 * STEP 4 (medical)
 * --------------------------------- */
if (!function_exists('shec_handle_step4')) {
  function shec_handle_step4(){
    shec_check_nonce_or_bypass();

    $uid = intval($_POST['user_id'] ?? 0);
    if ($uid<=0) wp_send_json_error(['message'=>'کاربر معتبر نیست.']);

    $has_medical = isset($_POST['has_medical']) ? sanitize_text_field($_POST['has_medical']) : '';
    $has_meds    = isset($_POST['has_meds'])    ? sanitize_text_field($_POST['has_meds'])    : '';
    if (!in_array($has_medical,['yes','no'],true)) wp_send_json_error(['message'=>'لطفاً وضعیت ابتلا به بیماری را مشخص کنید.']);
    if (!in_array($has_meds,['yes','no'],true))    wp_send_json_error(['message'=>'لطفاً وضعیت مصرف دارو را مشخص کنید.']);
    if ($has_meds==='yes') {
      $meds_list = trim(sanitize_text_field($_POST['meds_list'] ?? ''));
      if ($meds_list==='') wp_send_json_error(['message'=>'نام دارو را وارد کنید.']);
    }

    $data = shec_get_data($uid);
    if (!$data) wp_send_json_error(['message'=>'شناسه فرم معتبر نیست.']);

    $medical = array_map('sanitize_text_field', $_POST);
    unset($medical['_nonce'],$medical['action'],$medical['user_id']);
    $data['medical'] = $medical;

    shec_update_data($uid, $data);
    wp_send_json_success();
  }
}
add_action('wp_ajax_shec_step4','shec_handle_step4');
add_action('wp_ajax_nopriv_shec_step4','shec_handle_step4');

/* ---------------------------------
 * STEP 5 (contact)
 * --------------------------------- */
if (!function_exists('shec_handle_step5')) {
  function shec_handle_step5(){
    shec_check_nonce_or_bypass();

    $uid = intval($_POST['user_id'] ?? 0);
    if ($uid<=0) wp_send_json_error(['message'=>'کاربر معتبر نیست.']);

    $data = shec_get_data($uid);
    if (!$data) wp_send_json_error(['message'=>'داده‌ای برای این کاربر پیدا نشد']);

    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name  = sanitize_text_field($_POST['last_name'] ?? '');
    $state      = sanitize_text_field($_POST['state'] ?? '');
    $city       = sanitize_text_field($_POST['city'] ?? '');
    $social     = sanitize_text_field($_POST['social'] ?? '');
    if (!$first_name || !$last_name || !$state || !$city || !$social) {
      wp_send_json_error(['message'=>'تمامی فیلدهای مرحله ۵ باید پر شوند.']);
    }

    if (!isset($data['contact'])) $data['contact'] = [];
    $data['contact'] = array_merge($data['contact'], compact('first_name','last_name','state','city','social'));
    shec_update_data($uid, $data);

    wp_send_json_success([
      'user'=>$data,
      'ai_result'=>wp_json_encode(['method'=>'FIT','graft_count'=>2800,'analysis'=>'نمونهٔ آزمایشی'], JSON_UNESCAPED_UNICODE)
    ]);
  }
}
add_action('wp_ajax_shec_step5','shec_handle_step5');
add_action('wp_ajax_nopriv_shec_step5','shec_handle_step5');

/* ---------------------------------
 * AI QUESTIONS (store into DB)
 * --------------------------------- */
/* ---------------------------------
 * AI QUESTIONS (store into DB) — robust 4-questions
 * --------------------------------- */
if (!function_exists('shec_ai_questions')) {
  function shec_ai_questions() {
    shec_check_nonce_or_bypass();

    $uid = intval($_POST['user_id'] ?? 0);
    if ($uid<=0) wp_send_json_error(['message'=>'کاربر معتبر نیست']);

    $data = shec_get_data($uid);
    if (!$data) wp_send_json_error(['message'=>'داده‌ای برای این کاربر پیدا نشد']);

    // خلاصهٔ پایدار برای کش
    $summary = [
      'gender'        => $data['gender'] ?? null,
      'age'           => $data['age'] ?? null,
      'confidence'    => $data['confidence'] ?? null,
      'loss_pattern'  => $data['loss_pattern'] ?? null,
      'medical'       => $data['medical'] ?? null,
      'uploads_count' => (isset($data['uploads']) && is_array($data['uploads'])) ? count($data['uploads']) : 0,
    ];
    $fp = sha1( wp_json_encode($summary, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) );

    // کش اگر ۴ تا معتبر و تازه
    $prev = $data['ai']['followups'] ?? [];
    if (!empty($prev['questions']) && count((array)$prev['questions']) === 4
        && ($prev['fp'] ?? '') === $fp
        && (time() - (int)($prev['generated_at'] ?? 0)) < 7*24*3600) {
      return wp_send_json_success([
        'questions' => array_values($prev['questions']),
        'debug'     => ['marker'=>'aiq_cached4','source'=>'cache','generated_at'=>$prev['generated_at'],'fp'=>$fp],
        'summary'   => $summary
      ]);
    }

    // fallback ثابت
    $fallback = [
      'آیا در خانواده‌تان سابقهٔ ریزش مو وجود دارد؟',
      'آیا طی ۱۲ ماه گذشته شدت ریزش موی شما بیشتر شده است؟',
      'آیا در حال حاضر سیگار یا قلیان مصرف می‌کنید؟',
      'آیا خواب و استرس شما در ماه‌های اخیر بدتر شده است؟'
    ];

    $questions = null;
    $debug = ['marker'=>'aiq_dyn4','source'=>'fallback','error'=>null,'retry'=>0];

    // پرامپت (اول از تنظیمات، بعد پیش‌فرض)
    $prompt_template = shec_get_prompt_questions();
    $summary_json    = wp_json_encode($summary, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if (strpos($prompt_template, '{{SUMMARY_JSON}}') !== false) {
      $prompt_user = str_replace('{{SUMMARY_JSON}}', $summary_json, $prompt_template);
    } else {
      $prompt_user = $prompt_template . "\n\nخلاصهٔ بیمار (JSON):\n" . $summary_json;
    }

    if (shec_openai_api_key()) {
      // تماس ۱ با system سخت‌گیر و response_format=json_object (تو shec_openai_chat هم ست می‌کنی)
      $resp = shec_openai_chat(
        [
          ['role'=>'system','content'=>'فقط یک شیء JSON معتبر برگردان. دقیقا با کلید "questions" و آرایه‌ای از ۴ رشتهٔ کوتاه فارسی. هیچ متن اضافه‌ای ننویس.'],
          ['role'=>'user',  'content'=>$prompt_user],
        ],
        ['temperature'=>0.0]
      );

      if ($resp['ok']) {
        $raw    = (string)($resp['content'] ?? '');
        $parsed = shec_json_force_decode_object($raw);
        $q      = shec_extract_questions_from_json($parsed);   // ← کلیدهای جایگزین و آرایهٔ آبجکت/رشته را هندل می‌کند
        $q      = shec_ensure_four_questions($q, $fallback);   // ← همیشه ۴تا می‌کند (زیاد=برش، کم=پر)
        if (count($q) === 4) {
          $questions = $q;
          $debug['source'] = 'openai';
        } else {
          $debug['error'] = 'normalize-fail-after-openai';
        }
      } else {
        $debug['error'] = $resp['error'] ?? 'openai call failed';
      }

      // اگر بار اول به ۴ نرسید → یک ریتری با دستور کوتاه‌تر و صریح‌تر
      if (!$questions || count($questions)!==4) {
        $debug['retry'] = 1;
        $resp2 = shec_openai_chat(
          [
            ['role'=>'system','content'=>'Return ONLY this exact JSON shape in Persian and nothing else: {"questions":["","","",""]}'],
            ['role'=>'user','content'=>"با توجه به این خلاصهٔ بیمار، فقط ۴ سؤال کوتاه بله/خیر بده:\n".$summary_json],
          ],
          ['temperature'=>0.0]
        );
        if ($resp2['ok']) {
          $raw2   = (string)($resp2['content'] ?? '');
          $parsed2= shec_json_force_decode_object($raw2);
          $q2     = shec_extract_questions_from_json($parsed2);
          $q2     = shec_ensure_four_questions($q2, $fallback);
          if (count($q2)===4) { $questions=$q2; $debug['source']='openai'; }
          else { $debug['error'] = 'normalize-fail-after-retry'; }
        } else {
          $debug['error'] = ($debug['error'] ?: '') . ' | retry: ' . ($resp2['error'] ?? 'openai retry failed');
        }
      }
    } else {
      $debug['error'] = 'no api key';
    }

    // اگر هنوز ۴تا نشد → باگ نکن، fallback بده ولی لاگِ دلیل حفظ بشه
    if (!$questions || count($questions)!==4) {
      $questions = shec_ensure_four_questions((array)$questions, $fallback);
      $debug['source'] = 'openai+repair';
      if (defined('WP_DEBUG') && WP_DEBUG && !empty($debug['error'])) {
        error_log('[shec_ai_questions] repair: '. print_r($debug,true));
      }
    }

    // ذخیره در DB
    if (!isset($data['ai'])) $data['ai'] = [];
    $data['ai']['followups'] = [
      'questions'    => array_values($questions),
      'generated_at' => time(),
      'fp'           => $fp,
      'source'       => $debug['source']
    ];
    shec_update_data($uid, $data);

    wp_send_json_success(['questions'=>$questions, 'debug'=>$debug, 'summary'=>$summary]);
  }
}

/* ===== Helpers (paste once) ===== */

/** JSON را «هر طور شده» به شیء تبدیل می‌کند (کدبلاک/متن اضافه را می‌بُرد) */
if (!function_exists('shec_json_force_decode_object')) {
  function shec_json_force_decode_object($text) {
    $text = trim((string)$text);
    $text = preg_replace('~^```(?:json)?\s*|\s*```$~u', '', $text); // حذف ```json
    $j = json_decode($text, true);
    if (is_array($j)) return $j;
    if (preg_match('~\{(?:[^{}]|(?R))*\}~su', $text, $m)) {
      $j = json_decode($m[0], true);
      if (is_array($j)) return $j;
    }
    // احتمالاً مدل با \n تفکیک کرده؛ برگردانیم تا extractor هندل کند
    return ['__raw'=>$text];
  }
}

/** از ساختارهای مختلف questions را بیرون می‌کشد: کلیدهای جایگزین، آرایهٔ آبجکت، یا متن خط‌به‌خط */
if (!function_exists('shec_extract_questions_from_json')) {
  function shec_extract_questions_from_json($parsed) {
    $arr = [];

    // ۱) اگر شیء است: دنبال کلیدهای رایج
    if (is_array($parsed)) {
      $candidates = ['questions','سوالات','پرسش‌ها','qs','items','list'];
      foreach ($candidates as $k) {
        if (isset($parsed[$k])) { $arr = $parsed[$k]; break; }
      }
      // اگر هنوز هیچ چیز: شاید خودِ parsed آرایه باشد
      if (!$arr && array_keys($parsed)===range(0,count($parsed)-1)) {
        $arr = $parsed;
      }
      // اگر هنوز هیچ چیز و __raw داریم: سعی کن خط به خط جدا کنی
      if (!$arr && !empty($parsed['__raw']) && is_string($parsed['__raw'])) {
        $lines = preg_split('~\r?\n+~', $parsed['__raw']);
        $arr = array_values(array_filter(array_map('trim', $lines)));
      }
    }

    // ۲) اگر اعضای آرایه آبجکت بودن، فیلدهای متعارف را بردار
    $out = [];
    if (is_array($arr)) {
      foreach ($arr as $it) {
        if (is_string($it)) {
          $out[] = $it;
        } elseif (is_array($it)) {
          $cand = $it['q'] ?? ($it['text'] ?? ($it['title'] ?? ($it['label'] ?? '')));
          if ($cand !== '') $out[] = $cand;
        }
      }
    }

    // ۳) تمیزکاری (حذف شماره‌گذاری/تکراری/کوتاه‌سازی)
    return shec_clean_questions_array($out);
  }
}

/** تمیزسازی و یکتا و کوتاه‌سازی رشته‌ها */
if (!function_exists('shec_clean_questions_array')) {
  function shec_clean_questions_array($arr) {
    if (!is_array($arr)) return [];
    $out = [];
    foreach ($arr as $x) {
      $s = trim((string)$x);
      // حذف شماره‌گذاری ابتدایی: "1) "، "۱. "، "- "، "• "
      $s = preg_replace('~^\s*([0-9۰-۹]+[\)\.\-:]|\-|\•)\s*~u', '', $s);
      // کوتاه‌سازی خیلی طولانی‌ها
      if (mb_strlen($s,'UTF-8') > 140) $s = mb_substr($s, 0, 140, 'UTF-8').'…';
      if ($s !== '' && !in_array($s, $out, true)) $out[] = $s;
    }
    return $out;
  }
}

/** تضمین دقیقاً ۴ سؤال: زیاد→برش، کم→با fallback پُر */
if (!function_exists('shec_ensure_four_questions')) {
  function shec_ensure_four_questions($arr, $fallback) {
    $arr = shec_clean_questions_array($arr);
    if (count($arr) >= 4) return array_slice($arr, 0, 4);
    foreach ($fallback as $f) {
      if (!in_array($f, $arr, true)) $arr[] = $f;
      if (count($arr) >= 4) break;
    }
    while (count($arr) < 4) $arr[] = $fallback[0]; // بسیار نادر
    return $arr;
  }
}

add_action('wp_ajax_shec_ai_questions', 'shec_ai_questions');
add_action('wp_ajax_nopriv_shec_ai_questions', 'shec_ai_questions');

/* ---------------------------------
 * FINALIZE (store answers + final)
 * --------------------------------- */
if (!function_exists('shec_finalize')) {
function shec_finalize(){
  shec_check_nonce_or_bypass();

  $uid = intval($_POST['user_id'] ?? 0);
  if ($uid<=0) wp_send_json_error(['message'=>'کاربر معتبر نیست']);

  // 1) پاسخ‌های کاربر
  $answers = (isset($_POST['answers']) && is_array($_POST['answers'])) ? array_values($_POST['answers']) : [];

  // 2) داده فعلی
  $data = shec_get_data($uid);
  if (!$data) wp_send_json_error(['message'=>'داده‌ای برای این کاربر پیدا نشد']);

  // 3) ساخت QA از سوال‌های ذخیره‌شده
  $questions = $data['ai']['followups']['questions'] ?? [];
  $qa = [];
  for ($i=0; $i<count($questions); $i++) {
    $qa[] = ['q'=>(string)$questions[$i], 'a'=>(string)($answers[$i] ?? '')];
  }

  // 4) ذخیره فوری QA (فیکس اصلی)
  if (!isset($data['ai'])) $data['ai'] = [];
  if (!isset($data['ai']['followups'])) $data['ai']['followups'] = [];
  $data['ai']['followups']['qa']       = $qa;
  $data['ai']['followups']['answers']  = $answers;
  $data['ai']['followups']['generated_at'] = time();
  shec_update_data($uid, $data); // ← مهم: همین‌جا ذخیره شود

  // 5) پکیج ورودی برای AI (با QA)
  $pack = [
    'gender'       => $data['gender'] ?? null,
    'age'          => $data['age'] ?? null,
    'loss_pattern' => $data['loss_pattern'] ?? null,
    'medical'      => $data['medical'] ?? null,
    'uploads'      => array_values($data['uploads'] ?? []),
    'followups'    => $qa,                               // ← QA همین رکورد
    'contact'      => $data['contact'] ?? null,
    'mobile'       => $data['mobile'] ?? null,
  ];

  // 6) تماس با AI
  $prompt_user = shec_render_template(shec_get_prompt_final(), ['PACK_JSON' => $pack]);
  $resp = shec_openai_chat([['role'=>'user','content'=>$prompt_user]], ['temperature'=>0.2]);

  // 7) خروجی امن + سازگار با اسکیما جدید
  $final = [
    'method'           => 'FIT',
    'graft_count'      => 2500,
    'analysis'         => 'بر اساس اطلاعات موجود، روش FIT می‌تواند مناسب باشد. برای ارزیابی دقیق‌تر، معاینه حضوری توصیه می‌شود.',
    // فیلدهای جدید (اختیاری)
    'concern_box'      => '',
    'pattern_explain'  => [],
    'followups'        => [],   // هر آیتم: {q,a,coach/tip}
    'followup_summary' => '',
  ];

  if (!empty($resp['ok'])) {
    $parsed = shec_json_decode_safe($resp['content']);
    if (is_array($parsed)) {
      foreach (['method','graft_count','analysis','concern_box','pattern_explain','followups','followup_summary'] as $k) {
        if (isset($parsed[$k])) $final[$k] = $parsed[$k];
      }
      // فقط FIT نمایش/ذخیره شود
      $final['method'] = 'FIT';
    }
  } else if (($resp['http_code'] ?? 0) == 429) {
    shec_set_rate_limit_block(180);
  }

  // 8) ثبت زمان تولید نتیجه و ذخیره
  $final['generated_at'] = time();

  // بازخوانی، سپس ادغام (برای احتیاط)
  $data = shec_get_data($uid);
  if (!isset($data['ai'])) $data['ai'] = [];
  $data['ai']['final'] = $final;

  shec_update_data($uid, $data);

  wp_send_json_success([
    'ai_result' => wp_json_encode($final, JSON_UNESCAPED_UNICODE),
    'user'      => $data
  ]);
}

}
add_action('wp_ajax_shec_finalize','shec_finalize');
add_action('wp_ajax_nopriv_shec_finalize','shec_finalize');


/* ---------------------------------
 * PING
 * --------------------------------- */
if (!function_exists('shec_ai_ping')) {
  function shec_ai_ping(){
    shec_check_nonce_or_bypass();
    $has = (bool) shec_openai_api_key();
    $out = ['api_key_present'=>$has];
    if (!$has) return wp_send_json_success($out);
    $resp = shec_openai_chat([
      ['role'=>'system','content'=>'You return strict JSON only.'],
      ['role'=>'user','content'=>'Return {"pong":true} and nothing else.']
    ], ['temperature'=>0,'model'=>'gpt-4o-mini']);
    $out['http_code'] = $resp['http_code'] ?? 0;
    $out['model']     = $resp['model'] ?? null;
    if ($resp['ok']) {
      $parsed = shec_json_decode_safe($resp['content']);
      $out['openai_ok'] = (bool)($parsed['pong'] ?? false);
      $out['raw'] = $parsed;
      $out['source'] = 'openai';
    } else {
      $out['openai_ok'] = false;
      $out['error'] = $resp['error'] ?? 'unknown';
      error_log('[SHEC][AI_PING] '.$out['error']);
    }
    wp_send_json_success($out);
  }
}
add_action('wp_ajax_shec_ai_ping','shec_ai_ping');
add_action('wp_ajax_nopriv_shec_ai_ping','shec_ai_ping');