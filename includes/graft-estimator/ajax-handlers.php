<?php
/**
 * Smart Hair Graft Calculator — AJAX Handlers (Tokenized Result)
 * Version: 2.0.0
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
if (!function_exists('shec_map_yesno_to_fa')) {
  function shec_map_yesno_to_fa($v){
    $t = strtolower(trim((string)$v));
    if (in_array($t, ['yes','true','1','بله','بلی'], true)) return 'بله';
    if (in_array($t, ['no','false','0','خیر'], true))     return 'خیر';
    return ($t==='') ? '—' : $t;
  }
}

if (!function_exists('shec_guess_tip_from_question')) {
  function shec_guess_tip_from_question($q, $a_label){
    $q = (string)$q;
    $yes = ($a_label === 'بله');

    if (preg_match('~سیگار|قلیان|دخانیات~u',$q)) {
      return 'برای نتیجه بهتر، ۱۰ روز قبل تا ۷ روز بعد از کاشت از دخانیات دوری کنید؛ روی خون‌رسانی و ترمیم اثر مستقیم دارد.';
    }
    if (preg_match('~خواب|بی‌خوابی~u',$q)) {
      return 'بهداشت خواب را رعایت کنید: زمان ثابت خواب، کاهش نور آبی شب و محدودیت کافئین بعدازظهر؛ کیفیت خواب رشد مو را پشتیبانی می‌کند.';
    }
    if (preg_match('~استرس|اضطراب~u',$q)) {
      return 'کاهش استرس با تنفس عمیق، پیاده‌روی روزانه و روتین آرام‌سازی؛ مدیریت استرس ریزش تلوژن را کمتر می‌کند.';
    }
    if (preg_match('~التهاب|عفونت|زخم~u',$q)) {
      return 'اگر التهاب/عفونت پوست سر دارید، ابتدا درمان شود؛ سپس برای کاشت زمان بگذاریم تا ریسک عارضه پایین بیاید.';
    }
    if (preg_match('~دارو|بیماری|قند|دیابت|فشار~u',$q)) {
      return 'مصرف دارو و بیماری‌های زمینه‌ای را حتماً با پزشک هماهنگ کنید؛ ممکن است تنظیم دارو یا آزمایش قبل از کاشت لازم باشد.';
    }
    if (preg_match('~نقاهت|نتیجه|طول می‌کشد|چقدر|چند ماه~u',$q)) {
      return 'نتیجه به‌صورت مرحله‌ای ظاهر می‌شود: شوک‌ریزش هفته ۲–۳، شروع رشد ۳–۴ ماه، پرپشتی ۶–۹ ماه و تثبیت ۱۲ ماه.';
    }
    // نکتهٔ عمومی اگر چیزی پیدا نشد
    return $yes
      ? 'شرایط شما قابل مدیریت است؛ با رعایت توصیه‌های سبک زندگی و برنامهٔ مراقبتی، مسیر درمان روان‌تر پیش می‌رود.'
      : 'خوبه! همین روال سالم را ادامه دهید و اگر تغییری حس کردید، به‌موقع با ما هماهنگ کنید.';
  }
}

if (!function_exists('shec_normalize_followups_for_ui')) {
  /**
   * @param array $qa           آرایهٔ [{q,a}] از پاسخ‌های کاربر (yes/no)
   * @param array $ai_followups آرایهٔ AI که ممکن است با کلیدهای مختلف (a|tip یا a_label|ai_tip) آمده باشد
   * @return array              آرایهٔ نهایی [{q, a_label, ai_tip}] دقیقاً برای UI
   */
  function shec_normalize_followups_for_ui(array $qa, array $ai_followups){
    // ایندکس AI بر اساس q (برای مچ بهتر)
    $ai_by_q = [];
    foreach ($ai_followups as $item) {
      $q = trim((string)($item['q'] ?? ''));
      if ($q==='') continue;
      // نرمال‌سازی احتمالی کلیدها
      $a_label = $item['a_label'] ?? null;
      if (!$a_label && isset($item['a'])) $a_label = shec_map_yesno_to_fa($item['a']);
      $ai_tip  = $item['ai_tip']  ?? ($item['tip'] ?? '');
      $ai_by_q[$q] = ['a_label'=>$a_label, 'ai_tip'=>$ai_tip];
    }

    $out = [];
    foreach ($qa as $row) {
      $q = trim((string)($row['q'] ?? ''));
      if ($q==='') continue;

      // a_label از پاسخ‌های کاربر (قطعی‌تر)
      $a_label = shec_map_yesno_to_fa($row['a'] ?? '');

      // تلاش برای گرفتن tip از AI؛ اگر نبود حدس بزن
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

    // اطمینان از ۴ مورد
    while (count($out) < 4) {
      $out[] = [
        'q'       => '—',
        'a_label' => 'خیر',
        'ai_tip'  => 'برای تکمیل اطلاعات، به چند نکتهٔ سبک زندگی و مراقبتی توجه کنید؛ در جلسهٔ مشاوره، دقیق‌تر تنظیم می‌کنیم.'
      ];
    }
    if (count($out) > 4) $out = array_slice($out, 0, 4);

    return $out;
  }
}

// اسکیمای ۴ سؤال باینری
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
      if (in_array($status, ['completed','failed','cancelled','expired'])) break;
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


/* ---------------------------------
 * Token helpers (guarded)
 * --------------------------------- */
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

    // 1) اگر قبلاً توکن plaintext در data داریم و منقضی نشده، همونو استفاده کن و مطمئن شو در links هم هست
    if (!empty($data['public_token']['token'])) {
      $tok = (string)$data['public_token']['token'];
      $exp = (int)($data['public_token']['expires'] ?? 0);

      // اگر منقضی شده، تاریخ را تمدید کن
      if ($exp <= time()) {
        $exp = $expires_ts;
        $data['public_token']['expires'] = $exp;
        shec_update_data($uid, $data);
      }

      // اطمینان از وجود رکورد در links (upsert سبک)
      $hash = hash('sha256', $tok);
      $row  = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$links_table} WHERE token_hash=%s LIMIT 1",
        $hash
      ), ARRAY_A);

      if (!$row) {
        // همه لینک‌های قبلی کاربر را غیرفعال کن (اختیاری ولی تمیزتر)
        $wpdb->update($links_table, ['is_active'=>0], ['wp_user_id'=>$uid]);

        $wpdb->insert($links_table, [
          'wp_user_id' => $uid,
          'token_hash' => $hash,
          'created'    => current_time('mysql', 1), // GMT
          'expires'    => $expires_dt,
          'is_active'  => 1,
        ], ['%d','%s','%s','%s','%d']);
      } else {
        // تمدید expiry و فعال‌سازی
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

    // 2) تولید توکن جدید و ذخیره در هر دو جا (data + links)
    //    احتیاط: احتمال خیلی کمِ تکرار → تلاش چندباره
    $tok = '';
    for ($i=0; $i<5; $i++) {
      $tok = shec_generate_token(9);
      $hash = hash('sha256', $tok);
      $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$links_table} WHERE token_hash=%s",
        $hash
      ));
      if (!$exists) break;
      $tok = '';
    }
    if ($tok === '') {
      return ['url'=>'', 'token'=>'', 'expires'=>0]; // خیلی نادر
    }

    // همه لینک‌های قبلی کاربر را غیرفعال کن (اختیاری)
    $wpdb->update($links_table, ['is_active'=>0], ['wp_user_id'=>$uid]);

    $wpdb->insert($links_table, [
      'wp_user_id' => $uid,
      'token_hash' => $hash,
      'created'    => current_time('mysql', 1), // GMT
      'expires'    => $expires_dt,
      'is_active'  => 1,
    ], ['%d','%s','%s','%s','%d']);

    // نگه‌داری plaintext token در data جهت reuse
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
  "post_op":["<۰ تا ۳ توصیه‌ی کوتاه پس از کاشت کاملاً متناسب با پاسخ‌ها>"],
  "followup_summary":"<خلاصه‌ی همدلانه ~۱۲۰ کلمه بر اساس پاسخ‌ها>"
}

# قواعد مهم
- همه‌چیز فارسی محاوره‌ـمودبانه؛ لحن همدلانه و مطمئن‌کننده.
- method همیشه "FIT" باشد؛ از FUT نام نبر.
- graft_count را 0 بگذار (سیستم محاسبه می‌کند).
- analysis حتماً شامل: علت احتمالی ریزش + اطمینان‌بخشی نتیجه طبیعی در کلینیک فخرائی + 2–3 توصیه ساده تا زمان کاشت + جمع‌بندی روشن مسیر درمان.
- concern_box متناسب با دغدغه ثبت‌شده؛ نمونه‌ها: هزینه/درد/نقاهت/طول‌کشیدن نتیجه/… .
- pattern_explain: male→Norwood(stage from pattern-1..6)، female→Ludwig I/II/III (mapping 1–2/3–4/5–6).
- followups: برای هر q/a، a را «بله/خیر» کن و tip عملی و دقیق بده (سیگار/خواب/استرس/عفونت/بدترشدن…).
- pre_op/post_op عمومی ننویس؛ دقیقاً متناسب با پاسخ‌های همین کاربر باشد.
- هیچ متن اضافه/Markdown/کدبلاک نده؛ فقط JSON شیء واحد.

# ورودی
اطلاعات بیمار (JSON):
{{PACK_JSON}}

# فقط JSON
EOT;
  }
}
if (!function_exists('shec_get_prompt_questions')) { function shec_get_prompt_questions(){ $p=get_option('shec_prompt_questions',''); return $p ?: shec_prompt_questions_default(); } }
if (!function_exists('shec_get_prompt_final'))     { function shec_get_prompt_final(){ $p=get_option('shec_prompt_final','');     return $p ?: shec_prompt_final_default(); } }
if (!function_exists('shec_render_template')) {
  function shec_render_template($tpl, array $vars){
    foreach ($vars as $k=>$v) {
      if (is_array($v) || is_object($v)) $v = wp_json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
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
 * STEP 2  (UPDATE loss pattern)
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
    unset($medical['_nonce'],$medical['_wpnonce'],$medical['action'],$medical['user_id']);
    $data['medical'] = $medical;

    shec_update_data($uid, $data);
    wp_send_json_success();
  }
}
add_action('wp_ajax_shec_step4','shec_handle_step4');
add_action('wp_ajax_nopriv_shec_step4','shec_handle_step4');

/* ---------------------------------
 * STEP 5 (contact) — فقط ذخیره تماس
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

    // همینجا AI نمی‌زنیم؛ finalize این کار را می‌کند
    wp_send_json_success(['user'=>$data]);
  }
}
add_action('wp_ajax_shec_step5','shec_handle_step5');
add_action('wp_ajax_nopriv_shec_step5','shec_handle_step5');

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

    $summary = [
      'gender'        => $data['gender'] ?? null,
      'age'           => $data['age'] ?? null,
      'confidence'    => $data['confidence'] ?? null,
      'loss_pattern'  => $data['loss_pattern'] ?? null,
      'medical'       => $data['medical'] ?? null,
      'uploads_count' => (isset($data['uploads']) && is_array($data['uploads'])) ? count($data['uploads']) : 0,
    ];
    $fp = sha1( wp_json_encode($summary, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) );

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

    $fallback = [
      'آیا در خانواده‌تان سابقهٔ ریزش مو وجود دارد؟',
      'آیا طی ۱۲ ماه گذشته شدت ریزش موی شما بیشتر شده است؟',
      'آیا در حال حاضر سیگار یا قلیان مصرف می‌کنید؟',
      'آیا خواب و استرس شما در ماه‌های اخیر بدتر شده است؟'
    ];

    $questions = null;
    $debug = ['marker'=>'aiq_dyn4','source'=>'fallback','error'=>null,'retry'=>0];

    $prompt_template = shec_get_prompt_questions();
    $summary_json    = wp_json_encode($summary, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $prompt_user     = (strpos($prompt_template, '{{SUMMARY_JSON}}') !== false)
      ? str_replace('{{SUMMARY_JSON}}', $summary_json, $prompt_template)
      : ($prompt_template . "\n\nخلاصهٔ بیمار (JSON):\n" . $summary_json);

    if (shec_openai_api_key()) {
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
        $q      = shec_extract_questions_from_json($parsed);
        $q      = shec_ensure_four_questions($q, $fallback);
        if (count($q) === 4) { $questions = $q; $debug['source'] = 'openai'; }
        else { $debug['error'] = 'normalize-fail-after-openai'; }
      } else {
        $debug['error'] = $resp['error'] ?? 'openai call failed';
      }

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

    if (!$questions || count($questions)!==4) {
      $questions = shec_ensure_four_questions((array)$questions, $fallback);
      $debug['source'] = 'openai+repair';
      if (defined('WP_DEBUG') && WP_DEBUG && !empty($debug['error'])) {
        error_log('[shec_ai_questions] repair: '. print_r($debug,true));
      }
    }

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

/* ===== JSON extract helpers ===== */
if (!function_exists('shec_json_force_decode_object')) {
  function shec_json_force_decode_object($text) {
    $text = trim((string)$text);
    $text = preg_replace('~^```(?:json)?\s*|\s*```$~u', '', $text);
    $j = json_decode($text, true);
    if (is_array($j)) return $j;
    if (preg_match('~\{(?:[^{}]|(?R))*\}~su', $text, $m)) {
      $j = json_decode($m[0], true);
      if (is_array($j)) return $j;
    }
    return ['__raw'=>$text];
  }
}
if (!function_exists('shec_extract_questions_from_json')) {
  function shec_extract_questions_from_json($parsed) {
    $arr = [];
    if (is_array($parsed)) {
      $candidates = ['questions','سوالات','پرسش‌ها','qs','items','list'];
      foreach ($candidates as $k) { if (isset($parsed[$k])) { $arr = $parsed[$k]; break; } }
      if (!$arr && array_keys($parsed)===range(0,count($parsed)-1)) $arr = $parsed;
      if (!$arr && !empty($parsed['__raw']) && is_string($parsed['__raw'])) {
        $lines = preg_split('~\r?\n+~', $parsed['__raw']);
        $arr = array_values(array_filter(array_map('trim', $lines)));
      }
    }
    $out = [];
    if (is_array($arr)) {
      foreach ($arr as $it) {
        if (is_string($it)) $out[] = $it;
        elseif (is_array($it)) {
          $cand = $it['q'] ?? ($it['text'] ?? ($it['title'] ?? ($it['label'] ?? '')));
          if ($cand !== '') $out[] = $cand;
        }
      }
    }
    return shec_clean_questions_array($out);
  }
}
if (!function_exists('shec_clean_questions_array')) {
  function shec_clean_questions_array($arr) {
    if (!is_array($arr)) return [];
    $out = [];
    foreach ($arr as $x) {
      $s = trim((string)$x);
      $s = preg_replace('~^\s*([0-9۰-۹]+[\)\.\-:]|\-|\•)\s*~u', '', $s);
      if (mb_strlen($s,'UTF-8') > 140) $s = mb_substr($s, 0, 140, 'UTF-8').'…';
      if ($s !== '' && !in_array($s, $out, true)) $out[] = $s;
    }
    return $out;
  }
}
if (!function_exists('shec_ensure_four_questions')) {
  function shec_ensure_four_questions($arr, $fallback) {
    $arr = shec_clean_questions_array($arr);
    if (count($arr) >= 4) return array_slice($arr, 0, 4);
    foreach ($fallback as $f) {
      if (!in_array($f, $arr, true)) $arr[] = $f;
      if (count($arr) >= 4) break;
    }
    while (count($arr) < 4) $arr[] = $fallback[0];
    return $arr;
  }
}

add_action('wp_ajax_shec_ai_questions', 'shec_ai_questions');
add_action('wp_ajax_nopriv_shec_ai_questions', 'shec_ai_questions');

/* ---------------------------------
 * FINALIZE (store answers + final + token)
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
    $data['ai']['followups']['qa']       = $qa;
    $data['ai']['followups']['answers']  = $answers;
    $data['ai']['followups']['generated_at'] = time();
    shec_update_data($uid, $data);

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

    // پرامپت نهایی از تنظیمات + تزریق PACK_JSON
    $prompt_user = shec_render_template(shec_get_prompt_final(), ['PACK_JSON' => $pack]);

    // ------------- AI CALL (Assistants یا Chat) -------------
    $api_key   = shec_openai_api_key();
    $use_asst  = (int) get_option('shec_asst_enable', 0) === 1;
    $asst_id   = trim((string) get_option('shec_asst_final_id', ''));
    $resp      = ['ok'=>false,'content'=>'','http_code'=>0,'error'=>null];

    if ($use_asst && $asst_id && $api_key && function_exists('shec_openai_assistant_run')) {
      $resp = shec_openai_assistant_run($asst_id, $prompt_user, 60);
    } else {
      $messages = [
        ['role'=>'system','content'=>'فقط و فقط یک شیء JSON معتبر مطابق دستور برگردان. کلیدها حذف نشوند؛ متن اضافه و مارک‌داون ممنوع.'],
        ['role'=>'user','content'=>$prompt_user],
      ];
      $chat_opts = [
        'model' => 'gpt-4o-mini',
        'temperature' => 0.3,
        'json_schema' => shec_json_schema_final(),
        'schema_name' => 'SmartHairCalc'
      ];
      $resp = shec_openai_chat($messages, $chat_opts);
    }
    // ------------- END AI CALL -------------

    // پیش‌فرض امن
    $final = [
      'method'           => 'FIT',
      'graft_count'      => 0,
      'analysis'         => 'با توجه به اطلاعات شما، روش FIT مناسب است. مسیر درمان روشن است و در کلینیک فخرائی همراه شماییم.',
      'concern_box'      => '',
      'pattern_explain'  => [
        'label'       => '',
        'what_it_is'  => '',
        'why_happens' => '',
        'fit_ok'      => true,
        'note'        => ''
      ],
      'followups'        => [],   // بعداً نرمال‌سازی می‌کنیم
      'pre_op'           => [],
      'post_op'          => [],
      'followup_summary' => '',
    ];

    if (!empty($resp['ok'])) {
      $parsed = shec_json_decode_safe($resp['content']);
      if (is_array($parsed)) {
        foreach (['method','graft_count','analysis','concern_box','pattern_explain','followups','followup_summary','pre_op','post_op'] as $k) {
          if (isset($parsed[$k])) $final[$k] = $parsed[$k];
        }
      }
    } else if (($resp['http_code'] ?? 0) == 429) {
      shec_set_rate_limit_block(180);
    }

    // enforce مقادیر حساس
    $final['method'] = 'FIT';
    if (!isset($final['graft_count']) || !is_numeric($final['graft_count'])) $final['graft_count'] = 0;
    if (!is_array($final['pattern_explain'])) {
      $final['pattern_explain'] = ['label'=>'','what_it_is'=>'','why_happens'=>'','fit_ok'=>true,'note'=>''];
    }
    if (!isset($final['pre_op']) || !is_array($final['pre_op']))   $final['pre_op']  = [];
    if (!isset($final['post_op']) || !is_array($final['post_op'])) $final['post_op'] = [];

    // ✨ نرمال‌سازی followups به فرم دقیق UI (q, a_label, ai_tip)
    $final['followups'] = shec_normalize_followups_for_ui($qa, is_array($final['followups'] ?? null) ? $final['followups'] : []);

    // محدودیت تعداد آیتم‌ها
    if (count($final['pre_op'])  > 3) $final['pre_op']  = array_slice($final['pre_op'],  0, 3);
    if (count($final['post_op']) > 3) $final['post_op'] = array_slice($final['post_op'], 0, 3);

    $final['generated_at'] = time();

    // ذخیره
    $data = shec_get_data($uid);
    if (!isset($data['ai'])) $data['ai'] = [];
    $data['ai']['final'] = $final;
    shec_update_data($uid, $data);

    // صدور لینک عمومی
    $pub = shec_public_link_issue($uid, 180);

    // TELEGRAM NOTIFY
    shec_notify_admin_telegram($uid, $pub['url']);

    wp_send_json_success([
      'ai_result'       => wp_json_encode($final, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'user'            => $data,
      'public_url'      => $pub['url'],
      'public_expires'  => $pub['expires'],
      'token'           => $pub['token'],
    ]);
  }
}
add_action('wp_ajax_shec_finalize','shec_finalize');
add_action('wp_ajax_nopriv_shec_finalize','shec_finalize');

/* ---------------------------------
 * PUBLIC: get result by token (no nonce)
 * --------------------------------- */
if (!function_exists('shec_result_by_token')) {
  function shec_result_by_token() {
    global $wpdb;
    $token = sanitize_text_field($_REQUEST['token'] ?? $_REQUEST['t'] ?? '');
    if ($token === '') wp_send_json_error(['message'=>'token missing'], 400);

    // 1) اول transient (سریع)
    $uid = (int)get_transient('shec_tok_'.$token);

    // 2) اگر نبود، از جدول links با hash بخوان
    if ($uid <= 0) {
      $links_table = shec_links_table();
      $hash = hash('sha256', $token);
      $now  = gmdate('Y-m-d H:i:s');

      $row = $wpdb->get_row($wpdb->prepare(
        "SELECT wp_user_id FROM {$links_table}
         WHERE token_hash=%s AND is_active=1 AND (expires IS NULL OR expires >= %s)
         LIMIT 1",
        $hash, $now
      ), ARRAY_A);

      if ($row) {
        $uid = (int)$row['wp_user_id'];
        // cache برای دفعات بعد
        set_transient('shec_tok_'.$token, $uid, 180*DAY_IN_SECONDS);
      }
    }

    // 3) مهاجرت رکوردهای قدیمی (توکن داخل data): اگر هنوز پیدا نشد
    if ($uid <= 0) {
      $table = shec_table();
      $like  = '%' . $wpdb->esc_like($token) . '%';
      $legacy = $wpdb->get_row( $wpdb->prepare(
        "SELECT wp_user_id, data FROM {$table} WHERE data LIKE %s LIMIT 1", $like
      ), ARRAY_A );

      if ($legacy) {
        $uid = (int)$legacy['wp_user_id'];

        // upsert داخل links برای آینده
        $links_table = shec_links_table();
        $hash = hash('sha256', $token);
        $now  = current_time('mysql', 1);
        $exp  = gmdate('Y-m-d H:i:s', time() + 180*DAY_IN_SECONDS);

        // همه لینک‌های قبلی کاربر را غیرفعال کن
        $wpdb->update($links_table, ['is_active'=>0], ['wp_user_id'=>$uid]);

        // درج رکورد جدید
        $wpdb->insert($links_table, [
          'wp_user_id' => $uid,
          'token_hash' => $hash,
          'created'    => $now,
          'expires'    => $exp,
          'is_active'  => 1,
        ], ['%d','%s','%s','%s','%d']);

        set_transient('shec_tok_'.$token, $uid, 180*DAY_IN_SECONDS);
      }
    }

    if ($uid <= 0) wp_send_json_error(['message'=>'result not found'], 404);

    $data = shec_get_data($uid);
    if (!$data) wp_send_json_error(['message'=>'result not found'], 404);

    $final = $data['ai']['final'] ?? ['method'=>'FIT','graft_count'=>0,'analysis'=>'نتیجه هنوز آماده نیست.'];

    wp_send_json_success([
      'user'       => $data,
      'ai_result'  => wp_json_encode($final, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'public_url' => shec_public_page_url($token)
    ]);
  }
}

add_action('wp_ajax_shec_result_by_token', 'shec_result_by_token');
add_action('wp_ajax_nopriv_shec_result_by_token', 'shec_result_by_token');

/* ---------------------------------
 * Shortcode: [smart_hair_result]  (renders token view like Step 6)
 * --------------------------------- */
if (!function_exists('shec_result_viewer_shortcode')) {
function shec_result_viewer_shortcode($atts = []) {

  $calc_url = '';
  if ($p = get_page_by_path('hair-graft-calculator')) {
    $calc_url = get_permalink($p->ID);
  }
  if (!$calc_url) {
    $calc_url = home_url('/hair-graft-calculator/');
  }


  // 2) HTML (استایل و ساختار همون استپ 6، اما بدون کلاس .step که سفید نشه)
  $img_path = SHEC_URL.'public/assets/img/';
  ob_start(); ?>
<div id="shec-result-token" class="shec-result-root">



  <!-- 👇 همین قسمت PDF می‌شود -->
  <div id="proposal-pdf-root" class="proposal-container">
    <h3>نتیجه مشاوره</h3>

    <!-- خروجی هوش مصنوعی -->
    <div id="ai-result-box" class="result-box" style="min-height:320px;padding:24px">
      <div style="opacity:.7">در حال بارگذاری نتیجه...</div>
    </div>

    <!-- خلاصه اطلاعات کاربر (بلوک‌های استپ ۶ شما) -->
    <div class="sample-info-wrapper">
      <p style="font-size:20px; font-weight:bold; text-align:center;">شما هم می‌توانید ظاهر خود را متحول کنید!</p>
      <img class="sample-image" src="https://fakhraei.clinic/wp-content/uploads/2025/06/BEFORE_Miss.webp" style="width: 100%;border-radius: 5px;" />
    </div>

    <div class="hair-trans-wrapper">
      <img src="https://fakhraei.clinic/wp-content/uploads/2025/06/FIT1-1-scaled-1.png" style="width: 100%;border-radius: 5px;" alt="کاشت مو" />
    </div>

    <div class="fit-timeline-wrapper">
      <p style="font-size:20px; font-weight:bold; text-align:center;">جدول زمانی پیش‌بینی نتایج کاشت مو (تکنیک FIT)</p>
      <table class="fit-timeline-table">
        <thead>
          <tr><th>بازه زمانی</th><th>چه چیزی انتظار می‌رود؟</th></tr>
        </thead>
        <tbody>
          <tr><td>روز ۱ تا ۷</td><td>قرمزی و کمی تورم طبیعی است. این علائم به مرور کاهش می‌یابند.</td></tr>
          <tr><td>هفته ۲ تا ۳</td><td>موهای کاشته‌شده به‌طور موقت می‌ریزند (شوک ریزش)؛ که کاملاً طبیعی است.</td></tr>
          <tr><td>ماه ۱ تا ۲</td><td>پوست سر به حالت عادی برمی‌گردد اما هنوز موهای جدید قابل‌مشاهده نیستند.</td></tr>
          <tr><td>ماه ۳ تا ۴</td><td>شروع رشد موهای جدید؛ معمولاً نازک و ضعیف هستند.</td></tr>
          <tr><td>ماه ۵ تا ۶</td><td>بافت موها قوی‌تر می‌شود و تراکم بیشتری پیدا می‌کنند.</td></tr>
          <tr><td>ماه ۷ تا ۹</td><td>موها ضخیم‌تر، متراکم‌تر و طبیعی‌تر می‌شوند؛ تغییرات واضح‌تر خواهند بود.</td></tr>
          <tr><td>ماه ۱۰ تا ۱۲</td><td>۸۰ تا ۹۰ درصد نتیجه نهایی قابل مشاهده است.</td></tr>
          <tr><td>ماه ۱۲ به بعد</td><td>موها کاملاً تثبیت می‌شوند؛ نتیجه نهایی طبیعی و ماندگار خواهد بود.</td></tr>
        </tbody>
      </table>
    </div>

    <div class="why-padra-wrapper">
      <p style="font-size:20px; font-weight:bold; text-align:center;margin-top: 50px;">چرا کلینیک فخرائی را انتخاب کنیم؟</p>

      <div class="why-padra-item">
        <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Black-White-Yellow-Simple-Initial-Name-Logo-22-1.png" alt="" />
        <div class="why-padra-info">
          <span class="why-padra-info-title">تیم حرفه‌ای و با تجربه</span>
          <p class="why-padra-info-description">کاشت مو در کلینیک فخرائی توسط تکنسین‌های آموزش‌دیده و زیر نظر پزشک متخصص انجام می‌شود.</p>
        </div>
      </div>

      <div class="why-padra-item">
        <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003350.png" alt="" />
        <div class="why-padra-info">
          <span class="why-padra-info-title">روزانه بیش از ۷۰۰ عمل موفق</span>
          <p class="why-padra-info-description">با سابقه‌ای بیش از ۲۰ سال و هزاران کاشت موفق، به‌خوبی می‌دانیم چگونه نتیجه‌ای طبیعی و ماندگار به دست آوریم.</p>
        </div>
      </div>

      <div class="why-padra-item">
        <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003557.png" alt="" />
        <div class="why-padra-info">
          <span class="why-padra-info-title">تعرفه‌ منصفانه با حفظ کیفیت</span>
          <p class="why-padra-info-description">ما تلاش می‌کنیم بهترین تکنولوژی و تخصص را با هزینه‌ای منطقی ارائه دهیم؛ بدون افت در کیفیت یا نتیجه.</p>
        </div>
      </div>

      <div class="why-padra-item">
        <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003353.png" alt="" />
        <div class="why-padra-info">
          <span class="why-padra-info-title">محیط راحت و امکانات کامل </span>
          <p class="why-padra-info-description">فضایی آرام، بهداشتی و مجهز در کنار تجربه‌ای مطمئن، برای همراهی‌تان فراهم کرده‌ایم.</p>
        </div>
      </div>

      <div class="why-padra-item">
        <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003563.png" alt="" />
        <div class="why-padra-info">
          <span class="why-padra-info-title">اقامت رایگان برای مراجعین از شهرهای دیگر</span>
          <p class="why-padra-info-description">در کلینیک فخرائی، اقامت برای مراجعین از سایر شهرها رایگان است.</p>
        </div>
      </div>

      <div class="why-padra-item">
        <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/bihesi.png" alt="" />
        <div class="why-padra-info">
          <span class="why-padra-info-title">بدون درد و با آرامش</span>
          <p class="why-padra-info-description">فرایند درمان با استفاده از داروهای بی‌حسی و تکنیک‌های جدید انجام می‌شود تا کاشتی بدون درد را تجربه کنید.</p>
        </div>
      </div>

      <div class="why-padra-item">
        <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003351.png" alt="" />
        <div class="why-padra-info">
          <span class="why-padra-info-title">همراهی واقعی، قبل تا بعد از عمل</span>
          <p class="why-padra-info-description">از مشاوره و ارزیابی اولیه تا مراقبت‌های پس از عمل، همیشه در کنار شما هستیم.</p>
        </div>
      </div>
    </div>

    <div class="actions mt-3">
      <button id="reset-form"  data-reset-href="<?php echo esc_attr($calc_url); ?>" class="btn btn-danger">شروع مجدد</button>
      <button id="download-pdf" class="btn btn-primary">دانلود PDF</button>
    </div>
  </div>
</div>
<?php
  $html = ob_get_clean();

  // 3) Inline JS بعد از form.js
  $inline = <<<JS
  (function(){
    var box   = document.getElementById('ai-result-box');
    var t     = (new URLSearchParams(location.search)).get('t') || (new URLSearchParams(location.search)).get('token');
    if(!box) return;

    if(!t){
      box.innerHTML = '<div style="padding:24px">توکن پیدا نشد.</div>';
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
        box.innerHTML = '<div style="padding:24px">نتیجه‌ای یافت نشد.</div>';
        return;
      }
      var payload = { user: res.data.user, ai_result: res.data.ai_result };
      if (window.SHEC_renderFinal) {
        window.SHEC_renderFinal(payload);
      } else {
        box.innerHTML = '<div style="padding:24px">UI نتیجه بارگذاری نشد.</div>';
      }
    })
    .catch(function(){
      box.innerHTML = '<div style="padding:24px">خطا در دریافت نتیجه.</div>';
    });
  })();
JS;
  wp_add_inline_script('shec-form-js', $inline, 'after');

  return $html;
}

}
add_shortcode('smart_hair_result','shec_result_viewer_shortcode');

/* ---------------------------------
 * Ensure pages exist (no reliance on activation hook)
 * --------------------------------- */

