<?php
/**
 * Plugin Name: Email Marketing Scheduler
 * Description: A plugin to send scheduled emails in batches.
 * Version: 1.0
 * Author: Saeed Boroomand
 * Text Domain: email-marketing-scheduler
 */

 // Exit if accessed directly
 if (!defined('ABSPATH')) { exit; }

 // --------------------------
 add_action('admin_menu', 'es_add_settings_menu');
 function es_add_settings_menu() {
    add_menu_page(
        'Email Marketing Scheduler Settings',      // title page
        'Email Marketing Scheduler',               // menu name
        'manage_options',                          // auth
        'email-marketing-scheduler-settings',      // slug
        'es_settings_page',                        // do setting function
        'dashicons-email-alt',                     // icon
        100                                        // menu position
    );
 }

//  ---------Security for HTML-----------------
 function es_sanitize_email_content($content) {
    return wp_kses_post($content); // فقط تگ‌های مجاز وردپرس را قبول می‌کند
}

// --------------------------
add_action('admin_init', 'es_register_settings');
function es_register_settings() {
    register_setting('es_settings_group', 'es_email_csv');
    register_setting('es_settings_group', 'es_cron_job_during');
    // register_setting('es_settings_group', 'es_emails_per_hour');
    register_setting('es_settings_group', 'es_email_subject');
    register_setting('es_settings_group', 'es_email_content', [
        'type' => 'string',
        'sanitize_callback' => null, // keep html format
        'default' => '',
    ]);
    register_setting('es_settings_group', 'es_emails_number_day', [
        'type' => 'integer',
        'sanitize_callback' => 'absint', // فقط مقادیر عددی
        'default' => 2,
    ]);

    register_setting('es_settings_group', 'es_emails_number_night', [
        'type' => 'integer',
        'sanitize_callback' => 'absint', // فقط مقادیر عددی
        'default' => 4,
    ]);

    add_settings_section(
        'es_main_section',                      // ID بخش
        'Main Settings',                        // عنوان بخش
        null,                                   // توضیحات بخش
        'email-marketing-scheduler-settings'    // صفحه تنظیمات
    );

    add_settings_field(
        'es_email_csv',                       // ID فیلد
        'Upload CSV File',                    // عنوان فیلد
        'es_email_csv_field',                 // تابع نمایش فیلد
        'email-marketing-scheduler-settings', // صفحه تنظیمات
        'es_main_section'                     // بخش تنظیمات
    );

    add_settings_field(
        'es_cron_job_during',
        'Cron Job Scheduler',
        'es_cron_job_during_field',
        'email-marketing-scheduler-settings',
        'es_main_section'
    );

    // add_settings_field(
    //     'es_emails_per_hour',
    //     'Emails Number',
    //     'es_emails_per_hour_field',
    //     'email-marketing-scheduler-settings',
    //     'es_main_section'
    // );
    add_settings_field(
        'es_emails_number_day',
        'Emails to Send (Day)',
        function () {
            $value = get_option('es_emails_number_day', 2);
            echo '<input type="number" name="es_emails_number_day" value="' . esc_attr($value) . '" class="small-text">';
        },
        'email-marketing-scheduler-settings',
        'es_main_section'
    );

    add_settings_field(
        'es_emails_number_night',
        'Emails to Send (Night)',
        function () {
            $value = get_option('es_emails_number_night', 4);
            echo '<input type="number" name="es_emails_number_night" value="' . esc_attr($value) . '" class="small-text">';
        },
        'email-marketing-scheduler-settings',
        'es_main_section'
    );

    add_settings_field(
        'es_email_subject',
        'Email Subject',
        'es_email_subject_field',
        'email-marketing-scheduler-settings',
        'es_main_section'
    );

    add_settings_field(
        'es_email_content',
        'Email Content',
        'es_email_content_field',
        'email-marketing-scheduler-settings',
        'es_main_section'
    );
}

// --------------------------
function es_email_csv_field() {
    $value = get_option('es_email_csv', '');
    echo '<input type="file" name="es_email_csv" accept=".csv"> <hr>';
}

function es_cron_job_during_field() {
    $intervals = [
        'hourly' => 'Hourly',
        'twicedaily' => 'Twice Daily',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
    ];
    $current_value = get_option('es_cron_job_during', 'hourly');
    // echo '<input type="text" name="es_cron_job_during" value="' . esc_attr($value) . '">';

    echo '<select name="es_cron_job_during">';
    foreach ($intervals as $value => $label) {
        echo sprintf(
            '<option value="%s" %s>%s</option>',
            esc_attr($value),
            selected($current_value, $value, false),
            esc_html($label)
        );
    }
    echo '</select>';
}

function es_emails_per_hour_field() {
    $value = get_option('es_emails_per_hour', 2);
    echo '<input type="number" name="es_emails_per_hour" value="' . esc_attr($value) . '" min="1">';
}

function es_email_subject_field() {
    $value = get_option('es_email_subject', 'Your Scheduled Email');
    echo '<input type="text" name="es_email_subject" value="' . esc_attr($value) . '">';
}

function es_email_content_field() {
    $value = get_option('es_email_content', 'This is the email content.');
    echo '<textarea name="es_email_content" rows="8" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
}

// --------------------------
function es_save_csv_file($option, $old_value, $value) {
    if (isset($_FILES['es_email_csv']) && !empty($_FILES['es_email_csv']['tmp_name'])) {
        $upload_dir = wp_upload_dir();
        $target_file = $upload_dir['basedir'] . '/emails.csv';

        if (move_uploaded_file($_FILES['es_email_csv']['tmp_name'], $target_file)) {
            update_option('es_email_csv', $target_file);
        }
    }
}
add_action('update_option_es_email_csv', 'es_save_csv_file', 10, 3);


// ---------- Submit Button----------------
add_action('admin_post_update', 'es_handle_settings_update');
function es_handle_settings_update() {
    // بررسی ارسال تنظیمات
    if (isset($_POST['es_cron_job_during'])) {
        // مقدار جدید از فرم تنظیمات
        $new_value_cj_during = sanitize_text_field($_POST['es_cron_job_during']);

        // ذخیره مقدار در دیتابیس
        update_option('es_cron_job_during', $new_value_cj_during);

        // به‌روزرسانی کرون جاب
        alfa_cron_event_update($new_value_cj_during);

        // پیام موفقیت
        add_settings_error(
            'es_settings_messages',
            'es_settings_updated',
            'Email Cron Job during, updated successfully!',
            'updated'
        );
    }
}



// --------------------------
function es_settings_page() {
    ?>
    <div class="wrap">
        <h1>Email Marketing Scheduler Settings</h1>
        <form method="post" action="options.php" enctype="multipart/form-data">
            <?php
            // ثبت تنظیمات
            settings_fields('es_settings_group');
            // نمایش فیلدها
            do_settings_sections('email-marketing-scheduler-settings');
            // دکمه ذخیره
            submit_button('.. Save Changes ..');
            ?>
        </form>
        <?php settings_errors('es_settings_messages'); ?>

        <h2>Scheduled Emails display:</h2>
        <?php es_display_email_table(); ?>

        <br>
        <p style="font-size:0.6rem;">Developed by SAeeD BoroO.</p>
    </div>
    <?php
}


//  ================================ Display Table ===================================
function es_display_email_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'alfa_email_marketing';

    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $items_per_page = 50;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    $results = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_name LIMIT %d, %d", $offset, $items_per_page),
        ARRAY_A
    );

    if (empty($results)) {
        echo '<p>No data found in the table.</p>';
        return;
    }
    echo paginate_links(array(
        'base' => add_query_arg('paged', '%#%'),
        'format' => '',
        'prev_text' => __('&laquo;'),
        'next_text' => __('&raquo;'),
        'total' => ceil($total_items / $items_per_page),
        'current' => $current_page,
    ));

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Email</th>';
    echo '<th>Last Sent</th>';
    echo '<th>Status</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($results as $row) {
        echo '<tr>';
        echo '<td>' . esc_html($row['id']) . '</td>';
        echo '<td>' . esc_html($row['email_address']) . '</td>';
        echo '<td>' . esc_html($row['last_sent']) . '</td>';
        echo '<td>' . esc_html($row['status']) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    
    
}


//  ================================ LOGIC ===================================
 /** Create table in DB-------------------------------------------------------------------------------- */
// function create_email_schedule_table() {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'alfa_email_marketing';
//     $charset_collate = $wpdb->get_charset_collate();

//     $sql = "CREATE TABLE $table_name (
//         id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
//         email_address varchar(255) NOT NULL,
//         last_sent datetime DEFAULT NULL,
//         status varchar(20) DEFAULT 'pending',
//         PRIMARY KEY (id)
//     ) $charset_collate;";

//     require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
//     dbDelta($sql);
// }
// register_activation_hook(__FILE__, 'create_email_schedule_table');
// create_email_schedule_table();
// ----------Import from EXEL-------------
// function import_emails_from_csv() {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'alfa_email_marketing';

//     $csv_file = WP_CONTENT_DIR . '/uploads/emails.csv'; // مسیر فایل CSV
//     if (!file_exists($csv_file)) {
//         return;
//     }

//     $file = fopen($csv_file, 'r');
//     while (($data = fgetcsv($file, 1000, ',')) !== FALSE) {
//         $email = sanitize_email($data[0]);
// 		if (is_email($email)) {
//             $wpdb->insert($table_name, [
//                 'email_address' => $email,
//                 'last_sent'     => null,
//                 'status'        => 'pending'
//             ]);
//         }
//     }
//     fclose($file);
// }
// add_action('admin_init', 'import_emails_from_csv'); // اجرا فقط در مدیریت

function alfa_send_batch_scheduled_emails() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'alfa_email_marketing';

    // $emails_per_hour = get_option('es_emails_per_hour', 2);
    $day_emails = get_option('es_emails_number_day', 2);
    $night_emails = get_option('es_emails_number_night', 4);

    $current_hour = (int) current_time('H');

    if ($current_hour >= 18 || $current_hour < 8) {
        $emails_to_send = $night_emails;
    } else {
        $emails_to_send = $day_emails;
    }

    $email_subject = get_option('es_email_subject', 'معرفی شرکت آلفا نیرو');
    $email_content = get_option('es_email_content', 'سلام، این پیام از طرف شرکت دانش بنیان آلفا نیروی آریا است. ما در زمینه طراحی و تولید چراغهای هوشمند نورپردازی و روشنایی شهری فعالیت داریم. - لطفا جهت آشنایی با ما و اطلاع از قیمت و سفارش با ما تماس بگیرید - www.alfacomplex.com - 03152448900');

    // get emails with "pending"
    $emails = $wpdb->get_results("
        SELECT * FROM $table_name
        WHERE status = 'pending'
        ORDER BY id ASC
        LIMIT $emails_to_send
    ");
    foreach ($emails as $email) {
        $to = $email->email_address;
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Alfa Niroo <info@alfacomplex.com>',
        ];

        if (wp_mail($to, $email_subject, $email_content, $headers)) {
            $wpdb->update($table_name, [
                'last_sent' => current_time('mysql'),
                'status'    => 'sent'
            ], ['id' => $email->id]);
        } else {
            $wpdb->update($table_name, [
                'status' => 'failed'
            ], ['id' => $email->id]);
        }
    }
}

// ------------- Add Cron Job -----------------
add_action('init', 'alfa_cron_event_add'); // run cron job with active plugin
add_action('send_emails_cron_event', 'alfa_send_batch_scheduled_emails');
function alfa_cron_event_add() {
    $cron_job_during = get_option('es_cron_job_during', 'hourly');
    
    if (!wp_next_scheduled('send_emails_cron_event')) {
        wp_schedule_event(time(), $cron_job_during, 'send_emails_cron_event');
        error_log('EMS Cron job executed successfully!');
    }
}


register_deactivation_hook(__FILE__, 'alfa_cron_event_remove'); // remove cron event with deactivate plugin
function alfa_cron_event_remove() {
    $timestamp = wp_next_scheduled('send_emails_cron_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'send_emails_cron_event');
        error_log('EMS Cron job removed!');
    }
}


function alfa_cron_event_update($new_interval) {
    // حذف کرون جاب قدیمی
    alfa_cron_event_remove();
    // ثبت کرون جاب جدید
    wp_schedule_event(time(), $new_interval, 'send_emails_cron_event');
    error_log('EMS Cron job Updated!');
}


?>
