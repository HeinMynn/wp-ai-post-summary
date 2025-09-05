# AI Post Summary

Contributors: Aung Hein Mynn
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generate AI-powered summaries for your blog posts using Google Gemini or OpenAI ChatGPT to improve reader engagement and SEO.

## Features

### Admin Panel

- **Secure API Key Storage**: Safely store Gemini or ChatGPT API keys using WordPress options API
- **Character Count Control**: Set the desired length of generated summaries (50-1000 characters)
- **Global Toggle**: Enable or disable summary generation site-wide
- **API Provider Selection**: Choose between Gemini (preferred) and ChatGPT
- **Test Summary Generation**: Built-in testing tool in admin settings

### Post Editor

- **Per-Post Control**: Enable or disable summary generation for individual posts
- **One-Click Generation**: Generate summaries directly in the post editor
- **Real-time Preview**: See generated summaries before publishing
- **Auto-save**: Generated summaries are automatically saved with the post

### Frontend Display

- **Automatic Display**: Summaries appear automatically at the top of posts (when enabled)
- **Shortcode Support**: Use `[ai_post_summary]` shortcode for manual placement
- **Responsive Design**: Clean, mobile-friendly summary display box

## Installation

1. Upload the `ai-post-summary` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > AI Post Summary to configure your API keys

## Configuration

### API Keys

- **Gemini API**: Get your API key from [Google AI Studio](https://aistudio.google.com/app/apikey)
- **ChatGPT API**: Get your API key from [OpenAPI Platform](https://platform.openai.com/api-keys)

### Settings

- Navigate to **Settings > AI Post Summary** in your WordPress admin
- Enter your API key (the field is password-protected for security)
- Choose your preferred API provider
- Set the character count for summaries (default: 200)
- Enable global summary generation

## Usage

### Automatic Generation

1. Enable global summaries in settings
2. Create or edit a post
3. Check "Enable summary for this post" in the AI Post Summary meta box
4. Click "Generate Summary" to create the summary
5. Publish the post - the summary will appear automatically

### Manual Placement

Use the `[ai_post_summary]` shortcode anywhere in your post content to display the summary at a custom location.

## Security Features

- API keys are stored securely using WordPress options API
- Password-protected API key field in admin
- Nonce verification for all AJAX requests
- Capability checks for admin functions
- Input sanitization and output escaping

## File Structure

```
ai-post-summary/
├── ai-post-summary.php          # Main plugin file
├── includes/
│   ├── admin-settings.php       # Admin settings page
│   ├── post-editor.php         # Post editor integration
│   ├── api-handler.php         # API communication
│   └── frontend-display.php    # Summary display functions
└── README.md                   # This file
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Valid Gemini or ChatGPT API key
- Active internet connection for API calls

## Changelog

### Version 1.0.0

- Initial release
- Gemini and ChatGPT API integration
- Admin settings panel
- Post editor meta box
- Frontend summary display
- Shortcode support
- Security features implemented
