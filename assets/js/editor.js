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
        
        // View report button
        $('#facty-pro-view-report').on('click', viewReport);
        
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
    
    function viewReport() {
        const reportId = $(this).data('report-id');
        const $report = $('#facty-pro-report');
        
        if ($report.is(':visible')) {
            $report.slideUp();
            $(this).html('<span class="dashicons dashicons-visibility"></span> View Full Report');
        } else {
            loadReport(reportId);
            $(this).html('<span class="dashicons dashicons-hidden"></span> Hide Report');
        }
    }
    
    function loadReport(reportId) {
        const $report = $('#facty-pro-report');
        
        $report.html('<div style="text-align:center;padding:40px"><span class="spinner is-active"></span></div>');
        $report.slideDown();
        
        // Get report data via AJAX
        $.ajax({
            url: factyProEditor.ajaxUrl,
            type: 'POST',
            data: {
                action: 'facty_pro_get_report',
                report_id: reportId,
                nonce: factyProEditor.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderReport(response.data);
                } else {
                    $report.html('<p>Failed to load report.</p>');
                }
            },
            error: function() {
                $report.html('<p>Failed to load report.</p>');
            }
        });
    }
    
    function renderReport(report) {
        const $report = $('#facty-pro-report');
        const factCheck = report.fact_check || {};
        const seo = report.seo || {};
        const style = report.style || {};
        
        let html = '<div class="report-content">';
        
        // Fact Check Section
        if (factCheck.issues && factCheck.issues.length > 0) {
            html += '<div class="report-section"><h4>📋 Fact Check Issues</h4>';
            factCheck.issues.forEach(function(issue) {
                html += '<div class="issue-item severity-' + issue.severity + '">';
                html += '<strong>' + escapeHtml(issue.claim) + '</strong><br>';
                html += '<div style="margin-top:8px;color:#64748b">';
                html += '<strong>Problem:</strong> ' + escapeHtml(issue.the_problem) + '<br>';
                html += '<strong>Fix:</strong> ' + escapeHtml(issue.how_to_fix || 'Review and update this claim');
                html += '</div></div>';
            });
            html += '</div>';
        }
        
        // SEO Section
        if (seo.recommendations && seo.recommendations.length > 0) {
            html += '<div class="report-section"><h4>🔍 SEO Recommendations</h4>';
            seo.recommendations.forEach(function(rec) {
                html += '<div class="issue-item"><span class="dashicons dashicons-lightbulb"></span> ' + escapeHtml(rec) + '</div>';
            });
            html += '</div>';
        }
        
        // Style Section
        if (style.suggestions && style.suggestions.length > 0) {
            html += '<div class="report-section"><h4>✍️ Style Suggestions</h4>';
            style.suggestions.forEach(function(sug) {
                html += '<div class="issue-item"><span class="dashicons dashicons-edit"></span> ' + escapeHtml(sug) + '</div>';
            });
            html += '</div>';
        }
        
        html += '</div>';
        
        $report.html(html);
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
