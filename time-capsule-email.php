<?php
/**
 * Plugin Name: 时光邮局
 * Plugin URI: /wp-admin/options-general.php?page=time-capsule-email
 * Description: 让用户通过电子邮件记录当下，设置未来的发送时间，让未来的自己收到过去的消息。
 * Version: 1.0.1
 * Author: 属余
 * Author URI: https://waikanl.cn/
 * Text Domain: time-capsule-email
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('TCE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TCE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TCE_VERSION', '1.2.1');

// 自动加载类
spl_autoload_register(function ($class) {
    $prefix = 'TCE_';
    $base_dir = __DIR__ . '/includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-tce-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// 主类
class Time_Capsule_Email {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }
    
    private function includes() {
        require_once TCE_PLUGIN_PATH . 'includes/class-tce-email.php';
        require_once TCE_PLUGIN_PATH . 'includes/class-tce-shortcodes.php';
        require_once TCE_PLUGIN_PATH . 'includes/class-tce-cron.php';
        require_once TCE_PLUGIN_PATH . 'includes/class-tce-smtp.php';
        
        // 只在后台加载管理类
        if (is_admin()) {
            require_once TCE_PLUGIN_PATH . 'includes/class-tce-admin.php';
        }
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
    }
    
    public function activate() {
        $this->create_tables();
        
        // 确保计划任务正确设置
        TCE_Cron::get_instance()->clear_cron(); 
        TCE_Cron::get_instance()->setup_cron();
    }
    
    public function deactivate() {
        TCE_Cron::get_instance()->clear_cron();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'tce_emails';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            email_to varchar(100) NOT NULL,
            subject varchar(200) NOT NULL,
            message longtext NOT NULL,
            send_date datetime NOT NULL,
            is_sent tinyint(1) DEFAULT 0,
            is_verified tinyint(1) DEFAULT 0,
            is_public tinyint(1) DEFAULT 0,
            verification_token varchar(64) NULL,
            verified_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY is_sent (is_sent),
            KEY is_verified (is_verified),
            KEY is_public (is_public),
            KEY send_date (send_date),
            KEY verification_token (verification_token)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // 迁移旧数据：将没有验证token的旧邮件标记为已验证
        $this->migrate_old_emails();
    }
    
    /**
     * 迁移旧邮件数据
     */
    private function migrate_old_emails() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tce_emails';
        
        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        // 检查is_verified字段是否存在
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'is_verified'");
        
        if (!empty($column_exists)) {
            // 将所有没有verification_token的邮件（旧邮件）标记为已验证
            $wpdb->query(
                "UPDATE `{$table_name}` 
                SET `is_verified` = 1 
                WHERE (`verification_token` IS NULL OR `verification_token` = '') 
                AND `is_verified` = 0"
            );
        }
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('time-capsule-email', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function init() {
        // 初始化代码
    }
}

// 初始化插件
function time_capsule_email() {
    return Time_Capsule_Email::get_instance();
}

// 启动插件
time_capsule_email();
