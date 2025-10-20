<?php
if (!defined('ABSPATH')) exit;

/* کلید API از option یا env */
if (!defined('SHEC_SMS_API')) define('SHEC_SMS_API', getenv('SHEC_SMS_API') ?: '');

function shec_sms_api_key() {
    $opt = get_option('shec_sms_api', '');
    return apply_filters('shec_sms_api_key', $opt ?: SHEC_SMS_API);
}

/* ارسال متن ساده با IPPanel Edge
 * ابتدا با کلید message تلاش می‌کند؛ اگر موفق نبود، با content دوباره امتحان می‌کند.
 */
function shec_sms_send_text($to, $text, $originator = null) {
    $api = shec_sms_api_key();
    if (empty($api)) return new WP_Error('shec_sms_no_api', 'SMS API key missing');

    $to = preg_replace('/\D+/', '', (string)$to);
    if (strlen($to) < 10) return new WP_Error('shec_sms_bad_to', 'invalid recipient');

    $endpoint = 'https://api.ippanel.com/v1/messages';
    $headers  = array(
        'Content-Type'  => 'application/json',
        'Authorization' => 'AccessKey ' . $api,
    );

    // تلاش 1: کلید message
    $body1 = array(
        'originator' => $originator ?: '3000', // اگر خط خدماتی/اختصاصی دارید، اینجا بگذارید
        'recipients' => array($to),
        'message'    => $text,
        'type'       => 'text',
    );
    $res = wp_remote_post($endpoint, array(
        'timeout' => 20,
        'headers' => $headers,
        'body'    => wp_json_encode($body1, JSON_UNESCAPED_UNICODE),
    ));
    if (is_wp_error($res)) return $res;

    $code = (int) wp_remote_retrieve_response_code($res);
    if ($code >= 200 && $code < 300) return $res;

    // تلاش 2: بعضی پنل‌ها به جای message از content استفاده می‌کنند
    $body2 = array(
        'originator' => $originator ?: '3000',
        'recipients' => array($to),
        'content'    => $text,
        'type'       => 'text',
    );
    $res2 = wp_remote_post($endpoint, array(
        'timeout' => 20,
        'headers' => $headers,
        'body'    => wp_json_encode($body2, JSON_UNESCAPED_UNICODE),
    ));

    return $res2;
}
