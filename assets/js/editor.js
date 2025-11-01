/**
 * Facty Pro Editor JavaScript
 * Handles fact-checking process, progress updates, and report display
 */

(function($) {
    'use strict';
    
    let checkInterval = null;
    let currentJobId = null;
    
    $(document).ready(function() {
        // Start fact check button
        $('#facty-pro-start-check').on('click', startFactCheck);
        
        // Verify/unverify buttons
        $('#facty-pro-verify-post').on('click', verifyPost);
        $('#facty-pro-unverify-post').on('click', unverifyPost);
    });
    
    function startFactCheck() {
        const $button = $(this);
        const $progress = $('#facty-pro-progress');
        const $report = $('#facty-pro-report');
        
        // Disable button
        $button.prop('disabled', true).text('Starting...');
        
        // Show progress, hide report
        $report.slideUp();
        $progress.slideDown();
        
        // Start the job
        $.ajax({
            url: factyProEditor.ajaxUrl,
            type: 'POST',
            data: {
                action: 'facty_pro_start_fact_check',
                post_id: factyProEditor.postId,
                nonce: factyProEditor.nonce
            },
            success: function(response) {
                if (response.success) {
                    currentJobId = response.data.job_id;
                    pollJobStatus();
                } else {
                    showError(response.data);
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Start Fact Check');
                    $progress.slideUp();
                }
            },
            error: function() {
                showError('Failed to start fact check. Please try again.');
                $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Start Fact Check');
                $progress.slideUp();
            }
        });
    }
    
    function pollJobStatus() {
        checkInterval = setInterval(function() {
            $.ajax({
                url: factyProEditor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'facty_pro_check_status',
                    job_id: currentJobId,
                    nonce: factyProEditor.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const status = response.data;
                        updateProgress(status);
                        
                        if (status.status === 'completed') {
                            clearInterval(checkInterval);
                            showCompletedReport(status.report_id);
                        } else if (status.status === 'failed') {
                            clearInterval(checkInterval);
                            showError(status.message);
                        }
                    }
                }
            });
        }, 2000); // Poll every 2 seconds
    }
    
    function updateProgress(status) {
        const progress = status.progress || 0;
        const message = status.message || 'Processing...';
        
        $('.progress-fill').css('width', progress + '%');
        $('.progress-percentage').text(progress + '%');
        $('.progress-message').text(message);
    }
    
    function showCompletedReport(reportId) {
        const $button = $('#facty-pro-start-check');
        const $progress = $('#facty-pro-progress');
        
        $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Start Fact Check');
        $progress.slideUp();
        
        // Reload page to show updated report
        location.reload();
    }
    
    function verifyPost() {
        if (!confirm('Mark this article as fact-checked and verified?')) {
            return;
        }
        
        const reportId = $(this).data('report-id');
        const $button = $(this);
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: factyProEditor.ajaxUrl,
            type: 'POST',
            data: {
                action: 'facty_pro_verify_post',
                post_id: factyProEditor.postId,
                report_id: reportId,
                nonce: factyProEditor.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to verify post: ' + response.data);
                    $button.prop('disabled', false);
                }
            }
        });
    }
    
    function unverifyPost() {
        if (!confirm('Remove verification from this article?')) {
            return;
        }
        
        const reportId = $(this).data('report-id');
        const $button = $(this);
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: factyProEditor.ajaxUrl,
            type: 'POST',
            data: {
                action: 'facty_pro_unverify_post',
                post_id: factyProEditor.postId,
                report_id: reportId,
                nonce: factyProEditor.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to unverify post: ' + response.data);
                    $button.prop('disabled', false);
                }
            }
        });
    }
    
    function showError(message) {
        alert('Error: ' + message);
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
})(jQuery);
