<?php
/**
 * Post editor integration for AI Post Summary plugin
 *
 * @package AIPostSummary
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', 'ai_post_summary_add_meta_box');
add_action('save_post', 'ai_post_summary_save_post_meta');
add_action('publish_post', 'ai_post_summary_auto_generate');
add_action('save_post', 'ai_post_summary_auto_generate', 20); // Run after save_post_meta
add_action('transition_post_status', 'ai_post_summary_on_publish', 10, 3); // Handle status transitions
add_action('wp_ajax_ai_post_summary_check_update', 'ai_post_summary_ajax_check_update');
add_action('admin_enqueue_scripts', 'ai_post_summary_enqueue_admin_scripts');

function ai_post_summary_enqueue_admin_scripts($hook) {
    if ('post.php' !== $hook && 'post-new.php' !== $hook) {
        return;
    }
    
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'ai_post_summary_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ai_post_summary_generate'),
    ));
}

function ai_post_summary_add_meta_box() {
    add_meta_box(
        'ai_post_summary_meta',
        __('AI Post Summary', 'ai-post-summary'),
        'ai_post_summary_meta_box_callback',
        'post',
        'side'
    );
}

function ai_post_summary_meta_box_callback($post) {
    $enabled = get_post_meta($post->ID, '_ai_post_summary_enabled', true);
    $summary = get_post_meta($post->ID, '_ai_post_summary_content', true);
    $global_enabled = get_option('ai_post_summary_settings')['ai_post_summary_global_enable'] ?? false;
    
    wp_nonce_field('ai_post_summary_meta', 'ai_post_summary_nonce');
    ?>
    <div class="gpt-summary-meta-box">
        <label>
            <input type="checkbox" name="ai_post_summary_enabled" value="1" <?php checked($enabled); ?> />
            Enable automatic summary generation for this post
        </label>
        
        <?php if (!$global_enabled): ?>
            <p style="color: orange; font-style: italic; margin-top: 10px;">
                <strong>Note:</strong> Global summary is disabled. Enable it in Settings > AI Post Summary.
            </p>
        <?php endif; ?>
        
        <?php if ($summary): ?>
            <div id="gpt-summary-preview" style="margin-top: 15px; padding: 10px; background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; color: #0073aa;">Generated Summary:</h4>
                <p id="gpt-summary-text" style="margin: 0; line-height: 1.5; color: #333;"><?php echo esc_html($summary); ?></p>
                <div style="margin-top: 10px;">
                    <label>
                        <input type="checkbox" name="ai_post_summary_regenerate" value="1" />
                        Regenerate summary on next update
                    </label>
                </div>
                <div class="notice notice-info inline" style="margin: 10px 0 0 0; padding: 8px 12px; background: #fff3cd; border-left: 4px solid #ffc107;">
                    <p style="margin: 0; font-size: 13px;"><strong>💡 Tip:</strong> After checking "Regenerate summary" and saving, the system will attempt to update automatically. If the new summary doesn't appear, please <strong>refresh this page</strong> to see the updated content.</p>
                </div>
            </div>
        <?php else: ?>
            <div id="gpt-summary-preview" style="margin-top: 15px; display: none; padding: 10px; background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; color: #0073aa;">Generated Summary:</h4>
                <p id="gpt-summary-text" style="margin: 0; line-height: 1.5; color: #333;"></p>
                <div style="margin-top: 10px;">
                    <label>
                        <input type="checkbox" name="ai_post_summary_regenerate" value="1" />
                        Regenerate summary on next update
                    </label>
                </div>
            </div>
            <p id="gpt-summary-placeholder" style="color: #666; font-style: italic; margin-top: 10px;">
                Summary will be generated automatically when you publish the post (if enabled above).
            </p>
            <div class="notice notice-info inline" style="margin: 10px 0 0 0; padding: 8px 12px; background: #e7f3ff; border-left: 4px solid #0073aa;">
                <p style="margin: 0; font-size: 13px;"><strong>💡 Tip:</strong> Summary generation may take a few moments. If it doesn't appear automatically after saving, please <strong>refresh this page</strong> to see the generated summary.</p>
            </div>
        <?php endif; ?>
        
        <div id="gpt-summary-status" style="margin-top: 10px; padding: 8px; display: none; border-radius: 4px;">
            <span class="spinner" style="float: left; margin-right: 8px;"></span>
            <span id="gpt-summary-status-text">Generating summary...</span>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var postId = <?php echo esc_js($post->ID); ?>;
            var checkingForUpdate = false;
            
            // Function to check for summary updates
            function checkSummaryUpdate() {
                if (checkingForUpdate) return;
                checkingForUpdate = true;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_post_summary_check_update',
                        post_id: postId,
                        nonce: '<?php echo esc_attr(wp_create_nonce('ai_post_summary_check_' . $post->ID)); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var currentDisplayed = $('#gpt-summary-text').text().trim();
                            var newSummary = response.data.summary;
                            var isGenerating = response.data.generating;
                            
                            // If we got a new summary that's different from what's displayed
                            if (newSummary && newSummary !== currentDisplayed) {
                                $('#gpt-summary-text').text(newSummary);
                                $('#gpt-summary-preview').show();
                                $('#gpt-summary-placeholder').hide();
                                $('#gpt-summary-status').hide();
                                
                                // Show success message
                                showStatusMessage(response.data.regenerated ? 'Summary regenerated!' : 'Summary generated!', 'success');
                                
                                // Uncheck regenerate checkbox
                                $('input[name="ai_post_summary_regenerate"]').prop('checked', false);
                                
                                checkingForUpdate = false;
                                return; // Stop checking
                            }
                            
                            // If still generating, continue checking
                            if (isGenerating) {
                                setTimeout(function() {
                                    checkingForUpdate = false;
                                    checkSummaryUpdate();
                                }, 2000);
                                return;
                            }
                        }
                        
                        $('#gpt-summary-status').hide();
                        checkingForUpdate = false;
                    },
                    error: function() {
                        $('#gpt-summary-status').hide();
                        checkingForUpdate = false;
                    }
                });
            }
            
            // Function to show status messages
            function showStatusMessage(message, type) {
                var bgColor = type === 'success' ? '#d4edda' : '#f8d7da';
                var textColor = type === 'success' ? '#155724' : '#721c24';
                
                $('#gpt-summary-status').css({
                    'background-color': bgColor,
                    'color': textColor,
                    'border': '1px solid ' + (type === 'success' ? '#c3e6cb' : '#f5c6cb')
                }).find('.spinner').hide().end().find('#gpt-summary-status-text').text(message).end().show();
                
                // For success messages, add a refresh reminder after a delay
                if (type === 'success') {
                    setTimeout(function() {
                        $('#gpt-summary-status-text').html(message + '<br><small style="font-style: italic; opacity: 0.8;">If the summary didn\'t update above, please refresh this page.</small>');
                    }, 4000);
                }
                
                setTimeout(function() {
                    $('#gpt-summary-status').fadeOut();
                }, 8000);
            }
            
            // Listen for post save events
            $(document).on('heartbeat-send', function(event, data) {
                // WordPress heartbeat - we can use this to detect saves
                data.ai_post_summary_check = {post_id: postId};
            });
            
            // Check for updates when regenerate checkbox is checked and post is saved
            var originalSummary = $('#gpt-summary-text').text().trim();
            
            // Also listen for WordPress post update events
            $(document).on('heartbeat-tick.gpt-summary', function(e, data) {
                if (data.wp_autosave && data.wp_autosave.post_id == postId) {
                    // Post was saved, check for updates
                    setTimeout(checkSummaryUpdate, 1000);
                }
            });
            
            // Alternative approach: Monitor for page changes
            var originalUrl = window.location.href;
            var urlCheckInterval = setInterval(function() {
                if (window.location.href !== originalUrl) {
                    // URL changed (likely due to post save), check for updates
                    setTimeout(checkSummaryUpdate, 500);
                    originalUrl = window.location.href;
                }
            }, 1000);
            
            // Also check periodically after form submission
            $('form#post').on('submit', function() {
                var regenerateChecked = $('input[name="ai_post_summary_regenerate"]:checked').length > 0;
                var enabledChecked = $('input[name="ai_post_summary_enabled"]:checked').length > 0;
                var hasNoSummary = !$('#gpt-summary-text').text().trim();
                
                if (regenerateChecked || (enabledChecked && hasNoSummary)) {
                    $('#gpt-summary-status').css({
                        'background-color': '#fff3cd',
                        'color': '#856404',
                        'border': '1px solid #ffeaa7'
                    }).find('.spinner').show().css('visibility', 'visible').end().find('#gpt-summary-status-text').text('Generating summary...').end().show();
                    
                    // Start checking immediately and repeatedly
                    var checkInterval = setInterval(function() {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ai_post_summary_check_update',
                                post_id: postId,
                                original_summary: originalSummary,
                                nonce: '<?php echo esc_attr(wp_create_nonce('ai_post_summary_check_' . $post->ID)); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    var newSummary = response.data.summary;
                                    var isGenerating = response.data.generating;
                                    
                                    // If generation is complete and we have a new summary
                                    if (!isGenerating && newSummary && newSummary !== originalSummary) {
                                        // Summary was updated
                                        $('#gpt-summary-text').text(newSummary);
                                        $('#gpt-summary-preview').show();
                                        $('#gpt-summary-placeholder').hide();
                                        $('#gpt-summary-status').hide();
                                        
                                        // Show success message
                                        showStatusMessage(response.data.regenerated ? 'Summary regenerated!' : 'Summary generated!', 'success');
                                        
                                        // Stop checking
                                        clearInterval(checkInterval);
                                        
                                        // Uncheck the regenerate checkbox
                                        $('input[name="ai_post_summary_regenerate"]').prop('checked', false);
                                        
                                        // Update the original summary for future comparisons
                                        originalSummary = newSummary;
                                    }
                                }
                            },
                            error: function() {
                                // Hide status on error
                                $('#gpt-summary-status').hide();
                                clearInterval(checkInterval);
                            }
                        });
                    }, 2000); // Check every 2 seconds
                    
                    // Stop checking after 30 seconds to prevent infinite polling
                    setTimeout(function() {
                        clearInterval(checkInterval);
                        $('#gpt-summary-status').hide();
                    }, 30000);
                }
            });
        });
        </script>
        
        <div style="margin-top: 10px; font-size: 12px; color: #666;">
            💡 <strong>Tip:</strong> Summary is generated automatically when the post is published or updated.
        </div>
    </div>
    <?php
}

function ai_post_summary_save_post_meta($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['ai_post_summary_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ai_post_summary_nonce'])), 'ai_post_summary_meta')) return;
    
    $enabled = isset($_POST['ai_post_summary_enabled']) ? 1 : 0;
    update_post_meta($post_id, '_ai_post_summary_enabled', $enabled);
    
    // Handle regeneration request - set a flag instead of deleting immediately
    if (isset($_POST['ai_post_summary_regenerate']) && sanitize_text_field(wp_unslash($_POST['ai_post_summary_regenerate'])) === '1') {
        // Set a flag to regenerate summary
        update_post_meta($post_id, '_ai_post_summary_regenerate_flag', '1');
    } else {
        // Remove the flag if not checking regenerate
        delete_post_meta($post_id, '_ai_post_summary_regenerate_flag');
    }
}

function ai_post_summary_auto_generate($post_id) {
    // Check if this is an autosave or revision
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    
    // Prevent running multiple times in the same request
    static $processed = array();
    if (isset($processed[$post_id])) return;
    $processed[$post_id] = true;
    
    // Check global settings
    $options = get_option('ai_post_summary_settings');
    $global_enabled = $options['ai_post_summary_global_enable'] ?? false;
    if (!$global_enabled) return;
    
    // Check if summary is enabled for this post or should be auto-enabled
    $post_enabled = get_post_meta($post_id, '_ai_post_summary_enabled', true);
    
    // If global is enabled and no explicit setting exists, enable it for new posts
    if (!$post_enabled && $global_enabled) {
        $existing_meta = get_post_meta($post_id, '_ai_post_summary_enabled');
        // If meta doesn't exist at all (new post), auto-enable it
        if (empty($existing_meta)) {
            update_post_meta($post_id, '_ai_post_summary_enabled', '1');
            $post_enabled = true;
        }
    }
    
    if (!$post_enabled) return;
    
    // Check if we should regenerate or if no summary exists
    $existing_summary = get_post_meta($post_id, '_ai_post_summary_content', true);
    $should_regenerate = get_post_meta($post_id, '_ai_post_summary_regenerate_flag', true);
    
    // Only generate if no summary exists OR regeneration is requested
    if (!empty($existing_summary) && !$should_regenerate) return;
    
    // Get post content
    $post = get_post($post_id);
    if (!$post || empty($post->post_content)) return;
    
    // Generate summary
    $char_count = $options['ai_post_summary_char_count'] ?? 200;
    $summary = ai_post_summary_API_Handler::generate_summary($post->post_content, $char_count);
    
    // Save the summary if generation was successful
    if (!is_wp_error($summary)) {
        update_post_meta($post_id, '_ai_post_summary_content', $summary);
        // Clear the regeneration flag
        delete_post_meta($post_id, '_ai_post_summary_regenerate_flag');
        
        // Add admin notice for successful generation
        add_action('admin_notices', function() use ($should_regenerate) {
            $message = $should_regenerate ? 'Post summary regenerated successfully!' : 'Post summary generated successfully!';
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>AI Post Summary:</strong> ' . esc_html($message) . '</p>';
            echo '</div>';
        });
    } else {
        // Add admin notice for failed generation
        add_action('admin_notices', function() use ($summary) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>AI Post Summary Error:</strong> ' . esc_html($summary->get_error_message()) . '</p>';
            echo '</div>';
        });
    }
}

function ai_post_summary_on_publish($new_status, $old_status, $post) {
    // Only trigger on publish transition for posts
    if ($new_status !== 'publish' || $old_status === 'publish' || $post->post_type !== 'post') {
        return;
    }
    
    // Call the auto-generate function
    ai_post_summary_auto_generate($post->ID);
}

function ai_post_summary_ajax_check_update() {
    if (!isset($_POST['post_id'])) {
        wp_die('Missing post ID');
    }
    
    $post_id = intval(sanitize_text_field(wp_unslash($_POST['post_id'])));
    $nonce_action = 'ai_post_summary_check_' . $post_id;
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), $nonce_action) || !current_user_can('edit_post', $post_id)) {
        wp_die('Security check failed');
    }
    
    // Get current summary
    $summary = get_post_meta($post_id, '_ai_post_summary_content', true);
    $regenerate_flag = get_post_meta($post_id, '_ai_post_summary_regenerate_flag', true);
    
    // Check if summary generation is in progress
    $generating = !empty($regenerate_flag);
    
    wp_send_json_success([
        'summary' => $summary,
        'generating' => $generating,
        'regenerated' => !$generating && !empty($summary) // If we have a summary and no flag, it was just generated
    ]);
}
