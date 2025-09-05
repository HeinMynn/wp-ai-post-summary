<?php
/**
 * Language Detection Test for AI Post Summary
 * 
 * Temporary test file to debug language detection issues.
 * Add this content to your theme's functions.php or create as a test file.
 */

// Add admin page for language detection testing
add_action('admin_menu', 'ai_post_summary_add_language_test_page');
function ai_post_summary_add_language_test_page() {
    if (!current_user_can('manage_options')) return;
    
    add_submenu_page(
        'options-general.php',
        'Language Detection Test',
        'Language Test',
        'manage_options',
        'language-detection-test',
        'ai_post_summary_language_test_page'
    );
}

function ai_post_summary_language_test_page() {
    ?>
    <div class="wrap">
        <h1>AI Post Summary - Language Detection Test</h1>
        
        <form method="post" style="margin: 20px 0;">
            <table class="form-table">
                <tr>
                    <th>Test Content</th>
                    <td>
                        <textarea name="test_content" rows="10" cols="80" placeholder="Enter content to test language detection..."><?php echo isset($_POST['test_content']) ? esc_textarea($_POST['test_content']) : ''; ?></textarea>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="test_language" class="button-primary" value="Test Language Detection" />
            </p>
        </form>
        
        <?php
        if (isset($_POST['test_language']) && !empty($_POST['test_content'])) {
            $test_content = wp_unslash($_POST['test_content']);
            
            // Call the language detection function if it exists
            if (class_exists('ai_post_summary_API_Handler')) {
                // Use reflection to access the private method for testing
                $reflection = new ReflectionClass('ai_post_summary_API_Handler');
                if ($reflection->hasMethod('detect_language_instruction')) {
                    $method = $reflection->getMethod('detect_language_instruction');
                    $method->setAccessible(true);
                    
                    $language_instruction = $method->invoke(null, $test_content);
                    
                    echo '<div style="background: #f0f0f1; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">';
                    echo '<h3>Language Detection Result:</h3>';
                    echo '<p><strong>Detected Instruction:</strong></p>';
                    echo '<p style="background: white; padding: 10px; border: 1px solid #ccd0d4;">' . esc_html($language_instruction) . '</p>';
                    
                    // Show some analysis
                    $clean_content = wp_strip_all_tags($test_content);
                    $sample = substr($clean_content, 0, 1000);
                    $sample_lower = strtolower($sample);
                    
                    // Count common English words
                    $english_indicators = ['the ', 'and ', 'is ', 'are ', 'was ', 'were ', 'be ', 'been ', 'being ', 'have ', 'has ', 'had ', 'do ', 'does ', 'did ', 'will ', 'would ', 'could ', 'should ', 'may ', 'might ', 'can ', 'must ', 'shall ', 'to ', 'of ', 'in ', 'for ', 'on ', 'with ', 'at ', 'by ', 'from ', 'as ', 'but ', 'or ', 'if ', 'this ', 'that ', 'these ', 'those ', 'a ', 'an '];
                    $english_count = 0;
                    foreach ($english_indicators as $indicator) {
                        $english_count += substr_count($sample_lower, $indicator);
                    }
                    
                    // Count common French words
                    $french_indicators = ['le ', 'la ', 'les ', 'de ', 'du ', 'des ', 'et ', 'est ', 'être ', 'avoir ', 'que ', 'qui ', 'avec ', 'pour ', 'par ', 'sur ', 'dans ', 'une ', 'un '];
                    $french_count = 0;
                    foreach ($french_indicators as $indicator) {
                        $french_count += substr_count($sample_lower, $indicator);
                    }
                    
                    echo '<h4>Analysis Details:</h4>';
                    echo '<ul>';
                    echo '<li><strong>Content Length:</strong> ' . strlen($clean_content) . ' characters</li>';
                    echo '<li><strong>Sample Length:</strong> ' . strlen($sample) . ' characters</li>';
                    echo '<li><strong>English Indicators Found:</strong> ' . $english_count . '</li>';
                    echo '<li><strong>French Indicators Found:</strong> ' . $french_count . '</li>';
                    echo '<li><strong>Has Burmese Characters:</strong> ' . (preg_match('/[\x{1000}-\x{109F}]/u', $sample) ? 'Yes' : 'No') . '</li>';
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div class="notice notice-error"><p>Language detection method not found.</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>API Handler class not found. Make sure the plugin is activated.</p></div>';
            }
        }
        ?>
        
        <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
            <h3>Test Samples</h3>
            <p>Try these sample texts to test language detection:</p>
            
            <h4>English Sample:</h4>
            <textarea readonly rows="3" style="width: 100%;">WordPress is a free and open-source content management system written in PHP and paired with a MySQL or MariaDB database. Features include a plugin architecture and a template system, referred to within WordPress as Themes.</textarea>
            
            <h4>French Sample:</h4>
            <textarea readonly rows="3" style="width: 100%;">WordPress est un système de gestion de contenu gratuit et open-source écrit en PHP et associé à une base de données MySQL ou MariaDB. Les fonctionnalités comprennent une architecture de plugin et un système de modèles.</textarea>
            
            <h4>Burmese Sample:</h4>
            <textarea readonly rows="3" style="width: 100%;">ဝေါ့ပရက်စ်သည် PHP ဖြင့်ရေးသားထားသော အခမဲ့နှင့် open-source content management system တစ်ခုဖြစ်သည်။ MySQL သို့မဟုတ် MariaDB database နှင့်တွဲ၍အသုံးပြုသည်။</textarea>
        </div>
    </div>
    <?php
}
