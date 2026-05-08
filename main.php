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
        
        //ลบแคช
        delete_transient('sunny_wordpress_optimizer_health_stats');    
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

        // Optimize ตาราง
        $wpdb->query("OPTIMIZE TABLE $table_pages_visitor");

        echo '<div class="updated"><p>กวาดสถิติเก่าก่อนปี ' . date('Y') . ' ออกไปได้ <strong>' . number_format($deleted) . '</strong> แถวเรียบร้อยแล้วครับพี่!</p></div>';

        //ลบแคช
        delete_transient('sunny_wordpress_optimizer_health_stats');
    }
    ?>
    <style>
        .leftside {
            width: 350px;
            background: #f8f8f8;
            height: max-content;
        }
        .leftside h1 {
            background: #009FE3;
            color: #fff;
            font-size: 16px;
            padding: 10px 20px;
            margin: 0;
        }
        .leftside a {
            padding: 10px 20px;
            font-size: 14px;
            background: #f8f8f8;
            color: #000;
            transition: .2s ease-in-out;
            display: block;
            width: 100%;
            text-decoration: none;
        }
        .leftside a:hover {
            background: #fff;
            cursor: pointer;
        }
        .container {
            width: 1200px;
            background: #fff; 
        }
        .container h1 {
            background: #555;
            color: #fff;
            font-size: 16px;
            padding: 10px 20px;
            margin: 0;
        }
        .container p {
            padding: 0;
        }
        .white-label-zone {
            width: calc(100% + 20px);
            height: auto;
            background: #fff;
            display: flex;
            margin: 0 0 0 -20px;
        }
        .white-label-zone h1,p {
            padding: 0 20px;
        }
    </style>
    <div class="white-label-zone no-print">
        <span style="padding: 60px 10px 60px 40px;float: left;font-size: 60px;">🚀</span>
        <div style="padding: 20px 0;">
            <h1>Sunny's WordPress Optimizer</h1>
            <p>ระบบเพิ่มความเร็ว WordPress โดยการลบข้อมูลขยะ ผู้ใช้สแปมในระบบ
                <br>
                <strong>Github Repository:</strong> <a href="https://github.com/sunny420x/wordpress-optimizer" target="_blank">https://github.com/sunny420x/wordpress-optimizer</a>
            </p>
        </div>
    </div>
    <div class="wrap">
    <div style="display: flex;">
        <div class="leftside">
            <h1>🚀 Optimizer</h1>
            <a href="/wp-admin/admin.php?page=wordpress-optimizer&option=database_junk">🗃️ ขยะฐานข้อมูล</a>
            <a href="/wp-admin/admin.php?page=wordpress-optimizer&option=spam_user">👥 ผู้ใช้สแปม</a>
            <h1>⚙️ ตั้งค่า</h1>
            <a href="/wp-admin/admin.php?page=wordpress-optimizer&option=settings">⚙️ ตั้งค่าปลั้กอิน</a>
        </div>
        <?php
        if(isset($_GET['option']) && $_GET['option'] == "database_junk") {
        ?>
        <div class="container">
            <h1>📊 สถิติฐานข้อมูล (Database Maintenance)</h1>
            <div style="padding: 0 25px 25px 25px;">
                <p>ลบข้อมูลความสัมพันธ์ในตาราง <code><?= $table_relationships ?></code> ที่ไม่มีข้อมูลผู้เข้าชมตัวจริง</p>
                
                <?php
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
        </div>
        <?php
        } elseif(isset($_GET['option']) && $_GET['option'] == "spam_user") {
        ?>
        <div class="container">
            <h1>👥 ผู้ใช้ที่เข้าข่ายสแปม (Spam Users)</h1>
            <div style="padding: 0 25px 25px 25px;">
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

                    //ลบแคช
                    delete_transient('sunny_wordpress_optimizer_health_stats');
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
                ?>
            </div>
        </div>
        <?php
        } elseif(isset($_GET['option']) && $_GET['option'] == "settings") {
        ?>
        <div class="container">
            <h1>⚙️ ตั้งค่าปลั้กอิน (Plugin Setting)</h1>
            <div style="padding: 0 25px 25px 25px;">
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
        </div>
        <?php
        } else {
        ?>
        <div class="container">
            <h1>ยินดีต้อนรับเข้าสู่ Sunny's WordPress Optimizer</h1>
            <div style="padding: 0px 25px 25px 25px;">
                <h2>ปลั้กอินนี้ทำอะไร ?</h2>
                <p>ปลั้กอิน Sunny's WordPress Optimizer เป็นปลั้กอินที่รวบรวมคำสั่งที่ช่วยในการเพิ่มความเร็วเว็บไซต์ WordPress ได้จริง โดยเป็นการแก้ไขปัญหาจาก Case-Study จากเว็บไซต์จริงที่มีปัญหาเรื่องความเร็วที่มี
                    สาเหตุมาจากข้อมูลขยะที่สะสมในระบบ เช่น ข้อมูลความความสัมพันธ์ของผู้ใช้กับเว็บไซต์ที่ไม่มีความสัมพันธ์กับตารางอื่น หมายความว่าข้อมูลดังกล่าวไม่มีประโยชน์เลย รวมถึงผู้ใช้สแปมในระบบ เป็นต้น
                </p>
                <h2>คำแนะนำจากผู้พัฒนา</h2>
                <p>ใน <strong>Real-world scenario</strong> เว็บไซต์เว็บหนึ่ง ๆ อาจมีคำขอ (Requests) ที่ไม่มีประโยชน์เป็นจำนวนมาก เช่น Crawler หาช่องโหว่ Crawler จัดทำ Index หรือ Direactory Scanner เป็นต้น</p>
                <h3>WordFence</h3>
                <p>ปลั้กอิน WordFence เป็นปลั้กอินที่ผู้พัฒนาแนะนำให้ติดตั้งควบคู่ไปกับปลั้กอินนี้ เนื่องจาก WordFence ช่วยเป็น Application Firewall ในการกรองและ Block ผู้ใช้ที่ใช้ทรัพยากรของ Web Server โดยไม่เกิดประโยชน์ได้</p>
                <h3>การ Pruning WordPress Statistic</h3>
                <p>ให้ไปที่ <a href="/wp-admin/admin.php?page=wps_optimization_page&tab=purging" target="_blank">?page=wps_optimization_page&tab=purging</a> เพื่อทำการ Optimize ข้อมูลบ่อย ๆ โดยการลบข้อมูลสถิติที่เก่ากว่าหนึ่งปี</p>
            </div>
        </div>
        <?php
        }
        ?>
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
        'db_cleanup_widget', 
        'สรุปขยะสะสมใน Database', 
        'render_db_cleanup_widget'
    );
});

function render_db_cleanup_widget() {

    $cached_data = get_transient('sunny_wordpress_optimizer_health_stats');

    if ( false === $cached_data ) {
        global $wpdb;
    
        $table_relationships = $wpdb->prefix . 'statistics_visitor_relationships';
        $table_visitor = $wpdb->prefix . 'statistics_visitor';
        $table_pages_visitor = $wpdb->prefix . 'statistics_pages';

        $junk_rel_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_relationships r
             LEFT JOIN $table_visitor v ON r.visitor_id = v.ID
             WHERE v.ID IS NULL"
        );

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

        // บันทึกเก็บไว้ใน Cache 1 ชั่วโมง
        set_transient('sunny_wordpress_optimizer_health_stats', $cached_data, 1 * HOUR_IN_SECONDS);
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
        
        <a href="<?=admin_url('admin.php?page=wordpress-optimizer');?>" class="button button-primary" style="width: 100%; text-align: center; height: 36px; line-height: 34px;">เริ่มทำความสะอาด !</a>
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
    if ( is_admin() ) {
        global $pagenow;
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