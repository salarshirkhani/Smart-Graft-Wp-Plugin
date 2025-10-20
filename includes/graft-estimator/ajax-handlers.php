<?php
/**
 * Smart Hair Graft Calculator — AJAX Handlers (Tokenized Result)
 * Version: 2.0.0
 */
if (!defined('ABSPATH')) exit;

/* ---------------------------------
 * Helpers (guarded)
 * --------------------------------- */
// helper: safe logger
if (!function_exists('shec_log')) {
  function shec_log($label, $data=null){
    if (!defined('WP_DEBUG') || !WP_DEBUG) return;
    $txt = is_string($data) ? $data : wp_json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    error_log("[SHEC] {$label}: " . $txt);
  }
}

// جمع‌کردن لاگ‌ها در یک آرایهٔ گلوبال
if (!function_exists('shec_console')) {
  function shec_console($label, $payload = null) {
    $GLOBALS['__shec_console'][] = ['l' => (string)$label, 'p' => $payload];
  }
}

// چاپ در کنسول (صفحات معمولی؛ نه AJAX)
if (!function_exists('shec_console_flush')) {
  function shec_console_flush() {
    if (empty($GLOBALS['__shec_console'])) return;
    $json = wp_json_encode($GLOBALS['__shec_console'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    ?>
    <script>
      (function(){
        try {
          var entries = <?php echo $json; ?>;
          entries.forEach(function(e){
            console.log('[SHEC]', e.l, e.p);
          });
        } catch(e){}
      })();
    </script>
    <?php
  }
}
add_action('wp_footer', 'shec_console_flush', 999);
add_action('admin_footer', 'shec_console_flush', 999);


if (!function_exists('shec_table')) {
  function shec_table(){ global $wpdb; return $wpdb->prefix.'shec_users'; }
}

if (!function_exists('shec_check_nonce_or_bypass')) {
  function shec_check_nonce_or_bypass() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host==='localhost' || $host==='127.0.0.1') return; // local dev
    $nonce = $_POST['_nonce'] ?? $_POST['_wpnonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'shec_nonce')) {
      wp_send_json_error(['message'=>'Invalid nonce'], 403);
    }
  }
}

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
  function shec_openai_chat(array $messages, array $opts = []) {
    $api_key = shec_openai_api_key();
    if (!$api_key) return ['ok'=>false,'error'=>'OpenAI API key not set','http_code'=>0];

    $model  = $opts['model'] ?? 'gpt-4o-mini';
    $temp   = isset($opts['temperature']) ? (float)$opts['temperature'] : 0.2;
    $schema = $opts['json_schema'] ?? null;
    $schema_name = $opts['schema_name'] ?? 'SmartHairCalc';

    // تلاش اول با json_schema (strict)، سپس فالبک json_object
    $formats = [];
    if (is_array($schema)) {
      $formats[] = [
        'type' => 'json_schema',
        'json_schema' => [
          'name'   => $schema_name,
          'schema' => $schema,
          'strict' => true
        ]
      ];
    }
    $formats[] = ['type'=>'json_object'];

    $lastErr = null; $lastCode = 0; $lastRaw = null;

    foreach ($formats as $rf) {
      $body = [
        'model' => $model,
        'temperature' => $temp,
        'response_format' => $rf,
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
      if (is_wp_error($res)) { $lastErr = $res->get_error_message(); continue; }

      $code = wp_remote_retrieve_response_code($res);
      $json = json_decode(wp_remote_retrieve_body($res), true);
      $content = $json['choices'][0]['message']['content'] ?? '';
      if ($code < 400 && is_string($content) && $content !== '') {
        return ['ok'=>true, 'content'=>$content, 'http_code'=>$code, 'model'=>$model, 'format'=>$rf['type']];
      }
      $lastCode = $code;
      $lastErr  = $json['error']['message'] ?? ("OpenAI HTTP ".$code);
      $lastRaw  = $json;
    }

    return ['ok'=>false,'error'=>$lastErr ?: 'OpenAI call failed','http_code'=>$lastCode,'raw'=>$lastRaw,'model'=>$model];
  }
}

if (!function_exists('shec_openai_assist_run')) {
  function shec_openai_assist_run($assistant_id, $user_content, array $opts = []) {
    $api_key = shec_openai_api_key();
    if (!$api_key) return ['ok'=>false,'error'=>'OpenAI API key not set','http_code'=>0];
    $poll_ms = isset($opts['poll_ms']) ? (int)$opts['poll_ms'] : 800;
    $max_ms  = isset($opts['max_ms'])  ? (int)$opts['max_ms']  : 30000;

    $headers = [
      'Authorization' => 'Bearer '.$api_key,
      'Content-Type'  => 'application/json',
      // اگر حسابت نیاز داشت:
      // 'OpenAI-Beta'   => 'assistants=v2',
    ];

    // 1) create thread
    $res = wp_remote_post('https://api.openai.com/v1/threads', [
      'headers' => $headers, 'body' => '{}', 'timeout' => 45,
    ]);
    if (is_wp_error($res)) return ['ok'=>false,'error'=>$res->get_error_message(),'http_code'=>0];
    $code = wp_remote_retrieve_response_code($res);
    $json = json_decode(wp_remote_retrieve_body($res), true);
    $thread_id = $json['id'] ?? '';
    if ($code>=400 || !$thread_id) {
      return ['ok'=>false,'error'=>'thread create failed','http_code'=>$code?:500];
    }

    // 2) add user message
    $res = wp_remote_post("https://api.openai.com/v1/threads/{$thread_id}/messages", [
      'headers'=>$headers,
      'body'   => wp_json_encode(['role'=>'user','content'=>$user_content], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'timeout'=> 45,
    ]);
    if (is_wp_error($res)) return ['ok'=>false,'error'=>$res->get_error_message(),'http_code'=>0];
    if (wp_remote_retrieve_response_code($res) >= 400) {
      return ['ok'=>false,'error'=>'add message failed','http_code'=>wp_remote_retrieve_response_code($res)];
    }

    // 3) create run
    $res = wp_remote_post("https://api.openai.com/v1/threads/{$thread_id}/runs", [
      'headers'=>$headers,
      'body'   => wp_json_encode(['assistant_id'=>$assistant_id], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'timeout'=>45,
    ]);
    if (is_wp_error($res)) return ['ok'=>false,'error'=>$res->get_error_message(),'http_code'=>0];
    $code = wp_remote_retrieve_response_code($res);
    $json = json_decode(wp_remote_retrieve_body($res), true);
    $run_id = $json['id'] ?? '';
    if ($code>=400 || !$run_id) {
      return ['ok'=>false,'error'=>'run create failed','http_code'=>$code?:500];
    }

    // 4) poll run status
    $elapsed = 0;
    $status  = 'queued';
    while ($elapsed < $max_ms) {
      usleep($poll_ms * 1000);
      $elapsed += $poll_ms;
      $res = wp_remote_get("https://api.openai.com/v1/threads/{$thread_id}/runs/{$run_id}", ['headers'=>$headers,'timeout'=>30]);
      if (is_wp_error($res)) return ['ok'=>false,'error'=>$res->get_error_message(),'http_code'=>0];
      $code = wp_remote_retrieve_response_code($res);
      $json = json_decode(wp_remote_retrieve_body($res), true);
      $status = $json['status'] ?? '';
      if (in_array($status, ['completed','failed','cancelled','expired'], true)) break;
    }
    if ($status !== 'completed') {
      return ['ok'=>false,'error'=>"run not completed: {$status}",'http_code'=>200];
    }

    // 5) get last assistant message
    $res = wp_remote_get("https://api.openai.com/v1/threads/{$thread_id}/messages?limit=10&order=desc", ['headers'=>$headers,'timeout'=>30]);
    if (is_wp_error($res)) return ['ok'=>false,'error'=>$res->get_error_message(),'http_code'=>0];
    $code = wp_remote_retrieve_response_code($res);
    $json = json_decode(wp_remote_retrieve_body($res), true);
    if ($code>=400 || empty($json['data'])) {
      return ['ok'=>false,'error'=>'messages fetch failed','http_code'=>$code?:500];
    }

    // پیدا کردن اولین پیام assistant و استخراج متن
    $text = '';
    foreach ($json['data'] as $msg) {
      if (($msg['role'] ?? '') !== 'assistant') continue;
      if (!empty($msg['content']) && is_array($msg['content'])) {
        foreach ($msg['content'] as $block) {
          if (($block['type'] ?? '') === 'text' && !empty($block['text']['value'])) {
            $text = (string)$block['text']['value'];
            break 2;
          }
        }
      }
    }
    if ($text==='') $text = (string)($json['data'][0]['content'][0]['text']['value'] ?? '');

    return ['ok'=>($text!=='') , 'content'=>$text, 'http_code'=>200];
  }
}

if (!function_exists('shec_get_lang')) {
  function shec_get_lang($uid = 0) {
    // 1) از درخواست جاری
    $lang = isset($_POST['lang']) ? sanitize_text_field($_POST['lang']) : '';

    // 2) از پروفایل فرم ذخیره‌شده
    if (!$lang && $uid > 0) {
      $data = shec_get_data($uid);
      if (is_array($data) && !empty($data['lang'])) {
        $lang = (string)$data['lang'];
      }
    }

    // 3) از افزونه‌های چندزبانه (اختیاری)
    if (!$lang) {
      if (function_exists('pll_current_language')) {           // Polylang
        $lang = pll_current_language('slug');                  // fa / en ...
      } elseif (defined('ICL_LANGUAGE_CODE')) {                // WPML
        $lang = ICL_LANGUAGE_CODE;                             // fa / en ...
      }
    }

    // 4) از وردپرس
    if (!$lang) {
      $loc = get_locale();           // مثلا fa_IR یا en_US
      $lang = (stripos($loc, 'fa') === 0) ? 'fa' : ((stripos($loc, 'en') === 0) ? 'en' : '');
    }

    // 5) نهایی‌سازی و محدودسازی
    $lang = strtolower($lang);
    $allowed = ['fa','en'];
    if (!in_array($lang, $allowed, true)) {
      // نگاشت زبان‌های دیگر به نزدیک‌ترین انتخاب
      $lang = ($lang === 'fa' || stripos($lang, 'fa') === 0) ? 'fa' : 'en';
    }

    return $lang ?: 'fa';
  }
}

// =======================================================
// 1) JSON Schema نهایی — با کلیدهای دقیق a_label و ai_tip
// =======================================================
if (!function_exists('shec_json_schema_final')) {
  function shec_json_schema_final() {
    return [
      'type' => 'object',
      'additionalProperties' => false,
      'properties' => [
        'method'        => ['type'=>'string','enum'=>['FIT']],
        'graft_count'   => ['type'=>'integer'],
        'analysis'      => ['type'=>'string','minLength'=>100,'maxLength'=>900],
        'pattern_explain' => [
          'type'=>'object','additionalProperties'=>false,
          'properties'=>[
            'label'       => ['type'=>'string'],
            'what_it_is'  => ['type'=>'string','minLength'=>20],
            'why_happens' => ['type'=>'string','minLength'=>10],
            'fit_ok'      => ['type'=>'boolean'],
            'note'        => ['type'=>'string']
          ],
          'required'=>['label','what_it_is','why_happens','fit_ok','note']
        ],
        'concern_box' => ['type'=>'string','minLength'=>20],
        'followups' => [
          'type'=>'array','minItems'=>4,'maxItems'=>4,
          'items'=>[
            'type'=>'object','additionalProperties'=>false,
            'properties'=>[
              'q'       => ['type'=>'string'],
              'a_label' => ['type'=>'string','enum'=>['بله','خیر']],
              'ai_tip'  => ['type'=>'string','minLength'=>4]
            ],
            'required'=>['q','a_label','ai_tip']
          ]
        ],
        'pre_op'  => ['type'=>'array','items'=>['type'=>'string'],'maxItems'=>3],
        'post_op' => ['type'=>'array','items'=>['type'=>'string'],'maxItems'=>3],
        'followup_summary' => ['type'=>'string']
      ],
      'required'=>['method','graft_count','analysis','pattern_explain','concern_box','followups','pre_op','post_op']
    ];
  }
}

// =======================================================
// 2) Helperها برای نرمال‌سازی followups به فرم UI (q, a_label, ai_tip)
// =======================================================


// ===== Yes/No label mapper (translation-ready) =====
if (!function_exists('shec_map_yesno_label')) {
  function shec_map_yesno_label($v){
    $t = strtolower(trim((string)$v));
    if (in_array($t, ['yes','true','1','y','بله','بلی'], true)) return __('Yes', 'shec');
    if (in_array($t, ['no','false','0','n','خیر'], true))       return __('No', 'shec');
    return ($t === '') ? '—' : $t;
  }
}
// Backward compatibility (old name points to the new mapper)
if (!function_exists('shec_map_yesno_to_fa')) {
  function shec_map_yesno_to_fa($v){ return shec_map_yesno_label($v); }
}


// ===== Tip guesser (bilingual regex + EN i18n messages) =====
if (!function_exists('shec_guess_tip_from_question')) {
  /**
   * Return a short practical tip inferred from $q (question text) and $a_label (Yes/No).
   * Bilingual matching (FA+EN) — Output strings are English, translation-ready via __('...', 'shec')
   */
  function shec_guess_tip_from_question($q, $a_label){
    $q   = (string)$q;
    $al  = trim((string)$a_label);
    $yes = preg_match('~^(?:yes|true|1|y|بله|بلی)$~iu', $al) === 1;

    // --- Scalp issues / infection / inflammation ---
    if (preg_match('~(عفونت|التهاب|چرک|ترشح|زخم(?:\s*فعال)?|قرمزی(?:\s*شدید)?|تاول|فولیکولیت|پسوریازیس|قارچ|سبورئیک|درماتیت|خارش|پوسته(?:\s*ریزی)?)|\b(infection|inflammation|pus|discharge|active\s*wound|redness|blister|folliculitis|psoriasis|fung(?:us|al)|seborr(?:heic|hoeic)|dermatitis|itch(?:ing)?|flak(?:e|ing))\b~iu', $q)) {
      return $yes
        ? __('If there is any active lesion, treat and calm inflammation first; then schedule the transplant to reduce bleeding/infection risk and improve graft survival.', 'shec')
        : __('With no active scalp lesion, we can plan the procedure; report any unusual sign (itch/discharge/redness) promptly.', 'shec');
    }

    // --- Anticoagulants / coagulation ---
    if (preg_match('~وارفارین|\bwarfarin\b~iu', $q)) {
      return __('Warfarin requires INR adjustment and a physician-approved stop/bridging plan; do not change it on your own.', 'shec');
    }
    if (preg_match('~هپارین|enoxaparin|کلیکسان|heparin|\bLMWH\b~iu', $q)) {
      return __('Heparin/LMWH timing must be planned by the treating physician; we will coordinate the last pre-op dose.', 'shec');
    }
    if (preg_match('~ریواروکسابان|rivaroxaban|اپیکسابان|apixaban|دابیگاتران|dabigatran~iu', $q)) {
      return __('DOACs are managed temporarily based on individual risk and renal function; stop/continue only per physician advice.', 'shec');
    }
    if (preg_match('~آسپرین(?:\s*بچه)?|aspirin|کلوپیدوگرل|clopidogrel~iu', $q)) {
      return __('Aspirin/clopidogrel may increase bleeding; decisions about holding/continuing must be made by your cardiologist and treating physician.', 'shec');
    }
    if (preg_match('~انعقاد|رقیق[ -]?کننده|\b(coagulation|blood\s*thinner|anticoagulant)\b~iu', $q)) {
      return __('If you use blood thinners, any change must be physician-approved; a temporary alternative may be required.', 'shec');
    }

    // --- Cardiovascular / HTN / stroke ---
    if (preg_match('~قلب|عروقی|سکته|پرفشاری|کاردیو|\b(cardio|cardiovascular|stroke|hypertension|high\s*blood\s*pressure)\b~iu', $q)) {
      return __('With cardiac history, BP control and physician clearance are essential; in a stable condition, transplant is feasible with close monitoring.', 'shec');
    }

    // --- Thyroid ---
    if (preg_match('~تیروئید|\bTSH\b|\bhypo(?:thyroid)?\b|\bhyper(?:thyroid)?\b~iu', $q)) {
      return $yes
        ? __('Bring TSH/Free T4 to target and obtain your physician’s clearance; hormonal imbalance can impair hair growth.', 'shec')
        : __('Good; we will include pre-op screening to ensure hormonal balance.', 'shec');
    }

    // --- Immunodeficiency / chemo / immunosuppression ---
    if (preg_match('~ایمنی|HIV|ایدز|شیمی[ -]?درمانی|سرکوب\s*ایمنی|\b(immune\s*deficienc(y|ies)|immuno\s*suppression|chemotherapy)\b~iu', $q)) {
      return __('Due to infection risk and slower healing, the decision depends on specialist evaluation and treating physician approval.', 'shec');
    }
    if (preg_match('~خودایمنی|لوپوس|areata|آلوپسی\s*آره ?آتا|پسوریازیس|\b(autoimmune|lupus|alopecia\s*areata)\b~iu', $q)) {
      return __('Active autoimmune disease may reduce graft acceptance; if controlled and without extensive scalp involvement, planning may be possible.', 'shec');
    }

    // --- Hair-related meds ---
    if (preg_match('~ایزوترتینوئین|راکوتان|اکوتان|isotretinoin|accutane~iu', $q)) {
      return __('Isotretinoin can affect wound healing; we will coordinate a safe interval from your last dose with your physician.', 'shec');
    }
    if (preg_match('~فیناستراید|finasteride|دوتاستراید|dutasteride~iu', $q)) {
      return $yes
        ? __('Hormonal therapy can help; you will be guided about continuing/adjusting dosing around the procedure per clinic protocol.', 'shec')
        : __('If appropriate, maintenance hormonal therapy can help stabilize loss; we will review this during the consultation.', 'shec');
    }
    if (preg_match('~ماینوکسیدیل|minoxidil~iu', $q)) {
      return __('Topical minoxidil is usually paused 3–5 days pre-op and restarted 7–10 days post-op; follow clinic instructions.', 'shec');
    }

    // --- NSAIDs / analgesics ---
    if (preg_match('~ایبوپروفن|ژلوفن|ناپروکسن|دیکلوفناک|\bNSAID(?:s)?\b|ibuprofen|naproxen|diclofenac~iu', $q)) {
      return __('NSAIDs can slightly increase bleeding; analgesic substitution will be arranged per clinic protocol.', 'shec');
    }

    // --- Lifestyle: smoking / sleep / stress / exercise ---
    if (preg_match('~سیگار|قلیان|دخانیات|نیکوتین|\b(smok(?:e|ing)|cigarette(?:s)?|cigar(?:s)?|hookah|shisha|water-?pipe|tobacco|nicotine|vape|vaping|e-?cig(?:arette)?s?)\b~iu', $q)) {
      return $yes
        ? __('If usage is significant, stop completely from 10 days before to 7 days after the procedure; nicotine reduces perfusion and slows healing.', 'shec')
        : __('Great; please keep it smoke-free from 7–10 days pre-op through one week post-op to maximize graft survival.', 'shec');
    }
    if (preg_match('~خواب|بی[ -]?خوابی|کیفیت\s*خواب|\b(sleep|insomnia|sleep\s*quality)\b~iu', $q)) {
      return $yes
        ? __('Sleep hygiene: consistent hours, avoid afternoon caffeine, reduce blue light at night; adequate sleep lowers inflammation and speeds recovery.', 'shec')
        : __('Good; have a full night’s sleep before the procedure and keep a regular sleep schedule.', 'shec');
    }
    if (preg_match('~استرس|اضطراب|فشار\s*روان|\b(stress|anxiety|psychological\s*pressure)\b~iu', $q)) {
      return $yes
        ? __('Add 10–15 minutes of deep breathing or light walking daily; high stress can worsen telogen effluvium.', 'shec')
        : __('Great; keep it up. A few minutes of daily relaxation also supports healing.', 'shec');
    }
    if (preg_match('~ورزش|فعالیت\s*بدنی|تمرین|تعریق|\b(exercise|workout|physical\s*activity|sweat(?:ing)?)\b~iu', $q)) {
      return $yes
        ? __('Avoid intense exercise, sauna, and heavy sweating for 72 hours after transplant; then resume light activity gradually.', 'shec')
        : __('Post-op, light walking is fine from early days; avoid heavy activity for the first 72 hours.', 'shec');
    }

    // --- Pain / anesthesia ---
    if (preg_match('~درد|بی[ -]?حسی|ناراحتی(?:\s*حین)?\s*عمل|حساسیت\s*به\s*بی‌?حسی|\b(pain|numbness|discomfort|intra-?op|anesthesia|local\s*anesthetic\s*allergy)\b~iu', $q)) {
      return $yes
        ? __('We use local anesthesia and continuously adjust comfort; standard post-op analgesics are usually sufficient.', 'shec')
        : __('No worries; local anesthesia controls pain and can be adjusted during the procedure.', 'shec');
    }

    // --- Recovery / timeline ---
    if (preg_match('~نقاهت|بهبودی|چند\s*ماه|چقدر\s*طول|timeline|\b(recovery|downtime|healing|how\s*long|timeline)\b~iu', $q)) {
      return __('Results are phased: shedding in weeks 2–3, early regrowth at months 3–4, fuller density by months 6–9, and stabilization around 12 months.', 'shec');
    }

    // --- Progression / getting worse ---
    if (preg_match('~بدتر\s*شده|شدت\s*ریزش|افزایش\s*ریزش|اخیراً|اخیرًا|\b(worse|worsening|increased\s*shedding|recently)\b~iu', $q)) {
      return $yes
        ? __('Given recent worsening, don’t postpone treatment; start maintenance therapy in parallel.', 'shec')
        : __('Good that it hasn’t worsened; continue maintenance therapy to stabilize the situation.', 'shec');
    }

    // --- Occupational / dusty environments / heavy work ---
    if (preg_match('~کار\s*(?:سنگین|سخت)|وزنه|ساختمانی|گرد\s*و\s*غبار|کارگاه|کوره|سیمان|کاشی|\b(heavy\s*work|weight\s*lifting|construction|dust(y)?\s*env(?:ironment)?|workshop|kiln|furnace|cement|tile)\b~iu', $q)) {
      return __('In dusty/heavy-work environments, protect the recipient area for the first 72 hours (avoid sweating/pressure); if needed, use a clean, non-tight cap.', 'shec');
    }

    // --- Safe default ---
    return $yes
      ? __('By optimizing lifestyle factors and completing pre-op checks, you reduce risks and support better healing quality.', 'shec')
      : __('Your current routine seems fine; keep it and follow pre/post-op care instructions precisely.', 'shec');
  }
}


// ===== Normalize follow-ups for UI (kept bilingual-friendly) =====
if (!function_exists('shec_normalize_followups_for_ui')) {
  /**
   * @param array $qa           Array of [{q,a}] from user (yes/no-like)
   * @param array $ai_followups Array from AI (flexible keys: a|tip or a_label|ai_tip)
   * @return array              Final [{q, a_label, ai_tip}] for UI
   */
  function shec_normalize_followups_for_ui(array $qa, array $ai_followups){
    // Index AI by q
    $ai_by_q = [];
    foreach ($ai_followups as $item) {
      $q = trim((string)($item['q'] ?? ''));
      if ($q==='') continue;
      $a_label = $item['a_label'] ?? null;
      if (!$a_label && isset($item['a'])) $a_label = shec_map_yesno_label($item['a']);
      $ai_tip  = $item['ai_tip']  ?? ($item['tip'] ?? '');
      $ai_by_q[$q] = ['a_label'=>$a_label, 'ai_tip'=>$ai_tip];
    }

    $out = [];
    foreach ($qa as $row) {
      $q = trim((string)($row['q'] ?? ''));
      if ($q==='') continue;

      // Prefer user answer label
      $a_label = shec_map_yesno_label($row['a'] ?? '');

      // Try AI tip; if missing, guess from question/answer
      $ai_tip = '';
      if (isset($ai_by_q[$q])) {
        $ai_tip = trim((string)$ai_by_q[$q]['ai_tip']);
      }
      if ($ai_tip === '') {
        $ai_tip = shec_guess_tip_from_question($q, $a_label);
      }

      $out[] = [
        'q'       => $q,
        'a_label' => $a_label,
        'ai_tip'  => $ai_tip
      ];
    }

    // Ensure exactly 4 items (pad with defaults if needed)
    while (count($out) < 4) {
      $out[] = [
        'q'       => '—',
        'a_label' => __('No', 'shec'),
        'ai_tip'  => __('To complete the picture, please consider a few lifestyle and care pointers; we will tailor them further during your consultation.', 'shec'),
      ];
    }
    if (count($out) > 4) $out = array_slice($out, 0, 4);

    return $out;
  }
}


// ===== JSON schema for 4 binary questions =====
if (!function_exists('shec_json_schema_questions')) {
  function shec_json_schema_questions() {
    return [
      'type'=>'object','additionalProperties'=>false,
      'properties'=>[
        'questions'=>[
          'type'=>'array','minItems'=>4,'maxItems'=>4,
          'items'=>['type'=>'string','minLength'=>3,'maxLength'=>140]
        ]
      ],
      'required'=>['questions']
    ];
  }
}


// ===== Safe JSON decode (strips ```json fences) =====
if (!function_exists('shec_json_decode_safe')) {
  function shec_json_decode_safe($str){
    if (!is_string($str)) return null;
    $str = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($str));
    $data = json_decode($str, true);
    return is_array($data) ? $data : null;
  }
}


// ===== Simple rate limit helper =====
if (!function_exists('shec_set_rate_limit_block')) {
  function shec_set_rate_limit_block($seconds=180){
    $until = time() + max(60, min((int)$seconds, 600));
    set_transient('shec_ai_block_until', $until, $until - time());
    return $until;
  }
}


// ===== OpenAI Assistants API (server-side) =====
if (!function_exists('shec_openai_assistant_run')) {
  function shec_openai_assistant_run($assistant_id, $input_text, $timeout=60) {
    $api_key = shec_openai_api_key();
    if (!$api_key) return ['ok'=>false,'error'=>'OpenAI API key not set','http_code'=>0];

    // 1) create thread
    $th = wp_remote_post('https://api.openai.com/v1/threads', [
      'headers'=>['Authorization'=>'Bearer '.$api_key,'Content-Type'=>'application/json'],
      'body'=> wp_json_encode([], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'timeout'=> $timeout
    ]);
    if (is_wp_error($th)) return ['ok'=>false,'error'=>$th->get_error_message(),'http_code'=>0];
    $th_body = json_decode(wp_remote_retrieve_body($th), true);
    $thread_id = $th_body['id'] ?? '';
    if (!$thread_id) return ['ok'=>false,'error'=>'thread create failed','http_code'=>wp_remote_retrieve_response_code($th)];

    // 2) add message
    $msg = wp_remote_post("https://api.openai.com/v1/threads/{$thread_id}/messages", [
      'headers'=>['Authorization'=>'Bearer '.$api_key,'Content-Type'=>'application/json'],
      'body'=> wp_json_encode(['role'=>'user','content'=>$input_text], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'timeout'=>$timeout
    ]);
    if (is_wp_error($msg)) return ['ok'=>false,'error'=>$msg->get_error_message(),'http_code'=>0];

    // 3) run
    $run = wp_remote_post("https://api.openai.com/v1/threads/{$thread_id}/runs", [
      'headers'=>['Authorization'=>'Bearer '.$api_key,'Content-Type'=>'application/json'],
      'body'=> wp_json_encode(['assistant_id'=>$assistant_id], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'timeout'=>$timeout
    ]);
    if (is_wp_error($run)) return ['ok'=>false,'error'=>$run->get_error_message(),'http_code'=>0];
    $run_body = json_decode(wp_remote_retrieve_body($run), true);
    $run_id = $run_body['id'] ?? '';
    if (!$run_id) return ['ok'=>false,'error'=>'run create failed','http_code'=>wp_remote_retrieve_response_code($run)];

    // 4) poll status
    $t0 = time();
    do {
      usleep(600000); // 600ms
      $chk = wp_remote_get("https://api.openai.com/v1/threads/{$thread_id}/runs/{$run_id}", [
        'headers'=>['Authorization'=>'Bearer '.$api_key],
        'timeout'=>15
      ]);
      if (is_wp_error($chk)) return ['ok'=>false,'error'=>$chk->get_error_message(),'http_code'=>0];
      $st = json_decode(wp_remote_retrieve_body($chk), true);
      $status = $st['status'] ?? '';
      if (in_array($status, ['completed','failed','cancelled','expired'], true)) break;
    } while (time()-$t0 < $timeout);

    if (($status ?? '') !== 'completed') {
      return ['ok'=>false,'error'=>'assistant run not completed','http_code'=>0];
    }

    // 5) read last message
    $msgs = wp_remote_get("https://api.openai.com/v1/threads/{$thread_id}/messages?limit=1&order=desc", [
      'headers'=>['Authorization'=>'Bearer '.$api_key],
      'timeout'=>15
    ]);
    if (is_wp_error($msgs)) return ['ok'=>false,'error'=>$msgs->get_error_message(),'http_code'=>0];
    $mb = json_decode(wp_remote_retrieve_body($msgs), true);
    $content = $mb['data'][0]['content'][0]['text']['value'] ?? '';
    return ['ok'=>true,'content'=>$content,'http_code'=>200];
  }
}


// ===== Token helpers (guarded) =====
if (!function_exists('shec_links_table')) {
  function shec_links_table(){ global $wpdb; return $wpdb->prefix.'shec_links'; }
}
if (!function_exists('shec_generate_token')) {
  function shec_generate_token($len = 9) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'; // base58-like
    $max = strlen($alphabet) - 1;
    $tok = '';
    for ($i=0;$i<$len;$i++) { $tok .= $alphabet[random_int(0, $max)]; }
    return $tok;
  }
}
if (!function_exists('shec_public_page_url')) {
  function shec_public_page_url($token) {
    $page = get_page_by_path('hair-result');
    $base = $page ? get_permalink($page) : home_url('/');
    $sep  = (strpos($base,'?')===false) ? '?' : '&';
    return $base . $sep . 't=' . rawurlencode($token);
  }
}
if (!function_exists('shec_public_link_issue')) {
  function shec_public_link_issue($uid, $days = 180) {
    global $wpdb;
    $uid = (int)$uid;
    $data = shec_get_data($uid);
    if (empty($data)) return ['url'=>'', 'token'=>'', 'expires'=>0];

    $links_table = shec_links_table();
    $expires_ts  = time() + (int)$days * DAY_IN_SECONDS;
    $expires_dt  = gmdate('Y-m-d H:i:s', $expires_ts);

    // Reuse plaintext token if present (extend if expired), ensure it exists in links table
    if (!empty($data['public_token']['token'])) {
      $tok = (string)$data['public_token']['token'];
      $exp = (int)($data['public_token']['expires'] ?? 0);

      if ($exp <= time()) {
        $exp = $expires_ts;
        $data['public_token']['expires'] = $exp;
        shec_update_data($uid, $data);
      }

      $hash = hash('sha256', $tok);
      $row  = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$links_table} WHERE token_hash=%s LIMIT 1",
        $hash
      ), ARRAY_A);

      if (!$row) {
        $wpdb->update($links_table, ['is_active'=>0], ['wp_user_id'=>$uid]);
        $wpdb->insert($links_table, [
          'wp_user_id' => $uid,
          'token_hash' => $hash,
          'created'    => current_time('mysql', 1), // GMT
          'expires'    => $expires_dt,
          'is_active'  => 1,
        ], ['%d','%s','%s','%s','%d']);
      } else {
        $wpdb->update($links_table, [
          'expires'   => $expires_dt,
          'is_active' => 1,
        ], [
          'token_hash'=> $hash
        ], ['%s','%d'], ['%s']);
      }

      set_transient('shec_tok_'.$tok, $uid, (int)$days*DAY_IN_SECONDS);
      return ['url'=>shec_public_page_url($tok), 'token'=>$tok, 'expires'=>$exp];
    }

    // Create a new token (retry a few times for safety)
    $tok = '';
    for ($i=0; $i<5; $i++) {
      $tok  = shec_generate_token(9);
      $hash = hash('sha256', $tok);
      $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$links_table} WHERE token_hash=%s",
        $hash
      ));
      if (!$exists) break;
      $tok = '';
    }
    if ($tok === '') {
      return ['url'=>'', 'token'=>'', 'expires'=>0];
    }

    $wpdb->update($links_table, ['is_active'=>0], ['wp_user_id'=>$uid]);
    $hash = hash('sha256', $tok);
    $wpdb->insert($links_table, [
      'wp_user_id' => $uid,
      'token_hash' => $hash,
      'created'    => current_time('mysql', 1), // GMT
      'expires'    => $expires_dt,
      'is_active'  => 1,
    ], ['%d','%s','%s','%s','%d']);

    $data['public_token'] = ['token'=>$tok, 'created'=>time(), 'expires'=>$expires_ts];
    shec_update_data($uid, $data);

    set_transient('shec_tok_'.$tok, $uid, (int)$days*DAY_IN_SECONDS);

    return ['url'=>shec_public_page_url($tok), 'token'=>$tok, 'expires'=>$expires_ts];
  }
}

/* ---------------------------------
 * Dynamic prompts (guarded)
 * --------------------------------- */
if (!function_exists('shec_prompt_questions_default')) {
  function shec_prompt_questions_default() {
    return <<<EOT
# Role / نقش شما
You are the intake assistant of a hair-transplant clinic. Return JSON only.
شما دستیار پذیرش کلینیک کاشت مو هستید. فقط JSON برگردان.

# Output / خروجی لازم
{"questions": ["...", "...", "...", "..."]}

# Language / زبان
- Output language MUST be: {{LANG}}  
- زبان خروجی حتماً باید {{LANG}} باشد 

# Rules / قواعد
- Exactly 4 yes/no questions, short, simple, non-technical, in {{LANG}}.
- دقیقاً ۴ سؤال بله/خیر، کوتاه، ساده و غیرتخصصی، به زبان {{LANG}}.
- Base questions on the patient summary: gender / age / loss pattern / medical history / meds / severity & trend.
- سؤال‌ها را بر مبنای خلاصهٔ بیمار تنظیم کن (جنسیت/سن/الگوی ریزش/پرونده پزشکی/دارو/شدت و روند).
- Do NOT repeat facts already known; ask only complementary, useful items.
- از تکرار مواردِ معلوم خودداری کن؛ فقط نکات تکمیلیِ مهم را بپرس.
- No extra text, no markdown; valid JSON only.
- هیچ متن اضافه/مارک‌داون نزن؛ فقط JSON معتبر.

# Input / ورودی
Patient summary (JSON) / خلاصهٔ بیمار (JSON):
{{SUMMARY_JSON}}
EOT;
  }
}

if (!function_exists('shec_prompt_final_default')) {
  function shec_prompt_final_default() {
    return <<<EOT
# Role / نقش شما
You are the hair-transplant specialist assistant at Fakhraei Clinic. Return valid JSON only.
شما دستیار متخصص کاشت مو در کلینیک فخرائی هستید. فقط و فقط JSON معتبر برگردان.

# Output (exact keys) / خروجی لازم (دقیقاً همین کلیدها)
{
  "method": "FIT",
  "graft_count": 0,
  "analysis": "<100–160 words, empathetic, friendly, in {{LANG}}>",
  "pattern_explain": {
    "label": "Norwood 5 | Ludwig II | …",
    "what_it_is": "<very short, plain-language description, in {{LANG}}>",
    "why_happens": "<typical causes: genetics/hormones/lifestyle; very short, in {{LANG}}>",
    "fit_ok": true,
    "note": "<if Norwood 1 or Ludwig I: usually no transplant is needed; maintenance therapy suggested; otherwise empty, in {{LANG}}>"
  },
  "concern_box": "<empathetic, calming response aligned with the user's recorded concern; avoid direct call-to-visit except an optional single closing sentence, in {{LANG}}>",
  "followups": [
    {"q":"<question 1 in {{LANG}}>","a":"Yes|No for en, یا «بله|خیر» برای fa","tip":"<one short, practical tip based on the answer, in {{LANG}}>"},
    {"q":"<question 2 in {{LANG}}>","a":"Yes|No | بله|خیر","tip":"<...>"},
    {"q":"<question 3 in {{LANG}}>","a":"Yes|No | بله|خیر","tip":"<...>"},
    {"q":"<question 4 in {{LANG}}>","a":"Yes|No | بله|خیر","tip":"<...>"}
  ],
  "pre_op": ["<0–3 short pre-op tips tailored to this user’s answers, in {{LANG}}>"],
  "post_op":["<0–3 short post-op tips tailored to this user’s answers, in {{LANG}}>"],
  "followup_summary":"<~120 words, empathetic summary based on the answers, in {{LANG}}>"
}

# Language / زبان
- ALL generated text MUST be in {{LANG}} ("fa" or "en").
- تمام متن تولیدی باید به {{LANG}} باشد («fa» یا «en»).

# Critical rules / قواعد مهم
- Tone: empathetic, reassuring, conversational; in {{LANG}}.
- لحن: همدلانه و اطمینان‌بخش و محاوره‌ای؛ به {{LANG}}.
- "method" MUST always be "FIT"; do NOT mention FUT.
- مقدار "method" همیشه "FIT" باشد؛ از FUT نام نبر.
- Set "graft_count" to 0 (system computes it).
- "graft_count" را 0 بگذار (سیستم محاسبه می‌کند).
- "analysis" MUST include: likely cause of loss + reassurance about natural results at Fakhraei Clinic + 2–3 simple tips until surgery + a clear treatment path.
- "analysis" حتماً شامل: علت احتمالی ریزش + اطمینان‌بخشی از نتیجهٔ طبیعی در کلینیک فخرائی + ۲–۳ توصیهٔ ساده تا زمان کاشت + جمع‌بندی روشن مسیر درمان.
- "concern_box": match the recorded concern (e.g., cost/pain/downtime/result timeline/...).
- "concern_box" دقیقاً متناسب با دغدغهٔ ثبت‌شده (مانند هزینه/درد/نقاهت/طول‌کشیدن نتیجه/…).
- Pattern mapping: male → Norwood(stage from pattern-1..6), female → Ludwig I/II/III where 1–2→I, 3–4→II, 5–6→III.
- نگاشت الگو: مرد → Norwood(stage از pattern-1..6)، زن → Ludwig I/II/III (۱–۲→I، ۳–۴→II، ۵–۶→III).
- For each followup q/a, set "a" to "Yes/No" if {{LANG}}="en", or «بله/خیر» if {{LANG}}="fa"; give a concrete, actionable "tip".
- برای هر q/a، اگر {{LANG}}="fa" باشد «بله/خیر»، و اگر "en" باشد "Yes/No" بنویس؛ "tip" عملی و دقیق باشد.
- "pre_op"/"post_op" must NOT be generic; tailor them to the same user’s answers.
- "pre_op"/"post_op" عمومی ننویس؛ دقیقاً متناسب با پاسخ‌های همین کاربر باشد.
- No extra text, no Markdown/code blocks; JSON object only.
- هیچ متن اضافه/Markdown/کدبلاک نده؛ فقط شیء JSON.

# Input / ورودی
Patient data (JSON) / اطلاعات بیمار (JSON):
{{PACK_JSON}}

# JSON only / فقط JSON
EOT;
  }
}

if (!function_exists('shec_get_prompt_questions')) {
  function shec_get_prompt_questions(){
    $p = get_option('shec_prompt_questions','');
    return $p ?: shec_prompt_questions_default();
  }
}
if (!function_exists('shec_get_prompt_final')) {
  function shec_get_prompt_final(){
    $p = get_option('shec_prompt_final','');
    return $p ?: shec_prompt_final_default();
  }
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
  * STEP 1 (create form)
  * --------------------------------- */
  if ( ! function_exists( 'shec_handle_step1' ) ) {
    function shec_handle_step1() {
      shec_check_nonce_or_bypass();
      global $wpdb;

      $normalize = function( $m ) {
        $m = preg_replace( '/\D+/', '', (string) $m );
        if ( strpos( $m, '0098' ) === 0 ) $m = substr( $m, 4 );
        if ( strpos( $m, '98' ) === 0 )   $m = substr( $m, 2 );
        if ( strpos( $m, '9' ) === 0 )    $m = '0' . $m;
        return $m;
      };

      $gender     = sanitize_text_field( $_POST['gender'] ?? '' );
      $age        = sanitize_text_field( $_POST['age'] ?? '' );
      $confidence = sanitize_text_field( $_POST['confidence'] ?? '' );
      $mobile     = $normalize( sanitize_text_field( $_POST['mobile'] ?? '' ) );
      $lang = shec_get_lang(0);  

      $valid_ages = [ '18-23', '24-29', '30-35', '36-43', '44-56', '+56' ];
      if ( ! $gender || ! in_array( $age, $valid_ages, true ) ) {
        wp_send_json_error( [ 'message' => __( 'Please select a valid gender and age range.', 'shec' ) ] );
      }
      if ( ! preg_match( '/^09\d{9}$/', $mobile ) ) {
        wp_send_json_error( [ 'message' => __( 'Invalid mobile number. Example: 09xxxxxxxxx', 'shec' ) ] );
      }

      $form_uid = shec_generate_form_uid();

      $data = [
        'gender'     => $gender,
        'age'        => $age,
        'mobile'     => $mobile,
        'confidence' => $confidence,
      ];

      $wpdb->insert(
        shec_table(),
        [ 'wp_user_id' => $form_uid, 'data' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ],
        [ '%d', '%s' ]
      );

      if ( ! $wpdb->insert_id ) {
        wp_send_json_error( [ 'message' => __( 'An error occurred while saving the initial data.', 'shec' ) ] );
      }

      wp_send_json_success( [ 'user_id' => $form_uid ] );
    }
  }
  add_action( 'wp_ajax_shec_step1', 'shec_handle_step1' );
  add_action( 'wp_ajax_nopriv_shec_step1', 'shec_handle_step1' );

  /* ---------------------------------
  * STEP 2 (update loss pattern)
  * --------------------------------- */
  if ( ! function_exists( 'shec_handle_step2' ) ) {
    function shec_handle_step2() {
      shec_check_nonce_or_bypass();

      $uid     = intval( $_POST['user_id'] ?? 0 );
      $pattern = sanitize_text_field( $_POST['loss_pattern'] ?? '' );
      if ( $uid <= 0 || ! $pattern ) {
        wp_send_json_error( [ 'message' => __( 'Step 2 data is incomplete.', 'shec' ) ] );
      }

      $lang = shec_get_lang(0);  

      $data = shec_get_data( $uid );
      if ( ! $data ) {
        wp_send_json_error( [ 'message' => __( 'Invalid form ID.', 'shec' ) ] );
      }

      $data['loss_pattern'] = $pattern;
      shec_update_data( $uid, $data );

      wp_send_json_success();
    }
  }
  add_action( 'wp_ajax_shec_step2', 'shec_handle_step2' );
  add_action( 'wp_ajax_nopriv_shec_step2', 'shec_handle_step2' );

  /* ---------------------------------
  * STEP 3 (upload)
  * --------------------------------- */
  if ( ! function_exists( 'shec_handle_step3' ) ) {
    function shec_handle_step3() {
      shec_check_nonce_or_bypass();

      $uid      = intval( $_POST['user_id'] ?? 0 );
      $position = sanitize_text_field( $_POST['position'] ?? '' );
      if ( $uid <= 0 || empty( $_FILES ) ) {
        wp_send_json_error( [ 'message' => __( 'Invalid file or user.', 'shec' ) ] );
      }

      $data = shec_get_data( $uid );
      if ( ! $data ) {
        wp_send_json_error( [ 'message' => __( 'Invalid form ID.', 'shec' ) ] );
      }

      require_once ABSPATH . 'wp-admin/includes/file.php';
      $uploaded = wp_handle_upload( $_FILES[ array_key_first( $_FILES ) ], [ 'test_form' => false ] );
      if ( isset( $uploaded['error'] ) ) {
        wp_send_json_error( [ 'message' => __( 'Upload error: ', 'shec' ) . $uploaded['error'] ] );
      }

      if ( ! isset( $data['uploads'] ) ) {
        $data['uploads'] = [];
      }
      $data['uploads'][ $position ] = $uploaded['url'];
      shec_update_data( $uid, $data );

      wp_send_json_success( [ 'file' => $uploaded['url'] ] );
    }
  }
  add_action( 'wp_ajax_shec_step3', 'shec_handle_step3' );
  add_action( 'wp_ajax_nopriv_shec_step3', 'shec_handle_step3' );

  /* ---------------------------------
  * STEP 4 (medical)
  * --------------------------------- */
  if ( ! function_exists( 'shec_handle_step4' ) ) {
    function shec_handle_step4() {
      shec_check_nonce_or_bypass();

      $uid = intval( $_POST['user_id'] ?? 0 );
      if ( $uid <= 0 ) {
        wp_send_json_error( [ 'message' => __( 'Invalid user.', 'shec' ) ] );
      }

      $has_medical = isset( $_POST['has_medical'] ) ? sanitize_text_field( $_POST['has_medical'] ) : '';
      $has_meds    = isset( $_POST['has_meds'] ) ? sanitize_text_field( $_POST['has_meds'] ) : '';
      if ( ! in_array( $has_medical, [ 'yes', 'no' ], true ) ) {
        wp_send_json_error( [ 'message' => __( 'Please specify whether you have any medical conditions.', 'shec' ) ] );
      }
      if ( ! in_array( $has_meds, [ 'yes', 'no' ], true ) ) {
        wp_send_json_error( [ 'message' => __( 'Please specify whether you are currently taking any medication.', 'shec' ) ] );
      }
      if ( $has_meds === 'yes' ) {
        $meds_list = trim( sanitize_text_field( $_POST['meds_list'] ?? '' ) );
        if ( $meds_list === '' ) {
          wp_send_json_error( [ 'message' => __( 'Please enter the medication name.', 'shec' ) ] );
        }
      }

      $data = shec_get_data( $uid );
      if ( ! $data ) {
        wp_send_json_error( [ 'message' => __( 'Invalid form ID.', 'shec' ) ] );
      }

      $medical = array_map( 'sanitize_text_field', $_POST );
      unset( $medical['_nonce'], $medical['_wpnonce'], $medical['action'], $medical['user_id'] );
      $data['medical'] = $medical;

      shec_update_data( $uid, $data );
      wp_send_json_success();
    }
  }
  add_action( 'wp_ajax_shec_step4', 'shec_handle_step4' );
  add_action( 'wp_ajax_nopriv_shec_step4', 'shec_handle_step4' );

  /* ---------------------------------
  * STEP 5 (contact) — store contact only
  * --------------------------------- */
  if ( ! function_exists( 'shec_handle_step5' ) ) {
    function shec_handle_step5() {
      shec_check_nonce_or_bypass();

      $uid = intval( $_POST['user_id'] ?? 0 );
      if ( $uid <= 0 ) {
        wp_send_json_error( [ 'message' => __( 'Invalid user.', 'shec' ) ] );
      }

      $data = shec_get_data( $uid );
      if ( ! $data ) {
        wp_send_json_error( [ 'message' => __( 'No data found for this user.', 'shec' ) ] );
      }

      $first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
      $last_name  = sanitize_text_field( $_POST['last_name'] ?? '' );
      $state      = sanitize_text_field( $_POST['state'] ?? '' );
      $city       = sanitize_text_field( $_POST['city'] ?? '' );
      $social     = sanitize_text_field( $_POST['social'] ?? '' );

      if ( ! $first_name || ! $last_name || ! $state || ! $city || ! $social ) {
        wp_send_json_error( [ 'message' => __( 'All fields in step 5 are required.', 'shec' ) ] );
      }

      if ( ! isset( $data['contact'] ) ) {
        $data['contact'] = [];
      }
      $data['contact'] = array_merge( $data['contact'], compact( 'first_name', 'last_name', 'state', 'city', 'social' ) );
      shec_update_data( $uid, $data );

      wp_send_json_success( [ 'user' => $data ] );
    }
  }
  add_action( 'wp_ajax_shec_step5', 'shec_handle_step5' );
  add_action( 'wp_ajax_nopriv_shec_step5', 'shec_handle_step5' );

  /* ---------------------------------
  * AI QUESTIONS (robust 4-questions; English source, translatable)
  * --------------------------------- */
  if ( ! function_exists( 'shec_prompt_questions_default' ) ) {
    function shec_prompt_questions_default() {
      // Poedit can extract this entire prompt:
      return __( <<<'EOT'
  # Your role
  You are a hair transplant clinic intake assistant. Return JSON only.

  # Required output
  {"questions": ["...", "...", "...", "..."]}

  # Rules
  - Exactly 4 short yes/no questions, simple and non-technical, in the site language.
  - Tailor questions to the patient summary (gender/age/loss pattern/medical file/medications/severity & trend).
  - Do not repeat facts already known; ask for important complementary points.
  - No extra text; valid JSON only.

  # Input
  Patient summary (JSON):
  {{SUMMARY_JSON}}
  EOT, 'shec' );
    }
  }
  if ( ! function_exists( 'shec_prompt_final_default' ) ) {
    function shec_prompt_final_default() {
      return __( <<<'EOT'
  # Your role
  You are the hair transplant specialist assistant at Fakhraei Clinic. Return ONLY valid JSON.

  # Required output (exact keys)
  {
    "method": "FIT",
    "graft_count": 0,
    "analysis": "<100–160 words, friendly and reassuring analysis in the site language>",
    "pattern_explain": {
      "label": "Norwood 5 | Ludwig II | …",
      "what_it_is": "<very short, clear description of the pattern>",
      "why_happens": "<common causes: genetics/hormones/lifestyle; very short>",
      "fit_ok": true,
      "note": "<if Norwood 1 or Ludwig I: hair transplant is usually unnecessary; maintenance therapy recommended. Otherwise empty>"
    },
    "concern_box": "<empathetic, calming note precisely matching the user’s concern; avoid direct invitation to visit except for an optional one-sentence closer>",
    "followups": [
      {"q":"<Question 1>","a":"Yes|No","tip":"<one short actionable tip based on the answer>"},
      {"q":"<Question 2>","a":"Yes|No","tip":"<…>"},
      {"q":"<Question 3>","a":"Yes|No","tip":"<…>"},
      {"q":"<Question 4>","a":"Yes|No","tip":"<…>"}
    ],
    "pre_op": ["<0–3 short pre-op tips tailored to the answers>"],
    "post_op":["<0–3 short post-op tips tailored to the answers>"],
    "followup_summary":"<~120-word empathetic summary based on the answers>"
  }

  # Important rules
  - Use the site language and a polite, reassuring tone.
  - method must always be "FIT"; do not mention FUT.
  - Set graft_count to 0 (system calculates it).
  - analysis MUST include: likely cause of loss + assurance about natural results at Fakhraei + 2–3 simple tips until surgery + a clear treatment path.
  - concern_box must reflect the recorded concern (cost/pain/recovery/time-to-result/etc.).
  - pattern_explain: male → Norwood (stage from pattern-1..6), female → Ludwig I/II/III (map 1–2/3–4/5–6).
  - followups: for each q/a, normalize a to Yes/No and give one precise, actionable tip (smoking/sleep/stress/infection/worsening…).
  - pre_op/post_op must be tailored to this specific user, not generic.
  - No extra text/Markdown/code block; JSON object only.

  # Input
  Pack (JSON):
  {{PACK_JSON}}

  # JSON only
  EOT, 'shec' );
    }
  }
  if ( ! function_exists( 'shec_get_prompt_questions' ) ) {
    function shec_get_prompt_questions() {
      $p = get_option( 'shec_prompt_questions', '' );
      return $p ?: shec_prompt_questions_default();
    }
  }
  if ( ! function_exists( 'shec_get_prompt_final' ) ) {
    function shec_get_prompt_final() {
      $p = get_option( 'shec_prompt_final', '' );
      return $p ?: shec_prompt_final_default();
    }
  }
  if ( ! function_exists( 'shec_render_template' ) ) {
    function shec_render_template( $tpl, array $vars ) {
      foreach ( $vars as $k => $v ) {
        if ( is_array( $v ) || is_object( $v ) ) {
          $v = wp_json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        }
        $tpl = str_replace( '{{' . $k . '}}', (string) $v, $tpl );
      }
      return $tpl;
    }
  }

  /* ---------------------------------
  * AI QUESTIONS endpoint
  * --------------------------------- */
  if ( ! function_exists( 'shec_ai_questions' ) ) {
    function shec_ai_questions() {
      shec_check_nonce_or_bypass();
      $lang = shec_get_lang($uid);
      $uid = intval( $_POST['user_id'] ?? 0 );
      if ( $uid <= 0 ) {
        wp_send_json_error( [ 'message' => __( 'Invalid user.', 'shec' ) ] );
      }

      $data = shec_get_data( $uid );
      if ( ! $data ) {
        wp_send_json_error( [ 'message' => __( 'No data found for this user.', 'shec' ) ] );
      }

      $summary = [
        'gender'        => $data['gender'] ?? null,
        'age'           => $data['age'] ?? null,
        'confidence'    => $data['confidence'] ?? null,
        'loss_pattern'  => $data['loss_pattern'] ?? null,
        'medical'       => $data['medical'] ?? null,
        'uploads_count' => ( isset( $data['uploads'] ) && is_array( $data['uploads'] ) ) ? count( $data['uploads'] ) : 0,
        'lang'       => $lang ?? null,
      ];
      $fp = sha1( wp_json_encode( $summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

      $prev = $data['ai']['followups'] ?? [];
      if (
        ! empty( $prev['questions'] ) &&
        count( (array) $prev['questions'] ) === 4 &&
        ( $prev['fp'] ?? '' ) === $fp &&
        ( time() - (int) ( $prev['generated_at'] ?? 0 ) ) < 7 * DAY_IN_SECONDS
      ) {
        return wp_send_json_success( [
          'questions' => array_values( $prev['questions'] ),
          'debug'     => [
            'marker'       => 'aiq_cached4',
            'source'       => 'cache',
            'generated_at' => $prev['generated_at'],
            'fp'           => $fp,
          ],
          'summary'   => $summary,
          'lang'       => $lang ?? null,
        ] );
      }

      // English fallback (source strings are translatable)
      $fallback = [
        __( 'Is there a family history of hair loss?', 'shec' ),
        __( 'Has your hair loss worsened over the past 12 months?', 'shec' ),
        __( 'Do you currently use tobacco (cigarettes, vape, hookah)?', 'shec' ),
        __( 'Have your sleep quality or stress levels worsened recently?', 'shec' ),
      ];

      $questions = null;
      $debug     = [ 'marker' => 'aiq_dyn4', 'source' => 'fallback', 'error' => null, 'retry' => 0 ];

      $prompt_template = shec_get_prompt_questions();
      $summary_json    = wp_json_encode( $summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
      $lang = shec_get_lang($uid);
      $prompt_user     = ( strpos( $prompt_template, '{{SUMMARY_JSON}}' ) !== false )
        ? str_replace( '{{SUMMARY_JSON}}', $summary_json, $prompt_template )
        : ( $prompt_template . "\n\n" . __( 'Patient summary (JSON):', 'shec' ) . "\n" . $summary_json );

      //DEBUG
      shec_console('AIQ → prompt', $prompt_user);
      shec_console('AIQ → SUMMARY_JSON', $summary);

      if ( shec_openai_api_key() ) {
        $resp = shec_openai_chat(
          [
            [ 'role' => 'system', 'content' => __( 'Return ONLY a valid JSON object with key "questions" and an array of exactly 4 short yes/no questions. No extra text.', 'shec' ) ],
            [ 'role' => 'user',   'content' => $prompt_user ],
          ],
          [ 'temperature' => 0.0 ]
        );

        shec_console('AIQ ← raw', $resp['content'] ?? '');
        shec_console('AIQ ← parsed', isset($parsed) ? $parsed : '(parse later)');

        if ( $resp['ok'] ) {
          $raw    = (string) ( $resp['content'] ?? '' );
          $parsed = shec_json_force_decode_object( $raw );
          $q      = shec_extract_questions_from_json( $parsed );
          $q      = shec_ensure_four_questions( $q, $fallback );
          if ( count( $q ) === 4 ) {
            $questions     = $q;
            $debug['source'] = 'openai';
          } else {
            $debug['error'] = 'normalize-fail-after-openai';
          }
        } else {
          $debug['error'] = $resp['error'] ?? 'openai call failed';
        }

        if ( ! $questions || count( $questions ) !== 4 ) {
          $debug['retry'] = 1;
          $resp2          = shec_openai_chat(
            [
              [ 'role' => 'system', 'content' => __( 'Return ONLY this exact JSON shape and nothing else: {"questions":["","","",""]}', 'shec' ) ],
              [ 'role' => 'user',   'content' => __( 'Based on this patient summary, return exactly 4 short yes/no questions:', 'shec' ) . "\n" . $summary_json ],
            ],
            [ 'temperature' => 0.0 ]
          );
          if ( $resp2['ok'] ) {
            $raw2    = (string) ( $resp2['content'] ?? '' );
            $parsed2 = shec_json_force_decode_object( $raw2 );
            $q2      = shec_extract_questions_from_json( $parsed2 );
            $q2      = shec_ensure_four_questions( $q2, $fallback );
            if ( count( $q2 ) === 4 ) {
              $questions       = $q2;
              $debug['source'] = 'openai';
            } else {
              $debug['error'] = 'normalize-fail-after-retry';
            }
          } else {
            $debug['error'] = ( $debug['error'] ?: '' ) . ' | retry: ' . ( $resp2['error'] ?? 'openai retry failed' );
          }
        }
      } else {
        $debug['error'] = 'no api key';
      }

      if ( ! $questions || count( $questions ) !== 4 ) {
        $questions      = shec_ensure_four_questions( (array) $questions, $fallback );
        $debug['source'] = 'openai+repair';
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $debug['error'] ) ) {
          error_log( '[shec_ai_questions] repair: ' . print_r( $debug, true ) );
        }
      }

      if ( ! isset( $data['ai'] ) ) {
        $data['ai'] = [];
      }
      $data['ai']['followups'] = [
        'questions'    => array_values( $questions ),
        'generated_at' => time(),
        'fp'           => $fp,
        'source'       => $debug['source'],
      ];
      shec_update_data( $uid, $data );

      wp_send_json_success( [ 'questions' => $questions, 'debug' => $debug, 'summary' => $summary ] );
    }
  }
  add_action( 'wp_ajax_shec_ai_questions', 'shec_ai_questions' );
  add_action( 'wp_ajax_nopriv_shec_ai_questions', 'shec_ai_questions' );

  /* ===== JSON extract helpers (unchanged) ===== */
  if ( ! function_exists( 'shec_json_force_decode_object' ) ) {
    function shec_json_force_decode_object( $text ) {
      $text = trim( (string) $text );
      $text = preg_replace( '~^```(?:json)?\s*|\s*```$~u', '', $text );
      $j    = json_decode( $text, true );
      if ( is_array( $j ) ) return $j;
      if ( preg_match( '~\{(?:[^{}]|(?R))*\}~su', $text, $m ) ) {
        $j = json_decode( $m[0], true );
        if ( is_array( $j ) ) return $j;
      }
      return [ '__raw' => $text ];
    }
  }
  if ( ! function_exists( 'shec_extract_questions_from_json' ) ) {
    function shec_extract_questions_from_json( $parsed ) {
      $arr = [];
      if ( is_array( $parsed ) ) {
        $candidates = [ 'questions', 'qs', 'items', 'list' /* plus legacy keys if needed */ ];
        foreach ( $candidates as $k ) {
          if ( isset( $parsed[ $k ] ) ) { $arr = $parsed[ $k ]; break; }
        }
        if ( ! $arr && array_keys( $parsed ) === range( 0, count( $parsed ) - 1 ) ) $arr = $parsed;
        if ( ! $arr && ! empty( $parsed['__raw'] ) && is_string( $parsed['__raw'] ) ) {
          $lines = preg_split( '~\r?\n+~', $parsed['__raw'] );
          $arr   = array_values( array_filter( array_map( 'trim', $lines ) ) );
        }
      }
      $out = [];
      if ( is_array( $arr ) ) {
        foreach ( $arr as $it ) {
          if ( is_string( $it ) ) $out[] = $it;
          elseif ( is_array( $it ) ) {
            $cand = $it['q'] ?? ( $it['text'] ?? ( $it['title'] ?? ( $it['label'] ?? '' ) ) );
            if ( $cand !== '' ) $out[] = $cand;
          }
        }
      }
      return shec_clean_questions_array( $out );
    }
  }
  if ( ! function_exists( 'shec_clean_questions_array' ) ) {
    function shec_clean_questions_array( $arr ) {
      if ( ! is_array( $arr ) ) return [];
      $out = [];
      foreach ( $arr as $x ) {
        $s = trim( (string) $x );
        $s = preg_replace( '~^\s*([0-9۰-۹]+[\)\.\-:]|\-|\•)\s*~u', '', $s );
        if ( mb_strlen( $s, 'UTF-8' ) > 140 ) $s = mb_substr( $s, 0, 140, 'UTF-8' ) . '…';
        if ( $s !== '' && ! in_array( $s, $out, true ) ) $out[] = $s;
      }
      return $out;
    }
  }
  if ( ! function_exists( 'shec_ensure_four_questions' ) ) {
    function shec_ensure_four_questions( $arr, $fallback ) {
      $arr = shec_clean_questions_array( $arr );
      if ( count( $arr ) >= 4 ) return array_slice( $arr, 0, 4 );
      foreach ( $fallback as $f ) {
        if ( ! in_array( $f, $arr, true ) ) $arr[] = $f;
        if ( count( $arr ) >= 4 ) break;
      }
      while ( count( $arr ) < 4 ) $arr[] = $fallback[0];
      return $arr;
    }
  }



/* ---------------------------------
 * FINALIZE (store answers + final + token)
 * --------------------------------- */

/* --------------------------------------------
 *  Lightweight logger (writes to PHP error_log)
 *  فعال فقط وقتی WP_DEBUG=true باشد
 * -------------------------------------------- */
if (!function_exists('shec_log')) {
  function shec_log($label, $data = null) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) return;
    if (is_string($data)) {
      $txt = $data;
    } else {
      $txt = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    error_log("[SHEC] {$label}: {$txt}");
  }
}

/* --------------------------------------------
 *  Fallback امن برای pattern_explain
 *  اگر AI خالی/ناقص برگرداند، این تابع آن را کامل می‌کند
 * -------------------------------------------- */
if ( ! function_exists( 'shec_fix_pattern_explain' ) ) {
  function shec_fix_pattern_explain( $pe, $gender, $patternVal ) {
    $pe     = ( is_array( $pe ) ? $pe : [] );
    $gender = strtolower( $gender ?: 'male' );

    // extract stage from pattern-1..6
    $stage = 0;
    if ( preg_match( '~pattern[-_\s]?(\d+)~i', (string) $patternVal, $m ) ) {
      $stage = max( 1, min( 6, intval( $m[1] ) ) );
    }

    if ( $gender === 'female' ) {
      $label = ( $stage <= 2 ) ? 'Ludwig I' : ( ( $stage <= 4 ) ? 'Ludwig II' : 'Ludwig III' );
      $fb = [
        'label'       => $label,
        'what_it_is'  => __( 'Diffuse thinning across the central scalp that widens as it progresses.', 'shec' ),
        'why_happens' => __( 'Hormonal and genetic factors; stress and lifestyle can contribute.', 'shec' ),
        'fit_ok'      => true,
        'note'        => ( $label === 'Ludwig I' )
          ? __( 'At this stage, a transplant is usually not necessary; maintenance therapy is recommended.', 'shec' )
          : '',
      ];
    } else {
      $lbl = $stage ? ( 'Norwood ' . $stage ) : 'Norwood';
      $fb = [
        'label'       => $lbl,
        'what_it_is'  => ( $stage >= 5 )
          ? __( 'Frontal and vertex involvement with a narrowing mid-bridge.', 'shec' )
          : __( 'Frontal hairline recession or localized thinning with a progressive course.', 'shec' ),
        'why_happens' => __( 'Genetics and follicular sensitivity to androgens; stress and lifestyle may modify severity.', 'shec' ),
        'fit_ok'      => true,
        'note'        => ( $stage === 1 )
          ? __( 'At this stage, a transplant is usually not necessary; maintenance therapy is recommended.', 'shec' )
          : '',
      ];
    }

    // keep only non-empty AI values; fill the rest from fallback
    $out = [
      'label'       => ( isset( $pe['label'] )       && trim( (string) $pe['label'] )       !== '' ) ? trim( (string) $pe['label'] )       : $fb['label'],
      'what_it_is'  => ( isset( $pe['what_it_is'] )  && trim( (string) $pe['what_it_is'] )  !== '' ) ? trim( (string) $pe['what_it_is'] )  : $fb['what_it_is'],
      'why_happens' => ( isset( $pe['why_happens'] ) && trim( (string) $pe['why_happens'] ) !== '' ) ? trim( (string) $pe['why_happens'] ) : $fb['why_happens'],
      'fit_ok'      => array_key_exists( 'fit_ok', $pe ) ? (bool) $pe['fit_ok'] : $fb['fit_ok'],
      'note'        => ( isset( $pe['note'] )        && trim( (string) $pe['note'] )        !== '' ) ? trim( (string) $pe['note'] )        : $fb['note'],
    ];
    return $out;
  }
}

/* --------------------------------------------
 *  shec_finalize (clean + logged)
 * -------------------------------------------- */
if ( ! function_exists( 'shec_finalize' ) ) {
  function shec_finalize() {
    shec_check_nonce_or_bypass();

    // 1) initial input
    $uid = intval( $_POST['user_id'] ?? 0 );
    if ( $uid <= 0 ) {
      wp_send_json_error( [ 'message' => __( 'Invalid user.', 'shec' ) ] );
    }

    $answers = ( isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ) ? array_values( $_POST['answers'] ) : [];

    // 2) previous user data
    $data = shec_get_data( $uid );
    if ( ! $data ) {
      wp_send_json_error( [ 'message' => __( 'No data found for this user.', 'shec' ) ] );
    }

    // 3) attach step-5 answers to stored questions
    $questions = $data['ai']['followups']['questions'] ?? [];
    $qa = [];
    for ( $i = 0; $i < count( $questions ); $i++ ) {
      $qa[] = [
        'q' => (string) $questions[ $i ],
        'a' => (string) ( $answers[ $i ] ?? '' ),
      ];
    }

    // 4) store QA into profile
    if ( ! isset( $data['ai'] ) )              $data['ai'] = [];
    if ( ! isset( $data['ai']['followups'] ) ) $data['ai']['followups'] = [];
    $data['ai']['followups']['qa']           = $qa;
    $data['ai']['followups']['answers']      = $answers;
    $data['ai']['followups']['generated_at'] = time();
    shec_update_data( $uid, $data );
    $lang = shec_get_lang($uid);
    // 5) pack for final prompt
    $pack = [
      'gender'       => $data['gender']       ?? null,
      'age'          => $data['age']          ?? null,
      'loss_pattern' => $data['loss_pattern'] ?? null,
      'medical'      => $data['medical']      ?? null,
      'uploads'      => array_values( $data['uploads'] ?? [] ),
      'followups'    => $qa,
      'contact'      => $data['contact']      ?? null,
      'mobile'       => $data['mobile']       ?? null,
      'lang'         => $lang      ?? null,
    ];
    $pack_json = wp_json_encode( $pack, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    shec_log( 'finalize:prompt_input_pack', $pack );

    // 6) final prompt (inject JSON string)
    $prompt_tpl  = shec_get_prompt_final();
    $prompt_user = shec_render_template( $prompt_tpl, [ 'PACK_JSON' => $pack_json ] );
    shec_log( 'finalize:prompt_user', $prompt_user );

    // 7) call AI
    $api_key  = shec_openai_api_key();
    $use_asst = (int) get_option( 'shec_asst_enable', 0 ) === 1;
    $asst_id  = trim( (string) get_option( 'shec_asst_final_id', '' ) );
    $resp     = [ 'ok' => false, 'content' => '', 'http_code' => 0, 'error' => null ];
shec_console('FINAL → prompt', $prompt_user);
shec_console('FINAL → PACK_JSON', $pack);
    if ( $use_asst && $asst_id && $api_key && function_exists( 'shec_openai_assistant_run' ) ) {
      $resp = shec_openai_assistant_run( $asst_id, $prompt_user, 60 );
    } else {
      $messages  = [
        [ 'role' => 'system', 'content' => __( 'Return one valid JSON object exactly as instructed. Do not remove keys. No extra text or Markdown.', 'shec' ) ],
        [ 'role' => 'user',   'content' => $prompt_user ],
      ];
      $chat_opts = [
        'model'       => 'gpt-4o-mini',
        'temperature' => 0.3,
        'json_schema' => shec_json_schema_final(),
        'schema_name' => 'SmartHairCalc',
      ];
      $resp = shec_openai_chat( $messages, $chat_opts );
    }
    shec_log( 'finalize:ai_raw_response', $resp );

    // 8) safe defaults
    $final = [
      'method'           => 'FIT',
      'graft_count'      => 0,
      'analysis'         => __( 'Based on your information, the FIT technique is suitable. Your treatment path is clear, and our team at Fakhraei Clinic will support you throughout.', 'shec' ),
      'concern_box'      => '',
      'pattern_explain'  => [
        'label'       => '',
        'what_it_is'  => '',
        'why_happens' => '',
        'fit_ok'      => true,
        'note'        => '',
      ],
      'followups'        => [],
      'pre_op'           => [],
      'post_op'          => [],
      'followup_summary' => '',
    ];
shec_console('FINAL ← raw', $resp['content'] ?? '');
shec_console('FINAL ← parsed', isset($parsed) ? $parsed : '(parse later)');
    // 9) merge AI if OK
    if ( ! empty( $resp['ok'] ) ) {
      $parsed = shec_json_decode_safe( $resp['content'] );
      shec_log( 'finalize:ai_decoded', $parsed );

      if ( is_array( $parsed ) ) {
        foreach ( [ 'method', 'graft_count', 'analysis', 'concern_box', 'pattern_explain', 'followups', 'followup_summary', 'pre_op', 'post_op' ] as $k ) {
          if ( array_key_exists( $k, $parsed ) ) $final[ $k ] = $parsed[ $k ];
        }
      }
    } elseif ( ( $resp['http_code'] ?? 0 ) == 429 ) {
      shec_set_rate_limit_block( 180 );
    }

    // 10) enforce critical values + fill pattern_explain
    $final['method'] = 'FIT';
    if ( ! isset( $final['graft_count'] ) || ! is_numeric( $final['graft_count'] ) ) $final['graft_count'] = 0;
    if ( ! is_array( $final['pattern_explain'] ) ) {
      $final['pattern_explain'] = [ 'label' => '', 'what_it_is' => '', 'why_happens' => '', 'fit_ok' => true, 'note' => '' ];
    }

    $final['pattern_explain'] = shec_fix_pattern_explain(
      $final['pattern_explain'],
      $data['gender']       ?? 'male',
      $data['loss_pattern'] ?? ''
    );

    if ( ! isset( $final['pre_op'] )  || ! is_array( $final['pre_op'] ) )  $final['pre_op']  = [];
    if ( ! isset( $final['post_op'] ) || ! is_array( $final['post_op'] ) ) $final['post_op'] = [];

    // normalize followups for UI
    $final['followups'] = shec_normalize_followups_for_ui(
      $qa,
      ( is_array( $final['followups'] ?? null ) ? $final['followups'] : [] )
    );

    // clamp list sizes
    if ( count( $final['pre_op'] )  > 3 ) $final['pre_op']  = array_slice( $final['pre_op'],  0, 3 );
    if ( count( $final['post_op'] ) > 3 ) $final['post_op'] = array_slice( $final['post_op'], 0, 3 );

    $final['generated_at'] = time();

    shec_log( 'finalize:final_payload_after_merge', $final );

    // 11) persist
    $data = shec_get_data( $uid ); // freshest
    if ( ! isset( $data['ai'] ) ) $data['ai'] = [];
    $data['ai']['final'] = $final;
    shec_update_data( $uid, $data );

    // 12) public link + notify
    $pub = shec_public_link_issue( $uid, 180 );
    shec_log( 'finalize:public_link', $pub );

    shec_notify_admin_telegram( $uid, $pub['url'] );

    // 13) output (ai_result as JSON string)
    $resp_out = [
      'ai_result'      => wp_json_encode( $final, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
      'user'           => $data,
      'public_url'     => $pub['url'],
      'public_expires' => $pub['expires'],
      'token'          => $pub['token'],
    ];
    shec_log( 'finalize:response_to_front', $resp_out );

    wp_send_json_success( $resp_out );
  }
}
add_action( 'wp_ajax_shec_finalize',        'shec_finalize' );
add_action( 'wp_ajax_nopriv_shec_finalize', 'shec_finalize' );

/* ---------------------------------
 * PUBLIC: get result by token (no nonce)
 * --------------------------------- */
if ( ! function_exists( 'shec_result_by_token' ) ) {
  function shec_result_by_token() {
    global $wpdb;
    $token = sanitize_text_field( $_REQUEST['token'] ?? $_REQUEST['t'] ?? '' );
    if ( $token === '' ) {
      wp_send_json_error( [ 'message' => __( 'Token is missing.', 'shec' ) ], 400 );
    }

    // 1) transient first (fast)
    $uid = (int) get_transient( 'shec_tok_' . $token );

    // 2) if not found, read from links table via hash
    if ( $uid <= 0 ) {
      $links_table = shec_links_table();
      $hash = hash( 'sha256', $token );
      $now  = gmdate( 'Y-m-d H:i:s' );

      $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT wp_user_id FROM {$links_table}
         WHERE token_hash=%s AND is_active=1 AND (expires IS NULL OR expires >= %s)
         LIMIT 1",
        $hash, $now
      ), ARRAY_A );

      if ( $row ) {
        $uid = (int) $row['wp_user_id'];
        set_transient( 'shec_tok_' . $token, $uid, 180 * DAY_IN_SECONDS );
      }
    }

    // 3) legacy migration (token inside data), if still not found
    if ( $uid <= 0 ) {
      $table = shec_table();
      $like  = '%' . $wpdb->esc_like( $token ) . '%';
      $legacy = $wpdb->get_row( $wpdb->prepare(
        "SELECT wp_user_id, data FROM {$table} WHERE data LIKE %s LIMIT 1", $like
      ), ARRAY_A );

      if ( $legacy ) {
        $uid = (int) $legacy['wp_user_id'];

        $links_table = shec_links_table();
        $hash = hash( 'sha256', $token );
        $now  = current_time( 'mysql', 1 );
        $exp  = gmdate( 'Y-m-d H:i:s', time() + 180 * DAY_IN_SECONDS );

        // deactivate previous links
        $wpdb->update( $links_table, [ 'is_active' => 0 ], [ 'wp_user_id' => $uid ] );

        // insert new record
        $wpdb->insert( $links_table, [
          'wp_user_id' => $uid,
          'token_hash' => $hash,
          'created'    => $now,
          'expires'    => $exp,
          'is_active'  => 1,
        ], [ '%d', '%s', '%s', '%s', '%d' ] );

        set_transient( 'shec_tok_' . $token, $uid, 180 * DAY_IN_SECONDS );
      }
    }

    if ( $uid <= 0 ) {
      wp_send_json_error( [ 'message' => __( 'Result not found.', 'shec' ) ], 404 );
    }

    $data = shec_get_data( $uid );
    if ( ! $data ) {
      wp_send_json_error( [ 'message' => __( 'Result not found.', 'shec' ) ], 404 );
    }

    $final = $data['ai']['final'] ?? [
      'method'      => 'FIT',
      'graft_count' => 0,
      'analysis'    => __( 'The result is not ready yet.', 'shec' ),
    ];

    wp_send_json_success( [
      'user'       => $data,
      'ai_result'  => wp_json_encode( $final, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
      'public_url' => shec_public_page_url( $token ),
    ] );
  }
}
add_action( 'wp_ajax_shec_result_by_token',       'shec_result_by_token' );
add_action( 'wp_ajax_nopriv_shec_result_by_token','shec_result_by_token' );

/* ---------------------------------
 * Shortcode: [smart_hair_result] (renders token view)
 * --------------------------------- */
if ( ! function_exists( 'shec_result_viewer_shortcode' ) ) {
  function shec_result_viewer_shortcode( $atts = [] ) {

    $calc_url = '';
    if ( $p = get_page_by_path( 'hair-graft-calculator' ) ) {
      $calc_url = get_permalink( $p->ID );
    }
    if ( ! $calc_url ) {
      $calc_url = home_url( '/hair-graft-calculator/' );
    }

    ob_start(); ?>
    <script>
      (function(){
        if (typeof window.heRenderAllWarnings === 'function') return;

        // i18n strings injected from PHP so Poedit can translate them
        window.HE_WARNINGS = {
          diabetes:    "<?php echo esc_js( __( 'If you have diabetes, hair transplant is only possible when the condition is fully controlled. Diabetes can slow healing and raise infection risk. A written approval from your physician is required.', 'shec' ) ); ?>",
          coagulation: "<?php echo esc_js( __( 'Coagulation disorders may increase bleeding during the procedure and affect graft survival. A written approval from your physician is required.', 'shec' ) ); ?>",
          cardiac:     "<?php echo esc_js( __( 'With cardiovascular disease, transplant is possible only when the condition is stable and controlled. Local anesthetic and recovery risks may be higher. Physician approval is required.', 'shec' ) ); ?>",
          thyroid:     "<?php echo esc_js( __( 'For thyroid disorders, transplant is feasible when hormone levels are balanced; uncontrolled states can affect hair growth and graft survival. Physician approval is required.', 'shec' ) ); ?>",
          immunodef:   "<?php echo esc_js( __( 'With significant immunodeficiency (e.g., certain HIV cases or chemotherapy), transplant is usually not recommended due to higher complication rates and delayed healing. Final decision requires specialist evaluation and physician approval.', 'shec' ) ); ?>",
          autoimmune:  "<?php echo esc_js( __( 'In autoimmune conditions, feasibility depends on disease type and activity; active disease can reduce graft acceptance. Specialist evaluation and physician approval are required.', 'shec' ) ); ?>"
        };
        window.HE_SCALP_WARNINGS = {
          active_infection: "<?php echo esc_js( __( 'Active scalp infection must be fully treated before scheduling transplant to reduce complications and improve graft survival.', 'shec' ) ); ?>",
          psoriasis:        "<?php echo esc_js( __( 'If psoriasis is active—especially with wide involvement—control/treat first, then reassess transplant eligibility.', 'shec' ) ); ?>",
          fungal_derm:      "<?php echo esc_js( __( 'Seborrheic dermatitis/fungal infections should be controlled before considering transplant; active inflammation lowers success rates.', 'shec' ) ); ?>",
          folliculitis:     "<?php echo esc_js( __( 'Treat folliculitis (infection/inflammation) first; transplant can be considered afterwards.', 'shec' ) ); ?>",
          areata:           "<?php echo esc_js( __( 'Transplant is not recommended during the active phase of alopecia areata; wait until the disease is inactive.', 'shec' ) ); ?>",
          scarring_alo:     "<?php echo esc_js( __( 'Scarring alopecia can reduce transplant success; proceed only after specialist evaluation and stability of lesions.', 'shec' ) ); ?>",
          scar:             "<?php echo esc_js( __( 'Existing scalp scars may reduce success; vascularity of the recipient area must be assessed.', 'shec' ) ); ?>"
        };

        // bilingual-ish mapping (checks EN & FA keywords)
        window.heMapLabelToWarningKey = function(label){
          if(!label) return null;
          var t = String(label).toLowerCase();
          if (/(^|[^a-z])diab|دیابت/.test(t)) return 'diabetes';
          if (/coag|انعقاد/.test(t))          return 'coagulation';
          if (/card|قلب|عروقی/.test(t))       return 'cardiac';
          if (/thyroid|تیروئید/.test(t))      return 'thyroid';
          if (/immuno|hiv|chemo|ایمنی|شیمی|ایدز/.test(t)) return 'immunodef';
          if (/autoim|lupus|alopecia|خودایمنی|لوپوس|آلوپسی/.test(t)) return 'autoimmune';
          return null;
        };
        window.heMapScalpLabelToKey = function(label){
          if(!label) return null;
          var t = String(label).toLowerCase();
          if (/active[_\-\s]*infection|عفونت\s*فعال/.test(t))             return 'active_infection';
          if (/psoriasis|پسوریازیس/.test(t))                               return 'psoriasis';
          if (/fung|derm|seborr|قارچی|سبورئیک/.test(t))                    return 'fungal_derm';
          if (/folliculit|فولیکولیت/.test(t))                              return 'folliculitis';
          if (/areata|alopecia\s*areata|ریزش\s*سکه‌ای|آلوپسی\s*آره‌آتا/.test(t)) return 'areata';
          if (/scarring[_\-\s]*alo|آلوپسی\s*به\s*همراه\s*اسکار/.test(t))  return 'scarring_alo';
          if (/scar|اسکار|جای\s*زخم/.test(t))                              return 'scar';
          if (/none|هیچ|ندارم/.test(t)) return null;
          return null;
        };
        window.heRenderAllWarnings = function(opt){
          opt = opt || {};
          var systemicLabels = Array.isArray(opt.systemicLabels) ? opt.systemicLabels : [];
          var scalpLabels    = Array.isArray(opt.scalpLabels)    ? opt.scalpLabels    : [];
          var anchorSel      = opt.anchor || '#he-medical-warning-wrap';
          var host = document.querySelector(anchorSel);
          if (!host) return;
          host.innerHTML = '';

          var sysKeys = Array.from(new Set(systemicLabels.map(window.heMapLabelToWarningKey).filter(Boolean)));
          sysKeys.forEach(function(k){
            var div = document.createElement('div');
            div.className = 'he-warn-card';
            div.innerHTML = '<p>' + (window.HE_WARNINGS[k] || '') + '</p>';
            host.appendChild(div);
          });

          var scalpKeys = Array.from(new Set(scalpLabels.map(window.heMapScalpLabelToKey).filter(Boolean)));
          scalpKeys.forEach(function(k){
            var div = document.createElement('div');
            div.className = 'he-warn-card';
            div.innerHTML = '<p>' + (window.HE_SCALP_WARNINGS[k] || '') + '</p>';
            host.appendChild(div);
          });

          host.style.display = (host.children.length ? '' : 'none');
        };
      })();
    </script>
    <?php
    // HTML (English, translatable)
    $img_path = SHEC_URL . 'public/assets/img/';
    ?>
    <div id="shec-result-token" class="shec-result-root">
      <div id="proposal-pdf-root" class="proposal-container">
        <h3><?php echo esc_html__( 'Consultation Result', 'shec' ); ?></h3>

        <div id="ai-result-box" class="result-box" style="min-height:320px;padding:24px">
          <div style="opacity:.7"><?php echo esc_html__( 'Loading result…', 'shec' ); ?></div>
        </div>

        <div class="sample-info-wrapper">
          <p style="font-size:20px; font-weight:bold; text-align:center;"><?php echo esc_html__( 'You can transform your look too!', 'shec' ); ?></p>
          <img class="sample-image" src="https://fakhraei.clinic/wp-content/uploads/2025/06/BEFORE_Miss.webp" style="width: 100%;border-radius: 5px;" alt="<?php echo esc_attr__( 'Before/After sample', 'shec' ); ?>" />
        </div>

        <div class="hair-trans-wrapper">
          <img src="https://fakhraei.clinic/wp-content/uploads/2025/06/FIT1-1-scaled-1.png" style="width: 100%;border-radius: 5px;" alt="<?php echo esc_attr__( 'Hair transplant', 'shec' ); ?>" />
        </div>

        <div class="fit-timeline-wrapper">
          <p style="font-size:20px; font-weight:bold; text-align:center;margin-top: 10px;"><?php echo esc_html__( 'Expected Timeline of FIT Hair Transplant Results', 'shec' ); ?></p>
          <table class="fit-timeline-table">
            <thead>
              <tr><th><?php echo esc_html__( 'Timeframe', 'shec' ); ?></th><th><?php echo esc_html__( 'What to expect', 'shec' ); ?></th></tr>
            </thead>
            <tbody>
              <tr><td><?php echo esc_html__( 'Days 1–7', 'shec' ); ?></td><td><?php echo esc_html__( 'Redness and mild swelling are normal and improve gradually.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'Weeks 2–3', 'shec' ); ?></td><td><?php echo esc_html__( 'Temporary shedding of transplanted hairs (shock loss) — completely normal.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'Months 1–2', 'shec' ); ?></td><td><?php echo esc_html__( 'Scalp returns to normal; new hairs are not usually visible yet.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'Months 3–4', 'shec' ); ?></td><td><?php echo esc_html__( 'New hair growth starts; initially thin and soft.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'Months 5–6', 'shec' ); ?></td><td><?php echo esc_html__( 'Hair shafts strengthen and density improves.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'Months 7–9', 'shec' ); ?></td><td><?php echo esc_html__( 'Thicker, denser, more natural look; changes become obvious.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'Months 10–12', 'shec' ); ?></td><td><?php echo esc_html__( 'Around 80–90% of the final result is visible.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'After 12 months', 'shec' ); ?></td><td><?php echo esc_html__( 'Full stabilization; natural, lasting result.', 'shec' ); ?></td></tr>
            </tbody>
          </table>
        </div>

        <div class="why-padra-wrapper">
          <p style="font-size:20px; font-weight:bold; text-align:center;margin-top: 50px;"><?php echo esc_html__( 'Why choose Fakhraei Clinic?', 'shec' ); ?></p>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Black-White-Yellow-Simple-Initial-Name-Logo-22-1.png" alt="" />
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'Experienced professional team', 'shec' ); ?></span>
              <p class="why-padra-info-description"><?php echo esc_html__( 'Procedures are performed by trained technicians under the supervision of a specialist physician.', 'shec' ); ?></p>
            </div>
          </div>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003350.png" alt="" />
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'Thousands of successful procedures', 'shec' ); ?></span>
              <p class="why-padra-info-description"><?php echo esc_html__( 'With 20+ years of experience, we know how to deliver natural, lasting results.', 'shec' ); ?></p>
            </div>
          </div>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003557.png" alt="" />
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'Fair pricing without quality compromise', 'shec' ); ?></span>
              <p class="why-padra-info-description"><?php echo esc_html__( 'We aim to offer top technology and expertise at reasonable costs.', 'shec' ); ?></p>
            </div>
          </div>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003353.png" alt="" />
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'Comfortable, fully equipped environment', 'shec' ); ?></span>
              <p class="why-padra-info-description"><?php echo esc_html__( 'A calm, hygienic, and well-equipped space for a confident experience.', 'shec' ); ?></p>
            </div>
          </div>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003563.png" alt="" />
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'Complimentary lodging for out-of-town clients', 'shec' ); ?></span>
              <p class="why-padra-info-description"><?php echo esc_html__( 'We provide free accommodation for clients traveling from other cities.', 'shec' ); ?></p>
            </div>
          </div>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/bihesi.png" alt="" />
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'Comfort-focused, low-pain experience', 'shec' ); ?></span>
              <p class="why-padra-info-description"><?php echo esc_html__( 'Local anesthesia and modern techniques help you experience a comfortable procedure.', 'shec' ); ?></p>
            </div>
          </div>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003351.png" alt="" />
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'We’re with you—before and after surgery', 'shec' ); ?></span>
              <p class="why-padra-info-description"><?php echo esc_html__( 'From consultation to post-op care, we’re here to support you.', 'shec' ); ?></p>
            </div>
          </div>
        </div>

        <div class="actions mt-3" style="margin-bottom:15px; display:flex; gap:12px; flex-wrap:wrap; justify-content:center;">
          <button id="reset-form" style="margin-bottom:15px;" data-reset-href="<?php echo esc_attr( $calc_url ); ?>" class="btn btn-danger"><?php echo esc_html__( 'Start Over', 'shec' ); ?></button>
          <button id="download-pdf" style="margin-bottom:15px;" class="btn btn-primary"><?php echo esc_html__( 'Download PDF', 'shec' ); ?></button>
        </div>
      </div>
    </div>
    <?php
    $html = ob_get_clean();

    // Inline JS (status messages translated)
    $inline = sprintf(
      "(function(){
        var box = document.getElementById('ai-result-box');
        var t   = (new URLSearchParams(location.search)).get('t') || (new URLSearchParams(location.search)).get('token');
        if(!box) return;

        if(!t){
          box.innerHTML = '<div style=\"padding:24px\">%s</div>';
          return;
        }

        fetch(shec_ajax.url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: 'action=shec_result_by_token&t=' + encodeURIComponent(t)
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
          if(!res || !res.success){
            box.innerHTML = '<div style=\"padding:24px\">%s</div>';
            return;
          }
          var payload = { user: res.data.user, ai_result: res.data.ai_result };
          if (window.SHEC_renderFinal) {
            window.SHEC_renderFinal(payload);
          } else {
            box.innerHTML = '<div style=\"padding:24px\">%s</div>';
          }
        })
        .catch(function(){
          box.innerHTML = '<div style=\"padding:24px\">%s</div>';
        });
      })();",
      esc_js( __( 'Token not found.', 'shec' ) ),
      esc_js( __( 'No result found.', 'shec' ) ),
      esc_js( __( 'Result UI did not load.', 'shec' ) ),
      esc_js( __( 'Error fetching result.', 'shec' ) )
    );
    wp_add_inline_script( 'shec-form-js', $inline, 'after' );

    return $html;
  }
}
add_shortcode( 'smart_hair_result', 'shec_result_viewer_shortcode' );
