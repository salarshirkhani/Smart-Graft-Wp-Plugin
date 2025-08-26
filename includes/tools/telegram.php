<?php
// ===== Telegram Bot API =====

if ( ! defined('SHEC_TG_BOT_TOKEN') )     define('SHEC_TG_BOT_TOKEN', getenv('SHEC_TG_BOT_TOKEN') ?: '');
if ( ! defined('SHEC_TG_ADMIN_CHAT_ID') ) define('SHEC_TG_ADMIN_CHAT_ID', getenv('SHEC_TG_ADMIN_CHAT_ID') ?: '');
if ( ! defined('SHEC_TG_SECRET') )        define('SHEC_TG_SECRET', getenv('SHEC_TG_SECRET') ?: '');

function shec_tg_bot_token() {
    $opt = get_option('shec_telegram_api');
    return $opt ?: SHEC_TG_BOT_TOKEN;
}
function shec_tg_admin_chat() {
    $opt = get_option('shec_admin_chat_id');
    return $opt ?: SHEC_TG_ADMIN_CHAT_ID;
}

function shec_tg_api($method, $params) {
    $token = shec_tg_bot_token();
    if (!$token) return new WP_Error('shec_tg_no_token','Telegram bot token missing');
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $res = wp_remote_post($url, array(
        'timeout' => 12,
        'headers' => array('Content-Type'=>'application/json'),
        'body'    => wp_json_encode($params, JSON_UNESCAPED_UNICODE),
    ));
    if (is_wp_error($res)) return $res;
    $code = (int) wp_remote_retrieve_response_code($res);
    if ($code === 429) {
        usleep(1500000);
        $res = wp_remote_post($url, array(
            'timeout' => 12,
            'headers' => array('Content-Type'=>'application/json'),
            'body'    => wp_json_encode($params, JSON_UNESCAPED_UNICODE),
        ));
    }
    return $res;
}

function shec_tg_send_message($chat_id, $text, $parse_mode='HTML') {
    if (!$chat_id) return new WP_Error('shec_tg_no_chat','chat_id empty');
    return shec_tg_api('sendMessage', array(
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => false,
    ));
}

function shec_tg_build_msg($public_url, $contact=array(), $final=array()) {
    $name   = trim( ($contact['first_name'] ?? '').' '.($contact['last_name'] ?? '') );
    $phone  = $contact['mobile'] ?? ($contact['phone'] ?? '-');
    $method = $final['method'] ?? '-';
    $grafts = isset($final['graft_count']) ? (int)$final['graft_count'] : '-';
    $lines = array(
        "🧾 <b>نتیجه برآورد کاشت مو</b>",
        "👤 نام: <b>".esc_html($name ?: '-')."</b>",
        "📞 تلفن: <b>".esc_html($phone)."</b>",
        "🧠 گرافت تخمینی: <b>".$grafts."</b>",
        "🧩 روش: <b>".esc_html($method)."</b>",
        "",
        "🔗 نتیجه کامل:\n".$public_url,
    );
    return implode("\n", $lines);
}

function shec_find_wp_user_by_token($token) {
    global $wpdb;
    if (!$token) return 0;
    $links = $wpdb->prefix.'shec_links';
    $hash  = hash('sha256', $token);
    $row = $wpdb->get_row( $wpdb->prepare("SELECT wp_user_id FROM {$links} WHERE token_hash=%s AND is_active=1", $hash) );
    return $row ? intval($row->wp_user_id) : 0;
}

function shec_attach_user_chat_id($wp_user_id, $chat_id) {
    global $wpdb;
    if (!$wp_user_id || !$chat_id) return false;
    $tbl = $wpdb->prefix.'shec_users';
    $row = $wpdb->get_row( $wpdb->prepare("SELECT data FROM {$tbl} WHERE wp_user_id=%d", $wp_user_id) );
    $data = $row && $row->data ? json_decode($row->data, true) : array();
    if (!is_array($data)) $data = array();
    if (!isset($data['contact'])) $data['contact'] = array();
    $data['contact']['telegram_chat_id'] = (string)$chat_id;
    $ok = $wpdb->update($tbl, array('data'=>wp_json_encode($data, JSON_UNESCAPED_UNICODE)), array('wp_user_id'=>$wp_user_id));
    return $ok !== false;
}

function shec_get_user_chat_id($wp_user_id) {
    global $wpdb;
    $tbl = $wpdb->prefix.'shec_users';
    $row = $wpdb->get_row( $wpdb->prepare("SELECT data FROM {$tbl} WHERE wp_user_id=%d", $wp_user_id) );
    if (!$row || !$row->data) return '';
    $data = json_decode($row->data, true);
    return $data['contact']['telegram_chat_id'] ?? '';
}

// Webhook برای ثبت chat_id کاربر (اختیاری برای ارسال به کاربر)
add_action('rest_api_init', function(){
    register_rest_route('shec/v1', '/telegram/webhook', array(
        'methods'  => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function( WP_REST_Request $req ) {
            $secret = SHEC_TG_SECRET;
            if ($secret) {
                $hdr = $req->get_header('x-telegram-bot-api-secret-token');
                if ($hdr !== $secret) return new WP_REST_Response(array('ok'=>false), 403);
            }
            $u = $req->get_json_params();
            if (!is_array($u)) return array('ok'=>true);
            $msg = $u['message'] ?? $u['edited_message'] ?? null;
            if ($msg) {
                $chat_id = $msg['chat']['id'] ?? '';
                $text    = trim($msg['text'] ?? '');
                if ($chat_id && strpos($text, '/start') === 0) {
                    $payload = '';
                    $parts = explode(' ', $text, 2);
                    if (isset($parts[1])) $payload = trim($parts[1]);
                    $uid = 0;
                    if (strpos($payload, 't=') === 0) {
                        $uid = shec_find_wp_user_by_token(substr($payload, 2));
                    } elseif (preg_match('/^u:(\d+)/', $payload, $m)) {
                        $uid = intval($m[1]);
                    }
                    if ($uid) {
                        shec_attach_user_chat_id($uid, (string)$chat_id);
                        shec_tg_send_message($chat_id, "✅ اتصال شما ثبت شد.");
                    } else {
                        shec_tg_send_message($chat_id, "ربات فعال است. لینک Start داخل صفحه نتیجه را لمس کنید.");
                    }
                }
            }
            return array('ok'=>true);
        }
    ));
});

// Notify (Admin + optional User)
function shec_notify_telegram($wp_user_id, $public_url, $contact, $final){
    $admin_chat = shec_tg_admin_chat();
    if (!$admin_chat) {
        error_log('[SHEC][TG] admin chat id missing');
        return;
    }
    $msg = shec_tg_build_msg($public_url, $contact, $final);
    $r = shec_tg_send_message($admin_chat, $msg);
    if (is_wp_error($r)) error_log('[SHEC][TG] admin send error: '.$r->get_error_message());

    $wants_tg = !empty($contact['social']) && strtolower($contact['social']) === 'telegram';
    if ($wants_tg) {
        $user_chat_id = $contact['telegram_chat_id'] ?? shec_get_user_chat_id((int)$wp_user_id);
        if ($user_chat_id) {
            $r2 = shec_tg_send_message($user_chat_id, $msg);
            if (is_wp_error($r2)) error_log('[SHEC][TG] user send error: '.$r2->get_error_message());
        }
    }
}

// Bridge ایمن برای جلوگیری از دوباره‌ارسال
function shec_finalize_telegram_bridge($public_url, $wp_user_id, $contact, $final) {
    if (!$public_url) return;
    $key = 'shec_tg_notified_' . md5($public_url);
    if (get_transient($key)) return;
    set_transient($key, 1, 600);
    shec_notify_telegram((int)$wp_user_id, $public_url, (array)$contact, (array)$final);
}


//WEBHOOK ADMIN UI
function shec_admin_render_telegram_webhook_box() {
    if ( ! current_user_can('manage_options') ) return;

    $token  = trim((string) get_option('shec_telegram_api', ''));
    $url    = home_url('/wp-json/shec/v1/telegram/webhook');
    $secret = get_option('shec_tg_secret', '');

    echo '<div class="shec-box" style="margin-top:16px;padding:12px;border:1px solid #ccd0d4;background:#fff;">';
    echo '<h2 style="margin:0 0 10px;">وبهوک تلگرام</h2>';
    echo '<p><strong>Webhook URL:</strong><br><code>'.esc_html($url).'</code></p>';

    echo '<div class="shec-field"><label>Secret Token (اختیاری برای امنیت وبهوک)</label><br/>';
    echo '<input type="text" name="shec_tg_secret" value="'.esc_attr($secret).'" style="width:360px" />';
    echo '<p class="description">اگر تنظیم شود، تلگرام هدر <code>x-telegram-bot-api-secret-token</code> را با همین مقدار می‌فرستد.</p>';
    echo '</div>';

    echo '<p style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">';
    echo '  <button class="button button-primary" name="shec_action" value="set_webhook" type="submit">ست وبهوک</button>';
    echo '  <button class="button" name="shec_action" value="delete_webhook" type="submit">حذف وبهوک</button>';
    echo '  <button class="button" name="shec_action" value="info_webhook" type="submit">وضعیت وبهوک</button>';
    echo '</p>';

    if (!$token) {
        echo '<p style="color:#b91c1c">توکن ربات وارد نشده است.</p>';
    }
    echo '</div>';
}

function shec_notify_admin_telegram($wp_user_id, $public_url) {
    $token = get_option('shec_telegram_api', '');
    $chat  = get_option('shec_admin_chat_id', '');
    if (!$token || !$chat) return;

    $data = shec_get_data($wp_user_id);
    if (!$data) return;

    $contact = $data['contact'] ?? [];
    $name    = trim(($contact['first_name'] ?? '').' '.($contact['last_name'] ?? ''));
    $mobile  = $data['mobile'] ?? ($contact['mobile'] ?? '');

    $text = "📩 فرم جدید ارسال شد:\n\n".
            "👤 نام: <b>".esc_html($name ?: '—')."</b>\n".
            "📞 موبایل: <b>".esc_html($mobile ?: '—')."</b>\n".
            "🔗 نتیجه کامل: {$public_url}";

    wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", [
        'headers' => ['Content-Type'=>'application/json'],
        'body'    => wp_json_encode([
            'chat_id' => $chat,
            'text'    => $text,
            'parse_mode' => 'HTML'
        ], JSON_UNESCAPED_UNICODE),
    ]);
}

