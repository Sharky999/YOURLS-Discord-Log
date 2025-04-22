<?php
/*
Plugin Name: Discord Logs
Plugin URI: https://github.com/Sharky999/YOURLS-Discord-Log
Description: Send notifications to Discord when someone visits a shortened URL
Version: 1.3
Author: Sharky999
Author URI: https://sharkytmp.report/sharky
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

// Helper function to output checked attribute
function discord_logs_checked($value) {
    echo $value ? ' checked="checked"' : '';
}

// Add hooks - for URL click and URL creation
yourls_add_action( 'redirect_shorturl', 'discord_logs_notify_click', 10, 2 );
yourls_add_filter( 'shunt_add_new_link', 'discord_logs_capture_keyword', 10, 2 );

// Default options
function discord_logs_default_options() {
    return array(
        'webhook_url' => '',
        'notify_on_all_urls' => true,
        'specific_urls' => array(),
        'include_ip' => true,
        'include_referrer' => true,
        'include_user_agent' => true,
        'include_location' => true,
        'include_browser_os' => true,
        'webhook_username' => 'Sharkys YOURLS discord Log.',
        'webhook_avatar' => '',
        'message_template' => '🔗 Short URL **{keyword}** was clicked! Original URL: {longurl}',
        'rate_limiting' => true,
        'rate_limit_seconds' => 60,
        'embed_color' => 7506394, // Blue color by default
        'embed_title_template' => 'URL Click: {keyword}',
        'discord_mentions' => '',
        'use_mentions' => false
    );
}

// Helper function to intercept and properly handle keywords at creation time
function discord_logs_capture_keyword($shunt, $args) {
    // Don't actually shunt the link creation, just capture details
    if (isset($args['keyword']) && !empty($args['keyword'])) {
        // Store this keyword in a transient to ensure we have the correct keyword value
        // when the URL is created and then clicked
        $keyword = $args['keyword'];
        error_log('YOURLS Discord Log - Captured keyword at creation: ' . $keyword);
        
        // Store for up to 24 hours
        yourls_set_option('discord_logs_last_keyword', $keyword);
    }
    
    // Return false to allow normal link creation to proceed
    return false;
}

// The main function that sends the notification when a link is clicked
function discord_logs_notify_click($url, $keyword) {
    // Check if we have a stored keyword from creation time that might be more reliable
    $saved_keyword = yourls_get_option('discord_logs_last_keyword');
    
    if (!empty($saved_keyword)) {
        error_log('YOURLS Discord Log - Using saved keyword: ' . $saved_keyword . ' instead of: ' . $keyword);
        $keyword = $saved_keyword;
        // Clear it after use to avoid confusion with future URLs
        yourls_delete_option('discord_logs_last_keyword');
    }
    
    // Now proceed with the notification using the corrected keyword
    discord_logs_notify($url, $keyword);
}

// Get options with defaults applied if missing
function discord_logs_get_options() {
    $options = yourls_get_option( 'discord_logs' );
    if( !$options ) {
        $options = discord_logs_default_options();
        yourls_add_option( 'discord_logs', $options );
    }
    return $options;
}

// Function to parse user agent and extract browser and OS
function discord_logs_parse_user_agent($user_agent) {
    $browser = 'Unknown';
    $os = 'Unknown';
    
    // Detect browser
    if (preg_match('/MSIE|Trident/i', $user_agent)) {
        $browser = 'Internet Explorer';
    } elseif (preg_match('/Firefox/i', $user_agent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Chrome/i', $user_agent)) {
        if (preg_match('/Edge|Edg/i', $user_agent)) {
            $browser = 'Edge';
        } elseif (preg_match('/OPR|Opera/i', $user_agent)) {
            $browser = 'Opera';
        } elseif (preg_match('/Brave/i', $user_agent)) {
            $browser = 'Brave';
        } else {
            $browser = 'Chrome';
        }
    } elseif (preg_match('/Safari/i', $user_agent)) {
        $browser = 'Safari';
    }
    
    // Detect OS
    if (preg_match('/Windows/i', $user_agent)) {
        $os = 'Windows';
        if (preg_match('/Windows NT 10\.0/i', $user_agent)) {
            $os .= ' 10/11';
        } elseif (preg_match('/Windows NT 6\.3/i', $user_agent)) {
            $os .= ' 8.1';
        } elseif (preg_match('/Windows NT 6\.2/i', $user_agent)) {
            $os .= ' 8';
        } elseif (preg_match('/Windows NT 6\.1/i', $user_agent)) {
            $os .= ' 7';
        }
    } elseif (preg_match('/Macintosh|Mac OS X/i', $user_agent)) {
        $os = 'macOS';
    } elseif (preg_match('/Android/i', $user_agent)) {
        $os = 'Android';
    } elseif (preg_match('/iOS|iPhone|iPad|iPod/i', $user_agent)) {
        $os = 'iOS';
    } elseif (preg_match('/Linux/i', $user_agent)) {
        $os = 'Linux';
    }
    
    return array(
        'browser' => $browser,
        'os' => $os
    );
}

// The function that sends the notification
function discord_logs_notify( $url, $keyword ) {
    $options = discord_logs_get_options();
    
    // Enhanced debug logging to troubleshoot keyword issues
    error_log('YOURLS Discord Log - Debug info:');
    error_log('- URL type: ' . gettype($url) . ', value: ' . (is_array($url) ? json_encode($url) : $url));
    error_log('- Keyword type: ' . gettype($keyword) . ', value: ' . (is_array($keyword) ? json_encode($keyword) : $keyword));
    
    // Handle case where $keyword is an array or a numeric value
    if (is_array($keyword)) {
        // Try all possible fields where the keyword might be
        if (isset($keyword['keyword'])) {
            $keyword = $keyword['keyword'];
        } elseif (isset($keyword['shorturl'])) {
            // Extract keyword from shorturl if present
            $parts = explode('/', $keyword['shorturl']);
            $keyword = end($parts);
        } else {
            // Last resort - take first element
            $keyword = (string)reset($keyword);
        }
        error_log('YOURLS Discord Log - Extracted keyword from array: ' . $keyword);
    }
    
    // Make sure keyword is a string, not a number (avoid '1' display issue)
    $keyword = (string)$keyword;
    error_log('YOURLS Discord Log - Final keyword after conversion: ' . $keyword);
    
    // Check if we should send a notification for this URL
    if( !$options['notify_on_all_urls'] && !in_array( $keyword, $options['specific_urls'] ) ) {
        return;
    }
    
    // Early bail if no webhook URL is set
    if ( empty($options['webhook_url']) ) {
        return;
    }
    
    // Check rate limiting
    if ($options['rate_limiting']) {
        $rate_limited = discord_logs_check_rate_limit($keyword, $options['rate_limit_seconds']);
        if ($rate_limited) {
            return; // Skip notification due to rate limiting
        }
    }
    
    // Handle case where $url is an array (happens in some YOURLS versions/configurations)
    if (is_array($url)) {
        $url = $url['url'] ?? (string)reset($url);
    }
    
    // Get click data
    $data = array(
        'keyword' => $keyword,
        'longurl' => (string)$url,
        'timestamp' => date('Y-m-d H:i:s')
    );
    
    // Add optional data
    if ( $options['include_ip'] ) {
        $data['ip'] = (string)yourls_get_IP();
    }
    
    if ( $options['include_referrer'] ) {
        $data['referrer'] = (string)(yourls_get_referrer() ?: 'Unknown');
    }
    
    $user_agent_string = (string)(yourls_get_user_agent() ?: 'Unknown');
    if ( $options['include_user_agent'] ) {
        $data['user_agent'] = $user_agent_string;
    }
    
    if ( $options['include_location'] ) {
        $data['location'] = (string)(yourls_geo_ip_to_countrycode(yourls_get_IP()) ?: 'Unknown');
    }
    
    // Parse user agent for browser and OS info
    if ( $options['include_browser_os'] && $user_agent_string != 'Unknown' ) {
        $parsed_ua = discord_logs_parse_user_agent($user_agent_string);
        $data['browser'] = (string)$parsed_ua['browser'];
        $data['os'] = (string)$parsed_ua['os'];
    }
    
    // Replace placeholders in message template
    $message = $options['message_template'];
    foreach ( $data as $key => $value ) {
        $message = str_replace( '{'.$key.'}', $value, $message );
    }
    
    // Replace placeholders in title template
    $title = $options['embed_title_template'];
    foreach ( $data as $key => $value ) {
        $title = str_replace( '{'.$key.'}', $value, $title );
    }
    
    // Build the Discord embed
    $embed = array(
        'title' => $title,
        'description' => $message,
        'color' => $options['embed_color'],
        'timestamp' => gmdate('c'),
        'fields' => array(
            array(
                'name' => 'Short URL',
                'value' => YOURLS_SITE . '/' . $keyword,
                'inline' => true
            ),
            array(
                'name' => 'Destination',
                'value' => $url,
                'inline' => true
            )
        ),
        'footer' => array(
            'text' => 'YOURLS Discord Logs'
        )
    );
    
    // Add optional fields to embed
    if ( $options['include_referrer'] ) {
        $embed['fields'][] = array(
            'name' => 'Referrer',
            'value' => $data['referrer'],
            'inline' => true
        );
    }
    
    if ( $options['include_location'] ) {
        $embed['fields'][] = array(
            'name' => 'Location',
            'value' => $data['location'],
            'inline' => true
        );
    }
    
    if ( $options['include_ip'] ) {
        $embed['fields'][] = array(
            'name' => 'IP Address',
            'value' => $data['ip'],
            'inline' => true
        );
    }
    
    // Add browser and OS info
    if ( $options['include_browser_os'] && isset($data['browser']) && isset($data['os']) ) {
        $embed['fields'][] = array(
            'name' => 'Browser',
            'value' => $data['browser'],
            'inline' => true
        );
        
        $embed['fields'][] = array(
            'name' => 'Operating System',
            'value' => $data['os'],
            'inline' => true
        );
    }
    
    if ( $options['include_user_agent'] ) {
        $embed['fields'][] = array(
            'name' => 'User Agent',
            'value' => $data['user_agent'],
            'inline' => false
        );
    }
    
    // Prepare the payload
    $payload = array(
        'username' => $options['webhook_username'],
        'avatar_url' => $options['webhook_avatar'],
        'embeds' => array($embed)
    );
    
    // Add mentions if enabled
    if ($options['use_mentions'] && !empty($options['discord_mentions'])) {
        $payload['content'] = $options['discord_mentions'];
    } else {
        $payload['content'] = null;
    }
    
    // Send to Discord
    discord_logs_send_to_discord($options['webhook_url'], $payload);
}

// Function to send the actual HTTP request to Discord
function discord_logs_send_to_discord($webhook_url, $payload) {
    // Validate webhook URL
    if (empty($webhook_url) || !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    // Convert array to JSON
    $json_payload = json_encode($payload);
    
    // Check if we should send asynchronously 
    if (discord_logs_can_send_async()) {
        return discord_logs_send_async($webhook_url, $json_payload);
    }
    
    // Fallback to synchronous sending
    return discord_logs_send_sync($webhook_url, $json_payload);
}

// Check if we can send asynchronously
function discord_logs_can_send_async() {
    // Check if we're on Windows (doesn't support non-blocking)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return false;
    }
    
    // Check if allow_url_fopen is enabled
    if (!ini_get('allow_url_fopen')) {
        return false;
    }
    
    return true;
}

// Send notification asynchronously
function discord_logs_send_async($webhook_url, $json_payload) {
    // Setup the stream context
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $json_payload,
            'timeout' => 1, // Very short timeout
        ]
    ];
    $context = stream_context_create($opts);
    
    // Send the request without waiting for response
    $fp = @fopen($webhook_url, 'rb', false, $context);
    if ($fp) {
        @fclose($fp);
        return true;
    }
    
    // Fallback to sync if async fails
    return discord_logs_send_sync($webhook_url, $json_payload);
}

// Send notification synchronously 
function discord_logs_send_sync($webhook_url, $json_payload) {
    // Setup cURL
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    // Execute the request
    $response = curl_exec($ch);
    $curl_errno = curl_errno($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for errors
    if ($curl_errno > 0 || ($http_code < 200 || $http_code >= 300)) {
        // Log the error
        $error_msg = 'Discord webhook error: ' . curl_error($ch) . ' (HTTP code: ' . $http_code . ')';
        error_log($error_msg);
    }
    
    // Close cURL
    curl_close($ch);
    
    return $response && $http_code >= 200 && $http_code < 300;
}

// Register plugin admin page
yourls_add_action('plugins_loaded', 'discord_logs_add_page');

function discord_logs_add_page(): void {
    yourls_register_plugin_page('discord_logs', 'Discord Logs', 'discord_logs_do_page');
}

// Display admin page
function discord_logs_do_page() {
    // Check if form was submitted
    if (isset($_POST['discord_logs_webhook_url'])) {
        // Verify nonce
        yourls_verify_nonce('discord_logs_settings');
        
        // Get current options and update with form values
        $options = discord_logs_get_options();
        
        $options['webhook_url'] = trim($_POST['discord_logs_webhook_url']);
        $options['notify_on_all_urls'] = isset($_POST['discord_logs_notify_all']) ? true : false;
        $options['webhook_username'] = trim($_POST['discord_logs_username']);
        $options['webhook_avatar'] = trim($_POST['discord_logs_avatar']);
        $options['message_template'] = trim($_POST['discord_logs_message']);
        $options['embed_title_template'] = trim($_POST['discord_logs_embed_title']);
        
        // Handle color input
        $color = trim($_POST['discord_logs_embed_color']);
        if (preg_match('/^#([A-Fa-f0-9]{6})$/', $color, $matches)) {
            // Convert hex color to decimal for Discord
            $options['embed_color'] = hexdec($matches[1]);
        } else {
            // Fallback to default blue
            $options['embed_color'] = 7506394;
        }
        
        // Optional data to include
        $options['include_ip'] = isset($_POST['discord_logs_include_ip']) ? true : false;
        $options['include_referrer'] = isset($_POST['discord_logs_include_referrer']) ? true : false;
        $options['include_user_agent'] = isset($_POST['discord_logs_include_ua']) ? true : false;
        $options['include_location'] = isset($_POST['discord_logs_include_location']) ? true : false;
        $options['include_browser_os'] = isset($_POST['discord_logs_include_browser_os']) ? true : false;
        
        // Rate limiting
        $options['rate_limiting'] = isset($_POST['discord_logs_rate_limiting']) ? true : false;
        $rate_limit_seconds = intval($_POST['discord_logs_rate_limit_seconds']);
        $options['rate_limit_seconds'] = $rate_limit_seconds > 0 ? $rate_limit_seconds : 60;
        
        // Specific URLs
        if (!empty($_POST['discord_logs_specific_urls'])) {
            $urls = explode(',', $_POST['discord_logs_specific_urls']);
            $options['specific_urls'] = array_map('trim', $urls);
        } else {
            $options['specific_urls'] = array();
        }
        
        // Mention settings
        $options['use_mentions'] = isset($_POST['discord_logs_use_mentions']) ? true : false;
        $options['discord_mentions'] = trim($_POST['discord_logs_mentions']);
        
        // Save options
        $result = yourls_update_option('discord_logs', $options);
        if (!$result) {
            error_log('YOURLS Discord Log - Failed to save options');
        }
        
        echo '<div class="updated"><p>' . yourls__('Settings updated.') . '</p></div>';
    }
    
    // Check if test webhook button was clicked
    if (isset($_POST['discord_logs_test_webhook'])) {
        // Verify nonce
        yourls_verify_nonce('discord_logs_test');
        
        // Get current options
        $options = discord_logs_get_options();
        
        // Test the webhook
        $test_result = discord_logs_test_webhook($options['webhook_url']);
        
        if ($test_result === true) {
            echo '<div class="updated"><p>' . yourls__('Webhook test successful! Check your Discord channel for a test message.') . '</p></div>';
        } else {
            echo '<div class="error"><p>' . yourls__('Webhook test failed: ') . $test_result . '</p></div>';
        }
    }
    
    // Get current options
    $options = discord_logs_get_options();
    
    // Create nonces
    $settings_nonce = yourls_create_nonce('discord_logs_settings');
    $test_nonce = yourls_create_nonce('discord_logs_test');
    
    // Display settings form
    ?>
    <div class="wrap">
        <h2>Discord Logs Settings</h2>
        
        <form method="post">
            <input type="hidden" name="nonce" value="<?php echo $settings_nonce; ?>" />
            
            <p>
                <label for="discord_logs_webhook_url">Discord Webhook URL:</label><br />
                <input type="text" id="discord_logs_webhook_url" name="discord_logs_webhook_url" value="<?php echo htmlspecialchars($options['webhook_url']); ?>" class="text" size="80" />
                <br /><small>Create a webhook URL in your Discord server: Server Settings &gt; Integrations &gt; Webhooks</small>
            </p>
            
            <?php if (!empty($options['webhook_url'])): ?>
            <p>
                <!-- Fix: Don't use nested forms - use a button instead and handle via JavaScript -->
                <input type="button" id="test_webhook_btn" value="Test Webhook" class="button secondary" onclick="testWebhook()" />
                <small>Send a test message to verify your webhook is working correctly.</small>
                
                <!-- Add a hidden form for test webhook functionality -->
                <script>
                function testWebhook() {
                    document.getElementById('test_webhook_form').submit();
                }
                </script>
            </p>
            <?php endif; ?>
            
            <p>
                <label for="discord_logs_username">Discord Bot Username (optional):</label><br />
                <input type="text" id="discord_logs_username" name="discord_logs_username" value="<?php echo htmlspecialchars($options['webhook_username']); ?>" class="text" />
            </p>
            
            <p>
                <label for="discord_logs_avatar">Discord Bot Avatar URL (optional):</label><br />
                <input type="text" id="discord_logs_avatar" name="discord_logs_avatar" value="<?php echo htmlspecialchars($options['webhook_avatar']); ?>" class="text" size="80" />
            </p>
            
            <p>
                <label for="discord_logs_message">Message Template:</label><br />
                <textarea id="discord_logs_message" name="discord_logs_message" class="text" rows="3" cols="80"><?php echo htmlspecialchars($options['message_template']); ?></textarea>
                <br /><small>Available placeholders: {keyword}, {longurl}, {timestamp}, {ip}, {referrer}, {user_agent}, {browser}, {os}, {location}</small>
            </p>
            
            <p>
                <label for="discord_logs_embed_title">Embed Title Template:</label><br />
                <input type="text" id="discord_logs_embed_title" name="discord_logs_embed_title" value="<?php echo htmlspecialchars($options['embed_title_template']); ?>" class="text" size="80" />
                <br /><small>Same placeholders as above are available</small>
            </p>
            
            <p>
                <label for="discord_logs_embed_color">Embed Color:</label><br />
                <input type="color" id="discord_logs_embed_color" name="discord_logs_embed_color" value="<?php echo '#' . dechex($options['embed_color']); ?>" />
                <br /><small>Choose the color for the left border of the Discord embed</small>
            </p>
            
            <p>
                <label>Notify for:</label><br />
                <input type="checkbox" id="discord_logs_notify_all" name="discord_logs_notify_all" <?php discord_logs_checked($options['notify_on_all_urls']); ?> />
                <label for="discord_logs_notify_all">All shortened URLs</label>
                <br />
                <small>OR specify comma-separated keywords below:</small><br />
                <input type="text" id="discord_logs_specific_urls" name="discord_logs_specific_urls" value="<?php echo htmlspecialchars(implode(', ', $options['specific_urls'])); ?>" class="text" size="80" />
            </p>
            
            <p>
                <label>Rate Limiting:</label><br />
                <input type="checkbox" id="discord_logs_rate_limiting" name="discord_logs_rate_limiting" <?php discord_logs_checked($options['rate_limiting']); ?> />
                <label for="discord_logs_rate_limiting">Enable rate limiting</label>
                <br />
                <label for="discord_logs_rate_limit_seconds">Minimum seconds between notifications for the same URL:</label>
                <input type="number" id="discord_logs_rate_limit_seconds" name="discord_logs_rate_limit_seconds" value="<?php echo intval($options['rate_limit_seconds']); ?>" min="1" max="86400" class="small-text" />
                <br />
                <small>Prevents notification spam when a URL is clicked multiple times in quick succession.</small>
            </p>
            
            <p>
                <label>Include in notifications:</label><br />
                <input type="checkbox" id="discord_logs_include_ip" name="discord_logs_include_ip" <?php discord_logs_checked($options['include_ip']); ?> />
                <label for="discord_logs_include_ip">IP Address</label>
                <br />
                <input type="checkbox" id="discord_logs_include_referrer" name="discord_logs_include_referrer" <?php discord_logs_checked($options['include_referrer']); ?> />
                <label for="discord_logs_include_referrer">Referrer</label>
                <br />
                <input type="checkbox" id="discord_logs_include_browser_os" name="discord_logs_include_browser_os" <?php discord_logs_checked($options['include_browser_os']); ?> />
                <label for="discord_logs_include_browser_os">Browser and OS</label>
                <br />
                <input type="checkbox" id="discord_logs_include_ua" name="discord_logs_include_ua" <?php discord_logs_checked($options['include_user_agent']); ?> />
                <label for="discord_logs_include_ua">User Agent</label>
                <br />
                <input type="checkbox" id="discord_logs_include_location" name="discord_logs_include_location" <?php discord_logs_checked($options['include_location']); ?> />
                <label for="discord_logs_include_location">Location (Country)</label>
            </p>
            
            <p>
                <label>Discord Mentions:</label><br />
                <input type="checkbox" id="discord_logs_use_mentions" name="discord_logs_use_mentions" <?php discord_logs_checked($options['use_mentions']); ?> />
                <label for="discord_logs_use_mentions">Enable mentions</label>
                <br />
                <input type="text" id="discord_logs_mentions" name="discord_logs_mentions" value="<?php echo htmlspecialchars($options['discord_mentions']); ?>" class="text" size="80" />
                <br />
                <small>
                    Examples:<br />
                    - To mention a role: <code>@here</code> or <code>@everyone</code> or <code>&lt;@&amp;ROLE_ID&gt;</code><br />
                    - To mention a user: <code>&lt;@USER_ID&gt;</code><br />
                    You can find IDs by enabling Developer Mode in Discord (User Settings > Advanced)
                </small>
            </p>
            
            <p><input type="submit" value="Save Settings" class="button primary" /></p>
        </form>
        
        <!-- Separate form for test webhook -->
        <?php if (!empty($options['webhook_url'])): ?>
        <form id="test_webhook_form" method="post" style="display:none;">
            <input type="hidden" name="nonce" value="<?php echo $test_nonce; ?>" />
            <input type="hidden" name="discord_logs_test_webhook" value="1" />
        </form>
        <?php endif; ?>
        
        <div class="postbox">
            <h3>How to Use Discord Logs</h3>
            <div class="inside">
                <ol>
                    <li>Create a webhook in your Discord server (Server Settings &gt; Integrations &gt; Webhooks)</li>
                    <li>Copy the webhook URL and paste it above</li>
                    <li>Configure the notification settings</li>
                    <li>Save your settings</li>
                </ol>
                <p>Notifications will be sent to your Discord channel when someone visits your shortened URLs.</p>
            </div>
        </div>
    </div>
    <?php
}

// Function to test the webhook
function discord_logs_test_webhook($webhook_url) {
    error_log('YOURLS Discord Log - Testing webhook: ' . $webhook_url);
    
    if (empty($webhook_url)) {
        error_log('YOURLS Discord Log - Test failed: No webhook URL provided');
        return 'No webhook URL provided.';
    }
    
    $options = discord_logs_get_options();
    
    $embed = array(
        'title' => 'YOURLS Discord Logs Test',
        'description' => 'This is a test message to verify your webhook is configured correctly.',
        'color' => $options['embed_color'], // Use custom color
        'timestamp' => gmdate('c'),
        'fields' => array(
            array(
                'name' => 'Status',
                'value' => '✅ Webhook is working!',
                'inline' => true
            ),
            array(
                'name' => 'Timestamp',
                'value' => date('Y-m-d H:i:s'),
                'inline' => true
            )
        ),
        'footer' => array(
            'text' => 'YOURLS Discord Logs'
        )
    );
    
    $payload = array(
        'content' => 'Test message from YOURLS Discord Logs plugin',
        'username' => $options['webhook_username'],
        'avatar_url' => $options['webhook_avatar'],
        'embeds' => array($embed)
    );
    
    // Convert payload to JSON and check for encoding errors
    $json_payload = json_encode($payload);
    if ($json_payload === false) {
        $error = 'JSON encoding error: ' . json_last_error_msg();
        error_log('YOURLS Discord Log - Test failed: ' . $error);
        return $error;
    }
    
    // Send test message synchronously with detailed error handling
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Increased timeout for testing
    
    // Execute the request
    $response = curl_exec($ch);
    $curl_errno = curl_errno($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Log the test results
    error_log('YOURLS Discord Log - Test result: HTTP code ' . $http_code);
    error_log('YOURLS Discord Log - Test response: ' . ($response ?: 'No response'));
    
    // Check for errors
    if ($curl_errno > 0) {
        $error_msg = 'cURL error: ' . curl_error($ch) . ' (Error code: ' . $curl_errno . ')';
        error_log('YOURLS Discord Log - Test failed: ' . $error_msg);
        curl_close($ch);
        return $error_msg;
    }
    
    // Check HTTP response code
    if ($http_code < 200 || $http_code >= 300) {
        $error_msg = 'HTTP error: ' . $http_code . ' - ' . $response;
        error_log('YOURLS Discord Log - Test failed: ' . $error_msg);
        curl_close($ch);
        return $error_msg;
    }
    
    // Close cURL
    curl_close($ch);
    
    error_log('YOURLS Discord Log - Test successful');
    return true;
}

// Function to check if a notification should be rate limited
function discord_logs_check_rate_limit($keyword, $seconds) {
    // Get last notification time for this keyword
    $last_notifications = yourls_get_option('discord_logs_last_notifications', array());
    
    // Get current time
    $current_time = time();
    
    // If this keyword has been notified recently
    if (isset($last_notifications[$keyword])) {
        $last_time = $last_notifications[$keyword];
        
        // If last notification was within the rate limit period
        if (($current_time - $last_time) < $seconds) {
            return true; // Rate limited
        }
    }
    
    // Update the last notification time for this keyword
    $last_notifications[$keyword] = $current_time;
    yourls_update_option('discord_logs_last_notifications', $last_notifications);
    
    return false; // Not rate limited
} 
