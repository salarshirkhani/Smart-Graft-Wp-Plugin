<?php 

// تغییر منوی ادمین
function shec_add_admin_menu() {
    add_menu_page(
        'هوش مصنوعی', // عنوان صفحه
        'هوش مصنوعی', // عنوان منو در داشبورد
        'manage_options', // مجوز دسترسی
        'shec-form', // شناسه منو
        'shec_display_data', // تابعی که داده‌ها را نمایش می‌دهد
        'dashicons-chart-pie', // آیکون منو
        6 // موقعیت منو
    );

    add_submenu_page(
        'shec-form',
        'تنظیمات', // عنوان صفحه زیرمنو
        'تنظیمات', // عنوان منو
        'manage_options', // مجوز دسترسی
        'shec-settings', // شناسه صفحه زیرمنو
        'shec_display_settings' // تابع برای نمایش تنظیمات
    );
}
add_action('admin_menu', 'shec_add_admin_menu');

// نمایش داده‌ها در صفحه ادمین
function shec_display_data() {
    global $wpdb;

    $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}shec_users");

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">داده‌های فرم هوشمند فخرایی</h1>';

    if (!empty($results)) {
        echo '<table class="wp-list-table widefat fixed striped posts">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column">شناسه</th>
                        <th scope="col" class="manage-column">جنسیت</th>
                        <th scope="col" class="manage-column">سن</th>
                        <th scope="col" class="manage-column">اعتماد به نفس</th>
                        <th scope="col" class="manage-column">نام</th>
                        <th scope="col" class="manage-column">نام خانوادگی</th>
                        <th scope="col" class="manage-column">مشاهده</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($results as $row) {
            $data = json_decode($row->data, true);

            // استخراج داده‌ها
            $gender = $data['gender'] ?? 'N/A';
            $age = $data['age'] ?? 'N/A';
            $confidence = $data['confidence'] ?? 'N/A';
            $first_name = $data['contact']['first_name'] ?? 'N/A';
            $last_name = $data['contact']['last_name'] ?? 'N/A';

            echo '<tr>';
            echo '<td>' . $row->id . '</td>';
            echo '<td>' . $gender . '</td>';
            echo '<td>' . $age . '</td>';
            echo '<td>' . $confidence . '</td>';
            echo '<td>' . $first_name . '</td>';
            echo '<td>' . $last_name . '</td>';
            echo '<td><a href="' . admin_url('admin.php?page=shec-form-data&user_id=' . $row->id) . '" target="_blank" class="button">مشاهده</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>داده‌ای یافت نشد.</p>';
    }
    echo '</div>';
}

// نمایش جزئیات داده‌های هر کاربر در تب جدید
function shec_display_user_details() {
    global $wpdb;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if (!$user_id) {
        echo '<p>اطلاعات کاربر پیدا نشد.</p>';
        return;
    }

    // گرفتن داده‌ها از دیتابیس
    $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shec_users WHERE id = $user_id");

    if ($row) {
        $data = json_decode($row->data, true);

        echo '<div class="wrap">';
        echo '<h1>جزئیات داده‌های کاربر</h1>';
        echo '<p><strong>شناسه کاربر:</strong> ' . $row->id . '</p>';
        echo '<p><strong>جنسیت:</strong> ' . ($data['gender'] ?? 'N/A') . '</p>';
        echo '<p><strong>سن:</strong> ' . ($data['age'] ?? 'N/A') . '</p>';
        echo '<p><strong>اعتماد به نفس:</strong> ' . ($data['confidence'] ?? 'N/A') . '</p>';
        echo '<p><strong>الگوی ریزش مو:</strong> ' . ($data['loss_pattern'] ?? 'N/A') . '</p>';
        echo '<p><strong>نام:</strong> ' . ($data['contact']['first_name'] ?? 'N/A') . '</p>';
        echo '<p><strong>نام خانوادگی:</strong> ' . ($data['contact']['last_name'] ?? 'N/A') . '</p>';
        echo '<p><strong>شماره تلفن:</strong> ' . esc_html($data['mobile'] ?? ($data['contact']['mobile'] ?? 'N/A')) . '</p>';
        echo '<p><strong>سوشال مدیا:</strong> ' . ($data['contact']['social'] ?? 'N/A') . '</p>';

        // نمایش سوالات و پاسخ‌ها
        echo '<h2>سوالات و پاسخ‌ها:</h2>';
        // اگر سوالات پرامپت را ذخیره کرده‌ایم اینجا نمایش می‌دهیم
        echo '</div>';
    } else {
        echo '<p>اطلاعات کاربر پیدا نشد.</p>';
    }
}

add_action('admin_menu', function () {
    add_submenu_page('shec-form', 'جزئیات داده‌ها', 'جزئیات داده‌ها', 'manage_options', 'shec-form-data', 'shec_display_user_details');
});

// نمایش تنظیمات صفحه
function shec_display_settings() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['shec_api_key'])) {
            update_option('shec_api_key', sanitize_text_field($_POST['shec_api_key']));
        }
        if (isset($_POST['shec_sms_api'])) {
            update_option('shec_sms_api', sanitize_text_field($_POST['shec_sms_api']));
        }
        if (isset($_POST['shec_telegram_api'])) {
            update_option('shec_telegram_api', sanitize_text_field($_POST['shec_telegram_api']));
        }
        echo '<div class="updated"><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
    }

    $api_key = get_option('shec_api_key', '');
    $sms_api = get_option('shec_sms_api', '');
    $telegram_api = get_option('shec_telegram_api', '');

    echo '<div class="wrap">';
    echo '<h1>تنظیمات افزونه</h1>';
    echo '<form method="POST">';
    echo '<h2>تنظیمات API OpenAI</h2>';
    echo '<input type="text" name="shec_api_key" value="' . esc_attr($api_key) . '" />';
    echo '<h2 style="margin-top:70px;">پنل SMS</h2>';
    echo '<input type="text" name="shec_sms_api" value="' . esc_attr($sms_api) . '" />';
    echo '<h2 style="margin-top:70px;">ربات تلگرام</h2>';
    echo '<input type="text" name="shec_telegram_api" value="' . esc_attr($telegram_api) . '" />';
    echo '<input type="submit" value="ذخیره" class="button-primary" />';
    echo '</form>';
    echo '</div>';
}
