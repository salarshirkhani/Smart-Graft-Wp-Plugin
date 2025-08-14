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
شما دستیار متخصص کاشت مو هستید. فقط JSON برگردان.

# خروجی لازم
{"method":"FIT|FUT|Micro|Combo","graft_count":2200,"analysis":"<=120 کلمه فارسی، دوستانه و غیرتخصصی"}

# قواعد
- بر اساس همه‌ی داده‌ها (سن/جنس/الگو/پرونده پزشکی/پاسخ‌های بله‌خیر/تصاویر) جمع‌بندی کن.
- اگر عدم‌قطعیت داری، در analysis کوتاه اشاره کن.
- فقط JSON خروجی بده.

# ورودی
اطلاعات بیمار (JSON):
{{PACK_JSON}}
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
if (!function_exists('shec_ai_questions')) {
  function shec_ai_questions() {
    shec_check_nonce_or_bypass();

    $uid = intval($_POST['user_id'] ?? 0);
    if ($uid<=0) wp_send_json_error(['message'=>'کاربر معتبر نیست']);

    $data = shec_get_data($uid);
    if (!$data) wp_send_json_error(['message'=>'داده‌ای برای این کاربر پیدا نشد']);

    $summary = [
      'gender'        => $data['gender'] ?? null,
      'age'           => $data['age'] ?? null,
      'confidence'    => $data['confidence'] ?? null,
      'loss_pattern'  => $data['loss_pattern'] ?? null,
      'medical'       => $data['medical'] ?? null,
      'uploads_count' => isset($data['uploads']) && is_array($data['uploads']) ? count($data['uploads']) : 0,
    ];

    $fp = sha1(wp_json_encode([$summary['gender'],$summary['age'],$summary['loss_pattern'],$summary['medical']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

    // کش ۴تایی معتبر
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

    // fallback
    $fallback = [
      'آیا در خانواده‌تان سابقهٔ ریزش مو وجود دارد؟',
      'آیا طی ۱۲ ماه گذشته شدت ریزش موی شما بیشتر شده است؟',
      'آیا در حال حاضر سیگار یا قلیان مصرف می‌کنید؟',
      'آیا خواب و استرس شما در ماه‌های اخیر بدتر شده است؟'
    ];
    $questions = null;
    $debug = ['marker'=>'aiq_dyn4','source'=>'fallback','error'=>null];

    if (shec_openai_api_key()) {
      $prompt_user = shec_render_template(shec_get_prompt_questions(), ['SUMMARY_JSON' => $summary]);
      $resp = shec_openai_chat([['role'=>'user','content'=>$prompt_user]], ['temperature'=>0.4]);

      if ($resp['ok']) {
        $parsed = shec_json_decode_safe($resp['content']);
        $q = is_array($parsed['questions'] ?? null) ? array_values(array_filter(array_map('trim',$parsed['questions']))) : [];
        if (count($q) === 4) { $questions = $q; $debug['source'] = 'openai'; }
        else { $debug['error'] = 'bad JSON shape (need 4 questions)'; }
      } else { $debug['error'] = $resp['error'] ?? 'openai call failed'; }
    } else { $debug['error'] = 'no api key'; }

    if (!$questions || count($questions) !== 4) $questions = $fallback;

    // ذخیره در DB
    if (!isset($data['ai'])) $data['ai'] = [];
    $data['ai']['followups'] = [
      'questions'    => $questions,
      'generated_at' => time(),
      'fp'           => $fp,
      'source'       => $debug['source']
    ];
    shec_update_data($uid, $data);

    wp_send_json_success(['questions'=>$questions, 'debug'=>$debug, 'summary'=>$summary]);
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

    $answers = (isset($_POST['answers']) && is_array($_POST['answers'])) ? array_values($_POST['answers']) : [];

    $data = shec_get_data($uid);
    if (!$data) wp_send_json_error(['message'=>'داده‌ای برای این کاربر پیدا نشد']);

    $questions = $data['ai']['followups']['questions'] ?? [];
    $qa = [];
    for ($i=0; $i<count($questions); $i++) {
      $qa[] = ['q'=>(string)$questions[$i], 'a'=>(string)($answers[$i] ?? '')];
    }
    if (!isset($data['ai'])) $data['ai'] = [];
    if (!isset($data['ai']['followups'])) $data['ai']['followups'] = [];
    $data['ai']['followups']['qa'] = $qa;
    $data['ai']['followups']['answers'] = $answers;

    $pack = [
      'gender'       => $data['gender'] ?? null,
      'age'          => $data['age'] ?? null,
      'loss_pattern' => $data['loss_pattern'] ?? null,
      'medical'      => $data['medical'] ?? null,
      'uploads'      => array_values($data['uploads'] ?? []),
      'followups'    => $qa,
      'contact'      => $data['contact'] ?? null,
      'mobile'       => $data['mobile'] ?? null,
    ];

    $prompt_user = shec_render_template(shec_get_prompt_final(), ['PACK_JSON' => $pack]);
    $resp = shec_openai_chat([['role'=>'user','content'=>$prompt_user]], ['temperature'=>0.2]);

    $final = [
      'method'=>'FIT',
      'graft_count'=>2500,
      'analysis'=>'بر اساس اطلاعات موجود، روش FIT می‌تواند مناسب باشد. برای ارزیابی دقیق‌تر، معاینه حضوری توصیه می‌شود.'
    ];
    if ($resp['ok']) {
      $parsed = shec_json_decode_safe($resp['content']);
      if (isset($parsed['method']) && isset($parsed['graft_count']) && isset($parsed['analysis'])) {
        $final = $parsed;
      }
    } else if (($resp['http_code'] ?? 0) == 429) {
      shec_set_rate_limit_block(180);
    }

    // ذخیره‌ی نتیجه
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
