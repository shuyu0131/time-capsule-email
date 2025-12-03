jQuery(document).ready(function($) {
    'use strict';
    
    
    // 发送测试邮件
    $('#tce-test-email').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.addClass('loading').text('发送中...');
        
        $.ajax({
            type: 'POST',
            url: tce_admin.ajax_url,
            data: {
                action: 'tce_test_email',
                nonce: tce_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: function() {
                showNotice('测试邮件发送失败！', 'error');
            },
            complete: function() {
                $button.removeClass('loading').text(originalText);
            }
        });
    });
    
    // SMTP连接测试
    $('#tce-test-smtp').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.addClass('loading').text('测试连接中...');
        
        $.ajax({
            type: 'POST',
            url: tce_admin.ajax_url,
            data: {
                action: 'tce_test_smtp_connection',
                nonce: tce_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                } else {
                    showNotice(response.data || 'SMTP连接测试失败！', 'error');
                }
            },
            error: function() {
                showNotice('SMTP连接测试失败！', 'error');
            },
            complete: function() {
                $button.removeClass('loading').text(originalText);
            }
        });
    });
    
    // SMTP设置显示/隐藏
    $('input[name="tce_email_settings[smtp_enabled]"]').on('change', function() {
        toggleSmtpSettings($(this).is(':checked'));
    });
    
    // 初始化SMTP设置显示状态 - 默认显示所有SMTP字段
    $(document).ready(function() {
        // 确保所有SMTP字段都显示
        var $smtpFields = $('.tce-smtp-field');
        $smtpFields.removeClass('hidden');
        
        // 然后根据复选框状态调整
        var smtpEnabled = $('input[name="tce_email_settings[smtp_enabled]"]').is(':checked');
        toggleSmtpSettings(smtpEnabled);
    });
    
    // 表单保存提示
    $('#tce-settings-form').on('submit', function() {
        var $submitBtn = $(this).find('input[type="submit"]');
        var originalText = $submitBtn.val();
        
        $submitBtn.val(tce_admin.i18n.saving).prop('disabled', true);
        
        // 模拟保存过程
        setTimeout(function() {
            $submitBtn.val(originalText).prop('disabled', false);
            showNotice(tce_admin.i18n.saved, 'success');
        }, 1000);
    });
    
    // 显示通知
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.tce-admin-main').prepend($notice);
        
        // 3秒后自动隐藏
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // 切换SMTP设置显示
    function toggleSmtpSettings(show) {
        var $smtpFields = $('.tce-smtp-field');
        if (show) {
            $smtpFields.removeClass('hidden');
        } else {
            $smtpFields.addClass('hidden');
        }
    }
    
    // 重置模板按钮
    $('#tce-reset-template').on('click', function(e) {
        e.preventDefault();
        if (confirm('确定要恢复默认模板吗？当前的自定义模板将被覆盖。')) {
            // 获取默认模板内容
            $.ajax({
                type: 'POST',
                url: tce_admin.ajax_url,
                data: {
                    action: 'tce_get_default_template',
                    nonce: tce_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        $('#tce-email-template').val(response.data);
                        showNotice('模板已重置为默认值', 'success');
                    } else {
                        showNotice('获取默认模板失败', 'error');
                    }
                },
                error: function() {
                    showNotice('获取默认模板失败', 'error');
                }
            });
        }
    });
    
    // 预览模板按钮
    $('#tce-preview-template').on('click', function(e) {
        e.preventDefault();
        
        var templateHtml = $('#tce-email-template').val();
        
        if (!templateHtml || templateHtml.trim() === '') {
            showNotice('模板内容为空，无法预览', 'error');
            return;
        }
        
        var previewWindow = window.open('', '_blank', 'width=800,height=600');
        
        if (previewWindow) {
            var previewHtml = templateHtml
                .replace(/\{\{title\}\}/g, '测试邮件标题')
                .replace(/\{\{content\}\}/g, '<p>这是一封测试邮件，用于预览模板效果。</p><p>您可以在这里看到邮件内容的排版效果。</p><p>支持图片：</p><img src="https://www.baidu.com/img/flexible/logo/plus_logo_web_2.png" style="max-width:100%;height:auto;" />')
                .replace(/\{\{site_name\}\}/g, '时光邮局')
                .replace(/\{\{site_url\}\}/g, window.location.origin);
            
            previewWindow.document.write(previewHtml);
            previewWindow.document.close();
        } else {
            showNotice('无法打开预览窗口，请检查您的浏览器是否阻止了弹出窗口', 'error');
        }
    });
    
    // 图片上传功能
    var mediaUploader;
    
    $('.tce-upload-image').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var targetId = $button.data('target');
        var $target = $('#' + targetId);
        var $preview = $button.closest('.tce-image-upload').find('.tce-image-preview img');
        
        // 如果媒体上传器已存在，重新打开
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        // 创建新的媒体上传器
        mediaUploader = wp.media({
            title: tce_admin.i18n.select_image || '选择图片',
            button: {
                text: '使用此图片'
            },
            multiple: false
        });
        
        // 选择图片后
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $target.val(attachment.url);
            $preview.attr('src', attachment.url).css('opacity', '1');
        });
        
        mediaUploader.open();
    });
    
    // 移除图片
    $('.tce-remove-image').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var targetId = $button.data('target');
        var $target = $('#' + targetId);
        var $preview = $button.closest('.tce-image-upload').find('.tce-image-preview img');
        var defaultImage = $preview.data('default') || '';
        
        if (confirm('确定要移除此图片吗？')) {
            $target.val('');
            if (defaultImage) {
                $preview.attr('src', defaultImage).css('opacity', '0.5');
            } else {
                $preview.attr('src', '').css('opacity', '0.5');
            }
        }
    });
    
    // 初始化
    function init() {
        // 添加帮助提示
        $('.tce-help').each(function() {
            var $this = $(this);
            var helpText = $this.data('help');
            if (helpText) {
                $(this).append(' <a href="#" class="tce-help-trigger" data-help="' + helpText + '">[?]</a>');
            }
        });
    }
    
    init();
});