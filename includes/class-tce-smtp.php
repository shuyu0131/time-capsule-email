<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMTP邮件发送类 - 使用WordPress内置PHPMailer
 */
class TCE_SMTP_Mailer {
    private $settings;
    
    public function __construct() {
        $this->load_settings();
    }
    
    private function load_settings() {
        $email_settings = get_option('tce_email_settings', array());
        
        $this->settings = array(
            'smtp_host' => isset($email_settings['smtp_host']) ? trim($email_settings['smtp_host']) : '',
            'smtp_port' => isset($email_settings['smtp_port']) ? intval($email_settings['smtp_port']) : 587,
            'smtp_username' => isset($email_settings['smtp_username']) ? trim($email_settings['smtp_username']) : '',
            'smtp_password' => isset($email_settings['smtp_password']) ? $email_settings['smtp_password'] : '',
            'smtp_encryption' => isset($email_settings['smtp_encryption']) ? $email_settings['smtp_encryption'] : 'tls',
            'from_name' => isset($email_settings['from_name']) ? $email_settings['from_name'] : get_bloginfo('name'),
            'from_email' => isset($email_settings['from_email']) ? $email_settings['from_email'] : get_option('admin_email'),
            'reply_to' => isset($email_settings['reply_to']) ? $email_settings['reply_to'] : '',
        );
    }
    
    /**
     * 配置PHPMailer使用SMTP
     * 使用高优先级确保配置不被其他插件覆盖
     */
    public function configure_phpmailer($phpmailer) {
        if (empty($this->settings['smtp_host']) || empty($this->settings['smtp_username'])) {
            return;
        }

        // 强制重新配置SMTP，防止被其他插件修改
        $phpmailer->isSMTP();
        $phpmailer->Host = $this->settings['smtp_host'];
        if (!empty($this->settings['smtp_port'])) {
            $phpmailer->Port = intval($this->settings['smtp_port']);
        }
        $phpmailer->SMTPAuth = !empty($this->settings['smtp_username']);
        $phpmailer->Username = $this->settings['smtp_username'];
        $phpmailer->Password = $this->settings['smtp_password'];
        
        // 设置加密方式
        if ($this->settings['smtp_encryption'] === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($this->settings['smtp_encryption'] === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
        }
        
        // 设置发件人
        $from_email = $this->settings['from_email'];
        
        // 对于某些邮件服务商（如163、QQ、126），发件人必须与SMTP用户名一致
        if (strpos($this->settings['smtp_host'], '163.com') !== false || 
            strpos($this->settings['smtp_host'], 'qq.com') !== false || 
            strpos($this->settings['smtp_host'], '126.com') !== false) {
            $from_email = $this->settings['smtp_username'];
        }
        
        // 设置发件人（不清除已有的收件人）
        if (!empty($from_email)) {
            $phpmailer->From = $from_email;
        }
        if (!empty($this->settings['from_name'])) {
            $phpmailer->FromName = $this->settings['from_name'];
        }
        // 确保信封发件人与 From 一致，兼容 Aliyun 等 SMTP 要求
        if (empty($phpmailer->Sender) || $phpmailer->Sender !== $phpmailer->From) {
            $phpmailer->Sender = $phpmailer->From;
        }
        
        // 设置回复地址
        if (!empty($this->settings['reply_to'])) {
            $phpmailer->addReplyTo($this->settings['reply_to']);
        }
        
        // 设置超时和其他选项
        $phpmailer->Timeout = 30;
        $phpmailer->SMTPKeepAlive = false;
        
        // 禁用调试输出
        $phpmailer->SMTPDebug = 0;
        
        // 设置字符集
        $phpmailer->CharSet = 'UTF-8';
        
        // 确保使用HTML格式
        $phpmailer->isHTML(true);
    }
    
    /**
     * 发送邮件 - 直接使用PHPMailer，不依赖wp_mail()
     * 这样可以完全避免与其他插件的冲突
     */
    public function send($to, $subject, $message, $headers = array()) {
        if (empty($this->settings['smtp_host']) || 
            empty($this->settings['smtp_username']) || 
            empty($this->settings['smtp_password'])) {
            return false;
        }
        
        // 确保PHPMailer已加载
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        
        try {
            // 创建独立的PHPMailer实例，避免主题/插件改写 wp_mail 模板
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // 配置SMTP
            $mail->isSMTP();
            $mail->Host = $this->settings['smtp_host'];
            if (!empty($this->settings['smtp_port'])) {
                $mail->Port = intval($this->settings['smtp_port']);
            }
            $mail->SMTPAuth = !empty($this->settings['smtp_username']);
            $mail->Username = $this->settings['smtp_username'];
            $mail->Password = $this->settings['smtp_password'];
            
            // 设置加密方式
            if ($this->settings['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = 'ssl';
            } elseif ($this->settings['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = 'tls';
            } else {
                $mail->SMTPSecure = '';
            }
            
            // 设置发件人
            $from_email = $this->settings['from_email'];
            
            // 对于某些邮件服务商（如163、QQ、126），发件人必须与SMTP用户名一致
            if (strpos($this->settings['smtp_host'], '163.com') !== false || 
                strpos($this->settings['smtp_host'], 'qq.com') !== false || 
                strpos($this->settings['smtp_host'], '126.com') !== false) {
                $from_email = $this->settings['smtp_username'];
            }
            
            $mail->setFrom($from_email, $this->settings['from_name']);
            // 确保信封发件人与 From 一致，兼容 Aliyun 等 SMTP 要求
            if (empty($mail->Sender) || $mail->Sender !== $mail->From) {
                $mail->Sender = $mail->From;
            }
            
            // 设置收件人
            $mail->addAddress($to);
            
            // 设置回复地址
            if (!empty($this->settings['reply_to'])) {
                $mail->addReplyTo($this->settings['reply_to']);
            }
            
            // 设置邮件内容
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            
            // 设置超时和其他选项
            $mail->Timeout = 30;
            $mail->SMTPKeepAlive = false;
            $mail->SMTPDebug = 0;
            
            // 发送邮件
            $result = $mail->send();
            
            return $result;
            
        } catch (Exception $e) {
            // 记录错误（可选）
            // error_log('TCE SMTP Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 测试SMTP连接（带详细调试）
     */
    public function test_connection() {
        if (empty($this->settings['smtp_host']) || 
            empty($this->settings['smtp_username']) || 
            empty($this->settings['smtp_password'])) {
            return array(
                'success' => false, 
                'message' => __('SMTP配置不完整，请检查主机、用户名和密码是否都已填写', 'time-capsule-email')
            );
        }
        
        // 使用 wp_mail 走 WordPress 全局 PHPMailer，与其他插件路径一致
        $debug_output = '';
        $summary = '';
        $mailer_hook = function($phpmailer) use (&$debug_output, &$summary) {
            $this->configure_phpmailer($phpmailer);

            $phpmailer->SMTPDebug = 2;
            $phpmailer->Debugoutput = function($str, $level) use (&$debug_output) {
                $debug_output .= $str . "\n";
            };

            $summary = "Mailer: " . $phpmailer->Mailer . "\n" .
                       "Host: " . $phpmailer->Host . "\n" .
                       "Port: " . $phpmailer->Port . "\n" .
                       "SMTPSecure: " . $phpmailer->SMTPSecure . "\n" .
                       "SMTPAuth: " . ($phpmailer->SMTPAuth ? 'true' : 'false') . "\n" .
                       "From: " . $phpmailer->From . "\n" .
                       "Sender: " . $phpmailer->Sender;
        };
        add_action('phpmailer_init', $mailer_hook, 10002);

        $wp_mail_error = null;
        $error_hook = function($wp_error) use (&$wp_mail_error) {
            $wp_mail_error = $wp_error;
        };
        add_action('wp_mail_failed', $error_hook);

        $email_settings = get_option('tce_email_settings', array());
        $to = '';
        if (!empty($email_settings['test_email']) && is_email($email_settings['test_email'])) {
            $to = $email_settings['test_email'];
        } elseif (!empty($this->settings['from_email']) && is_email($this->settings['from_email'])) {
            $to = $this->settings['from_email'];
        } else {
            $to = get_option('admin_email');
        }
        $subject = 'SMTP Test';
        $body = 'Test';
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $sent = wp_mail($to, $subject, $body, $headers);

        remove_action('phpmailer_init', $mailer_hook, 10002);
        remove_action('wp_mail_failed', $error_hook);

        if ($sent) {
            return array(
                'success' => true,
                'message' => __('SMTP连接和认证测试成功！配置正确。', 'time-capsule-email')
            );
        }

        $error_msg = '';
        if ($wp_mail_error instanceof WP_Error) {
            $error_msg = $wp_mail_error->get_error_message();
        }
        $debug_output = trim($debug_output);
        $summary = trim($summary);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TCE SMTP Test Failed (wp_mail): ' . ($error_msg ?: 'Unknown error'));
        }

        return array(
            'success' => false,
            'message' => __('SMTP测试失败: ', 'time-capsule-email') .
                        ($error_msg ?: __('未知错误，请查看服务器错误日志。', 'time-capsule-email')) .
                        (!empty($summary) ? "\n\n" . __('当前配置: ', 'time-capsule-email') . "\n" . $summary : '') .
                        (!empty($debug_output) ? "\n\n" . __('调试输出: ', 'time-capsule-email') . "\n" . $debug_output : '')
        );
    }
    
    /**
     * 发送测试邮件
     */
    public function send_test_email($to = null) {
        if (!$to) {
            $to = get_option('admin_email');
        }
        
        $subject = __('时光邮局SMTP测试邮件', 'time-capsule-email');
        $message = $this->get_test_email_template();
        
        return $this->send($to, $subject, $message);
    }
    
    /**
     * 获取测试邮件模板
     */
    private function get_test_email_template() {
        $blog_name = get_bloginfo('name');
        $site_url = home_url('/');
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>SMTP测试邮件</title>
    <style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
    .header { background: linear-gradient(135deg, #98d6cf, #fde0f7); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
    .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
    .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
    .success { color: #28a745; font-weight: bold; }
    .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #007cba; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 SMTP测试成功！</h1>
        </div>
        <div class="content">
            <p class="success">恭喜！您的SMTP配置已正确设置，邮件发送功能正常工作。</p>
            
            <div class="info">
                <h3>📧 邮件发送信息</h3>
                <p><strong>发件人:</strong> ' . esc_html($this->settings['from_name']) . ' &lt;' . esc_html($this->settings['from_email']) . '&gt;</p>
                <p><strong>SMTP服务器:</strong> ' . esc_html($this->settings['smtp_host']) . ':' . esc_html($this->settings['smtp_port']) . '</p>
                <p><strong>加密方式:</strong> ' . esc_html(strtoupper($this->settings['smtp_encryption'])) . '</p>
                <p><strong>发送时间:</strong> ' . date('Y-m-d H:i:s') . '</p>
            </div>
            
            <p>现在您可以正常使用时光邮局插件发送邮件了！</p>
        </div>
        <div class="footer">
            <p>此邮件由 <a href="' . esc_url($site_url) . '">' . esc_html($blog_name) . '</a> 的时光邮局插件发送</p>
        </div>
    </div>
</body>
</html>';
    }
}
