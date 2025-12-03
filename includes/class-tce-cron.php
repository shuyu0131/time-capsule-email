<?php
if (!defined('ABSPATH')) {
    exit;
}

class TCE_Cron {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('tce_daily_event', array($this, 'process_scheduled_emails'));
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
    }
    
    public function add_cron_interval($schedules) {
        $schedules['tce_five_minutes'] = array(
            'interval' => 300, // 5分钟
            'display'  => __('每5分钟', 'time-capsule-email')
        );
        
        return $schedules;
    }
    
    public function setup_cron() {
        if (!wp_next_scheduled('tce_daily_event')) {
            wp_schedule_event(time(), 'tce_five_minutes', 'tce_daily_event');
        }
    }
    
    public function clear_cron() {
        wp_clear_scheduled_hook('tce_daily_event');
    }
    
    public function process_scheduled_emails() {
        // 确保邮件类已加载
        if (!class_exists('TCE_Email')) {
            require_once TCE_PLUGIN_PATH . 'includes/class-tce-email.php';
        }
        
        // 调用邮件类的处理函数
        TCE_Email::get_instance()->process_scheduled_emails();
    }
}

TCE_Cron::get_instance();
