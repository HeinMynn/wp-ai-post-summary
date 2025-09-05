<?php
/**
 * Admin settings for AI Post Summary plugin
 *
 * @package AIPostSummary
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'ai_post_summary_add_admin_menu');
add_action('admin_init', 'ai_post_summary_settings_init');
add_action('wp_ajax_ai_post_summary_test', 'ai_post_summary_ajax_test');

function ai_post_summary_ajax_test() {
    // Verify nonce and user permissions
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ai_post_summary_test') || !current_user_can('manage_options')) {
        wp_die(esc_html__('Security check failed', 'ai-post-summary'));
    }
    
    $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
    if (empty($content)) {
        wp_send_json_error(esc_html__('No content provided for testing.', 'ai-post-summary'));
    }
    
    $options = get_option('ai_post_summary_settings', array());
    $char_count = isset($options['ai_post_summary_char_count']) ? intval($options['ai_post_summary_char_count']) : 200;
    
    $summary = ai_post_summary_API_Handler::generate_summary($content, $char_count);
    
    if (is_wp_error($summary)) {
        wp_send_json_error($summary->get_error_message());
    } else {
        wp_send_json_success($summary);
    }
}

function ai_post_summary_add_admin_menu() {
    add_options_page(
        'AI Post Summary Settings',
        'AI Post Summary',
        'manage_options',
        'ai_post_summary',
        'ai_post_summary_options_page'
    );
}

// Sanitization callback for settings
function ai_post_summary_sanitize_settings($input) {
    $sanitized = array();
    
    // Sanitize API provider
    if (isset($input['ai_post_summary_api_provider'])) {
        $sanitized['ai_post_summary_api_provider'] = in_array($input['ai_post_summary_api_provider'], array('gemini', 'chatgpt')) ? $input['ai_post_summary_api_provider'] : 'gemini';
    }
    
    // Sanitize API key
    if (isset($input['ai_post_summary_api_key'])) {
        $sanitized['ai_post_summary_api_key'] = sanitize_text_field($input['ai_post_summary_api_key']);
    }
    
    // Sanitize character count
    if (isset($input['ai_post_summary_char_count'])) {
        $char_count = intval($input['ai_post_summary_char_count']);
        $sanitized['ai_post_summary_char_count'] = ($char_count >= 50 && $char_count <= 1000) ? $char_count : 200;
    }
    
    // Sanitize global enable checkbox - only allow if API key is present
    if (isset($input['ai_post_summary_global_enable'])) {
        $api_key = isset($input['ai_post_summary_api_key']) ? trim(sanitize_text_field($input['ai_post_summary_api_key'])) : '';
        
        // If no API key provided, check existing settings
        if (empty($api_key)) {
            $existing_options = get_option('ai_post_summary_settings', array());
            $api_key = isset($existing_options['ai_post_summary_api_key']) ? trim($existing_options['ai_post_summary_api_key']) : '';
        }
        
        if (!empty($api_key)) {
            $sanitized['ai_post_summary_global_enable'] = 1;
        } else {
            $sanitized['ai_post_summary_global_enable'] = 0;
            // Add admin notice about requiring API key
            add_settings_error(
                'ai_post_summary_settings',
                'api_key_required',
                __('Global summaries cannot be enabled without a valid API key. Please enter your API key first.', 'ai-post-summary'),
                'error'
            );
        }
    } else {
        $sanitized['ai_post_summary_global_enable'] = 0;
    }
    
    // Sanitize disclaimer text
    if (isset($input['ai_post_summary_disclaimer'])) {
        $sanitized['ai_post_summary_disclaimer'] = sanitize_textarea_field($input['ai_post_summary_disclaimer']);
    }
    
    return $sanitized;
}

function ai_post_summary_settings_init() {
    register_setting('ai_post_summary', 'ai_post_summary_settings', array(
        'type' => 'array',
        'sanitize_callback' => 'ai_post_summary_sanitize_settings',
        'show_in_rest' => false
    ));

    add_settings_section(
        'ai_post_summary_section',
        __('Settings', 'ai-post-summary'),
        null,
        'ai_post_summary'
    );

    add_settings_field(
        'ai_post_summary_api_provider',
        __('API Provider', 'ai-post-summary'),
        'ai_post_summary_api_provider_render',
        'ai_post_summary',
        'ai_post_summary_section'
    );

    add_settings_field(
        'ai_post_summary_api_key',
        __('API Key (Gemini/ChatGPT)', 'ai-post-summary'),
        'ai_post_summary_api_key_render',
        'ai_post_summary',
        'ai_post_summary_section'
    );

    add_settings_field(
        'ai_post_summary_char_count',
        __('Summary Character Count', 'ai-post-summary'),
        'ai_post_summary_char_count_render',
        'ai_post_summary',
        'ai_post_summary_section'
    );

    add_settings_field(
        'ai_post_summary_global_enable',
        __('Enable Globally', 'ai-post-summary'),
        'ai_post_summary_global_enable_render',
        'ai_post_summary',
        'ai_post_summary_section'
    );

    add_settings_field(
        'ai_post_summary_disclaimer',
        __('Disclaimer Text', 'ai-post-summary'),
        'ai_post_summary_disclaimer_render',
        'ai_post_summary',
        'ai_post_summary_section'
    );
}

function ai_post_summary_api_provider_render() {
    $options = get_option('ai_post_summary_settings');
    $provider = $options['ai_post_summary_api_provider'] ?? 'gemini';
    echo '<select name="ai_post_summary_settings[ai_post_summary_api_provider]" id="ai_post_summary_api_provider">';
    echo '<option value="gemini" ' . selected($provider, 'gemini', false) . '>Gemini (Preferred)</option>';
    echo '<option value="chatgpt" ' . selected($provider, 'chatgpt', false) . '>ChatGPT</option>';
    echo '</select>';
    echo '<p class="description">Choose your preferred AI service. Gemini is recommended for better performance and lower costs.</p>';
}

function ai_post_summary_api_key_render() {
    $options = get_option('ai_post_summary_settings');
    $provider = $options['ai_post_summary_api_provider'] ?? 'gemini';
    
    echo '<input type="password" name="ai_post_summary_settings[ai_post_summary_api_key]" value="' . esc_attr($options['ai_post_summary_api_key'] ?? '') . '" style="width: 400px;" />';
    
    echo '<div id="gemini-instructions" style="' . ($provider === 'chatgpt' ? 'display: none;' : '') . '">';
    echo '<p class="description">';
    echo '🔐 <strong>Get your Gemini API key:</strong><br>';
    echo '1. Visit <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio →</a><br>';
    echo '2. Sign in with your Google account<br>';
    echo '3. Click "Create API Key" and select your project<br>';
    echo '4. Copy the generated API key and paste it above<br>';
    echo '<em>💡 Gemini offers generous free tier and faster responses.</em>';
    echo '</p>';
    echo '</div>';
    
    echo '<div id="chatgpt-instructions" style="' . ($provider === 'gemini' ? 'display: none;' : '') . '">';
    echo '<p class="description">';
    echo '🔐 <strong>Get your ChatGPT API key:</strong><br>';
    echo '1. Visit <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI Platform →</a><br>';
    echo '2. Sign in to your OpenAI account (create one if needed)<br>';
    echo '3. Click "Create new secret key" and give it a name<br>';
    echo '4. Copy the generated API key and paste it above<br>';
    echo '<em>⚠️ Note: You may need to add billing information to use the API.</em>';
    echo '</p>';
    echo '</div>';
}

function ai_post_summary_char_count_render() {
    $options = get_option('ai_post_summary_settings');
    echo '<input type="number" name="ai_post_summary_settings[ai_post_summary_char_count]" value="' . esc_attr($options['ai_post_summary_char_count'] ?? '200') . '" min="50" max="1000" />';
    echo '<p class="description">Set the target length for generated summaries (50-1000 characters). Recommended: 200-300 for optimal readability.</p>';
}

function ai_post_summary_global_enable_render() {
    $options = get_option('ai_post_summary_settings');
    $api_key = $options['ai_post_summary_api_key'] ?? '';
    $is_enabled = !empty($options['ai_post_summary_global_enable']);
    $has_api_key = !empty(trim($api_key));
    
    $checked = $is_enabled ? 'checked' : '';
    $disabled = !$has_api_key ? 'disabled' : '';
    
    echo '<input type="checkbox" name="ai_post_summary_settings[ai_post_summary_global_enable]" value="1" ' . esc_attr($checked) . ' ' . esc_attr($disabled) . ' id="ai_post_summary_global_enable" />';
    echo '<label for="ai_post_summary_global_enable"> Enable automatic summary generation for all new posts</label>';
    
    if (!$has_api_key) {
        echo '<div class="notice notice-warning inline" style="margin: 10px 0; padding: 10px;">';
        echo '<p><strong>⚠️ Warning:</strong> You must enter a valid API key above before enabling global summaries. ';
        echo 'The checkbox will be enabled automatically once you save an API key.</p>';
        echo '</div>';
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                var apiKeyField = document.querySelector(\'input[name="ai_post_summary_settings[ai_post_summary_api_key]"]\');
                var globalEnableField = document.getElementById("ai_post_summary_global_enable");
                
                function checkApiKey() {
                    if (apiKeyField.value.trim() !== "") {
                        globalEnableField.disabled = false;
                    } else {
                        globalEnableField.disabled = true;
                        globalEnableField.checked = false;
                    }
                }
                
                if (apiKeyField) {
                    apiKeyField.addEventListener("input", checkApiKey);
                    apiKeyField.addEventListener("change", checkApiKey);
                }
            });
        </script>';
    } else {
        echo '<p class="description">When enabled, AI summaries will be automatically generated for all new posts (individual posts can still opt out).</p>';
    }
}

function ai_post_summary_disclaimer_render() {
    $options = get_option('ai_post_summary_settings');
    $disclaimer = $options['ai_post_summary_disclaimer'] ?? 'This summary was generated by AI and may contain inaccuracies or omissions. Please refer to the full article for complete information.';
    echo '<textarea name="ai_post_summary_settings[ai_post_summary_disclaimer]" rows="3" cols="50" style="width: 100%;">' . esc_textarea($disclaimer) . '</textarea>';
    echo '<p class="description">This disclaimer will appear below all AI-generated summaries on your site.</p>';
}

function ai_post_summary_options_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('ai_post_summary');
            do_settings_sections('ai_post_summary');
            submit_button();
            ?>
        </form>
        
        <div style="margin-top: 20px; padding: 15px; background: #f1f1f1; border: 1px solid #ccd0d4;">
            <h3>Generate Summary Test</h3>
            <textarea id="test_content" rows="4" cols="60" placeholder="Enter content to test summary generation..."></textarea><br><br>
            <button type="button" id="generate_test_summary" class="button button-secondary">Generate Test Summary</button>
            <div id="test_result" style="margin-top: 10px;"></div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Handle API provider change
        $('#ai_post_summary_api_provider').change(function() {
            var provider = $(this).val();
            if (provider === 'gemini') {
                $('#gemini-instructions').show();
                $('#chatgpt-instructions').hide();
            } else {
                $('#gemini-instructions').hide();
                $('#chatgpt-instructions').show();
            }
        });
        
        // Handle test summary generation
        $('#generate_test_summary').click(function() {
            var content = $('#test_content').val();
            if (!content) return;
            
            $('#test_result').html('Generating summary...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_post_summary_test',
                    content: content,
                    nonce: '<?php echo esc_attr(wp_create_nonce('ai_post_summary_test')); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#test_result').html('<strong>Summary:</strong> ' + response.data);
                    } else {
                        $('#test_result').html('<span style="color: red;">Error: ' + response.data + '</span>');
                    }
                }
            });
        });
    });
    </script>
    <?php
}
