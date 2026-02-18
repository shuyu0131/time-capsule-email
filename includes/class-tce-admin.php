<?php
if (!defined('ABSPATH')) {
    exit;
}

class TCE_Admin {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX处理
        add_action('wp_ajax_tce_test_email', array($this, 'ajax_test_email'));
        add_action('wp_ajax_tce_test_smtp_connection', array($this, 'ajax_test_smtp_connection'));
        add_action('wp_ajax_tce_get_default_template', array($this, 'ajax_get_default_template'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('时光邮局设置', 'time-capsule-email'),
            __('时光邮局', 'time-capsule-email'),
            'manage_options',
            'time-capsule-email',
            array($this, 'admin_page')
        );
    }
    
    public function register_settings() {
        // 注册设置组
        register_setting('tce_settings', 'tce_email_settings');
        register_setting('tce_settings', 'tce_template_settings');
        
        // 邮箱设置部分
        add_settings_section(
            'tce_email_section',
            __('邮箱配置', 'time-capsule-email'),
            array($this, 'email_section_callback'),
            'time-capsule-email'
        );
        
        // 邮箱设置字段
        add_settings_field(
            'from_name',
            __('发件人姓名', 'time-capsule-email'),
            array($this, 'from_name_callback'),
            'time-capsule-email',
            'tce_email_section'
        );
        
        add_settings_field(
            'from_email',
            __('发件人邮箱', 'time-capsule-email'),
            array($this, 'from_email_callback'),
            'time-capsule-email',
            'tce_email_section'
        );
        
        add_settings_field(
            'reply_to',
            __('回复邮箱', 'time-capsule-email'),
            array($this, 'reply_to_callback'),
            'time-capsule-email',
            'tce_email_section'
        );
        
        add_settings_field(
            'email_verification',
            __('启用邮箱验证', 'time-capsule-email'),
            array($this, 'email_verification_callback'),
            'time-capsule-email',
            'tce_email_section'
        );
        
        add_settings_field(
            'enable_public_letters',
            __('启用公开信功能', 'time-capsule-email'),
            array($this, 'enable_public_letters_callback'),
            'time-capsule-email',
            'tce_email_section'
        );
        
        // SEO设置部分
        add_settings_section(
            'tce_seo_section',
            __('SEO设置', 'time-capsule-email'),
            array($this, 'seo_section_callback'),
            'time-capsule-email'
        );
        
        add_settings_field(
            'seo_title',
            __('页面标题', 'time-capsule-email'),
            array($this, 'seo_title_callback'),
            'time-capsule-email',
            'tce_seo_section'
        );
        
        add_settings_field(
            'seo_description',
            __('页面描述', 'time-capsule-email'),
            array($this, 'seo_description_callback'),
            'time-capsule-email',
            'tce_seo_section'
        );
        
        add_settings_field(
            'seo_keywords',
            __('页面关键词', 'time-capsule-email'),
            array($this, 'seo_keywords_callback'),
            'time-capsule-email',
            'tce_seo_section'
        );
        
        add_settings_field(
            'seo_og_image',
            __('分享图片', 'time-capsule-email'),
            array($this, 'seo_og_image_callback'),
            'time-capsule-email',
            'tce_seo_section'
        );
        
        add_settings_field(
            'smtp_enabled',
            __('启用SMTP', 'time-capsule-email'),
            array($this, 'smtp_enabled_callback'),
            'time-capsule-email',
            'tce_email_section'
        );
        
        add_settings_field(
            'smtp_host',
            __('SMTP主机', 'time-capsule-email'),
            array($this, 'smtp_host_callback'),
            'time-capsule-email',
            'tce_email_section'
        );
        
        add_settings_field(
            'smtp_port',
            __('SMTP端口', 'time-capsule-email'),
            array($this, 'smtp_port_callback'),
            'time-capsule-email',
            'tce_email_section'
        );
        
        add_settings_field(
            'smtp_username',
            __('SMTP用户名', 'time-capsule-email'),
            array($this, 'smtp_username_callback'),
            'time-capsule-email',
            'tce_email_section'
        );
        
        add_settings_field(
            'smtp_password',
            __('SMTP密码', 'time-capsule-email'),
            array($this, 'smtp_password_callback'),
            'time-capsule-email',
            'tce_email_section'
        );
        
        add_settings_field(
            'smtp_encryption',
            __('SMTP加密', 'time-capsule-email'),
            array($this, 'smtp_encryption_callback'),
            'time-capsule-email',
            'tce_email_section'
        );
        
        add_settings_field(
            'test_email',
            __('测试邮箱', 'time-capsule-email'),
            array($this, 'test_email_callback'),
            'time-capsule-email',
            'tce_email_section'
        );
        
        // 模板设置部分
        add_settings_section(
            'tce_template_section',
            __('邮件模板设置', 'time-capsule-email'),
            array($this, 'template_section_callback'),
            'time-capsule-email'
        );
        
        add_settings_field(
            'email_template',
            __('邮件模板', 'time-capsule-email'),
            array($this, 'email_template_callback'),
            'time-capsule-email',
            'tce_template_section'
        );
        
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_time-capsule-email') {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        wp_enqueue_script('tce-admin', TCE_PLUGIN_URL . 'assets/js/tce-admin.js', array('jquery'), TCE_VERSION, true);
        wp_enqueue_style('tce-admin', TCE_PLUGIN_URL . 'assets/css/tce-admin.css', array(), TCE_VERSION);
        
        wp_localize_script('tce-admin', 'tce_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tce_admin_nonce'),
            'i18n' => array(
                'select_image' => __('选择图片', 'time-capsule-email'),
                'remove_image' => __('移除图片', 'time-capsule-email'),
                'test_email_success' => __('测试邮件发送成功！', 'time-capsule-email'),
                'test_email_error' => __('测试邮件发送失败！', 'time-capsule-email'),
                'saving' => __('保存中...', 'time-capsule-email'),
                'saved' => __('设置已保存！', 'time-capsule-email')
            )
        ));
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('时光邮局设置', 'time-capsule-email'); ?></h1>
            
            <div class="tce-admin-container">
                <div class="tce-admin-main">
                    <form method="post" action="options.php" id="tce-settings-form">
                        <?php
                        settings_fields('tce_settings');
                        ?>
                        
                        <table class="form-table">
                            <tbody>
                                <!-- 基本邮箱设置 -->
                                <tr>
                                    <th scope="row">
                                        <label for="tce_from_name"><?php _e('发件人姓名', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->from_name_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="tce_from_email"><?php _e('发件人邮箱', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->from_email_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="tce_reply_to"><?php _e('回复邮箱', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->reply_to_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="tce_email_verification"><?php _e('启用邮箱验证', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->email_verification_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="tce_enable_public_letters"><?php _e('启用公开信功能', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->enable_public_letters_callback(); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <h2><?php _e('SEO设置', 'time-capsule-email'); ?></h2>
                        <p><?php _e('配置前台页面的SEO信息，提升搜索引擎优化效果', 'time-capsule-email'); ?></p>
                        
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="tce_seo_title"><?php _e('页面标题', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->seo_title_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="tce_seo_description"><?php _e('页面描述', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->seo_description_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="tce_seo_keywords"><?php _e('页面关键词', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->seo_keywords_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="tce_seo_og_image"><?php _e('分享图片', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->seo_og_image_callback(); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <h2><?php _e('SMTP设置', 'time-capsule-email'); ?></h2>
                        <p><?php _e('配置SMTP服务器信息，使用专业邮件服务发送', 'time-capsule-email'); ?></p>
                        
                        <table class="form-table">
                            <tbody>
                                <!-- SMTP设置 -->
                                <tr>
                                    <th scope="row">
                                        <label for="tce_smtp_enabled"><?php _e('启用SMTP', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->smtp_enabled_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr class="tce-smtp-field">
                                    <th scope="row">
                                        <label for="tce_smtp_host"><?php _e('SMTP主机', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->smtp_host_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr class="tce-smtp-field">
                                    <th scope="row">
                                        <label for="tce_smtp_port"><?php _e('SMTP端口', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->smtp_port_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr class="tce-smtp-field">
                                    <th scope="row">
                                        <label for="tce_smtp_username"><?php _e('SMTP用户名', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->smtp_username_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr class="tce-smtp-field">
                                    <th scope="row">
                                        <label for="tce_smtp_password"><?php _e('SMTP密码', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->smtp_password_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr class="tce-smtp-field">
                                    <th scope="row">
                                        <label for="tce_smtp_encryption"><?php _e('SMTP加密', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->smtp_encryption_callback(); ?>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="tce_test_email"><?php _e('测试邮箱', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->test_email_callback(); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <h2><?php _e('邮件模板设置', 'time-capsule-email'); ?></h2>
                        <p><?php _e('自定义邮件模板样式和内容', 'time-capsule-email'); ?></p>
                        
                        <table class="form-table">
                            <tbody>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="tce_email_template"><?php _e('邮件模板', 'time-capsule-email'); ?></label>
                                    </th>
                                    <td>
                                        <?php $this->email_template_callback(); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="tce-admin-actions">
                            <?php submit_button(__('保存设置', 'time-capsule-email'), 'primary', 'submit', false); ?>
                            <button type="button" id="tce-test-email" class="button button-secondary">
                                <?php _e('发送测试邮件', 'time-capsule-email'); ?>
                            </button>
                            <button type="button" id="tce-test-smtp" class="button button-secondary">
                                <?php _e('测试SMTP连接', 'time-capsule-email'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="tce-admin-sidebar">
                    <div class="tce-admin-widget">
                        <h3><?php _e('使用说明', 'time-capsule-email'); ?></h3>
                        <div class="tce-admin-content">
                            <p><?php _e('1. 配置发件人信息，确保邮件能正常发送', 'time-capsule-email'); ?></p>
                            <p><?php _e('2. 如需使用SMTP，请填写SMTP服务器信息', 'time-capsule-email'); ?></p>
                            <p><?php _e('3. 自定义邮件模板，让邮件更有个性', 'time-capsule-email'); ?></p>
                            <p><?php _e('4. 点击"发送测试邮件"验证配置是否正确', 'time-capsule-email'); ?></p>
                        </div>
                    </div>
                    
                    <div class="tce-admin-widget">
                        <h3><?php _e('统计信息', 'time-capsule-email'); ?></h3>
                        <div class="tce-admin-content">
                            <?php $this->display_stats(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    // 设置部分回调函数
    public function email_section_callback() {
        echo '<p>' . __('配置邮件发送相关设置', 'time-capsule-email') . '</p>';
    }
    
    public function template_section_callback() {
        echo '<p>' . __('自定义邮件模板样式和内容', 'time-capsule-email') . '</p>';
    }
    
    // 邮箱设置字段回调函数
    public function from_name_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['from_name']) ? $options['from_name'] : get_bloginfo('name');
        echo '<input type="text" name="tce_email_settings[from_name]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('邮件发件人显示的名称', 'time-capsule-email') . '</p>';
    }
    
    public function from_email_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['from_email']) ? $options['from_email'] : get_option('admin_email');
        echo '<input type="email" name="tce_email_settings[from_email]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('邮件发件人邮箱地址', 'time-capsule-email') . '</p>';
    }
    
    public function reply_to_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['reply_to']) ? $options['reply_to'] : '';
        echo '<input type="email" name="tce_email_settings[reply_to]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('回复邮件的接收地址（可选）', 'time-capsule-email') . '</p>';
    }
    
    public function email_verification_callback() {
        $options = get_option('tce_email_settings');
        $checked = isset($options['email_verification']) ? $options['email_verification'] : 0;
        echo '<input type="checkbox" name="tce_email_settings[email_verification]" value="1" ' . checked(1, $checked, false) . ' />';
        echo '<label>' . __('启用邮箱验证功能', 'time-capsule-email') . '</label>';
        echo '<p class="description">' . __('启用后，用户写信后需要先验证邮箱，验证成功后时光邮件才会在指定日期发送', 'time-capsule-email') . '</p>';
    }
    
    public function enable_public_letters_callback() {
        $options = get_option('tce_email_settings');
        $checked = isset($options['enable_public_letters']) ? $options['enable_public_letters'] : 0;
        echo '<input type="checkbox" name="tce_email_settings[enable_public_letters]" value="1" ' . checked(1, $checked, false) . ' />';
        echo '<label>' . __('允许用户将信件设为公开', 'time-capsule-email') . '</label>';
        echo '<p class="description">' . __('启用后，用户在写信时可以选择将信件设为公开，其他人可以在公开信广场浏览这些信件', 'time-capsule-email') . '</p>';
    }
    
    // SEO设置部分回调
    public function seo_section_callback() {
        echo '<p>' . __('配置前台页面的SEO信息，提升搜索引擎优化效果', 'time-capsule-email') . '</p>';
    }
    
    public function seo_title_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['seo_title']) ? $options['seo_title'] : '';
        $default = get_bloginfo('name') . ' - ' . __('时光邮局', 'time-capsule-email');
        echo '<input type="text" name="tce_email_settings[seo_title]" value="' . esc_attr($value) . '" class="large-text" placeholder="' . esc_attr($default) . '" />';
        echo '<p class="description">' . __('页面标题，显示在浏览器标签和搜索结果中（留空使用默认）', 'time-capsule-email') . '</p>';
    }
    
    public function seo_description_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['seo_description']) ? $options['seo_description'] : '';
        $default = __('给未来写封信，记录此刻的心情。无论是一年后还是五年后，这封信都会准时送达。', 'time-capsule-email');
        echo '<textarea name="tce_email_settings[seo_description]" class="large-text" rows="3" placeholder="' . esc_attr($default) . '">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('页面描述，显示在搜索结果中，建议120-160字符（留空使用默认）', 'time-capsule-email') . '</p>';
    }
    
    public function seo_keywords_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['seo_keywords']) ? $options['seo_keywords'] : '';
        $default = __('时光邮局,时光邮件,给未来写信,延时邮件,定时邮件', 'time-capsule-email');
        echo '<input type="text" name="tce_email_settings[seo_keywords]" value="' . esc_attr($value) . '" class="large-text" placeholder="' . esc_attr($default) . '" />';
        echo '<p class="description">' . __('页面关键词，用逗号分隔（留空使用默认）', 'time-capsule-email') . '</p>';
    }
    
    public function seo_og_image_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['seo_og_image']) ? $options['seo_og_image'] : '';
        $default_image = TCE_PLUGIN_URL . 'assets/images/banner.jpeg';
        
        echo '<div class="tce-image-upload">';
        echo '<input type="hidden" name="tce_email_settings[seo_og_image]" id="tce-og-image" value="' . esc_attr($value) . '" />';
        echo '<div class="tce-image-preview">';
        if (!empty($value)) {
            echo '<img src="' . esc_url($value) . '" style="max-width: 300px; height: auto;" />';
        } else {
            echo '<img src="' . esc_url($default_image) . '" style="max-width: 300px; height: auto; opacity: 0.5;" />';
        }
        echo '</div>';
        echo '<p>';
        echo '<button type="button" class="button tce-upload-image" data-target="tce-og-image">' . __('选择图片', 'time-capsule-email') . '</button> ';
        echo '<button type="button" class="button tce-remove-image" data-target="tce-og-image">' . __('移除图片', 'time-capsule-email') . '</button>';
        echo '</p>';
        echo '<p class="description">' . __('社交媒体分享时显示的图片，建议尺寸 1200x630 像素（留空使用默认）', 'time-capsule-email') . '</p>';
        echo '</div>';
    }
    
    public function smtp_enabled_callback() {
        $options = get_option('tce_email_settings');
        $checked = isset($options['smtp_enabled']) ? $options['smtp_enabled'] : 0;
        echo '<input type="checkbox" name="tce_email_settings[smtp_enabled]" value="1" ' . checked(1, $checked, false) . ' />';
        echo '<label>' . __('启用SMTP发送邮件', 'time-capsule-email') . '</label>';
    }
    
    public function smtp_host_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['smtp_host']) ? $options['smtp_host'] : '';
        echo '<input type="text" name="tce_email_settings[smtp_host]" value="' . esc_attr($value) . '" class="regular-text" placeholder="smtp.example.com" />';
    }
    
    public function smtp_port_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['smtp_port']) ? $options['smtp_port'] : '587';
        echo '<input type="number" name="tce_email_settings[smtp_port]" value="' . esc_attr($value) . '" class="small-text" min="1" max="65535" />';
        echo '<p class="description">' . __('163/QQ/126邮箱推荐使用 465 + SSL（部分环境 587 + TLS 可能无法连接）', 'time-capsule-email') . '</p>';
    }
    
    public function smtp_username_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['smtp_username']) ? $options['smtp_username'] : '';
        echo '<input type="text" name="tce_email_settings[smtp_username]" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    public function smtp_password_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['smtp_password']) ? $options['smtp_password'] : '';
        echo '<input type="password" name="tce_email_settings[smtp_password]" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    public function smtp_encryption_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['smtp_encryption']) ? $options['smtp_encryption'] : 'tls';
        echo '<select name="tce_email_settings[smtp_encryption]">';
        echo '<option value="none"' . selected('none', $value, false) . '>' . __('无', 'time-capsule-email') . '</option>';
        echo '<option value="ssl"' . selected('ssl', $value, false) . '>SSL</option>';
        echo '<option value="tls"' . selected('tls', $value, false) . '>TLS</option>';
        echo '</select>';
    }
    
    public function test_email_callback() {
        $options = get_option('tce_email_settings');
        $value = isset($options['test_email']) ? $options['test_email'] : get_option('admin_email');
        echo '<input type="email" name="tce_email_settings[test_email]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('用于接收测试邮件的邮箱地址，默认为管理员邮箱', 'time-capsule-email') . '</p>';
    }
    
    // 邮件模板字段回调函数
    public function email_template_callback() {
        $options = get_option('tce_template_settings');
        $default_template = $this->get_default_email_template();
        $value = isset($options['email_template']) ? $options['email_template'] : '';
        
        // 为了向后兼容，检查是否有旧的分离配置
        if (empty($value) || strlen(trim($value)) < 100) {
            $old_template = isset($options['template_html']) ? $options['template_html'] : '';
            $old_styles = isset($options['template_styles']) ? $options['template_styles'] : '';
            
            if (!empty($old_template) && strlen($old_template) > 100) {
                $value = $old_template;
                // 如果有自定义样式，插入到模板中
                if (!empty($old_styles)) {
                    $value = str_replace('/* 自定义样式会被插入到这里 */', $old_styles, $value);
                }
            } else {
                // 如果都没有，使用默认模板
                $value = $default_template;
            }
        }
        
        echo '<div class="tce-template-editor">';
        echo '<textarea name="tce_template_settings[email_template]" id="tce-email-template" class="large-text code" rows="20">' . esc_textarea($value) . '</textarea>';
        echo '</div>';
        
        echo '<div class="tce-template-help">';
        echo '<p><strong>' . __('模板变量:', 'time-capsule-email') . '</strong></p>';
        echo '<ul>';
        echo '<li><code>{{title}}</code> - ' . __('邮件标题', 'time-capsule-email') . '</li>';
        echo '<li><code>{{content}}</code> - ' . __('邮件正文内容', 'time-capsule-email') . '</li>';
        echo '<li><code>{{site_name}}</code> - ' . __('网站名称', 'time-capsule-email') . '</li>';
        echo '<li><code>{{site_url}}</code> - ' . __('网站地址', 'time-capsule-email') . '</li>';
        echo '</ul>';
        echo '<p>' . __('完整的HTML邮件模板，包含样式和结构。您可以使用模板变量来动态插入内容。', 'time-capsule-email') . '</p>';
        echo '<button type="button" class="button" id="tce-reset-template">' . __('恢复默认模板', 'time-capsule-email') . '</button>';
        echo '<button type="button" class="button" id="tce-preview-template">' . __('预览模板', 'time-capsule-email') . '</button>';
        echo '</div>';
    }
    
    public function template_styles_callback() {
        // 此方法已废弃，保留用于向后兼容
    }
    
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
    
    
    
    private function display_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tce_emails';
        
        $total_emails = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $sent_emails = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_sent = 1");
        $pending_emails = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_sent = 0");
        
        echo '<ul>';
        echo '<li><strong>' . __('总邮件数', 'time-capsule-email') . ':</strong> ' . $total_emails . '</li>';
        echo '<li><strong>' . __('已发送', 'time-capsule-email') . ':</strong> ' . $sent_emails . '</li>';
        echo '<li><strong>' . __('待发送', 'time-capsule-email') . ':</strong> ' . $pending_emails . '</li>';
        echo '</ul>';
    }
    
    // AJAX处理测试邮件
    public function ajax_test_email() {
        check_ajax_referer('tce_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'time-capsule-email'));
        }
        
        $email_settings = get_option('tce_email_settings');
        $to = isset($email_settings['test_email']) && !empty($email_settings['test_email']) 
              ? $email_settings['test_email'] 
              : get_option('admin_email');
        
        // 检查是否启用SMTP
        if (!empty($email_settings['smtp_enabled'])) {
            // 确保SMTP类已加载
            if (!class_exists('TCE_SMTP_Mailer')) {
                require_once TCE_PLUGIN_PATH . 'includes/class-tce-smtp.php';
            }
            
            // 使用自定义SMTP发送测试邮件
            $smtp_mailer = new TCE_SMTP_Mailer();
            $sent = $smtp_mailer->send_test_email($to);
            
            if ($sent) {
                wp_send_json_success(__('SMTP测试邮件发送成功！', 'time-capsule-email'));
            } else {
                wp_send_json_error(__('SMTP测试邮件发送失败！', 'time-capsule-email'));
            }
        } else {
            // 使用WordPress默认邮件系统
            $subject = __('时光邮局测试邮件', 'time-capsule-email');
            $test_message = __('这是一封测试邮件，如果您收到此邮件，说明邮箱配置正确！', 'time-capsule-email');
            
            // 使用邮件模板
            if (class_exists('TCE_Email')) {
                $email = TCE_Email::get_instance();
                $message = $email->get_email_template($test_message, $subject);
            } else {
                $message = $test_message;
            }
            
            // 设置邮件头
            $headers = array('Content-Type: text/html; charset=UTF-8');
            if (!empty($email_settings['from_name']) && !empty($email_settings['from_email'])) {
                $headers[] = 'From: ' . $email_settings['from_name'] . ' <' . $email_settings['from_email'] . '>';
            }
            if (!empty($email_settings['reply_to'])) {
                $headers[] = 'Reply-To: ' . $email_settings['reply_to'];
            }
            
            $sent = wp_mail($to, $subject, $message, $headers);
            
            if ($sent) {
                wp_send_json_success(__('测试邮件发送成功！', 'time-capsule-email'));
            } else {
                wp_send_json_error(__('测试邮件发送失败！', 'time-capsule-email'));
            }
        }
    }
    
    // AJAX处理SMTP连接测试
    public function ajax_test_smtp_connection() {
        check_ajax_referer('tce_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'time-capsule-email'));
        }
        
        // 确保SMTP类已加载
        if (!class_exists('TCE_SMTP_Mailer')) {
            require_once TCE_PLUGIN_PATH . 'includes/class-tce-smtp.php';
        }
        
        $smtp_mailer = new TCE_SMTP_Mailer();
        $result = $smtp_mailer->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    // AJAX处理获取默认模板
    public function ajax_get_default_template() {
        check_ajax_referer('tce_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('权限不足', 'time-capsule-email'));
        }
        
        // 确保Email类已加载
        if (!class_exists('TCE_Email')) {
            require_once TCE_PLUGIN_PATH . 'includes/class-tce-email.php';
        }
        
        // 获取默认模板
        $email = TCE_Email::get_instance();
        
        // 使用反射获取私有方法
        $reflection = new ReflectionClass('TCE_Email');
        $method = $reflection->getMethod('get_default_email_template');
        $method->setAccessible(true);
        $default_template = $method->invoke($email);
        
        wp_send_json_success($default_template);
    }
}

TCE_Admin::get_instance();
