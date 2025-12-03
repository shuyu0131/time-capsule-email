#!/usr/bin/env php
<?php
/**
 * 时光邮局 - 独立计划任务脚本
 * 用于宝塔面板定时任务
 * 
 * 使用方法：
 * 1. 在宝塔面板 -> 计划任务 -> Shell脚本
 * 2. 执行周期：每5分钟
 * 3. 脚本内容：/usr/bin/php /www/wwwroot/你的网站目录/wp-content/plugins/time-capsule-email/cron-send-emails.php
 */

// 设置执行时间限制
set_time_limit(300);
ini_set('memory_limit', '256M');

// 记录开始时间
$start_time = microtime(true);
$log_file = __DIR__ . '/cron-log.txt';

// 日志函数
function write_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $log_message;
}

write_log("========== 开始执行计划任务 ==========");

// 加载 WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

if (!file_exists($wp_load_path)) {
    write_log("错误：找不到 wp-load.php 文件，路径：{$wp_load_path}");
    exit(1);
}

require_once($wp_load_path);

write_log("WordPress 加载成功");

// 检查插件是否激活
if (!defined('TCE_PLUGIN_PATH')) {
    write_log("错误：时光邮局插件未激活");
    exit(1);
}

// 加载邮件处理类
require_once TCE_PLUGIN_PATH . 'includes/class-tce-email.php';

write_log("开始处理待发送邮件...");

try {
    // 获取邮件实例并处理
    $email_handler = TCE_Email::get_instance();
    $result = $email_handler->process_scheduled_emails();
    
    if (is_array($result)) {
        write_log("处理完成 - 成功: {$result['success']}, 失败: {$result['failed']}, 总计: {$result['total']}");
    } else {
        write_log("处理完成");
    }
    
} catch (Exception $e) {
    write_log("错误：" . $e->getMessage());
    exit(1);
}

// 计算执行时间
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

write_log("执行时间：{$execution_time} 秒");
write_log("========== 计划任务执行完毕 ==========\n");

exit(0);
