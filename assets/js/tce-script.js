/**
 * 时光邮局前台脚本
 * Version: 1.0.1
 * Last Updated: 2024-11-30
 */
jQuery(document).ready(function($) {
    
    // 打字机效果
    function initTypewriter() {
        const typewriterElements = document.querySelectorAll('.tce-typewriter');
        
        typewriterElements.forEach(element => {
            const texts = JSON.parse(element.getAttribute('data-texts') || '["给未来写封信"]');
            const speed = parseInt(element.getAttribute('data-speed') || '150');
            const deleteSpeed = parseInt(element.getAttribute('data-delete-speed') || '100');
            const delay = parseInt(element.getAttribute('data-delay') || '2000');
            
            let textIndex = 0;
            let charIndex = 0;
            let isDeleting = false;
            let currentText = '';
            
            function type() {
                const fullText = texts[textIndex];
                
                if (!isDeleting) {
                    // 打字
                    currentText = fullText.substring(0, charIndex + 1);
                    charIndex++;
                    
                    if (charIndex === fullText.length) {
                        // 完成打字，等待后开始删除
                        setTimeout(() => {
                            isDeleting = true;
                            type();
                        }, delay);
                        element.textContent = currentText;
                        return;
                    }
                } else {
                    // 删除
                    currentText = fullText.substring(0, charIndex - 1);
                    charIndex--;
                    
                    if (charIndex === 0) {
                        // 完成删除，切换到下一个文本
                        isDeleting = false;
                        textIndex = (textIndex + 1) % texts.length;
                        setTimeout(type, 500);
                        element.textContent = currentText;
                        return;
                    }
                }
                
                element.textContent = currentText;
                setTimeout(type, isDeleting ? deleteSpeed : speed);
            }
            
            // 开始打字机效果
            type();
        });
    }
    
    // 初始化打字机效果
    if (document.querySelector('.tce-typewriter')) {
        initTypewriter();
    }
    

    
    // 在iframe中初始化Quill编辑器
    var quill = null;
    
    function initQuillInIframe() {
        var iframe = document.getElementById('tce-editor-iframe');
        if (!iframe) return;
        
        var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        
        // 写入iframe内容
        iframeDoc.open();
        iframeDoc.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
                <style>
                    body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", "Helvetica Neue", Arial, sans-serif; }
                    #editor-container { height: 100%; }
                    .ql-container { font-size: 15px; height: calc(100% - 42px); }
                    .ql-editor { min-height: 200px; }
                </style>
            </head>
            <body>
                <div id="editor-container"></div>
                <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"><\/script>
                <script>
                    var quill = new Quill('#editor-container', {
                        theme: 'snow',
                        placeholder: '写下你想对未来说的话...',
                        modules: {
                            toolbar: [
                                [{ 'header': [1, 2, 3, false] }],
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ 'color': [] }, { 'background': [] }],
                                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                ['link', 'image'],
                                ['blockquote', 'code-block'],
                                ['clean']
                            ]
                        }
                    });
                    
                    // 同步内容到父页面
                    quill.on('text-change', function() {
                        var html = quill.root.innerHTML;
                        window.parent.postMessage({ type: 'quill-change', content: html }, '*');
                    });
                    
                    // 接收父页面的清空命令
                    window.addEventListener('message', function(e) {
                        if (e.data.type === 'quill-clear') {
                            quill.setContents([]);
                        }
                    });
                <\/script>
            </body>
            </html>
        `);
        iframeDoc.close();
        
        // 监听iframe发来的内容变化
        window.addEventListener('message', function(e) {
            if (e.data.type === 'quill-change') {
                $('#tce-email-message').val(e.data.content);
            }
        });
    }
    
    // 初始化编辑器
    if ($('#tce-editor-iframe').length > 0) {
        setTimeout(initQuillInIframe, 100);
    }

    // 标签页切换
    $('.tce-tab').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $clickedTab = $(this);
        var tabId = $clickedTab.data('tab');
        
        // 如果点击的是当前激活的标签，不做任何操作
        if ($clickedTab.hasClass('active')) {
            return;
        }
        
        // 移除所有标签的激活状态
        $('.tce-tab').removeClass('active');
        $clickedTab.addClass('active');
        
        // 隐藏所有内容区域
        $('.tce-tab-content').removeClass('active').hide();
        
        // 显示目标内容区域
        var $targetContent = $('#tce-' + tabId);
        $targetContent.addClass('active').fadeIn(300);
        
        // 切换到邮件列表时加载数据
        if (tabId === 'inbox' || tabId === 'sent') {
            showSkeletonLoading(tabId);
            setTimeout(function() {
                loadEmailList(tabId);
            }, 300);
        } else if (tabId === 'public') {
            loadPublicLetters(1);
        }
    });
    
    // 表单提交
    $('#tce-email-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var originalBtnHtml = $submitBtn.html();
        
        // 禁用按钮并显示加载状态
        $submitBtn.prop('disabled', true)
                  .addClass('tce-button-loading')
                  .html('<span class="tce-loading-wave"><span></span><span></span><span></span><span></span></span><span>' + tce_ajax.i18n.sending + '</span>');
        
        // 从 iframe 中获取最新的 Quill 内容
        var message = '';
        var iframe = document.getElementById('tce-editor-iframe');
        if (iframe && iframe.contentWindow && iframe.contentWindow.quill) {
            message = iframe.contentWindow.quill.root.innerHTML;
            $('#tce-email-message').val(message);
        } else {
            message = $('#tce-email-message').val().trim();
        }
        
        var formData = {
            email_to: $('#tce-email-to').val().trim(),
            subject: $('#tce-email-subject').val().trim(),
            message: message,
            send_date: $('#tce-send-date').val().trim(),
            action: 'tce_save_email',
            nonce: tce_ajax.nonce
        };
        
        // 验证必填字段
        if (!formData.email_to || !formData.subject || !formData.message || !formData.send_date) {
            showMessage('请填写所有必填字段', 'error');
            $submitBtn.prop('disabled', false).html(originalBtnHtml).removeClass('tce-button-loading');
            return;
        }
        
        // 发送AJAX请求
        $.ajax({
            type: 'POST',
            url: tce_ajax.ajax_url,
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage(response.data || tce_ajax.i18n.success, 'success');
                    
                    // 重置表单
                    $form[0].reset();
                    
                    // 清空iframe中的Quill编辑器
                    var iframe = document.getElementById('tce-editor-iframe');
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage({ type: 'quill-clear' }, '*');
                    }
                    
                    // 刷新邮件列表
                    loadEmailList('inbox');
                    loadEmailList('sent');
                } else {
                    showMessage(response.data || tce_ajax.i18n.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.log('Response:', xhr.responseText);
                showMessage(tce_ajax.i18n.error + ' (' + status + ')', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false)
                          .removeClass('tce-button-loading')
                          .html(originalBtnHtml);
            }
        });
    });
    
    // 加载邮件列表
    function loadEmailList(type) {
        var $container = $('#tce-' + type);
        
        // 显示加载动画
        var $loading = $('<div class="tce-loading-container">' +
            '<div class="tce-loading-spinner"></div>' +
            '<div class="tce-loading-text">' + tce_ajax.i18n.loading + '</div>' +
            '<div class="tce-loading-subtitle">' + getLoadingSubtitle(type) + '</div>' +
        '</div>');
        
        $container.html($loading).hide().fadeIn(300);
        
        $.ajax({
            type: 'POST',
            url: tce_ajax.ajax_url,
            data: {
                action: 'tce_get_emails',
                nonce: tce_ajax.nonce,
                type: type
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $container.fadeOut(200, function() {
                        $(this).html(response.data).fadeIn(300);
                    });
                } else {
                    console.error('加载邮件列表失败:', response.data);
                    $container.fadeOut(200, function() {
                        $(this).html('<div class="tce-message error">' + (response.data || tce_ajax.i18n.error) + '</div>').fadeIn(300);
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                $container.fadeOut(200, function() {
                    $(this).html('<div class="tce-message error">' + tce_ajax.i18n.error + ' (' + status + ')</div>').fadeIn(300);
                });
            }
        });
    }
    
    // 显示骨架屏加载
    function showSkeletonLoading(type) {
        var $container = $('#tce-' + type);
        var skeletonHtml = '<div class="tce-loading-container">' +
            '<div class="tce-loading-spinner"></div>' +
            '<div class="tce-loading-text">加载中...</div>' +
        '</div>';
        
        $container.html(skeletonHtml);
    }
    
    // 获取加载提示文本
    function getLoadingSubtitle(type) {
        switch(type) {
            case 'inbox':
                return '正在获取待发送的邮件...';
            case 'sent':
                return '正在获取已发送的邮件...';
            default:
                return '正在加载数据...';
        }
    }
    
    // 显示消息提示
    function showMessage(message, type) {
        var $message = $('#tce-message');
        $message.removeClass('success error')
                .addClass(type)
                .text(message)
                .css('display', 'block')
                .hide()
                .fadeIn(300);
        
        // 5秒后自动隐藏
        setTimeout(function() {
            $message.fadeOut(300);
        }, 5000);
    }
    
    // 设置日期选择器最小日期为明天
    var today = new Date();
    var tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    var dd = String(tomorrow.getDate()).padStart(2, '0');
    var mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
    var yyyy = tomorrow.getFullYear();
    var minDate = yyyy + '-' + mm + '-' + dd;
    $('#tce-send-date').attr('min', minDate);
    
    // 删除邮件
    $(document).on('click', '.tce-delete-email', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var emailId = $btn.data('id');
        var $item = $btn.closest('.tce-email-item');
        
        if (!confirm(tce_ajax.i18n.delete_confirm)) {
            return false;
        }
        
        // 显示删除中状态
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(tce_ajax.i18n.deleting);
        
        $.ajax({
            type: 'POST',
            url: tce_ajax.ajax_url,
            data: {
                action: 'tce_delete_email',
                nonce: tce_ajax.nonce,
                email_id: emailId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // 淡出并移除邮件项
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        
                        // 如果没有邮件了，重新加载列表显示空状态
                        if ($('.tce-email-item').length === 0) {
                            var currentTab = $('.tce-tab.active').data('tab');
                            loadEmailList(currentTab);
                        }
                    });
                } else {
                    alert(response.data || tce_ajax.i18n.error);
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert(tce_ajax.i18n.error);
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // 查看邮件
    $(document).on('click', '.tce-view-email', function(e) {
        e.preventDefault();
        
        var emailId = $(this).data('id');
        
        $.ajax({
            type: 'POST',
            url: tce_ajax.ajax_url,
            data: {
                action: 'tce_get_email',
                nonce: tce_ajax.nonce,
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
                    
                    openModal('#tce-view-modal');
                } else {
                    alert(response.data || tce_ajax.i18n.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert(tce_ajax.i18n.error);
            }
        });
    });
    
    // 编辑邮件
    var editQuill = null;
    $(document).on('click', '.tce-edit-email', function(e) {
        e.preventDefault();
        
        var emailId = $(this).data('id');
        
        $.ajax({
            type: 'POST',
            url: tce_ajax.ajax_url,
            data: {
                action: 'tce_get_email',
                nonce: tce_ajax.nonce,
                email_id: emailId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var email = response.data;
                    $('#tce-edit-id').val(email.id);
                    $('#tce-edit-to').val(email.email_to);
                    $('#tce-edit-subject').val(email.subject);
                    $('#tce-edit-date').val(email.send_date.split(' ')[0]);
                    $('#tce-edit-message').val(email.message);
                    
                    // 初始化编辑器
                    initEditQuill(email.message);
                    
                    openModal('#tce-edit-modal');
                } else {
                    alert(response.data || tce_ajax.i18n.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert(tce_ajax.i18n.error);
            }
        });
    });
    
    // 初始化编辑模态框中的Quill编辑器
    function initEditQuill(content) {
        var iframe = document.getElementById('tce-edit-iframe');
        if (!iframe) return;
        
        var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        
        iframeDoc.open();
        iframeDoc.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
                <style>
                    body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", "Helvetica Neue", Arial, sans-serif; }
                    #editor-container { height: 100%; }
                    .ql-container { font-size: 15px; height: calc(100% - 42px); }
                    .ql-editor { min-height: 200px; }
                </style>
            </head>
            <body>
                <div id="editor-container"></div>
                <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"><\/script>
                <script>
                    var quill = new Quill('#editor-container', {
                        theme: 'snow',
                        placeholder: '写下你想对未来说的话...',
                        modules: {
                            toolbar: [
                                [{ 'header': [1, 2, 3, false] }],
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ 'color': [] }, { 'background': [] }],
                                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                ['link', 'image'],
                                ['blockquote', 'code-block'],
                                ['clean']
                            ]
                        }
                    });
                    
                    // 同步内容到父页面
                    quill.on('text-change', function() {
                        var html = quill.root.innerHTML;
                        window.parent.postMessage({ type: 'edit-quill-change', content: html }, '*');
                    });
                    
                    // 接收父页面的内容设置
                    window.addEventListener('message', function(e) {
                        if (e.data.type === 'edit-quill-set') {
                            quill.root.innerHTML = e.data.content;
                        }
                    });
                <\/script>
            </body>
            </html>
        `);
        iframeDoc.close();
        
        // 监听编辑器内容变化
        window.addEventListener('message', function(e) {
            if (e.data.type === 'edit-quill-change') {
                $('#tce-edit-message').val(e.data.content);
            }
        });
        
        // 设置初始内容
        setTimeout(function() {
            iframe.contentWindow.postMessage({ type: 'edit-quill-set', content: content }, '*');
        }, 500);
    }
    
    // 提交编辑表单
    $('#tce-edit-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var originalBtnHtml = $submitBtn.html();
        
        // 禁用按钮
        $submitBtn.prop('disabled', true)
                  .addClass('tce-button-loading')
                  .html('<span class="tce-loading-wave"><span></span><span></span><span></span><span></span></span><span>保存中...</span>');
        
        // 从 iframe 中获取最新内容
        var iframe = document.getElementById('tce-edit-iframe');
        var message = '';
        if (iframe && iframe.contentWindow && iframe.contentWindow.quill) {
            message = iframe.contentWindow.quill.root.innerHTML;
            $('#tce-edit-message').val(message);
        } else {
            message = $('#tce-edit-message').val();
        }
        
        var formData = {
            email_id: $('#tce-edit-id').val(),
            email_to: $('#tce-edit-to').val().trim(),
            subject: $('#tce-edit-subject').val().trim(),
            message: message,
            send_date: $('#tce-edit-date').val().trim(),
            action: 'tce_update_email',
            nonce: tce_ajax.nonce
        };
        
        $.ajax({
            type: 'POST',
            url: tce_ajax.ajax_url,
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    closeModal('#tce-edit-modal');
                    showMessage(response.data || '邮件已更新', 'success');
                    
                    // 刷新待发送列表
                    loadEmailList('inbox');
                } else {
                    alert(response.data || tce_ajax.i18n.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert(tce_ajax.i18n.error);
            },
            complete: function() {
                $submitBtn.prop('disabled', false)
                          .removeClass('tce-button-loading')
                          .html(originalBtnHtml);
            }
        });
    });
    
    // 打开模态框（全局函数）
    window.openModal = function(selector) {
        var $modal = $(selector);
        $modal.addClass('active');
        $('body').css('overflow', 'hidden');
    }
    
    // 关闭模态框（全局函数）
    window.closeModal = function(selector) {
        var $modal = $(selector);
        $modal.removeClass('active');
        $('body').css('overflow', '');
    }
    
    // 关闭模态框按钮
    $(document).on('click', '.tce-modal-close, .tce-modal-cancel, .tce-modal-overlay', function(e) {
        e.preventDefault();
        var $modal = $(this).closest('.tce-modal');
        window.closeModal('#' + $modal.attr('id'));
    });
    
    // ESC键关闭模态框
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('.tce-modal.active').length > 0) {
            closeModal('.tce-modal.active');
        }
    });
    
    // 加载公开信列表
    function loadPublicLetters(page) {
        var $container = $('#tce-public-letters-list');
        
        if (page === 1) {
            $container.html('<div class="tce-loading-container">' +
                '<div class="tce-loading-spinner"></div>' +
                '<div class="tce-loading-text">加载中...</div>' +
            '</div>');
        }
        
        $.ajax({
            type: 'POST',
            url: tce_ajax.ajax_url,
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
                    $container.html('<div class="tce-message error">' + (response.data || '加载失败') + '</div>');
                }
            },
            error: function() {
                $container.html('<div class="tce-message error">加载失败，请稍后重试</div>');
            }
        });
    }
    
    // 加载更多公开信
    $(document).on('click', '#tce-load-more-public', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 加载中...');
        loadPublicLetters(page);
    });
    
    // 查看公开信
    $(document).on('click', '.tce-view-public-letter', function(e) {
        e.preventDefault();
        
        var emailId = $(this).data('id');
        var ajaxData = {
            action: 'tce_get_email',
            email_id: emailId
        };
        
        // 如果有 nonce（已登录），则添加
        if (typeof tce_ajax !== 'undefined' && tce_ajax.nonce) {
            ajaxData.nonce = tce_ajax.nonce;
        }
        
        $.ajax({
            type: 'POST',
            url: typeof tce_ajax !== 'undefined' ? tce_ajax.ajax_url : ajaxurl,
            data: ajaxData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var email = response.data;
                    $('#tce-view-to').text(email.email_to);
                    $('#tce-view-subject').text(email.subject);
                    $('#tce-view-date').text(email.send_date.split(' ')[0]);
                    $('#tce-view-message').html(email.message);
                    
                    openModal('#tce-view-modal');
                } else {
                    alert(response.data || '加载失败');
                }
            },
            error: function() {
                alert('加载失败，请稍后重试');
            }
        });
    });
    
});

