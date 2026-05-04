<?php
/**
 * Plugin Name: Sunny's Wordpress Optimizer
 * Description: เปิด API Calls ที่ใช้เวลานาน ลบ User ที่มีคำสแปมใน Display Name เช่น cash, money, bonus และล้างข้อมูลสถิติที่เป็นขยะ (ไม่มีความสัมพันธ์กับตารางอื่น)
 * Version: 1.0
 * Author: Jirakit Pawnsakunrungrot
 * Author URI: https://www.linkedin.com/in/sunny-jirakit
 * Plugin URI: https://github.com/sunny420x/wordpress-optimizer
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// เพิ่มเมนูในหน้า Admin เพื่อกดรัน
add_action( 'admin_menu', 'sunny_wordpress_cleaner_menu' );

function sunny_wordpress_cleaner_menu() {
    add_menu_page(
        'Sunny\'s Wordpress Optimizer', // Title ของหน้า
        'Wordpress Optimizer', // ชื่อเมนูที่โชว์ในแถบข้าง
        'manage_options', //สิทธิ์การเข้าถึง (Admin)
        'wordpress-optimizer', // Slug ของหน้า
        'sunny_wordpress_optimizer_page', // ฟังก์ชันที่ใช้พ่น HTML หน้า Setting
        'dashicons-admin-tools', // ไอคอน
        '80' // ตำแหน่งเมนู
    );
}

function sunny_wordpress_optimizer_page() {
    global $wpdb;

    $table_relationships = $wpdb->prefix . 'statistics_visitor_relationships';
    $table_visitor = $wpdb->prefix . 'statistics_visitor';
    $table_pages_visitor = $wpdb->prefix . 'statistics_pages';

    echo '<div class="wrap">';

    // --- ส่วนที่ 1: จัดการล้างข้อมูลสถิติ (Stats Junk) ---
    if ( isset($_POST['clean_stats']) ) {
        check_admin_referer('wcc_clean_stats');

        $deleted = $wpdb->query(
            "DELETE r FROM $table_relationships r
             LEFT JOIN $table_visitor v ON r.visitor_id = v.ID
             WHERE v.ID IS NULL"
        );

        // สั่ง Optimize ตารางเพื่อคืนพื้นที่ทันที
        $wpdb->query("OPTIMIZE TABLE $table_relationships");

        echo '<div class="updated"><p>กวาดขยะสถิติออกไปได้ <strong>' . number_format($deleted) . '</strong> แถว และคืนพื้นที่ฐานข้อมูลเรียบร้อย!</p></div>';
    }

    if ( isset($_POST['clean_pages_stats']) ) {
        check_admin_referer('do_clean_stats');

        // คำนวณวันแรกของปีปัจจุบัน (เช่น 2026-01-01)
        $first_day_of_year = date('Y') . '-01-01';

        // ลบโดยใช้ Index (เร็วกว่าการใช้ฟังก์ชัน YEAR() ครอบ column)
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_pages_visitor WHERE date < %s",
                $first_day_of_year
            )
        );

        // Optimize ตาราง (ตัวนี้แหละที่อาจจะใช้เวลาหน่อย ถ้าตารางใหญ่มาก)
        $wpdb->query("OPTIMIZE TABLE $table_pages_visitor");

        echo '<div class="updated"><p>กวาดสถิติเก่าก่อนปี ' . date('Y') . ' ออกไปได้ <strong>' . number_format($deleted) . '</strong> แถวเรียบร้อยแล้วครับพี่!</p></div>';
    }
    ?>
    <div class="stats-cleaner-section" style="background: #fff; padding: 20px; border-radius: 10px; margin-top: 20px;">
        <h1>📊 สถิติฐานข้อมูล (Database Maintenance)</h1>
        <p>ลบข้อมูลความสัมพันธ์ในตาราง <code><?= $table_relationships ?></code> ที่ไม่มีข้อมูลผู้เข้าชมตัวจริง</p>
        
        <?php
        // เช็คจำนวนขยะก่อนลบ
        $junk_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_relationships r
             LEFT JOIN $table_visitor v ON r.visitor_id = v.ID
             WHERE v.ID IS NULL"
        );

        $junk_visit_count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table_pages_visitor WHERE date < %s", date('Y') . '-01-01')
        );
        ?>
        
        <p>ตรวจพบข้อมูลขยะ: <strong style="color:red; font-size: 1.2em;"><?= number_format($junk_count) ?></strong> แถว</p>
        <form method="post">
            <?php wp_nonce_field('wcc_clean_stats'); ?>
            <input type="submit" name="clean_stats" class="button button-secondary" 
                   value="ล้างขยะสถิติและ Optimize ตาราง" 
                   onclick="return confirm('ล้างข้อมูลเลยไหม ?');"
                   <?= ($junk_count == 0) ? 'disabled' : '' ?>>
        </form>

        <p>ลบข้อมูลสถิติการเข้าชมในตาราง <code><?= $table_pages_visitor ?></code> ที่เก่ากว่าปี <?=date('Y')?></p>

        <p>ตรวจพบข้อมูลการเข้าชมที่เก่ากว่าปี <?=date('Y')?>: <strong style="color:red; font-size: 1.2em;"><?= number_format($junk_visit_count) ?></strong> แถว</p>
        <form method="post">
            <?php wp_nonce_field('do_clean_stats'); ?>
            <input type="submit" name="clean_pages_stats" class="button button-secondary" 
                   value="ล้างขยะสถิติการเข้าชมและ Optimize ตาราง" 
                   onclick="return confirm('ล้างข้อมูลเลยไหม ?');"
                   <?= ($junk_visit_count == 0) ? 'disabled' : '' ?>>
        </form>
    </div>
    <div style="background: #fff; padding: 20px; border-radius: 10px; margin-top: 20px;">
    <h1>👥 ผู้ใช้ที่เข้าข่ายสแปม (Spam Users)</h1>
    <p>ตรวจสอบผู้ใช้ที่เข้าข่ายสแปม เช่น ผู้ใช้ที่ตั้งชื่อเพื่อโปรโมทเว็บไซต์ภายนอก สามารถจัดการคำที่เข้าข่ายได้ใน Blacklist</p>
    <?php
    $spam_words = explode("\n", get_option('sunny_cleanner_blacklist', "cash\nmoney\nbonus\noffer\nprize\nblogspot"));
    $spam_words = array_map('trim', $spam_words);

    // ขั้นตอนการลบ (เมื่อกดปุ่ม Confirm Delete)
    if ( isset($_POST['confirm_delete']) ) {
        check_admin_referer('wcc_confirm_delete');
        $ids_to_delete = explode(',', $_POST['user_ids']);
        $count = 0;

        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        foreach ( $ids_to_delete as $user_id ) {
            if ( get_current_user_id() == $user_id ) continue;
            wp_delete_user( intval($user_id) );
            $count++;
        }
        echo '<div class="updated"><p>กำจัดสแปมออกไปแล้ว <strong>' . $count . '</strong> บัญชี!</p></div>';
    }

    $found_users = array();
    foreach ( $spam_words as $word ) {
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, user_login, display_name, user_email FROM $wpdb->users WHERE display_name LIKE %s",
            '%' . $wpdb->esc_like($word) . '%'
        ) );
        if ($results) {
            $found_users = array_merge($found_users, $results);
        }
    }

    $found_users = array_unique($found_users, SORT_REGULAR);

    if ( !empty($found_users) ) {
    ?>
    <h3>พบ User ที่เข้าข่ายสแปม <?=count($found_users);?> รายชื่อ</h3>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Login Name</th>
                <th>Display Name</th>
                <th>Email</th>
            </tr>
            
        </thead>
        <tbody>
            <?php
            $ids_array = array();
            foreach ( $found_users as $user ) {
                $ids_array[] = $user->ID;
            ?>
                <tr>
                    <td><?=$user->ID;?></td>
                    <td><strong><?=$user->user_login;?></strong></td>
                    <td><span style='color:red;'><?=$user->display_name;?></span></td>
                    <td><?=$user->user_email;?></td>
                </tr>
            <?php
            }
            ?>
        </tbody>
    </table>
    <form method="post" style="margin-top:20px;">
        <?php wp_nonce_field('wcc_confirm_delete'); ?>
        <input type="hidden" name="user_ids" value="<?= implode(',', $ids_array); ?>">
        <input type="submit" name="confirm_delete" class="button button-primary" 
                value="ลบรายชื่อข้างต้นทั้งหมด" 
                onclick="return confirm('ลบผู้ใช้ที่เข้าข่ายสแปมทั้งหมดเลยหรือไม่ ?');">
    </form>
<?php
    } else {
        echo '<h2>✅ ยินดีด้วย! ไม่พบ User สแปมในระบบแล้ว</h2>';
    }
    echo '</div>';
?>
    <div style="background: #fff; padding: 20px; border-radius: 10px; margin-top: 20px;">
        <h1>⚙️ ตั้งค่าปลั้กอิน (Plugin Setting)</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('sunny_optimizer_settings_group');
            ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>หมวดหมู่</th>
                        <th>ตั้งค่า</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Spam Word Blacklist</strong></td>
                        <td>
                            <textarea name="sunny_cleanner_blacklist" style="width: 500px; height: 200px;"><?php echo esc_attr(get_option('sunny_cleanner_blacklist', "cash\nmoney\nbonus\noffer\nprize\nblogspot")); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>ปิดใช้งานการติดต่อ API ภายนอก</strong> *ต้องปิดใช้งานฟีเจอร์นี้ชั่วคราว จึงจะสามารถติดตั้งปลั้กอินใหม่ได้</td>
                        <td>
                            <select name="sunny_cleanner_disable_external_api" id="">
                                <option value="yes" <?php if(get_option('sunny_cleanner_disable_external_api', 'no') == "yes") { echo "selected";} ?>>ป้องกันติดต่อ API ภายนอก</option>
                                <option value="no" <?php if(get_option('sunny_cleanner_disable_external_api', 'no') == "no") { echo "selected";} ?>>ยอมเปิดติดต่อ API ภายนอก</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button('บันทึกการเปลี่ยนแปลง'); ?>
        </form>
    </div>
<?php
}

add_action('admin_init', 'sunny_optimizer_settings_init');

function sunny_optimizer_settings_init() {
    register_setting('sunny_optimizer_settings_group', 'sunny_cleanner_blacklist');
    register_setting('sunny_optimizer_settings_group', 'sunny_cleanner_disable_external_api');
}

//Widget
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'wgc_db_cleanup_widget', 
        'สรุปขยะสะสมใน Database', 
        'wgc_render_db_cleanup_widget'
    );
});

function wgc_render_db_cleanup_widget() {

    $cached_data = get_transient('wgc_db_health_stats');

    if ( false === $cached_data ) {
        global $wpdb;
    
        // กำหนดชื่อตาราง (ปรับให้ตรงกับที่พี่ใช้นะครับ)
        $table_visitor = $wpdb->prefix . 'statistics_visitor';
        $table_relationships = $wpdb->prefix . 'statistics_relationships';
        $table_pages_visitor = $wpdb->prefix . 'statistics_pages';

        // 1. เช็คขยะความสัมพันธ์ (Orphaned Data)
        $junk_rel_count = $wpdb->get_var( "
            SELECT COUNT(*) FROM $table_relationships r
            LEFT JOIN $table_visitor v ON r.visitor_id = v.ID
            WHERE v.ID IS NULL
        " );

        // 2. เช็คสถิติเก่าค้างปี
        $current_year_start = date('Y') . '-01-01';
        $junk_visit_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_pages_visitor WHERE date < %s", 
            $current_year_start
        ) );

        $cached_data = [
            'rel' => $junk_rel_count,
            'visit' => $junk_visit_count,
            'time' => current_time('mysql')
        ];

        // 3. บันทึกเก็บไว้ใน Cache 12 ชั่วโมง
        set_transient('wgc_db_health_stats', $cached_data, 12 * HOUR_IN_SECONDS);
    }

    $total_junk = $cached_data['rel'] + $cached_data['visit'];
    ?>

    <div style="padding: 5px 0;">    
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px dashed #ccc;">
            <span>ขยะความสัมพันธ์:</span>
            <strong><?=number_format($cached_data['rel'])?> แถว</strong>
        </div>

        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
            <span>สถิติเก่า (ก่อนปี <?=date('Y')?>):</span>
            <strong><?=number_format($cached_data['visit'])?> แถว</strong>
        </div>

        <small>อัปเดตล่าสุดเมื่อ: <?=$cached_data['time']?></small>

        <br><br>

    <?php
    // สรุปสถานะ
    if ($total_junk > 0) {
        $color = ($total_junk > 100000) ? '#E94C3D' : '#E67E22'; // ถ้าเกินแสนแถวให้ขึ้นสีชมพูเข้ม
    ?>
        <div style="background: <?=$color?>; color: #fff; padding: 10px; border-radius: 4px; text-align: center; margin-bottom: 15px;">
        <span style="font-size: 16px; font-weight: bold;">รวมขยะสะสม: <?=number_format($total_junk);?> แถว</span>
        </div>
        
        <a href="<?=admin_url('admin.php?page=spam-cleaner');?>" class="button button-primary" style="width: 100%; text-align: center; height: 36px; line-height: 34px;">เริ่มทำความสะอาด !</a>;
    <?php } else { ?>
        <div style="background: #26AE60; color: #fff; padding: 10px; border-radius: 4px; text-align: center;">
            <strong>ฐานข้อมูลสะอาดกริบ !</strong>
        </div>
    <?php
    }
    ?>

    </div>
<?php
} 

/**
 * Block External API Requests to WordPress.org for WooCommerce Info
 */
add_filter( 'pre_http_request', function( $pre, $args, $url ) {
    // 1. เช็คว่าอยู่ในหน้า Admin ที่ต้องใช้ API หรือเปล่า?
    if ( is_admin() ) {
        global $pagenow;
        // รายชื่อหน้าที่ "ห้ามบล็อก" เพราะต้องใช้เชื่อมต่อ WordPress.org
        $allowed_pages = array(
            'plugin-install.php', 
            'update-core.php', 
            'plugins.php', 
            'theme-install.php'
        );

        if ( in_array( $pagenow, $allowed_pages ) ||  get_option('sunny_cleanner_disable_external_api', 'no') == "no") {
            return $pre; // ปล่อยให้ผ่านไปได้ ปกติ
        }
    }

    // 2. ถ้าไม่ใช่หน้าด้านบน และมีการยิงไปหา api.wordpress.org หรือ woocommerce.json ให้บล็อกทันที
    if ( strpos( $url, 'api.wordpress.org' ) !== false || strpos( $url, 'woocommerce.json' ) !== false ) {
        return new WP_Error( 'http_request_failed', 'Blocked for speed optimization!' );
    }

    return $pre;
}, 10, 3 );

add_filter( 'pre_http_request', function( $pre, $args, $url ) {
    if( get_option('sunny_cleanner_disable_external_api', 'no') == "yes") {
        if ( strpos( $url, 'api.wordpress.org/plugins/info' ) !== false && strpos( $url, 'woocommerce' ) !== false ) {
            return new WP_Error( 'blocked_request', 'Force blocked for speed', array( 'status' => 403 ) );
        }
    }
    return $pre;
}, 10, 3 );