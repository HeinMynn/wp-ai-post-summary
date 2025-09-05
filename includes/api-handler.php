<?php
/**
 * API handler for AI Post Summary plugin
 *
 * @package AIPostSummary
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Handler Class for AI Post Summary plugin
 * 
 * Handles communication with AI services (Gemini and ChatGPT)
 *
 * @package AIPostSummary
 * @since   1.0.0
 */
class ai_post_summary_API_Handler {
    
    /**
     * Detect content language for better AI prompting
     *
     * @param string $content The content to analyze
     * @return string Language instruction for AI
     */
    private static function detect_language_instruction($content) {
        // Clean content for analysis
        $clean_content = wp_strip_all_tags($content);
        $sample = substr($clean_content, 0, 500); // Use first 500 characters for detection
        
        // Check for Burmese/Myanmar text (Unicode range U+1000-U+109F)
        if (preg_match('/[\x{1000}-\x{109F}]/u', $sample)) {
            return "IMPORTANT: The content appears to be in Burmese (Myanmar language). You MUST write the summary in Burmese using Myanmar script. Do not translate to English.";
        }
        
        // Check for common non-Latin scripts
        if (preg_match('/[\x{0E00}-\x{0E7F}]/u', $sample)) { // Thai
            return "IMPORTANT: Write the summary in Thai language using Thai script.";
        }
        if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $sample)) { // Chinese
            return "IMPORTANT: Write the summary in Chinese using Chinese characters.";
        }
        if (preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $sample)) { // Japanese
            return "IMPORTANT: Write the summary in Japanese using appropriate Japanese script.";
        }
        if (preg_match('/[\x{AC00}-\x{D7AF}]/u', $sample)) { // Korean
            return "IMPORTANT: Write the summary in Korean using Hangul script.";
        }
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $sample)) { // Arabic
            return "IMPORTANT: Write the summary in Arabic using Arabic script.";
        }
        if (preg_match('/[\x{0900}-\x{097F}]/u', $sample)) { // Hindi/Devanagari
            return "IMPORTANT: Write the summary in Hindi using Devanagari script.";
        }
        
        // Default instruction for likely Latin-based languages
        return "IMPORTANT: Write the summary in the SAME LANGUAGE as the original content. Maintain the original language throughout the summary.";
    }
    
    /**
     * Generate summary using selected AI provider
     *
     * @param string $content    The content to summarize
     * @param int    $char_count Maximum character count for summary
     * @return string|WP_Error   Generated summary or error
     */
    public static function generate_summary($content, $char_count = 200) {
        $options = get_option('ai_post_summary_settings', array());
        $api_key = isset($options['ai_post_summary_api_key']) ? sanitize_text_field($options['ai_post_summary_api_key']) : '';
        $api_provider = isset($options['ai_post_summary_api_provider']) ? sanitize_text_field($options['ai_post_summary_api_provider']) : 'gemini';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', esc_html__('API key not configured. Please set your API key in plugin settings.', 'ai-post-summary'));
        }
        
        // Sanitize and validate inputs
        $content = wp_strip_all_tags($content);
        $char_count = intval($char_count);
        
        if ($char_count < 50 || $char_count > 1000) {
            $char_count = 200; // Default fallback
        }
        
        // Use the selected API provider
        if ($api_provider === 'gemini') {
            return self::call_gemini_api($content, $char_count, $api_key);
        } else {
            return self::call_chatgpt_api($content, $char_count, $api_key);
        }
    }
    
    private static function call_gemini_api($content, $char_count, $api_key) {
        // Try different Gemini models in order of preference (based on Google's latest models)
        $models = ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-1.5-pro'];
        $last_error = null;
        
        foreach ($models as $model) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';
            
            $language_instruction = self::detect_language_instruction($content);
            $prompt = "Please create a concise summary of the following content in approximately {$char_count} characters. {$language_instruction}\n\nContent to summarize:\n\n" . wp_strip_all_tags($content);
            
            $body = json_encode([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);
            
            $response = wp_remote_post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-goog-api-key' => $api_key,
                ],
                'body' => $body,
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                $last_error = new WP_Error('gemini_request_failed', 'Gemini API request failed: ' . $response->get_error_message());
                continue;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                $last_error = new WP_Error('gemini_api_error', 'Gemini API returned error code ' . $response_code . ' for model ' . $model . ': ' . $body);
                continue;
            }
            
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $last_error = new WP_Error('gemini_json_error', 'Invalid JSON response from Gemini API: ' . json_last_error_msg());
                continue;
            }
            
            if (isset($data['error'])) {
                $last_error = new WP_Error('gemini_api_error', 'Gemini API error with model ' . $model . ': ' . $data['error']['message']);
                continue;
            }
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($data['candidates'][0]['content']['parts'][0]['text']);
            }
            
            $last_error = new WP_Error('gemini_response_error', 'Unexpected response format from Gemini API with model ' . $model . ': ' . $body);
        }
        
        return $last_error ?: new WP_Error('gemini_all_models_failed', 'All Gemini models failed to respond');
    }
    
    private static function call_chatgpt_api($content, $char_count, $api_key) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $language_instruction = self::detect_language_instruction($content);
        $prompt = "Please create a concise summary of the following content in approximately {$char_count} characters. {$language_instruction}\n\nContent to summarize:\n\n" . wp_strip_all_tags($content);
        
        $body = json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => intval($char_count / 2), // Rough estimate
            'temperature' => 0.7
        ]);
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => $body,
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return new WP_Error('chatgpt_request_failed', 'ChatGPT API request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('chatgpt_api_error', 'ChatGPT API returned error code ' . $response_code . ': ' . $body);
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('chatgpt_json_error', 'Invalid JSON response from ChatGPT API: ' . json_last_error_msg());
        }
        
        if (isset($data['error'])) {
            return new WP_Error('chatgpt_api_error', 'ChatGPT API error: ' . $data['error']['message']);
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }
        
        return new WP_Error('chatgpt_response_error', 'Unexpected response format from ChatGPT API: ' . $body);
    }
}
