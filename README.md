# YOURLS-Discord-Log

A YOURLS Plugin which logs user visits to your shortened URLs and sends notifications to a Discord webhook.

## Features
- Real-time logging of URL visits to your Discord channel
- Capture visitor details including IP address, user agent, and timestamp
- Easy installation and configuration

- Disclaimer Currently the Short URL function on the log does not display correctly and will show as /1 as of now we do not have a fix for this.

## Installation

### Standard Installation
2. In your YOURLS `user/plugins/` directory, create a new folder named `discord-logs`
3. Extract the contents of the downloaded zip file into the `discord-logs` folder you created
4. Navigate to your YOURLS admin panel: `https://YOUR-YOURLS-DOMAIN.TLD/admin/plugins.php`
5. Activate the "Discord Logs" plugin
6. Configure your Discord webhook URL in the plugin settings

### Linux SSH Installation
If you're connecting to your server via SSH, follow these steps:

1. Connect to your server using SSH:
   ```
   ssh username@your-server-ip
   ```

2. Navigate to your YOURLS plugins directory:
   ```
   cd /path/to/yourls/user/plugins/
   ```

4. Download the plugin files:
   ```
   git clone https://github.com/Sharky999/YOURLS-Discord-Log discord-logs
   ```

5. Set proper permissions:
   ```
   chmod -R 755 .
   ```

6. Access your YOURLS admin panel and activate the plugin

## Configuration

1. Create a Discord webhook in your server's channel settings:
   - Open Discord and go to the server where you want notifications
   - Right-click on a text channel and select "Edit Channel"
   - Go to "Integrations" and then "Webhooks"
   - Click "New Webhook", give it a name, and click "Copy Webhook URL"

2. In your YOURLS admin panel:
   - Navigate to Plugins → Manage Plugins
   - Find the Discord Logs plugin and click on its settings
   - Paste your webhook URL in the provided field
   - Save settings

3. Test the integration by visiting one of your shortened URLs

## Troubleshooting

- Ensure your server can make outbound HTTPS requests to Discord
- Check your server's error logs if notifications aren't being received
- Verify that your webhook URL is correct and the associated Discord channel exists

## Support

For questions or issues, please open an issue on the GitHub repository: https://github.com/Sharky999/YOURLS-Discord-Log/issues
