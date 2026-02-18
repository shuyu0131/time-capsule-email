<?php
if (!defined('ABSPATH')) {
    exit;
}

class TCE_Email {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('tce_daily_event', array($this, 'process_scheduled_emails'));
    }
    
    public function save_email($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tce_emails';
        
        $current_user = wp_get_current_user();
        
        // 准备插入数据
        $insert_data = array(
            'user_id' => $current_user->ID,
            'email_to' => sanitize_email($data['email_to']),
            'subject' => sanitize_text_field($data['subject']),
            'message' => $data['message'],
            'send_date' => $data['send_date'],
            'is_sent' => 0,
            'created_at' => current_time('mysql')
        );
        
        $insert_format = array('%d', '%s', '%s', '%s', '%s', '%d', '%s');
        
        // 如果提供了验证相关字段，添加它们
        if (isset($data['is_verified'])) {
            $insert_data['is_verified'] = $data['is_verified'];
            $insert_format[] = '%d';
        }
        
        if (isset($data['verification_token'])) {
            $insert_data['verification_token'] = $data['verification_token'];
            $insert_format[] = '%s';
        }
        
        // 如果提供了公开信字段，添加它
        if (isset($data['is_public'])) {
            $insert_data['is_public'] = $data['is_public'];
            $insert_format[] = '%d';
        }
        
        $result = $wpdb->insert(
            $table_name,
            $insert_data,
            $insert_format
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public function send_email($email_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tce_emails';
        
        $email = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND is_sent = 0",
            $email_id
        ));
        
        if (!$email) {
            return false;
        }
        
        $to = $email->email_to;
        $subject = $email->subject;
        $original_message = $email->message;
        
        // 生成邮件模板
        $message = $this->get_email_template($original_message, $subject);
        
        // 获取邮件设置
        $email_settings = get_option('tce_email_settings', array());
        
        // 检查是否启用SMTP
        if (!empty($email_settings['smtp_enabled'])) {
            // 确保SMTP类已加载
            if (!class_exists('TCE_SMTP_Mailer')) {
                require_once TCE_PLUGIN_PATH . 'includes/class-tce-smtp.php';
            }
            
            // 使用SMTP发送
            $smtp_mailer = new TCE_SMTP_Mailer();
            $sent = $smtp_mailer->send($to, $subject, $message);
        } else {
            // 使用WordPress默认邮件系统
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            // 设置发件人信息
            if (!empty($email_settings['from_name']) && !empty($email_settings['from_email'])) {
                $headers[] = 'From: ' . $email_settings['from_name'] . ' <' . $email_settings['from_email'] . '>';
            }
            
            // 设置回复地址
            if (!empty($email_settings['reply_to'])) {
                $headers[] = 'Reply-To: ' . $email_settings['reply_to'];
            }
            
            $sent = wp_mail($to, $subject, $message, $headers);
        }
        
        if ($sent) {
            $wpdb->update(
                $table_name,
                array(
                    'is_sent' => 1,
                    'sent_at' => current_time('mysql')
                ),
                array('id' => $email_id),
                array('%d', '%s'),
                array('%d')
            );
            return true;
        }
        
        return false;
    }

    public function get_email_template($message, $title = '') {
        $blog_name = get_bloginfo('name');
        $site_url = home_url('/');
        
        // 获取模板设置
        $template_settings = get_option('tce_template_settings', array());
        $custom_template = !empty($template_settings['email_template']) ? $template_settings['email_template'] : '';
        
        // 为了向后兼容，检查是否有旧的分离配置
        if (empty($custom_template) || strlen(trim($custom_template)) < 100) {
            $old_template = !empty($template_settings['template_html']) ? $template_settings['template_html'] : '';
            $old_styles = !empty($template_settings['template_styles']) ? $template_settings['template_styles'] : '';
            
            if (!empty($old_template) && strlen($old_template) > 100) {
                $custom_template = $old_template;
                // 如果有自定义样式，插入到模板中
                if (!empty($old_styles)) {
                    $custom_template = str_replace('/* 自定义样式会被插入到这里 */', $old_styles, $custom_template);
                }
            } else {
                // 如果旧配置也不存在或为空，清空自定义模板
                $custom_template = '';
            }
        }
        
        // 获取格式化后的内容
        $formatted_content = $this->format_email_content($message);
        
        // 获取HTML模板
        $template = !empty($custom_template) ? $custom_template : $this->get_default_email_template();
        
        // 替换模板变量
        $replacements = array(
            '{{title}}' => esc_html($title ?: $blog_name),
            '{{content}}' => $formatted_content,
            '{{site_name}}' => esc_html($blog_name),
            '{{site_url}}' => esc_url($site_url),
            '{{logo}}'=>'',
        );
        
        foreach ($replacements as $placeholder => $value) {
            $template = str_replace($placeholder, $value, $template);
        }
        
        return $template;
    }
    
    /**
     * 获取默认邮件模板
     */
    private function get_default_email_template() {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{title}}</title>
            <style>
    /* 自定义样式会被插入到这里 */
    body {
        font-family: "Microsoft YaHei", "Segoe UI", Arial, sans-serif;
        line-height: 1.6;
        color: #333;
        background-color: #f9f9f9;
        margin: 0;
        padding: 0;
    }
    .email-container {
        max-width: 650px;
        margin: 20px auto;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 5px 25px rgba(0,0,0,0.15);
    }
    .email-title {
        position: relative;
        margin: 0;
        box-sizing: border-box;
        padding: 14px 52px 14px 20px;
        line-height: 1.6;
        font-size: 16px;
        font-weight: normal;
        background: linear-gradient(135deg, #98d6cf, #fde0f7);
        color: #fff;
    }
    .email-text {
        padding: 20px 28px;
        background: #fff;
    }
    .email-footer {
        padding: 10px 20px;
        border-top: 1px solid #eee;
        background: #f8f9fa;
        font-size: 13px;
        color: #999;
    }
    img {
        max-width: 100%;
        height: auto;
        border-radius: 4px;
        margin: 10px 0;
    }
    /* Quill 编辑器样式支持 - 只提供默认样式，不覆盖内联样式 */
    /* 注意：这些样式只在元素没有内联样式时生效 */
    pre {
        font-family: "Courier New", Courier, monospace;
        overflow-x: auto;
    }
    code {
        font-family: "Courier New", Courier, monospace;
    }
    .ql-indent-1 { padding-left: 3em; }
    .ql-indent-2 { padding-left: 6em; }
    .ql-indent-3 { padding-left: 9em; }
    .ql-indent-4 { padding-left: 12em; }
    .ql-indent-5 { padding-left: 15em; }
    .ql-indent-6 { padding-left: 18em; }
    .ql-indent-7 { padding-left: 21em; }
    .ql-indent-8 { padding-left: 24em; }
            @media screen and (max-width: 400px) {
        .email-container {
                    width: 96% !important;
                }
                .email-title {
                    padding: 12px 14px !important;
                    font-size: 15px !important;
                }
                .email-text {
                    padding: 20px 20px 0 !important;
                }
                .email-footer {
                    padding: 10px !important;
                }
            }
            </style>
        </head>
<body>
        <div class="email-page" style="background:#fff; min-height: 100vh; padding: 20px 0;">
    <div class="email-container">
        <div>
            <h1 class="email-title">
                {{title}}
            </h1>
            <div class="email-text">
                {{content}}
                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; color: #999; font-size: 13px;">
                    这封邮件来自 <a href="{{site_url}}" style="color:#50bfff;text-decoration:none;">{{site_name}}</a>
                </div>
            </div>
            <div class="email-footer">
                <p style="margin:0;padding:0;line-height:24px;">* 注意：此邮件由 <a href="{{site_url}}" target="_blank" style="color:#50bfff;text-decoration:none;">{{site_name}}</a> 自动发送，请勿直接回复。</p>
            </div>
                </div>
            </div>
        </div>
        </body>
        </html>';
    }
    
    private function format_email_content($message) {
        // 检查内容是否为空
        if (empty($message)) {
            return '<p>此邮件没有内容</p>';
        }
        
        // 处理图片标签，添加响应式样式
        $message = preg_replace_callback('/<img([^>]*)>/i', function($matches) {
            $img_tag = $matches[0];
            
            // 如果没有 max-width 样式，添加响应式样式
            if (strpos($img_tag, 'max-width') === false && strpos($img_tag, 'style=') !== false) {
                // 已有 style 属性，追加样式
                $img_tag = preg_replace('/style=["\']([^"\']*)["\']/', 'style="$1 max-width: 100%; height: auto;"', $img_tag);
            } elseif (strpos($img_tag, 'style=') === false) {
                // 没有 style 属性，添加新的
                $img_tag = str_replace('<img', '<img style="max-width: 100%; height: auto; display: block; margin: 10px 0;"', $img_tag);
            }
            
            return $img_tag;
        }, $message);
        
        // Quill 编辑器生成的 HTML 需要保留所有样式和属性
        $allowed_tags = array(
            'img' => array(
                'src' => array(),
                'alt' => array(),
                'style' => array(),
                'width' => array(),
                'height' => array(),
                'class' => array(),
                'data-*' => array()
            ),
            'p' => array('style' => array(), 'class' => array()),
            'br' => array(),
            'strong' => array('style' => array()),
            'em' => array('style' => array()),
            'u' => array('style' => array()),
            's' => array('style' => array()), // 删除线
            'ul' => array('style' => array(), 'class' => array()),
            'ol' => array('style' => array(), 'class' => array()),
            'li' => array('style' => array(), 'class' => array(), 'data-list' => array()),
            'a' => array('href' => array(), 'style' => array(), 'target' => array(), 'rel' => array()),
            'h1' => array('style' => array(), 'class' => array()),
            'h2' => array('style' => array(), 'class' => array()),
            'h3' => array('style' => array(), 'class' => array()),
            'h4' => array('style' => array(), 'class' => array()),
            'h5' => array('style' => array(), 'class' => array()),
            'h6' => array('style' => array(), 'class' => array()),
            'div' => array('style' => array(), 'class' => array()),
            'span' => array('style' => array(), 'class' => array()),
            'blockquote' => array('style' => array(), 'class' => array()), // 引用
            'pre' => array('style' => array(), 'class' => array()), // 代码块
            'code' => array('style' => array(), 'class' => array()), // 行内代码
            'table' => array('style' => array(), 'width' => array(), 'border' => array(), 'cellpadding' => array(), 'cellspacing' => array()),
            'tr' => array('style' => array()),
            'td' => array('style' => array(), 'width' => array()),
            'th' => array('style' => array(), 'width' => array()),
            'tbody' => array(),
            'thead' => array(),
        );
        
        // 使用 wp_kses 过滤，但保留更多属性
        $content = wp_kses($message, $allowed_tags);
        
        // 检查内容是否被过滤掉
        if (empty($content)) {
            // 如果过滤后为空，尝试使用原始内容
            $content = $message;
        }
        
        if (strpos($content, '<p') === false && strpos($content, '<div') === false) {
            $content = '<p>' . nl2br($content) . '</p>';
        } else {
            // 已经有HTML标签，只转换剩余的换行符
            $content = nl2br($content);
        }
        
        // 为没有内联样式的元素添加默认样式（确保邮件客户端兼容性）
        $content = preg_replace_callback('/<(blockquote|pre|code)([^>]*)>/i', function($matches) {
            $tag = $matches[1];
            $attrs = $matches[2];
            
            // 如果已经有 style 属性，不添加
            if (strpos($attrs, 'style=') !== false) {
                return $matches[0];
            }
            
            // 添加默认内联样式
            $default_styles = array(
                'blockquote' => 'border-left: 4px solid #ccc; padding-left: 20px; margin: 10px 0; color: #666; background: #f9f9f9;',
                'pre' => 'background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; font-family: "Courier New", Courier, monospace; font-size: 14px; line-height: 1.5;',
                'code' => 'background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: "Courier New", Courier, monospace; font-size: 14px;'
            );
            
            if (isset($default_styles[$tag])) {
                return '<' . $tag . ' style="' . $default_styles[$tag] . '"' . $attrs . '>';
            }
            
            return $matches[0];
        }, $content);
        
        // 转换 rgb() 颜色为十六进制格式（提高邮件客户端兼容性）
        $content = preg_replace_callback('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i', function($matches) {
            $r = intval($matches[1]);
            $g = intval($matches[2]);
            $b = intval($matches[3]);
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }, $content);
        
        // 转换 rgba() 颜色为十六进制格式（忽略透明度，因为邮件客户端支持差）
        $content = preg_replace_callback('/rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*[\d.]+\s*\)/i', function($matches) {
            $r = intval($matches[1]);
            $g = intval($matches[2]);
            $b = intval($matches[3]);
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }, $content);
        
        return $content;
    }
    
    /**
     * 上传 base64 图片到 WordPress 媒体库
     */
    /**
     */
    private function upload_base64_image($base64_data, $image_type) {
        try {
            $image_data = base64_decode($base64_data, true);
            
            if ($image_data === false || empty($image_data)) {
                return false;
            }
            
            $image_size = strlen($image_data);
            $max_size = 10 * 1024 * 1024;
            if ($image_size > $max_size) {
                return false;
            }
            
            $image_type = strtolower($image_type);
            $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp');
            if (!in_array($image_type, $allowed_types)) {
                return false;
            }
            
            $filename = 'tce-image-' . time() . '-' . wp_generate_password(8, false) . '.' . $image_type;
            $upload_dir = wp_upload_dir();
            
            if (!empty($upload_dir['error'])) {
                return false;
            }
            
            $upload_path = $upload_dir['path'] . '/' . $filename;
            $upload_url = $upload_dir['url'] . '/' . $filename;
            
            if (!is_writable($upload_dir['path'])) {
                return false;
            }
            
            $saved = @file_put_contents($upload_path, $image_data);
            if ($saved === false) {
                return false;
            }
            
            $filetype = wp_check_filetype($filename, null);
            if (!$filetype['type']) {
                @unlink($upload_path);
                return false;
            }
            
            $attachment = array(
                'post_mime_type' => $filetype['type'],
                'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attach_id = wp_insert_attachment($attachment, $upload_path);
            
            if (is_wp_error($attach_id) || !$attach_id) {
                @unlink($upload_path);
                return false;
            }
            
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $upload_path);
            wp_update_attachment_metadata($attach_id, $attach_data);
            
            return $upload_url;
            
        } catch (Exception $e) {
            return false;
        }
    }

    public function get_emails($user_id, $type = 'inbox') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tce_emails';
        
        try {
            
            if (!is_numeric($user_id) || $user_id <= 0) {
                return new WP_Error('invalid_user', __('Invalid user ID', 'time-capsule-email'));
            }
            
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d",
                $user_id
            );
            
            if ($type === 'sent') {
                $query .= $wpdb->prepare(" AND is_sent = %d", 1);
            } else {
                $query .= $wpdb->prepare(" AND is_sent = %d", 0);
            }
            
            $query .= " ORDER BY send_date DESC";
            
            $results = $wpdb->get_results($query);
            
            if ($wpdb->last_error) {
                return new WP_Error('db_error', __('Database error', 'time-capsule-email'));
            }
            
            return $results ?: array();
            
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }
    
    public function get_email($email_id, $user_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tce_emails';
        
        $query = "SELECT * FROM $table_name WHERE id = %d";
        $params = array($email_id);
        
        if ($user_id !== null) {
            $query .= " AND user_id = %d";
            $params[] = $user_id;
        }
        
        $query = $wpdb->prepare($query, $params);
        return $wpdb->get_row($query);
    }

    public function get_user_emails($user_id, $sent = false) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tce_emails';
        
        $is_admin = current_user_can('manage_options');
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE is_sent = %d",
            $sent ? 1 : 0
        );

        if (!$is_admin) {
            $query .= $wpdb->prepare(" AND user_id = %d", $user_id);
        }
        
        $query .= " ORDER BY " . ($sent ? 'sent_at DESC, send_date DESC' : 'send_date DESC');
        
        return $wpdb->get_results($query);
    }
    
    public function process_scheduled_emails() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tce_emails';
        
        $current_time = current_time('mysql');
        
        // 只发送已验证的邮件（is_verified = 1）
        $emails = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE is_sent = 0 AND is_verified = 1 AND send_date <= %s",
                $current_time
            )
        );
        
        if (!empty($emails)) {
            foreach ($emails as $email) {
                $this->send_email($email->id);
                
                // 添加短暂延迟，避免服务器压力过大
                if (count($emails) > 1) {
                    sleep(2);
                }
            }
        }
    }
}

TCE_Email::get_instance();
