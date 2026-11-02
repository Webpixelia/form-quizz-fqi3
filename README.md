# Form Quizz FQI3

A powerful WordPress plugin for creating and managing interactive quizzes with premium features.

## Description

Form Quizz FQI3 is a complete solution for adding interactive quizzes to your WordPress site. With an intuitive interface and advanced features, this plugin allows you to easily create, manage, and analyze the performance of your quizzes.

## Key Features

- Unlimited quiz question creation
- Advanced scoring system
- Difficulty level management
- Detailed performance statistics
- Badges and rewards system
- WordPress user integration
- Multilingual support
- Intuitive admin interface
- Gutenberg block editor compatible
- REST API for third-party integrations

## Requirements

- WordPress 5.0 or higher
- PHP 8.0 or higher
- MySQL 5.7.8 or higher, or MariaDB 10.2.7 or higher (the questions table uses a `JSON` column)

## Installation

1. Download the plugin from the GitHub repository
2. Install it via the "Plugins" > "Add New" menu in your WordPress dashboard
3. Activate the plugin
4. Go to the "Form Quizz" section in your admin menu to start configuration

## Configuration

1. Navigate to "Form Quizz" > "Settings"
2. Configure general options according to your needs
3. Create your first quiz levels
4. Add questions and answers
5. Customize display settings
6. (Optional) Enable add-on installation and updates: define your GitHub personal token in `wp-config.php`:

   ```php
   define( 'GITHUB_PERSONAL_TOKEN', 'your_token_here' );
   ```

   The plugin never writes to `wp-config.php`. The "License" settings screen only shows whether the constant is defined (last 4 characters).

## Usage

### Available Shortcodes

- `[fqi3_quizz_form]` - Displays the main quiz form (`[free_quiz_form]` is a legacy alias kept for backward compatibility)
- `[fqi3_remaining_attempts]` - Shows the remaining free quiz attempts for the day
- `[fqi3_user_statistics]` - Shows detailed statistics of the current user
- `[fqi3_comparative_statistics]` - Shows comparative statistics between users
- `[fqi3_current_user_badges]` - Shows the badges earned by the current user
- `[fqi3_periodic_stats]` - Shows weekly and monthly statistics

A Gutenberg block is also available for the quiz form. The "Infos" admin page lists every shortcode with its description.

### Access model

- **Visitors** (not logged in) only see, and can only request, the levels flagged as *free*.
- **Logged-in users** can access the other levels. Users with the `subscriber` role are limited by the "Number of free trials per day" setting; Premium members are not.
- The daily counter of logged-in users is stored server-side (user meta, reset each day in the site timezone), not in a cookie. One quiz started consumes one trial.
- Who may manage questions (add, edit, export) is controlled by the allowed roles in the access settings, on top of the `edit_posts` capability.

### Hooks and Filters

The plugin provides several hooks and filters for advanced customization. Please refer to the developer documentation for more information.

## Extensions

Form Quizz FQI3 supports several extensions to enhance its functionality:

- Email marketing module
- Social login
- Premium subscriptions
- Notifications system

Add-ons are installed from the "Add-ons" admin page and require the `GITHUB_PERSONAL_TOKEN` constant (see Configuration).

## Development

### Project Structure

```
form-quizz-fqi3/
├── includes/                 # Core plugin files
│   ├── admin/               # Admin files
│   ├── Utils/               # Utilities (Gutenberg blocks helpers)
│   └── ...
├── assets/                  # Static files (JS, CSS, images), no build step: files are served as is
├── languages/               # Translation files
└── templates/               # Customizable templates
```

### Dependencies

- PHP 8.0+
- Composer (optional, for development tooling)

There is no Node.js build step: the JavaScript and CSS files under `assets/` are loaded as they are.

### Scheduled tasks (WP-Cron)

- `fqi3_weekly_statistics_event`: weekly statistics, every Sunday 23:59.
- `fqi3_monthly_statistics_event`: monthly statistics, on the 1st of each month. WordPress has no monthly recurrence, so each run schedules the next one.
- `fqi3_purge_attempts_event`: daily purge of old quiz attempts.
- `fqi3_send_daily_email`: daily e-mail.

These events are removed when the plugin is deactivated and re-created when it is activated again.

## Security

All user input is sanitized and output is escaped. The plugin follows WordPress security best practices:

- Development rule: every admin action, AJAX handler and `admin-post` handler must check a capability **and** a nonce. A nonce is never a substitute for a permission check.
- REST API access uses an API token (stored encrypted, sent in the `X-API-Token` header) with scopes and optional rate limiting.
- Import/export is restricted to administrators. Before an import replaces data, the existing data is saved to `wp-content/uploads/fqi3_backups/`. If the import fails, the database changes are rolled back.
- The `fqi3_backups` and `fqi3_reports` folders contain an `index.php` and an Apache `.htaccess` deny rule and use unguessable file names. **On nginx, `.htaccess` is ignored**: add a rule denying access to these two folders, for example:

  ```nginx
  location ~* /uploads/fqi3_(backups|reports)/ { deny all; }
  ```

- Quiz scoring happens in the browser: scores sent by the client are informational. Do not use them for anything that needs to be tamper-proof (certificates, rewards with a monetary value) without an additional server-side check.

## Upgrade notes (unreleased, 3.3.1)

- The Premium Member role is no longer removed when the plugin is deactivated. It is removed on uninstall only if "Delete data on uninstall" is enabled.
- The GitHub token is no longer saved from the settings screen. Define `GITHUB_PERSONAL_TOKEN` in `wp-config.php` (sites that already have it keep working).
- Importing settings no longer replaces the current API token.
- After an import, new questions continue from the current auto-increment value instead of restarting at 1.
- Daily limit: one quiz started now consumes exactly one trial (the previous cookie-based counter could consume two). Counters start from zero after the update.
- The number of questions per quiz now follows the "Number of questions per quiz" setting.
- Monthly statistics are now actually scheduled.

## Support

For support, please open an issue on our [GitHub repository](https://github.com/Webpixelia/form-quizz-fqi3).

## License

GPL-2.0+

## Credits

Developed with ❤️ by [Webpixelia](https://webpixelia.com)
