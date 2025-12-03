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
        // 强制重新配置SMTP，防止被其他插件修改
        $phpmailer->isSMTP();
        $phpmailer->Host = $this->settings['smtp_host'];
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = intval($this->settings['smtp_port']);
        $phpmailer->Username = $this->settings['smtp_username'];
        $phpmailer->Password = $this->settings['smtp_password'];
        
        // 设置加密方式
        if ($this->settings['smtp_encryption'] === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($this->settings['smtp_encryption'] === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
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
        $phpmailer->setFrom($from_email, $this->settings['from_name'], false);
        
        // 设置回复地址
        if (!empty($this->settings['reply_to'])) {
            $phpmailer->addReplyTo($this->settings['reply_to']);
        }
        
        // 设置超时和其他选项
        $phpmailer->Timeout = 30;
        $phpmailer->SMTPKeepAlive = false;
        $phpmailer->SMTPAutoTLS = true;
        
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
            // 创建独立的PHPMailer实例
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // 配置SMTP
            $mail->isSMTP();
            $mail->Host = $this->settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Port = intval($this->settings['smtp_port']);
            $mail->Username = $this->settings['smtp_username'];
            $mail->Password = $this->settings['smtp_password'];
            
            // 设置加密方式
            if ($this->settings['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = 'ssl';
            } elseif ($this->settings['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = 'tls';
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
            $mail->SMTPAutoTLS = true;
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
        
        // 确保PHPMailer已加载
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        
        // 捕获调试输出
        $debug_output = '';
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // 启用调试模式捕获详细信息
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) use (&$debug_output) {
                $debug_output .= $str . "\n";
            };
            
            // 配置SMTP
            $mail->isSMTP();
            $mail->Host = trim($this->settings['smtp_host']);
            $mail->SMTPAuth = true;
            $mail->Port = intval($this->settings['smtp_port']);
            $mail->Username = trim($this->settings['smtp_username']);
            $mail->Password = $this->settings['smtp_password']; // 不trim密码，可能包含空格
            
            // 设置加密方式
            if ($this->settings['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = 'ssl';
            } elseif ($this->settings['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = 'tls';
            }
            
            // 设置超时和选项
            $mail->Timeout = 15;
            $mail->SMTPAutoTLS = true;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // 设置必要的邮件信息以进行完整测试
            $mail->setFrom($this->settings['smtp_username'], 'Test');
            $mail->addAddress($this->settings['smtp_username']);
            $mail->Subject = 'SMTP Test';
            $mail->Body = 'Test';
            
            // 尝试发送（但不真正发送）
            $mail->preSend();
            
            // 如果到这里没有异常，说明认证成功
            return array(
                'success' => true, 
                'message' => __('SMTP连接和认证测试成功！配置正确。', 'time-capsule-email')
            );
            
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
            
            // 记录错误到日志（仅在测试失败时）
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TCE SMTP Test Failed: ' . $error_msg);
            }
            
            // 分析错误类型并提供具体建议
            if (strpos($error_msg, '535') !== false || 
                strpos($error_msg, 'Authentication') !== false || 
                strpos($error_msg, 'authenticate') !== false ||
                strpos($error_msg, 'Username and Password not accepted') !== false) {
                
                // 检查是否是163/QQ/126邮箱
                $is_china_mail = (strpos($this->settings['smtp_host'], '163.com') !== false || 
                                 strpos($this->settings['smtp_host'], 'qq.com') !== false || 
                                 strpos($this->settings['smtp_host'], '126.com') !== false);
                
                if ($is_china_mail) {
                    return array(
                        'success' => false, 
                        'message' => __('SMTP认证失败！', 'time-capsule-email') . "\n\n" .
                                   __('您使用的是163/QQ/126邮箱，请确认：', 'time-capsule-email') . "\n" .
                                   __('1. 已在邮箱设置中开启SMTP服务', 'time-capsule-email') . "\n" .
                                   __('2. 使用的是授权码，不是邮箱登录密码', 'time-capsule-email') . "\n" .
                                   __('3. 用户名是完整邮箱地址（如：user@163.com）', 'time-capsule-email') . "\n" .
                                   __('4. 授权码复制时没有多余空格', 'time-capsule-email') . "\n\n" .
                                   __('当前配置：', 'time-capsule-email') . "\n" .
                                   'SMTP主机: ' . $this->settings['smtp_host'] . "\n" .
                                   'SMTP端口: ' . $this->settings['smtp_port'] . "\n" .
                                   'SMTP用户名: ' . $this->settings['smtp_username'] . "\n" .
                                   'SMTP加密: ' . strtoupper($this->settings['smtp_encryption'])
                    );
                } else {
                    return array(
                        'success' => false, 
                        'message' => __('SMTP认证失败！请检查用户名和密码是否正确。', 'time-capsule-email') . "\n\n" .
                                   __('错误详情: ', 'time-capsule-email') . $error_msg
                    );
                }
                
            } elseif (strpos($error_msg, 'connect') !== false || 
                     strpos($error_msg, 'Connection') !== false ||
                     strpos($error_msg, 'timed out') !== false) {
                return array(
                    'success' => false, 
                    'message' => __('无法连接到SMTP服务器！', 'time-capsule-email') . "\n\n" .
                               __('可能的原因：', 'time-capsule-email') . "\n" .
                               __('1. SMTP主机地址错误', 'time-capsule-email') . "\n" .
                               __('2. 端口号错误（465用SSL，587用TLS）', 'time-capsule-email') . "\n" .
                               __('3. 服务器防火墙阻止了SMTP端口', 'time-capsule-email') . "\n" .
                               __('4. 网络连接问题', 'time-capsule-email') . "\n\n" .
                               __('错误详情: ', 'time-capsule-email') . $error_msg
                );
            } else {
                return array(
                    'success' => false, 
                    'message' => __('SMTP测试失败: ', 'time-capsule-email') . $error_msg . "\n\n" .
                               __('请检查所有配置是否正确，或查看服务器错误日志获取更多信息。', 'time-capsule-email')
                );
            }
        }
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
