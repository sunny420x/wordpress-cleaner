<?php
/**
 * Plugin Name: Sunny's Wordpress Cleaner
 * Description: ลบ User ที่มีคำสแปมใน Display Name เช่น cash, money, bonus และล้างข้อมูลสถิติที่เป็นขยะ (ไม่มีความสัมพันธ์กับตารางอื่น)
 * Version: 1.0
 * Author: Jirakit Pawnsakunrungrot
 * Author URI: https://www.linkedin.com/in/sunny-jirakit
 * Plugin URI: https://github.com/sunny420x/wordpress-cleaner
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// เพิ่มเมนูในหน้า Admin เพื่อกดรัน
add_action( 'admin_menu', 'sunny_wordpress_cleaner_menu' );

function sunny_wordpress_cleaner_menu() {
    add_menu_page(
        'Sunny\'s Wordpress Cleaner', // Title ของหน้า
        'ระบบช่วยลบขยะบน Wordpress', // ชื่อเมนูที่โชว์ในแถบข้าง
        'manage_options', //สิทธิ์การเข้าถึง (Admin)
        'spam-cleaner', // Slug ของหน้า
        'sunny_wordpress_cleaner_page', // ฟังก์ชันที่ใช้พ่น HTML หน้า Setting
        'dashicons-admin-tools', // ไอคอน
        '80' // ตำแหน่งเมนู
    );
}

function sunny_wordpress_cleaner_page() {
    global $wpdb;

    $table_relationships = $wpdb->prefix . 'statistics_visitor_relationships';
    $table_visitor = $wpdb->prefix . 'statistics_visitor';

    echo '<div class="wrap">';
    echo '<h1>Sunny\' Wordpress Maintenance Tool</h1>';

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
    ?>
    <div class="stats-cleaner-section" style="background: #fff; padding: 20px; border-radius: 10px; margin-top: 20px;">
        <h1>📊 สถิติฐานข้อมูล (Database Maintenance)</h1>
        <p>ลบข้อมูลความสัมพันธ์ในตาราง <code><?= $table_relationships ?></code> ที่ไม่มีข้อมูลผู้เข้าชมตัวจริงแล้ว</p>
        
        <?php
        // เช็คจำนวนขยะก่อนลบ
        $junk_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_relationships r
             LEFT JOIN $table_visitor v ON r.visitor_id = v.ID
             WHERE v.ID IS NULL"
        );
        ?>
        
        <p>ตรวจพบข้อมูลขยะค้างค้าง: <strong style="color:red; font-size: 1.2em;"><?= number_format($junk_count) ?></strong> แถว</p>
        
        <form method="post">
            <?php wp_nonce_field('wcc_clean_stats'); ?>
            <input type="submit" name="clean_stats" class="button button-secondary" 
                   value="ล้างขยะสถิติและ Optimize ตาราง" 
                   onclick="return confirm('ล้างข้อมูลเลยไหม ?');"
                   <?= ($junk_count == 0) ? 'disabled' : '' ?>>
        </form>
    </div>
    <div style="background: #fff; padding: 20px; border-radius: 10px; margin-top: 20px;">
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
    <h1>ผู้ใช้สแปม (Spam Users)</h1>
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
        <h1>ตั้งค่าปลั้กอิน (Plugin Setting)</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('sunny_cleanner_settings_group');
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
                </tbody>
            </table>
            <?php submit_button('บันทึกการเปลี่ยนแปลง'); ?>
        </form>
    </div>
<?php
}

add_action('admin_init', 'sunny_cleanner_settings_init');

function sunny_cleanner_settings_init() {
    register_setting('sunny_cleanner_settings_group', 'sunny_cleanner_blacklist');
}