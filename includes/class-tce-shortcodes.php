<?php
if (!defined('ABSPATH')) {
    exit;
}

class TCE_Shortcodes {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('time_capsule_email', array($this, 'render_time_capsule_form'));
        add_shortcode('time_capsule_public', array($this, 'render_public_letters_page'));
        add_action('wp_ajax_tce_save_email', array($this, 'ajax_save_email'));
        add_action('wp_ajax_tce_get_emails', array($this, 'ajax_get_emails'));
        add_action('wp_ajax_tce_delete_email', array($this, 'ajax_delete_email'));
        add_action('wp_ajax_tce_get_email', array($this, 'ajax_get_email'));
        add_action('wp_ajax_tce_update_email', array($this, 'ajax_update_email'));
        add_action('wp_ajax_tce_get_public_letters', array($this, 'ajax_get_public_letters'));
        add_action('wp_ajax_nopriv_tce_get_public_letters', array($this, 'ajax_get_public_letters'));
        add_action('wp_ajax_tce_get_email', array($this, 'ajax_get_email'));
        add_action('wp_ajax_nopriv_tce_get_email', array($this, 'ajax_get_email'));
        add_action('wp_ajax_nopriv_tce_verify_email', array($this, 'ajax_verify_email'));
        add_action('wp_ajax_tce_verify_email', array($this, 'ajax_verify_email'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_head', array($this, 'add_seo_meta_tags'));
    }
    
    /**
     * 隐藏邮箱地址的部分信息
     * 例如：1659872714@qq.com -> 16********@qq.com
     */
    private function mask_email($email) {
        if (empty($email) || !is_email($email)) {
            return $email;
        }
        
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $local = $parts[0];
        $domain = $parts[1];
        
        $local_length = mb_strlen($local);
        
        // 如果本地部分长度小于等于2，只显示第一个字符
        if ($local_length <= 2) {
            $masked_local = mb_substr($local, 0, 1) . '*';
        } else {
            // 显示前2个字符，其余用*号代替
            $visible_chars = 2;
            $masked_chars = $local_length - $visible_chars;
            $masked_local = mb_substr($local, 0, $visible_chars) . str_repeat('*', min($masked_chars, 8));
        }
        
        return $masked_local . '@' . $domain;
    }
    
    /**
     * 隐藏用户昵称的部分信息
     * 例如：张三丰 -> 张**
     * 例如：JohnSmith -> Jo******
     */
    private function mask_username($username) {
        if (empty($username)) {
            return $username;
        }
        
        $length = mb_strlen($username);
        
        // 如果长度小于等于1，直接返回
        if ($length <= 1) {
            return $username;
        }
        
        // 如果长度为2，显示第一个字符
        if ($length == 2) {
            return mb_substr($username, 0, 1) . '*';
        }
        
        // 长度大于2，显示前2个字符，其余用*代替（最多6个星号）
        $visible_chars = 2;
        $masked_chars = $length - $visible_chars;
        return mb_substr($username, 0, $visible_chars) . str_repeat('*', min($masked_chars, 6));
    }
    
    /**
     * 添加SEO meta标签
     */
    public function add_seo_meta_tags() {
        global $post;
        
        // 只在包含时光邮局shortcode的页面添加SEO标签
        if (!is_singular() || !has_shortcode($post->post_content, 'time_capsule_email') && !has_shortcode($post->post_content, 'time_capsule_public')) {
            return;
        }
        
        $settings = get_option('tce_email_settings', array());
        
        // 页面标题
        $seo_title = !empty($settings['seo_title']) 
            ? $settings['seo_title'] 
            : get_bloginfo('name') . ' - ' . __('时光邮局', 'time-capsule-email');
        
        // 页面描述
        $seo_description = !empty($settings['seo_description']) 
            ? $settings['seo_description'] 
            : __('给未来写封信，记录此刻的心情。无论是一年后还是五年后，这封信都会准时送达。', 'time-capsule-email');
        
        // 页面关键词
        $seo_keywords = !empty($settings['seo_keywords']) 
            ? $settings['seo_keywords'] 
            : __('时光邮局,时光邮件,给未来写信,延时邮件,定时邮件', 'time-capsule-email');
        
        // 分享图片
        $og_image = !empty($settings['seo_og_image']) 
            ? $settings['seo_og_image'] 
            : TCE_PLUGIN_URL . 'assets/images/banner.jpeg';
        
        // 当前页面URL
        $current_url = get_permalink();
        
        // 输出meta标签
        echo "\n<!-- 时光邮局 SEO 开始 -->\n";
        echo '<meta name="description" content="' . esc_attr($seo_description) . '">' . "\n";
        echo '<meta name="keywords" content="' . esc_attr($seo_keywords) . '">' . "\n";
        
        // Open Graph标签（用于Facebook、LinkedIn等）
        echo '<meta property="og:title" content="' . esc_attr($seo_title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($seo_description) . '">' . "\n";
        echo '<meta property="og:image" content="' . esc_url($og_image) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($current_url) . '">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
        
        // Twitter Card标签
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($seo_title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($seo_description) . '">' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url($og_image) . '">' . "\n";
        
        echo "<!-- 时光邮局 SEO 结束 -->\n\n";
    }
    
    public function enqueue_scripts() {
        if (!is_admin()) {
            // 使用协议相对URL，自动适配HTTP和HTTPS
            wp_enqueue_style('font-awesome', '//cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
            
            // 加载landing page样式
            wp_enqueue_style('tce-landing', TCE_PLUGIN_URL . 'assets/css/tce-landing.css', array(), TCE_VERSION);
            
            // 先加载Quill编辑器CSS（使用协议相对URL）
            wp_enqueue_style('quill-css', '//cdn.quilljs.com/1.3.7/quill.snow.css', array(), '1.3.7');
            
            // 最后加载我们的样式系统（依赖Quill CSS，这样可以覆盖部分样式但不影响Quill核心功能）
            wp_enqueue_style('tce-style', TCE_PLUGIN_URL . 'assets/css/tce-style.css', array('font-awesome', 'tce-landing', 'quill-css'), TCE_VERSION);
            
            wp_enqueue_script('jquery');
            
            // 加载Quill编辑器JS（使用协议相对URL）
            wp_enqueue_script('quill-js', '//cdn.quilljs.com/1.3.7/quill.min.js', array(), '1.3.7', true);
            
            wp_enqueue_script('tce-script', TCE_PLUGIN_URL . 'assets/js/tce-script.js', array('jquery', 'quill-js'), TCE_VERSION, true);
            
            wp_localize_script('tce-script', 'tce_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tce_nonce'),
                'i18n' => array(
                    'sending' => __('保存中...', 'time-capsule-email'),
                    'success' => __('时光邮件已保存！', 'time-capsule-email'),
                    'error' => __('发送失败，请稍后重试。', 'time-capsule-email'),
                    'delete_confirm' => __('确定要删除这封邮件吗？此操作不可撤销。', 'time-capsule-email'),
                    'deleting' => __('删除中...', 'time-capsule-email'),
                    'loading' => __('加载中...', 'time-capsule-email')
                )
            ));
        }
    }
    
    public function render_time_capsule_form($atts) {
        if (!is_user_logged_in()) {
            // 获取统计数据
            global $wpdb;
            $table_name = $wpdb->prefix . 'tce_emails';
            $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_sent = 0");
            $sent_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_sent = 1");
            
            // 计算运行天数（从第一封邮件创建日期开始）
            $first_email_date = $wpdb->get_var("SELECT MIN(created_at) FROM $table_name");
            $days_running = 0;
            if ($first_email_date) {
                $start_date = new DateTime($first_email_date);
                $current_date = new DateTime();
                $days_running = $current_date->diff($start_date)->days;
            }
            
            return '<div class="tce-landing-page">
                <!-- 英雄区 -->
                <section class="tce-hero-section">
                    <div class="tce-hero-container">
                        <div class="tce-hero-content">
                            <div class="tce-hero-badge">
                                <span class="tce-badge">时光邮局</span>
                            </div>
                            
                            <h1 class="tce-hero-title">
                                <span class="tce-typewriter" data-texts=\'["给未来写封信", "记录此刻的心情", "给明天的自己"]\' data-speed="150" data-delete-speed="100" data-delay="2000"></span>
                                <span class="tce-cursor">|</span>
                            </h1>
                            <p class="tce-hero-desc">多年以后，愿你不负所期。记录此刻的心情，给未来的自己一份惊喜。无论是一年后还是五年后，这封信都会准时送达。</p>
                            
                            <div class="tce-hero-actions">
                                <a href="' . wp_login_url(get_permalink()) . '" class="tce-btn tce-btn-primary">
                                    <span>开始撰写</span>
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                                <a href="' . esc_url(home_url('/public-letters/')) . '" class="tce-btn tce-btn-outline">
                                    <span>公开信广场</span>
                                    <i class="fas fa-globe"></i>
                                </a>
                            </div>
                            
                            <div class="tce-hero-stats">
                                <div class="tce-stat-item">
                                    <div class="tce-stat-num">' . esc_html($pending_count) . '</div>
                                    <div class="tce-stat-label">封信件待投递</div>
                                </div>
                                <div class="tce-stat-item">
                                    <div class="tce-stat-num">' . esc_html($sent_count) . '</div>
                                    <div class="tce-stat-label">封信件已投递</div>
                                </div>
                                <div class="tce-stat-item">
                                    <div class="tce-stat-num">' . esc_html($days_running) . '</div>
                                    <div class="tce-stat-label">天已持续</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tce-hero-visual">
                            <div class="tce-hero-image-wrapper">
                                <img src="' . TCE_PLUGIN_URL . 'assets/images/banner.jpeg" alt="时光邮局" class="tce-hero-image" data-no-lightbox="true" onclick="return false;">
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- 箴言区 -->
                <section class="tce-quotes-section">
                    <div class="tce-section-container">
                        <h2 class="tce-section-heading">箴言</h2>
                        
                        <div class="tce-quotes-grid">
                            <div class="tce-quote-box">
                                <h2 class="tce-quote-content">"人生中你所经历的一切，都取决于你自己"</h2>
                                <p class="tce-quote-author">－－ 高尔基</p>
                            </div>
                            
                            <div class="tce-quote-box">
                                <h2 class="tce-quote-content">"不积跬步，无以至千里；不积小流，无以成江海"</h2>
                                <p class="tce-quote-author">－－ 荀况</p>
                            </div>
                            
                            <div class="tce-quote-box">
                                <h2 class="tce-quote-content">"未来，在你的世界里，你终会顶天立地"</h2>
                                <p class="tce-quote-author">－－ 拉迪亚德·吉卜林</p>
                            </div>
                            
                            <div class="tce-quote-box">
                                <h2 class="tce-quote-content">"长风破浪会有时，直挂云帆济沧海"</h2>
                                <p class="tce-quote-author">－－ 李白</p>
                            </div>
                            
                            <div class="tce-quote-box">
                                <h2 class="tce-quote-content">"理想是人生的太阳"</h2>
                                <p class="tce-quote-author">－－ 德莱赛</p>
                            </div>
                            
                            <div class="tce-quote-box">
                                <h2 class="tce-quote-content">"一个人要帮助弱者，应当自己成为强者，而不是和他们一样变成弱者"</h2>
                                <p class="tce-quote-author">－－ 罗曼·罗兰</p>
                            </div>
                        </div>
                        

                    </div>
                </section>
                
                <!-- 关于区 -->
                <section class="tce-about-section">
                    <div class="tce-section-container">
                        <h2 class="tce-section-heading">关于</h2>
                        
                        <div class="tce-about-grid">
                            <div class="tce-about-box">
                                <h3 class="tce-about-heading">为什么要给未来写信？</h3>
                                <p class="tce-about-content">从过去获得惊喜，给你的未来一些灵感或安慰的话，或者预测一下你的生活，这个世界，你的家人。以及一年，五年会发生什么。</p>
                            </div>
                            
                            <div class="tce-about-box">
                                <h3 class="tce-about-heading">可以给任何人写信么？</h3>
                                <p class="tce-about-content">为了防止骚扰，营销邮件，每次写信后都会往收件箱发一封验证邮件。点击了验证邮件，未来才会收到写的信。后续会开放更多功能哦。</p>
                            </div>
                        </div>
                    </div>
                </section>
                

            </div>';
        }
        
        // 检查是否启用公开信功能
        $settings = get_option('tce_email_settings', array());
        $enable_public = isset($settings['enable_public_letters']) ? $settings['enable_public_letters'] : 0;
        
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        
        ob_start();
        ?>
        <!-- 完全独立的样式容器 -->
        <div class="tce-isolated-wrapper">
            <div class="tce-container">
                <div class="tce-tabs">
                    <button class="tce-tab active" data-tab="compose">
                        <i class="fas fa-pen"></i>
                        <span><?php _e('写一封信', 'time-capsule-email'); ?></span>
                    </button>
                    <button class="tce-tab" data-tab="inbox">
                        <i class="fas fa-clock"></i>
                        <span><?php _e('待发送', 'time-capsule-email'); ?></span>
                    </button>
                    <button class="tce-tab" data-tab="sent">
                        <i class="fas fa-check-circle"></i>
                        <span><?php _e('已发送', 'time-capsule-email'); ?></span>
                    </button>
                    <?php if ($enable_public) : ?>
                    <button class="tce-tab" data-tab="public">
                        <i class="fas fa-globe"></i>
                        <span><?php _e('公开信广场', 'time-capsule-email'); ?></span>
                    </button>
                    <?php endif; ?>
                </div>
                
                <div id="tce-compose" class="tce-tab-content active">
                    <form id="tce-email-form" class="tce-form">
                        <div class="tce-form-group">
                            <label for="tce-email-to">
                                <i class="fas fa-envelope"></i>
                                <?php _e('收件邮箱', 'time-capsule-email'); ?>
                            </label>
                            <input type="email" id="tce-email-to" name="email_to" value="<?php echo esc_attr($user_email); ?>" placeholder="example@email.com" required>
                        </div>
                        
                        <div class="tce-form-group">
                            <label for="tce-email-subject">
                                <i class="fas fa-heading"></i>
                                <?php _e('邮件主题', 'time-capsule-email'); ?>
                            </label>
                            <input type="text" id="tce-email-subject" name="subject" placeholder="给未来的自己..." required>
                        </div>
                        
                        <div class="tce-form-group">
                            <label for="tce-email-message">
                                <i class="fas fa-edit"></i>
                                <?php _e('邮件内容', 'time-capsule-email'); ?>
                            </label>
                            <div class="tce-editor-wrapper">
                                <iframe id="tce-editor-iframe" frameborder="0"></iframe>
                                <input type="hidden" id="tce-email-message" name="message">
                            </div>
                        </div>
                        
                        <div class="tce-form-group">
                            <label for="tce-send-date">
                                <i class="fas fa-calendar-alt"></i>
                                <?php _e('发送日期', 'time-capsule-email'); ?>
                            </label>
                            <input type="date" id="tce-send-date" name="send_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                            <small class="tce-input-hint"><?php _e('提示：部分浏览器需选择日期后点击外部区域确认', 'time-capsule-email'); ?></small>
                        </div>
                        
                        <?php if ($enable_public) : ?>
                        <div class="tce-form-group">
                            <label class="tce-checkbox-label">
                                <input type="checkbox" id="tce-is-public" name="is_public" value="1">
                                <span>
                                    <i class="fas fa-globe"></i>
                                    <?php _e('设为公开信（其他人可以在公开信广场看到）', 'time-capsule-email'); ?>
                                </span>
                            </label>
                        </div>
                        <?php endif; ?>
                        
                        <div class="tce-form-actions">
                            <button type="submit" class="tce-button tce-button-primary">
                                <i class="fas fa-paper-plane"></i>
                                <span><?php _e('保存时光邮件', 'time-capsule-email'); ?></span>
                            </button>
                        </div>
                        
                        <div id="tce-message" class="tce-message"></div>
                    </form>
                </div>
                
                <div id="tce-inbox" class="tce-tab-content">
                    <?php $this->render_emails_list(false); ?>
                </div>
                
                <div id="tce-sent" class="tce-tab-content">
                    <?php $this->render_emails_list(true); ?>
                </div>
                
                <?php if ($enable_public) : ?>
                <div id="tce-public" class="tce-tab-content">
                    <?php $this->render_public_letters(); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 查看邮件模态框 -->
            <div id="tce-view-modal" class="tce-modal">
                <div class="tce-modal-overlay"></div>
                <div class="tce-modal-content">
                    <div class="tce-modal-header">
                        <h3 class="tce-modal-title"><?php _e('邮件详情', 'time-capsule-email'); ?></h3>
                        <button class="tce-modal-close" aria-label="<?php _e('关闭', 'time-capsule-email'); ?>">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="tce-modal-body">
                        <div class="tce-view-field">
                            <label><?php _e('收件人', 'time-capsule-email'); ?>:</label>
                            <div class="tce-view-value" id="tce-view-to"></div>
                        </div>
                        <div class="tce-view-field">
                            <label><?php _e('主题', 'time-capsule-email'); ?>:</label>
                            <div class="tce-view-value" id="tce-view-subject"></div>
                        </div>
                        <div class="tce-view-field">
                            <label><?php _e('发送日期', 'time-capsule-email'); ?>:</label>
                            <div class="tce-view-value" id="tce-view-date"></div>
                        </div>
                        <div class="tce-view-field">
                            <label><?php _e('内容', 'time-capsule-email'); ?>:</label>
                            <div class="tce-view-content" id="tce-view-message"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 编辑邮件模态框 -->
            <div id="tce-edit-modal" class="tce-modal">
                <div class="tce-modal-overlay"></div>
                <div class="tce-modal-content">
                    <div class="tce-modal-header">
                        <h3 class="tce-modal-title"><?php _e('编辑邮件', 'time-capsule-email'); ?></h3>
                        <button class="tce-modal-close" aria-label="<?php _e('关闭', 'time-capsule-email'); ?>">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="tce-modal-body">
                        <form id="tce-edit-form">
                            <input type="hidden" id="tce-edit-id" name="email_id">
                            
                            <div class="tce-form-group">
                                <label for="tce-edit-to">
                                    <i class="fas fa-envelope"></i>
                                    <?php _e('收件邮箱', 'time-capsule-email'); ?>
                                </label>
                                <input type="email" id="tce-edit-to" name="email_to" required>
                            </div>
                            
                            <div class="tce-form-group">
                                <label for="tce-edit-subject">
                                    <i class="fas fa-heading"></i>
                                    <?php _e('邮件主题', 'time-capsule-email'); ?>
                                </label>
                                <input type="text" id="tce-edit-subject" name="subject" required>
                            </div>
                            
                            <div class="tce-form-group">
                                <label for="tce-edit-message">
                                    <i class="fas fa-edit"></i>
                                    <?php _e('邮件内容', 'time-capsule-email'); ?>
                                </label>
                                <div class="tce-editor-wrapper">
                                    <iframe id="tce-edit-iframe" frameborder="0"></iframe>
                                    <input type="hidden" id="tce-edit-message" name="message">
                                </div>
                            </div>
                            
                            <div class="tce-form-group">
                                <label for="tce-edit-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php _e('发送日期', 'time-capsule-email'); ?>
                                </label>
                                <input type="date" id="tce-edit-date" name="send_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                            </div>
                            
                            <div class="tce-modal-footer">
                                <button type="button" class="tce-button tce-button-secondary tce-modal-cancel">
                                    <?php _e('取消', 'time-capsule-email'); ?>
                                </button>
                                <button type="submit" class="tce-button tce-button-primary">
                                    <i class="fas fa-save"></i>
                                    <span><?php _e('保存修改', 'time-capsule-email'); ?></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_emails_list($sent = false) {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $emails = TCE_Email::get_instance()->get_user_emails($user_id, $sent);
        
        if (empty($emails)) {
            echo '<div class="tce-no-emails">' . 
                 ($sent ? __('没有已发送的邮件。', 'time-capsule-email') : __('没有待发送的邮件。', 'time-capsule-email')) . 
                 '</div>';
            return;
        }
        
        echo '<ul class="tce-email-list">';
        foreach ($emails as $email) {
            $send_date = new DateTime($email->send_date);
            
            echo '<li class="tce-email-item' . ($email->is_sent ? ' sent' : ' pending') . '" data-email-id="' . esc_attr($email->id) . '">';
            echo '<div class="tce-email-header">';
            echo '<h3 class="tce-email-subject">' . esc_html($email->subject) . '</h3>';
            echo '<span class="tce-email-date">' . esc_html($send_date->format('Y-m-d')) . '</span>';
            echo '</div>';
            echo '<div class="tce-email-meta">';
            echo '<span>' . esc_html__('收件人:', 'time-capsule-email') . ' ' . esc_html($this->mask_email($email->email_to)) . '</span>';
            echo '</div>';
            echo '<div class="tce-email-content">';
            echo wp_trim_words(wp_strip_all_tags($email->message), 30, '...');
            echo '</div>';
            echo '<div class="tce-email-footer">';
            echo '<span class="tce-email-status' . ($email->is_sent ? ' sent' : ' pending') . '">';
            if ($email->is_sent) {
                echo '<i class="fas fa-check-circle"></i> ';
                echo esc_html__('已发送', 'time-capsule-email');
            } else {
                echo '<i class="fas fa-clock"></i> ';
                echo esc_html__('等待发送', 'time-capsule-email');
            }
            echo '</span>';
            echo '<div class="tce-email-actions">';
            if ($email->is_sent) {
                // 已发送：显示查看按钮
                echo '<a href="#" class="tce-email-action tce-view-email" ';
                echo 'data-id="' . esc_attr($email->id) . '">';
                echo '<i class="fas fa-eye"></i> ';
                echo esc_html__('查看', 'time-capsule-email');
                echo '</a>';
            } else {
                // 待发送：显示编辑按钮
                echo '<a href="#" class="tce-email-action tce-edit-email" ';
                echo 'data-id="' . esc_attr($email->id) . '">';
                echo '<i class="fas fa-edit"></i> ';
                echo esc_html__('编辑', 'time-capsule-email');
                echo '</a>';
            }
            echo '<a href="#" class="tce-email-action tce-delete-email" ';
            echo 'data-action="delete" ';
            echo 'data-id="' . esc_attr($email->id) . '" ';
            echo 'data-nonce="' . wp_create_nonce('tce_delete_email_' . $email->id) . '">';
            echo '<i class="fas fa-trash"></i> ';
            echo esc_html__('删除', 'time-capsule-email');
            echo '</a>';
            echo '</div>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * 渲染独立的公开信页面
     */
    public function render_public_letters_page($atts) {
        ob_start();
        ?>
        <div class="tce-isolated-wrapper">
            <div class="tce-public-page-header">
                <div class="tce-header-badge">
                    <i class="fas fa-globe"></i>
                    <span class="tce-badge-text"><?php _e('公开信广场', 'time-capsule-email'); ?></span>
                </div>
                <h1 class="tce-public-page-title">
                    <?php _e('见证时光的力量', 'time-capsule-email'); ?>
                </h1>
                <p class="tce-public-page-desc">
                    <?php _e('这里展示了其他用户分享的公开信件，每一封信都承载着对未来的期许与祝福', 'time-capsule-email'); ?>
                </p>
            </div>
            
            <div class="tce-public-page-content">
                <div id="tce-public-letters-list"></div>
            </div>
            
            <!-- 查看邮件模态框 -->
            <div id="tce-view-modal" class="tce-modal">
                <div class="tce-modal-overlay"></div>
                <div class="tce-modal-content">
                    <div class="tce-modal-header">
                        <h3 class="tce-modal-title"><?php _e('邮件详情', 'time-capsule-email'); ?></h3>
                        <button class="tce-modal-close" aria-label="<?php _e('关闭', 'time-capsule-email'); ?>">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="tce-modal-body">
                        <div class="tce-view-field">
                            <label><?php _e('收件人', 'time-capsule-email'); ?>:</label>
                            <div class="tce-view-value" id="tce-view-to"></div>
                        </div>
                        <div class="tce-view-field">
                            <label><?php _e('主题', 'time-capsule-email'); ?>:</label>
                            <div class="tce-view-value" id="tce-view-subject"></div>
                        </div>
                        <div class="tce-view-field">
                            <label><?php _e('发送日期', 'time-capsule-email'); ?>:</label>
                            <div class="tce-view-value" id="tce-view-date"></div>
                        </div>
                        <div class="tce-view-field">
                            <label><?php _e('内容', 'time-capsule-email'); ?>:</label>
                            <div class="tce-view-content" id="tce-view-message"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // 页面加载时自动加载公开信
            loadPublicLettersPage(1);
            
            function loadPublicLettersPage(page) {
                var $container = $('#tce-public-letters-list');
                
                if (page === 1) {
                    $container.html('<div class="tce-loading-container">' +
                        '<div class="tce-loading-spinner"></div>' +
                        '<div class="tce-loading-text">加载中...</div>' +
                    '</div>');
                }
                
                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'tce_get_public_letters',
                        page: page
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            if (page === 1) {
                                $container.html(response.data.html);
                            } else {
                                $('#tce-load-more-public').parent().remove();
                                $container.find('.tce-public-list').append($(response.data.html).find('.tce-public-item'));
                                if (response.data.has_more) {
                                    $container.append($(response.data.html).find('.tce-load-more-container'));
                                }
                            }
                        } else {
                            $container.html('<div class="tce-no-emails">暂时还没有公开信件</div>');
                        }
                    },
                    error: function() {
                        $container.html('<div class="tce-no-emails">加载失败，请稍后重试</div>');
                    }
                });
            }
            
            // 加载更多
            $(document).on('click', '#tce-load-more-public', function(e) {
                e.preventDefault();
                var page = $(this).data('page');
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 加载中...');
                loadPublicLettersPage(page);
            });
            
            // 查看公开信
            $(document).on('click', '.tce-view-public-letter', function(e) {
                e.preventDefault();
                
                var emailId = $(this).data('id');
                
                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'tce_get_email',
                        email_id: emailId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            var email = response.data;
                            $('#tce-view-to').text(email.email_to);
                            $('#tce-view-subject').text(email.subject);
                            $('#tce-view-date').text(email.send_date.split(' ')[0]);
                            $('#tce-view-message').html(email.message);
                            
                            $('#tce-view-modal').addClass('active');
                            $('body').css('overflow', 'hidden');
                        } else {
                            alert(response.data || '加载失败');
                        }
                    },
                    error: function() {
                        alert('加载失败，请稍后重试');
                    }
                });
            });
            
            // 关闭模态框
            $(document).on('click', '.tce-modal-close, .tce-modal-overlay', function(e) {
                e.preventDefault();
                var $modal = $(this).closest('.tce-modal');
                $modal.removeClass('active');
                $('body').css('overflow', '');
            });
            
            // ESC键关闭
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('.tce-modal.active').length > 0) {
                    $('.tce-modal.active').removeClass('active');
                    $('body').css('overflow', '');
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 渲染公开信列表（用于登录用户的选项卡）
     */
    private function render_public_letters() {
        echo '<div class="tce-public-letters-intro">';
        echo '<div class="tce-intro-badge">';
        echo '<i class="fas fa-sparkles"></i>';
        echo '<span>' . __('公开信广场', 'time-capsule-email') . '</span>';
        echo '</div>';
        echo '<p>' . __('这里展示了其他用户分享的公开信件，让我们一起见证时光的力量', 'time-capsule-email') . '</p>';
        echo '</div>';
        echo '<div id="tce-public-letters-list"></div>';
    }
    
    /**
     * 获取公开信列表（AJAX）
     */
    public function ajax_get_public_letters() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tce_emails';
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        // 获取公开的信件（包括已发送和未发送的）
        $emails = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, u.display_name as author_name 
            FROM $table_name e 
            LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID 
            WHERE e.is_public = 1 AND e.is_verified = 1
            ORDER BY e.created_at DESC 
            LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        if (empty($emails)) {
            $message = '<div class="tce-no-emails">' . __('暂时还没有公开信件', 'time-capsule-email') . '</div>';
            wp_send_json_success(array('html' => $message, 'has_more' => false));
            return;
        }
        
        ob_start();
        ?>
        <ul class="tce-email-list tce-public-list">
            <?php foreach ($emails as $email) : 
                $send_date = new DateTime($email->send_date);
                $created_date = new DateTime($email->created_at);
                $author = !empty($email->author_name) ? $this->mask_username($email->author_name) : __('匿名用户', 'time-capsule-email');
            ?>
                <li class="tce-email-item tce-public-item" data-email-id="<?php echo esc_attr($email->id); ?>">
                    <div class="tce-email-header">
                        <h3 class="tce-email-subject"><?php echo esc_html($email->subject); ?></h3>
                    </div>
                    <div class="tce-email-meta">
                        <span class="tce-meta-item tce-meta-author"><i class="fas fa-user"></i> <?php echo esc_html($author); ?></span>
                        <span class="tce-meta-item tce-meta-created"><i class="fas fa-pen"></i> <?php echo esc_html($created_date->format('Y-m-d')); ?></span>
                        <span class="tce-meta-item tce-meta-send"><i class="fas fa-paper-plane"></i> <?php echo esc_html($send_date->format('Y-m-d')); ?></span>
                    </div>
                    <div class="tce-email-content">
                        <?php echo wp_trim_words(wp_strip_all_tags($email->message), 50, '...'); ?>
                    </div>
                    <div class="tce-email-footer">
                        <span class="tce-email-status <?php echo $email->is_sent ? 'sent' : 'pending'; ?>">
                            <?php if ($email->is_sent) : ?>
                                <i class="fas fa-check-circle"></i> 
                                <?php echo esc_html__('已投递', 'time-capsule-email'); ?>
                            <?php else : ?>
                                <i class="fas fa-clock"></i> 
                                <?php echo esc_html__('待投递', 'time-capsule-email'); ?>
                            <?php endif; ?>
                        </span>
                        <span class="tce-email-badge public">
                            <i class="fas fa-globe"></i> 
                            <?php echo esc_html__('公开', 'time-capsule-email'); ?>
                        </span>
                        <a href="#" class="tce-email-action tce-view-public-letter" 
                           data-id="<?php echo esc_attr($email->id); ?>">
                            <i class="fas fa-eye"></i> 
                            <?php esc_html_e('查看', 'time-capsule-email'); ?>
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        
        // 检查是否还有更多
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_public = 1 AND is_verified = 1");
        $has_more = ($offset + $per_page) < $total;
        
        if ($has_more) {
            echo '<div class="tce-load-more-container">';
            echo '<button class="tce-button tce-button-secondary" id="tce-load-more-public" data-page="' . ($page + 1) . '">';
            echo '<i class="fas fa-chevron-down"></i> ';
            echo __('加载更多', 'time-capsule-email');
            echo '</button>';
            echo '</div>';
        }
        
        $output = ob_get_clean();
        
        wp_send_json_success(array('html' => $output, 'has_more' => $has_more));
    }
    
    public function ajax_save_email() {
        if (!check_ajax_referer('tce_nonce', 'nonce', false)) {
            wp_send_json_error(__('安全验证失败，请刷新页面重试。', 'time-capsule-email'));
            wp_die();
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('请先登录', 'time-capsule-email'));
            wp_die();
        }
        
        $email_to = isset($_POST['email_to']) ? sanitize_email($_POST['email_to']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $is_public = isset($_POST['is_public']) ? intval($_POST['is_public']) : 0;
        
        // 获取消息内容，保留 base64 图片
        $message = '';
        if (isset($_POST['message'])) {
            $message = wp_unslash($_POST['message']);
            // 使用自定义的过滤，允许 base64 图片和富文本样式
            $message = $this->sanitize_email_content($message);
        }
        
        $send_date = isset($_POST['send_date']) ? sanitize_text_field($_POST['send_date']) : '';
        
        if (empty($email_to) || empty($subject) || empty($message) || empty($send_date)) {
            wp_send_json_error(__('请填写所有必填字段', 'time-capsule-email'));
            wp_die();
        }
        
        if (!is_email($email_to)) {
            wp_send_json_error(__('请输入有效的电子邮件地址', 'time-capsule-email'));
            wp_die();
        }
        
        try {
            $today = new DateTime(current_time('mysql'));
            $send_date_obj = new DateTime($send_date . ' 00:00:00');
            
            if ($send_date_obj <= $today) {
                throw new Exception(__('发送日期必须是将来的日期', 'time-capsule-email'));
            }
            
            // 检查是否启用邮箱验证
            $settings = get_option('tce_email_settings', array());
            $email_verification = isset($settings['email_verification']) ? $settings['email_verification'] : false;
            
            $email_data = array(
                'user_id' => get_current_user_id(),
                'email_to' => $email_to,
                'subject' => $subject,
                'message' => $message,
                'send_date' => $send_date_obj->format('Y-m-d H:i:s'),
                'is_sent' => 0,
                'is_public' => $is_public,
                'is_verified' => $email_verification ? 0 : 1, // 如果不需要验证，直接标记为已验证
                'verification_token' => $email_verification ? wp_generate_password(32, false) : null,
                'created_at' => current_time('mysql')
            );
            
            $result = TCE_Email::get_instance()->save_email($email_data);
            
            if ($result) {
                // 如果启用了邮箱验证，发送验证邮件
                if ($email_verification && !empty($email_data['verification_token'])) {
                    $sent = $this->send_verification_email($result, $email_to, $email_data['verification_token']);
                    if ($sent) {
                        wp_send_json_success(__('时光邮件已保存！请查收验证邮件并点击链接完成验证。', 'time-capsule-email'));
                    } else {
                        wp_send_json_success(__('时光邮件已保存，但验证邮件发送失败。请检查邮箱设置或联系管理员。', 'time-capsule-email'));
                    }
                } else {
                    wp_send_json_success(__('时光邮件已成功保存！', 'time-capsule-email'));
                }
            } else {
                throw new Exception(__('保存邮件时出错，请重试。', 'time-capsule-email'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
        
        wp_die();
    }
    
    public function ajax_get_emails() {
        if (!check_ajax_referer('tce_nonce', 'nonce', false)) {
            wp_send_json_error(__('安全验证失败', 'time-capsule-email'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('请先登录', 'time-capsule-email'));
            return;
        }
        
        $type = isset($_POST['type']) && in_array($_POST['type'], array('inbox', 'sent')) ? $_POST['type'] : 'inbox';
        $is_sent = ($type === 'sent');
        
        $emails = TCE_Email::get_instance()->get_user_emails(get_current_user_id(), $is_sent);
        
        if (empty($emails)) {
            $no_emails_message = '<div class="tce-no-emails">' . 
                 ($is_sent ? __('没有已发送的邮件', 'time-capsule-email') : __('没有待发送的邮件', 'time-capsule-email')) . 
                 '</div>';
            wp_send_json_success($no_emails_message);
            return;
        }
        
        ob_start();
        ?>
        <ul class="tce-email-list">
            <?php foreach ($emails as $email) : 
                $send_date = new DateTime($email->send_date);
                $formatted_date = $send_date->format('Y-m-d');
            ?>
                <li class="tce-email-item<?php echo $email->is_sent ? ' sent' : ' pending'; ?>" data-email-id="<?php echo esc_attr($email->id); ?>">
                    <div class="tce-email-header">
                        <h3 class="tce-email-subject"><?php echo esc_html($email->subject); ?></h3>
                        <span class="tce-email-date"><?php echo esc_html($formatted_date); ?></span>
                    </div>
                    <div class="tce-email-meta">
                        <span><?php echo esc_html__('收件人:', 'time-capsule-email') . ' ' . esc_html($this->mask_email($email->email_to)); ?></span>
                    </div>
                    <div class="tce-email-content">
                        <?php echo wp_trim_words(wp_strip_all_tags($email->message), 30, '...'); ?>
                    </div>
                    <div class="tce-email-footer">
                        <span class="tce-email-status<?php echo $email->is_sent ? ' sent' : ' pending'; ?>">
                            <?php if ($email->is_sent) : ?>
                                <i class="fas fa-check-circle"></i> 
                                <?php echo esc_html__('已发送', 'time-capsule-email'); ?>
                            <?php else : ?>
                                <i class="fas fa-clock"></i> 
                                <?php echo esc_html__('等待发送', 'time-capsule-email'); ?>
                            <?php endif; ?>
                        </span>
                        <div class="tce-email-actions">
                            <?php if ($email->is_sent) : ?>
                                <a href="#" class="tce-email-action tce-view-email" 
                                   data-id="<?php echo esc_attr($email->id); ?>">
                                    <i class="fas fa-eye"></i> 
                                    <?php esc_html_e('查看', 'time-capsule-email'); ?>
                                </a>
                            <?php else : ?>
                                <a href="#" class="tce-email-action tce-edit-email" 
                                   data-id="<?php echo esc_attr($email->id); ?>">
                                    <i class="fas fa-edit"></i> 
                                    <?php esc_html_e('编辑', 'time-capsule-email'); ?>
                                </a>
                            <?php endif; ?>
                            <a href="#" class="tce-email-action tce-delete-email" 
                               data-action="delete" 
                               data-id="<?php echo esc_attr($email->id); ?>"
                               data-nonce="<?php echo wp_create_nonce('tce_delete_email_' . $email->id); ?>">
                                <i class="fas fa-trash"></i> 
                                <?php esc_html_e('删除', 'time-capsule-email'); ?>
                            </a>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $output = ob_get_clean();
        
        wp_send_json_success($output);
    }
    
    public function ajax_delete_email() {
        check_ajax_referer('tce_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(__('请先登录', 'time-capsule-email'));
            return;
        }
        
        $email_id = isset($_POST['email_id']) ? intval($_POST['email_id']) : 0;
        
        if (!$email_id) {
            wp_send_json_error(__('无效的邮件ID', 'time-capsule-email'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tce_emails';
        $user_id = get_current_user_id();
        
        $where = array('id' => $email_id);
        if (!current_user_can('manage_options')) {
            $where['user_id'] = $user_id;
        }
        
        $deleted = $wpdb->delete($table_name, $where);
        
        if ($deleted) {
            wp_send_json_success(__('邮件已删除', 'time-capsule-email'));
        } else {
            wp_send_json_error(__('删除邮件时出错', 'time-capsule-email'));
        }
    }
    
    /**
     * 获取单个邮件详情
     */
    public function ajax_get_email() {
        // 对于公开信，允许未登录用户访问
        if (isset($_POST['nonce'])) {
            check_ajax_referer('tce_nonce', 'nonce');
        }
        
        $email_id = isset($_POST['email_id']) ? intval($_POST['email_id']) : 0;
        
        if (!$email_id) {
            wp_send_json_error(__('无效的邮件ID', 'time-capsule-email'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tce_emails';
        
        // 如果用户已登录，检查是否是自己的邮件
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $email = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND (user_id = %d OR (is_public = 1 AND is_verified = 1))",
                $email_id,
                $user_id
            ));
        } else {
            // 未登录用户只能查看公开信
            $email = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND is_public = 1 AND is_verified = 1",
                $email_id
            ));
        }
        
        if (!$email) {
            wp_send_json_error(__('邮件不存在或无权访问', 'time-capsule-email'));
            return;
        }
        
        // 对于公开信，隐藏邮箱地址
        $display_email = $email->email_to;
        if ($email->is_public == 1) {
            $display_email = $this->mask_email($email->email_to);
        }
        
        wp_send_json_success(array(
            'id' => $email->id,
            'email_to' => $display_email,
            'subject' => $email->subject,
            'message' => $email->message,
            'send_date' => $email->send_date,
            'is_sent' => $email->is_sent
        ));
    }
    
    /**
     * 更新邮件
     */
    public function ajax_update_email() {
        check_ajax_referer('tce_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('请先登录', 'time-capsule-email'));
            return;
        }
        
        $email_id = isset($_POST['email_id']) ? intval($_POST['email_id']) : 0;
        $email_to = isset($_POST['email_to']) ? sanitize_email($_POST['email_to']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message = isset($_POST['message']) ? wp_unslash($_POST['message']) : '';
        $send_date = isset($_POST['send_date']) ? sanitize_text_field($_POST['send_date']) : '';
        
        if (!$email_id) {
            wp_send_json_error(__('无效的邮件ID', 'time-capsule-email'));
            return;
        }
        
        if (empty($email_to) || empty($subject) || empty($message) || empty($send_date)) {
            wp_send_json_error(__('请填写所有必填字段', 'time-capsule-email'));
            return;
        }
        
        if (!is_email($email_to)) {
            wp_send_json_error(__('请输入有效的电子邮件地址', 'time-capsule-email'));
            return;
        }
        
        try {
            $today = new DateTime(current_time('mysql'));
            $send_date_obj = new DateTime($send_date . ' 00:00:00');
            
            if ($send_date_obj <= $today) {
                throw new Exception(__('发送日期必须是将来的日期', 'time-capsule-email'));
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'tce_emails';
            $user_id = get_current_user_id();
            
            // 检查邮件是否存在且属于当前用户
            $email = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND user_id = %d AND is_sent = 0",
                $email_id,
                $user_id
            ));
            
            if (!$email) {
                throw new Exception(__('邮件不存在、无权访问或已发送', 'time-capsule-email'));
            }
            
            // 清理邮件内容
            $message = $this->sanitize_email_content($message);
            
            // 更新邮件
            $updated = $wpdb->update(
                $table_name,
                array(
                    'email_to' => $email_to,
                    'subject' => $subject,
                    'message' => $message,
                    'send_date' => $send_date_obj->format('Y-m-d H:i:s')
                ),
                array(
                    'id' => $email_id,
                    'user_id' => $user_id
                ),
                array('%s', '%s', '%s', '%s'),
                array('%d', '%d')
            );
            
            if ($updated !== false) {
                wp_send_json_success(__('邮件已更新', 'time-capsule-email'));
            } else {
                throw new Exception(__('更新邮件时出错', 'time-capsule-email'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * 清理邮件内容，保留 base64 图片和富文本格式
     */
    private function sanitize_email_content($content) {
        // 先提取并保存所有 style 属性
        $style_map = array();
        $style_counter = 0;
        
        $content = preg_replace_callback('/style=["\']([^"\']+)["\']/i', function($matches) use (&$style_map, &$style_counter) {
            $style_id = '___STYLE_PLACEHOLDER_' . $style_counter . '___';
            $style_map[$style_id] = $matches[1];
            $style_counter++;
            return 'data-style-id="' . $style_id . '"';
        }, $content);
        
        // 处理 base64 图片，上传到媒体库并替换为 URL
        $content = preg_replace_callback('/<img([^>]*)>/i', function($matches) {
            $img_tag = $matches[0];
            $img_attrs = $matches[1];
            
            // 检查是否是 base64 图片
            if (preg_match('/src=["\']data:image\/([^;]+);base64,([^"\']+)["\']/i', $img_attrs, $base64_match)) {
                $image_type = $base64_match[1];
                $base64_data = $base64_match[2];
                
                // 上传 base64 图片到 WordPress 媒体库
                $uploaded_url = $this->upload_base64_image($base64_data, $image_type);
                
                if ($uploaded_url) {
                    // 替换 base64 为上传后的 URL
                    $img_tag = preg_replace('/src=["\']data:image\/[^;]+;base64,[^"\']+["\']/', 'src="' . esc_url($uploaded_url) . '"', $img_tag);
                }
            }
            
            return $img_tag;
        }, $content);
        
        // 允许的标签和属性
        // 注意：我们添加了 data-style-id 属性用于临时保存样式
        $allowed_tags = array(
            'img' => array(
                'src' => array(),
                'alt' => array(),
                'style' => array(),
                'data-style-id' => array(),
                'width' => array(),
                'height' => array(),
                'class' => array(),
                'title' => array(),
            ),
            'p' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'br' => array(),
            'strong' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'b' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'em' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'i' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'u' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            's' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'strike' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'ul' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'ol' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'li' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
                'data-list' => array(),
            ),
            'a' => array(
                'href' => array(),
                'style' => array(),
                'data-style-id' => array(),
                'target' => array(),
                'rel' => array(),
                'class' => array(),
            ),
            'h1' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'h2' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'h3' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'h4' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'h5' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'h6' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'div' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'span' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'blockquote' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'pre' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'code' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'table' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
                'border' => array(),
                'cellpadding' => array(),
                'cellspacing' => array(),
            ),
            'thead' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'tbody' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'tr' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'th' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
            'td' => array(
                'style' => array(),
                'data-style-id' => array(),
                'class' => array(),
            ),
        );
        
        // 使用 wp_kses 过滤（此时 style 属性已被替换为 data-style-id）
        $filtered_content = wp_kses($content, $allowed_tags);
        
        // 恢复 style 属性
        $filtered_content = preg_replace_callback('/data-style-id="(___STYLE_PLACEHOLDER_\d+___)"/i', function($matches) use ($style_map) {
            $style_id = $matches[1];
            if (isset($style_map[$style_id])) {
                // 清理样式值，移除潜在的危险内容
                $style = $this->sanitize_css_value($style_map[$style_id]);
                
                // 转换 rgb() 颜色为十六进制格式（提高邮件客户端兼容性）
                $style = preg_replace_callback('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i', function($m) {
                    return sprintf('#%02x%02x%02x', intval($m[1]), intval($m[2]), intval($m[3]));
                }, $style);
                
                // 转换 rgba() 颜色为十六进制格式（忽略透明度）
                $style = preg_replace_callback('/rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*[\d.]+\s*\)/i', function($m) {
                    return sprintf('#%02x%02x%02x', intval($m[1]), intval($m[2]), intval($m[3]));
                }, $style);
                
                return 'style="' . esc_attr($style) . '"';
            }
            return '';
        }, $filtered_content);
        
        return $filtered_content;
    }
    
    /**
     * 清理 CSS 值，移除潜在的危险内容
     */
    private function sanitize_css_value($css) {
        // 移除可能的 JavaScript
        $css = preg_replace('/javascript:/i', '', $css);
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/import\s+/i', '', $css);
        $css = preg_replace('/@import/i', '', $css);
        $css = preg_replace('/behavior\s*:/i', '', $css);
        
        // 移除 -moz-binding (XBL)
        $css = preg_replace('/-moz-binding/i', '', $css);
        
        return trim($css);
    }

    
    /**
     * 上传 base64 图片到 WordPress 媒体库
     */
    private function upload_base64_image($base64_data, $image_type) {
        try {
            // 解码 base64 数据
            $image_data = base64_decode($base64_data, true);
            
            if ($image_data === false || empty($image_data)) {
                return false;
            }
            
            $image_size = strlen($image_data);
            
            // 检查文件大小限制（默认 10MB）
            $max_size = 10 * 1024 * 1024;
            if ($image_size > $max_size) {
                return false;
            }
            
            // 标准化图片类型
            $image_type = strtolower($image_type);
            $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp');
            if (!in_array($image_type, $allowed_types)) {
                return false;
            }
            
            // 生成文件名
            $filename = 'tce-image-' . time() . '-' . wp_generate_password(8, false) . '.' . $image_type;
            
            // 获取上传目录
            $upload_dir = wp_upload_dir();
            
            if (!empty($upload_dir['error'])) {
                return false;
            }
            
            $upload_path = $upload_dir['path'] . '/' . $filename;
            $upload_url = $upload_dir['url'] . '/' . $filename;
            
            // 检查目录是否可写
            if (!is_writable($upload_dir['path'])) {
                return false;
            }
            
            // 保存文件
            $saved = @file_put_contents($upload_path, $image_data);
            
            if ($saved === false) {
                return false;
            }
            
            // 获取文件类型
            $filetype = wp_check_filetype($filename, null);
            
            if (!$filetype['type']) {
                @unlink($upload_path);
                return false;
            }
            
            // 准备附件数据
            $attachment = array(
                'post_mime_type' => $filetype['type'],
                'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            // 插入附件到媒体库
            $attach_id = wp_insert_attachment($attachment, $upload_path);
            
            if (is_wp_error($attach_id) || !$attach_id) {
                @unlink($upload_path);
                return false;
            }
            
            // 生成附件元数据
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $upload_path);
            wp_update_attachment_metadata($attach_id, $attach_data);
            
            return $upload_url;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 发送验证邮件
     */
    private function send_verification_email($email_id, $email_to, $token) {
        $verification_url = add_query_arg(array(
            'action' => 'tce_verify_email',
            'token' => $token,
            'email_id' => $email_id
        ), admin_url('admin-ajax.php'));
        
        $subject = __('【时光邮局】请验证您的邮箱地址', 'time-capsule-email');
        
        $message = sprintf(
            __('您好！<br><br>您在 %s 创建了一封时光邮件。<br><br>为了确保邮件能够在指定日期准确送达，请点击下方链接验证您的邮箱地址：<br><br><a href="%s" style="display:inline-block;padding:12px 24px;background:#ee4d50;color:#fff;text-decoration:none;border-radius:4px;">验证邮箱地址</a><br><br>或复制以下链接到浏览器打开：<br>%s<br><br>此链接永久有效，验证后您的时光邮件将在指定日期自动发送。<br><br>如果这不是您的操作，请忽略此邮件。', 'time-capsule-email'),
            get_bloginfo('name'),
            $verification_url,
            $verification_url
        );
        
        // 使用邮件模板
        if (class_exists('TCE_Email')) {
            $email = TCE_Email::get_instance();
            $message = $email->get_email_template($message, $subject);
        }
        
        // 检查是否启用了SMTP
        $settings = get_option('tce_email_settings', array());
        $smtp_enabled = isset($settings['smtp_enabled']) && $settings['smtp_enabled'];
        
        $sent = false;
        
        if ($smtp_enabled) {
            // 使用SMTP发送
            $smtp_mailer = new TCE_SMTP_Mailer();
            $sent = $smtp_mailer->send($email_to, $subject, $message);
        } else {
            // 使用WordPress默认邮件发送
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            $from_name = isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name');
            $from_email = isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email');
            $reply_to = isset($settings['reply_to']) ? $settings['reply_to'] : '';
            
            if (!empty($from_name) && !empty($from_email)) {
                $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
            }
            
            if (!empty($reply_to)) {
                $headers[] = 'Reply-To: ' . $reply_to;
            }
            
            $sent = wp_mail($email_to, $subject, $message, $headers);
        }
        
        return $sent;
    }
    
    /**
     * 处理邮箱验证
     */
    public function ajax_verify_email() {
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $email_id = isset($_GET['email_id']) ? intval($_GET['email_id']) : 0;
        
        if (empty($token) || !$email_id) {
            wp_die(__('无效的验证链接', 'time-capsule-email'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tce_emails';
        
        // 查找邮件
        $email = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND verification_token = %s",
            $email_id,
            $token
        ));
        
        if (!$email) {
            wp_die(__('验证链接无效或已过期', 'time-capsule-email'));
        }
        
        if ($email->is_verified) {
            wp_die(__('该邮箱已经验证过了', 'time-capsule-email'));
        }
        
        // 更新验证状态
        $updated = $wpdb->update(
            $table_name,
            array(
                'is_verified' => 1,
                'verified_at' => current_time('mysql')
            ),
            array('id' => $email_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($updated) {
            // 显示成功页面
            $this->display_verification_success($email);
        } else {
            wp_die(__('验证失败，请稍后重试', 'time-capsule-email'));
        }
    }
    
    /**
     * 显示验证成功页面
     */
    private function display_verification_success($email) {
        $send_date = new DateTime($email->send_date);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('邮箱验证成功', 'time-capsule-email'); ?></title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Helvetica Neue", Arial, sans-serif;
                    background: #fff;
                    margin: 0;
                    padding: 20px;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .success-container {
                    background: white;
                    border-radius: 8px;
                    padding: 40px;
                    max-width: 500px;
                    text-align: center;
                    border: 1px solid #e5e5e5;
                }
                .success-icon {
                    width: 80px;
                    height: 80px;
                    background: #52c41a;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                    animation: scaleIn 0.5s ease;
                }
                .success-icon::after {
                    content: '✓';
                    color: white;
                    font-size: 48px;
                    font-weight: bold;
                }
                @keyframes scaleIn {
                    from { transform: scale(0); }
                    to { transform: scale(1); }
                }
                h1 {
                    color: #333;
                    font-size: 24px;
                    margin: 0 0 10px 0;
                }
                p {
                    color: #666;
                    line-height: 1.6;
                    margin: 10px 0;
                }
                .email-info {
                    background: #f5f5f5;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 20px 0;
                    text-align: left;
                }
                .email-info-item {
                    margin: 10px 0;
                    color: #333;
                }
                .email-info-label {
                    font-weight: 600;
                    color: #666;
                }
                .back-link {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 12px 24px;
                    background: #ee4d50;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                    transition: background 0.3s ease;
                }
                .back-link:hover {
                    background: #e03539;
                }
            </style>
        </head>
        <body>
            <div class="success-container">
                <div class="success-icon"></div>
                <h1><?php _e('邮箱验证成功！', 'time-capsule-email'); ?></h1>
                <p><?php _e('您的时光邮件已确认，将在指定日期自动发送。', 'time-capsule-email'); ?></p>
                
                <div class="email-info">
                    <div class="email-info-item">
                        <span class="email-info-label"><?php _e('收件人：', 'time-capsule-email'); ?></span>
                        <?php echo esc_html($this->mask_email($email->email_to)); ?>
                    </div>
                    <div class="email-info-item">
                        <span class="email-info-label"><?php _e('主题：', 'time-capsule-email'); ?></span>
                        <?php echo esc_html($email->subject); ?>
                    </div>
                    <div class="email-info-item">
                        <span class="email-info-label"><?php _e('发送日期：', 'time-capsule-email'); ?></span>
                        <?php echo esc_html($send_date->format('Y-m-d')); ?>
                    </div>
                </div>
                
                <p><?php _e('请耐心等待，您的时光邮件将准时送达。', 'time-capsule-email'); ?></p>
                
                <a href="<?php echo home_url(); ?>" class="back-link"><?php _e('返回首页', 'time-capsule-email'); ?></a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

TCE_Shortcodes::get_instance();
