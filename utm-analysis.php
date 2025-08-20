<?php
/**
 * Plugin Name: UTM Analysis
 * Description: A plugin to UTM URL analysis
 * Version: 1.0
 * Author: Saeed Boroomand
 * Text Domain: utm-analysis
 */

// Exit if accessed directly
if (!defined('ABSPATH')) { exit; }

// make table in DB
register_activation_hook(__FILE__, 'create_utm_table');
function create_utm_table() {
    global $wpdb;
    $table_utm = $wpdb->prefix . 'alfa_utm_analysis';
    $table_hits = $wpdb->prefix . 'alfa_utm_hits';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_utm (
        id int(11) NOT NULL AUTO_INCREMENT,
        source varchar(255) NOT NULL,
        medium varchar(255) NOT NULL,
        campaign varchar(255) NOT NULL,
        content varchar(255) NOT NULL,
        term varchar(255) NOT NULL,
        hit_count int(9) DEFAULT 0 NOT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    $sql2 = "CREATE TABLE $table_hits (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        utm_id int(11) NOT NULL,
        ip_address VARCHAR(100),
        user_agent TEXT,
        referer TEXT,
        landing_page TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    dbDelta($sql2);
}

add_action('init', 'save_utm_parameters');
function save_utm_parameters() {
    $source   = isset($_GET['utm_source'])   ? sanitize_text_field($_GET['utm_source'])   : (isset($_GET['source'])   ? sanitize_text_field($_GET['source'])   : '');
    $medium   = isset($_GET['utm_medium'])   ? sanitize_text_field($_GET['utm_medium'])   : (isset($_GET['medium'])   ? sanitize_text_field($_GET['medium'])   : '');
    $campaign = isset($_GET['utm_campaign']) ? sanitize_text_field($_GET['utm_campaign']) : (isset($_GET['campaign']) ? sanitize_text_field($_GET['campaign']) : '');
    $content  = isset($_GET['utm_content'])  ? sanitize_text_field($_GET['utm_content'])  : (isset($_GET['content'])  ? sanitize_text_field($_GET['content'])  : '');
    $term     = isset($_GET['utm_term'])     ? sanitize_text_field($_GET['utm_term'])     : (isset($_GET['term'])     ? sanitize_text_field($_GET['term'])     : '');    

    if ($source || $medium || $campaign || $content || $term) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = parseUserAgentToShort($_SERVER['HTTP_USER_AGENT']);
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $landing_page = $_SERVER['REQUEST_URI'];
        
        global $wpdb;
        $table_utm = $wpdb->prefix . 'alfa_utm_analysis';
        $table_hits = $wpdb->prefix . 'alfa_utm_hits';
        
        $existing_entry = $wpdb->get_row($wpdb->prepare(
            "SELECT id, hit_count FROM $table_utm WHERE source = %s AND medium = %s AND campaign = %s AND content = %s AND term = %s",
            $source, $medium, $campaign, $content, $term
        ));
        $utm_id = $existing_entry ? $existing_entry->id : $wpdb->insert_id;

        if ($existing_entry) {
            $wpdb->update(
                $table_utm,
                ['hit_count' => $existing_entry->hit_count + 1],
                ['id' => $existing_entry->id]
            );
        } else {
            $wpdb->insert($table_utm, [
                'source' => $source,
                'medium' => $medium,
                'campaign' => $campaign,
                'content' => $content,
                'term' => $term,
                'hit_count' => 1,
                'timestamp' => current_time('mysql'),
            ]);
            $utm_id = $wpdb->insert_id;
        }
        
        $wpdb->insert($table_hits, [
            'utm_id' => $utm_id,
            'ip_address' => $ip,
            'user_agent' => $user_agent,
            'referer' => $referer,
            'landing_page' => $landing_page,
            'timestamp' => current_time('mysql'),
        ]);
    }
}


function parseUserAgentToShort($userAgent) {
    $device = 'Unknown';
    $browser = 'Unknown';
    $deviceVersion = '';
    $browserVersion = '';

    if (preg_match('/Android ([0-9\.]+)/i', $userAgent, $match)) {
        $device = 'Android';
        $deviceVersion = $match[1];
    } elseif (preg_match('/iPhone OS ([0-9_]+)/i', $userAgent, $match)) {
        $device = 'iOS';
        $deviceVersion = str_replace('_', '.', $match[1]);
    } elseif (preg_match('/Windows NT ([0-9\.]+)/i', $userAgent, $match)) {
        $device = 'Windows';
        $deviceVersion = $match[1];
    } elseif (preg_match('/Mac OS X ([0-9_]+)/i', $userAgent, $match)) {
        $device = 'Mac';
        $deviceVersion = str_replace('_', '.', $match[1]);
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $device = 'Linux';
    } elseif (stripos($userAgent, 'googlebot') !== false) {
        $device = 'Googlebot';
    } elseif (stripos($userAgent, 'dataprovider.com') !== false) {
        $device = 'Bot-DataProvider';
    }

    if (preg_match('/Edg\/([0-9\.]+)/i', $userAgent, $match)) {
        $browser = 'Edge';
        $browserVersion = $match[1];
    } elseif (preg_match('/Chrome\/([0-9\.]+)/i', $userAgent, $match)) {
        $browser = 'Chrome';
        $browserVersion = $match[1];
    } elseif (preg_match('/Firefox\/([0-9\.]+)/i', $userAgent, $match)) {
        $browser = 'Firefox';
        $browserVersion = $match[1];
    } elseif (preg_match('/Safari\/([0-9\.]+)/i', $userAgent, $match) && !preg_match('/Chrome/i', $userAgent)) {
        $browser = 'Safari';
        $browserVersion = $match[1];
    }

    $result = "$device " . ($deviceVersion ? $deviceVersion : '') . " | $browser " . ($browserVersion ? $browserVersion : '');
    return trim($result);
}


add_action('admin_menu', 'es_add_settings_menu');
function es_add_settings_menu() {
    add_menu_page(
        'UTM Analysis Settings',
        'UTM Analysis',
        'manage_options',
        'utm-analysis-settings',
        'wp_settings_page',
        'dashicons-chart-bar',
        110
    );
}

// هندل کردن درخواست‌های AJAX برای DataTables
add_action('wp_ajax_utm_datatable_data', 'utm_datatable_data_callback');
function utm_datatable_data_callback() {
    global $wpdb;
    $table_utm = $wpdb->prefix . 'alfa_utm_analysis';
    $table_hits = $wpdb->prefix . 'alfa_utm_hits';

    // دریافت پارامترهای DataTables
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';

    // کوئری پایه
    $query = "
        SELECT hits.*, utm.source, utm.medium, utm.campaign, utm.content, utm.term 
        FROM $table_hits hits
        LEFT JOIN $table_utm utm ON hits.utm_id = utm.id
    ";

    // افزودن شرط جستجو
    $where = [];
    if (!empty($search)) {
        $where[] = $wpdb->prepare(
            "(utm.source LIKE %s OR utm.medium LIKE %s OR utm.campaign LIKE %s OR utm.content LIKE %s OR utm.term LIKE %s OR hits.ip_address LIKE %s OR hits.referer LIKE %s OR hits.user_agent LIKE %s OR hits.landing_page LIKE %s)",
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        );
    }

    if (!empty($where)) {
        $query .= " WHERE " . implode(' AND ', $where);
    }

    // محاسبه تعداد کل رکوردها
    $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_hits");
    $filtered_records = $wpdb->get_var("SELECT COUNT(*) FROM ($query) as subquery");

    // افزودن صفحه‌بندی و مرتب‌سازی
    $order_column = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
    $order_dir = isset($_POST['order'][0]['dir']) ? sanitize_text_field($_POST['order'][0]['dir']) : 'desc';
    $columns = ['hits.timestamp', 'utm.source', 'utm.medium', 'utm.campaign', 'utm.term', 'utm.content', 'hits.ip_address', 'hits.referer', 'hits.user_agent', 'hits.landing_page'];
    $order_by = $columns[$order_column] . ' ' . $order_dir;

    $query .= " ORDER BY $order_by LIMIT %d OFFSET %d";
    $results = $wpdb->get_results($wpdb->prepare($query, $length, $start));

    // آماده‌سازی داده‌ها برای DataTables
    $data = [];
    foreach ($results as $row) {
        $data[] = [
            esc_html($row->timestamp),
            esc_html($row->source ?: '-'),
            esc_html($row->medium ?: '-'),
            esc_html($row->campaign ?: '-'),
            esc_html($row->term ?: '-'),
            esc_html($row->content ?: '-'),
            esc_html($row->ip_address ?: '-'),
            esc_html($row->referer ?: '-'),
            esc_html($row->user_agent ?: '-'),
            esc_html($row->landing_page ?: '-')
        ];
    }

    $response = [
        'draw' => $draw,
        'recordsTotal' => $total_records,
        'recordsFiltered' => $filtered_records,
        'data' => $data
    ];

    wp_send_json($response);
}

function wp_settings_page() {
    ?>
    <div class="wrap">
        <h1>UTM Analysis Dashboard</h1>
        <h2>UTM Statistics</h2>
        <?php alfa_display_data(); ?>
        <br>
        <p style="font-size:0.6rem;">Developed by SAeeD Boroo.</p>
    </div>
    <?php
}

function alfa_display_data() {
    global $wpdb;
    $table_utm = $wpdb->prefix . 'alfa_utm_analysis';
    $table_hits = $wpdb->prefix . 'alfa_utm_hits';

    // دریافت آمار برای سورس
    $source_stats = $wpdb->get_results("
        SELECT source, SUM(hit_count) as total_hits
        FROM $table_utm
        WHERE source != ''
        GROUP BY source
        ORDER BY total_hits DESC
    ");

    // دریافت آمار برای مدیوم
    $medium_stats = $wpdb->get_results("
        SELECT medium, SUM(hit_count) as total_hits
        FROM $table_utm
        WHERE medium != ''
        GROUP BY medium
        ORDER BY total_hits DESC
    ");

    // دریافت آمار برای کمپین
    $campaign_stats = $wpdb->get_results("
        SELECT campaign, SUM(hit_count) as total_hits
        FROM $table_utm
        WHERE campaign != ''
        GROUP BY campaign
        ORDER BY total_hits DESC
    ");

    // دریافت آمار برای کانتنت
    $content_stats = $wpdb->get_results("
        SELECT content, SUM(hit_count) as total_hits
        FROM $table_utm
        WHERE content != ''
        GROUP BY content
        ORDER BY total_hits DESC
    ");

    // دریافت آمار برای ترم
    $term_stats = $wpdb->get_results("
        SELECT term, SUM(hit_count) as total_hits
        FROM $table_utm
        WHERE term != ''
        GROUP BY term
        ORDER BY total_hits DESC
    ");

    // بارگذاری کتابخانه‌ها
    wp_enqueue_script('jquery');
    wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], null, true);
    wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', [], null);
    wp_localize_script('datatables', 'utm_ajax', ['ajaxurl' => admin_url('admin-ajax.php')]);
    ?>
    <style>
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: space-between;
            align-items: stretch;
        }
        .stats-box {
            background: #ffffff;
            padding: 15px;
            border-radius: 12px;
            flex: 1;
            min-width: 200px;
            max-width: 300px;
            max-height: 250px;
            overflow: auto;
            border: 2px solid #ccc;
            box-shadow: 0 6px 10px -10px #000;
        }
        .stats-box h3 { margin-top: 0; color: #a2b3a0; }
        #utm-table_wrapper {
            margin-top: 20px;
        }
        #utm-table {
            width: 100% !important;
        }
        table.dataTable tbody tr:hover {
            background-color: #84ad8233 !important;
        }
    </style>

    <div class="stats-container">
        <div class="stats-box">
            <h3>Source Statistics</h3>
            <ul>
                <?php foreach ($source_stats as $stat) : ?>
                    <li><strong><?php echo esc_html($stat->source); ?>:</strong> <?php echo esc_html($stat->total_hits); ?> clicks</li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="stats-box">
            <h3>Medium Statistics</h3>
            <ul>
                <?php foreach ($medium_stats as $stat) : ?>
                    <li><strong><?php echo esc_html($stat->medium); ?>:</strong> <?php echo esc_html($stat->total_hits); ?> clicks</li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="stats-box">
            <h3>Campaign Statistics</h3>
            <ul>
                <?php foreach ($campaign_stats as $stat) : ?>
                    <li><strong><?php echo esc_html($stat->campaign); ?>:</strong> <?php echo esc_html($stat->total_hits); ?> clicks</li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="stats-box">
            <h3>Content Statistics</h3>
            <ul>
                <?php foreach ($content_stats as $stat) : ?>
                    <li><strong><?php echo esc_html($stat->content); ?>:</strong> <?php echo esc_html($stat->total_hits); ?> clicks</li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="stats-box">
            <h3>Term Statistics</h3>
            <ul>
                <?php foreach ($term_stats as $stat) : ?>
                    <li><strong><?php echo esc_html($stat->term); ?>:</strong> <?php echo esc_html($stat->total_hits); ?> clicks</li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <h2>Recent UTM Tracking Data</h2>
    <table id="utm-table" class="display" style="width:100%;">
        <thead>
            <tr>
                <th>Time</th>
                <th>Source</th>
                <th>Medium</th>
                <th>Campaign</th>
                <th>Term</th>
                <th>Content</th>
                <th>IP</th>
                <th>Referer</th>
                <th>User Agent</th>
                <th>Landing Page</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <script>
        jQuery(document).ready(function($) {
            $('#utm-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: utm_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'utm_datatable_data'
                    },
                    dataSrc: 'data'
                },
                columns: [
                    { data: 0 },
                    { data: 1 },
                    { data: 2 },
                    { data: 3 },
                    { data: 4 },
                    { data: 5 },
                    { data: 6 },
                    { data: 7 },
                    { data: 8 },
                    { data: 9 }
                ],
                pageLength: 10,
                order: [[0, 'desc']],
                language: {
                    search: "جستجو: ",
                    lengthMenu: "نمایش _MENU_ رکورد",
                    info: "نمایش _START_ تا _END_ از _TOTAL_ رکورد",
                    infoEmpty: "هیچ داده‌ای یافت نشد",
                    infoFiltered: "(فیلتر شده از _MAX_ رکورد)",
                    paginate: {
                        previous: "قبلی",
                        next: "بعدی"
                    }
                }
            });
        });
    </script>
    <?php
}
?>
