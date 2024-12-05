=== Form Quizz FQI3 ===
Contributors: jonathan, webpixelia
Donate link: https://buymeacoffee.com/webpixelia
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl.html
Tags: Quiz Form
Requires at least: 5.6
Tested up to: 6.7.1
Stable tag: 2.2.0
Requires PHP: 8.0

Create engaging, multi-level quizzes quickly and easily with full control over questions, stats, and gamification.  

== Description ==  

**Form Quizz FQI3** is the ultimate plugin for creating multi-level quizzes in the form of multiple-choice questions (MCQs).  

Simply add the shortcode **[free_quiz_form]** to your post or page, and your quiz will work out of the box! Participants will receive real-time feedback on their answers, and at the end of the quiz, a detailed summary is displayed, highlighting incorrect answers alongside the correct ones.  

This plugin provides a wealth of options to customize the quiz experience for both admins and users.  

### Key Features  
- **Real-Time Feedback**: Participants are informed immediately whether their answer is correct or not.  
- **Detailed End-of-Quiz Summary**: Display incorrect answers alongside the correct ones for easy review.  
- **Multi-Level Quizzes**: Easily configure different levels for a progressive challenge.  
- **Gamification**: Assign badges and track user progress for enhanced engagement.  
- **Responsive Design**: Fully compatible with RTL and optimized for mobile and desktop users.  

### Available Shortcodes  
Your plugin offers six shortcodes to easily integrate quizzes and stats into your site:  

1. **[free_quiz_form]**: Displays the main quiz interface.  
2. **[fqi3_user_stats]**: Displays individual user statistics for logged-in users.  
3. **[fqi3_global_stats]**: Displays overall quiz statistics for all users.  
4. **[fqi3_badges]**: Showcases user-earned badges and achievements.  
5. **[fqi3_quiz_history]**: Displays a logged-in user's quiz history.  
6. **[fqi3_periodic_stats]**: Shows advanced periodic statistics for the current user.  

### Admin Options  
- **Manage Questions**: Quickly create, edit, and delete questions via an intuitive admin interface.  
- **Customizable Settings**:  
  - Specify the **number of questions per quiz** (minimum: 4).  
  - Define the **number of free attempts per day** (default: 3).  
  - Configure **level settings** and **badges**.  
  - Enable or disable **email notifications** to inform free users when new attempts are available.  
  - Control **social sharing** of results (available for logged-in users only).  
  - Turn off **statistics tracking** for privacy-conscious setups.  
  - Set up a link to a **sales page** for premium features.  

- **Advanced Statistics**:  
  - View personal stats and compare them to other users.  
  - Track user progress and quiz performance over time.  
  - Export user stats and quiz results.  

- **Administration Access Control**: Restrict admin capabilities to certain user roles.  
- **Import/Export Settings**: Easily migrate plugin settings between sites.  
- **API Integration**: Use the API to interact with quizzes programmatically.  

### Additional Features  
- **Free Attempt Management**: Specify the maximum number of free attempts per user per day.  
- **Gamification Elements**: Configure badges and awards for users based on their performance.  
- **Custom Email Notifications**: Customize email templates for user notifications.  
- **Admin Stats Dashboard**: Gain insights into overall quiz performance and user activity.  

This plugin is designed to be flexible, scalable, and user-friendly, making it perfect for educators, businesses, and anyone who wants to engage users with quizzes.  

== Installation ==  

1. Upload the plugin files to the `/wp-content/plugins/form-quizz-fqi3` directory, or install the plugin directly through the Github repository.  
2. Activate the plugin through the 'Plugins' screen in WordPress.  
3. Go to the plugin's settings page to configure your options.  

== Frequently Asked Questions ==  

= How do I display a quiz on my site? =  
Simply insert the shortcode **[free_quiz_form]** into any post or page.  

= Can I customize the number of questions in each quiz? =  
Yes, you can specify the number of questions per quiz in the plugin's settings.  

= Are user statistics saved? =  
Statistics are saved for logged-in users only, unless you disable this feature in the settings.  

= Does the plugin support RTL languages? =  
Yes, the plugin fully supports right-to-left (RTL) languages. 

== Links ==
* [Support](https://webpixelia.com)

== Support us ==
⭐️⭐️⭐️⭐️⭐️ If you like this plugin, please give me a 5 star rating. This will motivate me to develop new features and write other plugins.

☕️ If you want buy me a coffee, you can do it here : [Buy me a coffee](https://buymeacoffee.com/webpixelia) ☕️

== Screenshots ==
1. Questions page View
2. Add Question page View
3. Edit Question page View
4. Statistics page View
5. Settings page View
6. Import/Export page View
7. Guide and Changelog page View

== Changelog ==
= 2.2.0 =
* New: Added a dashboard page for a quick overview of key information.
* Added: New option to include administrators in premium role features for testing purposes.
* Added: Download by the user the "Incorrect Answers" table as a CSV file.
* Modified: `fqi3_access_roles` database option structure
* Improved: UI/UX with customizable, responsive modals for a more seamless question deletion confirmation.
* Fixed: Display bug in the badge legend table

= 2.1.0 =
* Added: Option to set the number of answer choices for questions, with a range between 4 and 10.
* Enhanced: RTL support for forms
* Changed: Default option management to use the global fqi3_default_options() function instead of class-specific methods
* Fixed: SQL query preparation to comply with WordPress database query best practices
* Fixed: Call to undefined function during uninstallation
* Fixed: Fatal error due to private method used as callback during activation
* Fixed: Fatal error when quiz levels option is not initialized by ensuring a default empty array is returned
* Fixed: Fatal error in access control settings
* Fixed: Issue where plugin options were not properly initialized during uninstallation
* Updated: Readme.txt file

= 2.0.1 =
* New: Updates are now managed through GitHub releases, ensuring seamless updates directly from the repository.

= 2.0.0 =
* New Feature: Added a [fqi3_periodic_stats] shortcode to display advanced periodic statistics for the current user on posts, pages, or widgets.
* New: Displayed incorrectly answered questions at the end of the quiz, along with the user's answers and the correct answers.
* Added: Two new data exports: 'Performance' and 'Advanced Stats'
* Improved: Reviewed administration UI with Bootstrap for enhanced consistency and user experience
* Improved: Refactored FQI3 plugin classes and page classes for better performance, readability, and maintainability.
* Fixed: Resolved index issue during the saving of levels and badges.
* Fixed: Resolved issues with the add and remove buttons not functioning correctly.
* Fixed: Resolved issue with Level ID value sanitization.
* Fixed: Missing "Access" column in the exported quizzes CSV file.
* Support for WordPress 6.7.

= 1.6.0 =
* New Feature: Added REST API for accessing quiz data, with routes accessible via token.
* Added: Ability to copy the shortcode directly by clicking in the Guide and Changelog pages.
* Added: Automatic deletion of transients related to user statistics upon plugin uninstallation.
* Added support for translatable strings via WPML in wpml-config.xml file.
* Improved: Refined the FQI3_Awards class for enhanced validation , error handling and security
* Improved: Refactored JavaScript admin and frontend files to modern standards with enhanced performance, organization, and security.
* Improved: Refactored fqi3_render_consultation_page method into smaller, reusable functions for better readability and maintainability.
* Fixed: Corrected calculation for accurate user performance metrics.
* Fixed: Bug where the "Disable statistics" option didn't properly prevent saving and displaying statistics to users.
* Fixed: Missing nonce for export data.
* Fixed: Excluded question IDs from export.
* Fixed: Incorrect redirect URL in export form.

= 1.5.1 =
* Added: Display existing badges along with descriptions and how to earn them below the Awarded Badges section.
* Added: New option to enable or disable the display of the existing badge legend for users.
* Improved: Optimized autoload settings for plugin options.
* Changed: Updated the class definition for FQI3_Import_Export_Page
* Fixed: Corrected database errors related to multiple primary keys and SQL syntax issues during the creation of the wp_fqi3_awards table.
* Fixed: Corrected SQL syntax issues when altering the wp_fqi3_awards table to ensure valid queries are executed.
* Fixed: Display of level label instead of level ID in the output generated by the [fqi3_comparative_statistics] shortcode.
* Fixed: CSS bugs
* Fixed: URL not passed to CTA in the rendering of [fqi3_remaining_attempts]

= 1.5.0 =
* New Feature: Gamification system with badges to reward users based on quiz performance.
* New Feature: Introduced a new shortcode [fqi3_current_user_badges] to display the badges awarded to the currently logged-in user across posts, pages, or widgets.
* New Feature: Animations, including a confetti effect, triggered by achieving scores >= 80% in quizzes.
* New Feature: Ability to configure quiz levels directly from the admin interface.
* New Feature: Import/Export system for exporting questions, levels, badges, and options, and importing them via JSON.
* Added: PHP version check to require a minimum of 8.0.0.
* Added: Display the users statistics in the admin area.
* Added: Button to export questions to CSV format in the View Questions page
* Added: Search field in the View Questions page
* Improved: Standardized level management.
* Improved: Adapted class structures to PHP 8, including property promotion and strict typing.
* Fixed: Corrected the check for the 'fqi3_disable_statistics' option to ensure the error message displays correctly.
* Fixed: Bug with the daily free quiz attempt count for logged-in non-member users.

= 1.4.0 =
* Added: Notification emails titled 'Your Free Quiz Attempts Have Been Reset' with configurable options.
* Added: Introduced access control for roles with the capability to publish posts.
* Improved: New tabbed interface for plugin settings for better user experience.
* Improved: PHP code optimization and method organization across different settings sections.
* Fixed: Bug where the "Reset" button in the options page did not properly reset the values.

= 1.3.2 =
* Improved: Added dynamic progress bar and updated button functionality for quiz attempts in the shortcode.
* Improved: Added custom header and footer to all admin plugin pages.
* Fixed: Added the "Premium Member" role with restricted access to WordPress admin and disabled admin bar on the frontend for Premium users.

= 1.3.1 =
* Improved: Refactored the level selection logic to handle free and non-free levels based on user login status.
* Improved: Refactored admin pages generation code for better readability and maintainability.
* Added: Integration of a new "Changelog and User Guide" page in the admin dashboard to display the changelog and a user guide for the plugin.
* Fixed: Removed the incorrectly created `fqi3_content_options` entry from the options table.
* Fixed: Minor bugs.

= 1.3.0 =
* New Feature: Added charts and comparative statistics for premium users, including insights on total quizzes, correct answers, and success rates.
* New Feature: Introduced a shortcode [fqi3_user_statistics] to display detailed user statistics across posts, pages, or widgets.
* New Feature: Added a shortcode [fqi3_remaining_attempts] to show the number of remaining free quiz attempts with a user-friendly message.
* New Feature: Introduced a shortcode [fqi3_comparative_statistics] to display comparative statistics between users.
* New Feature: Added an admin option to enable or disable the statistics feature.
* Improved: Refactored statistics generation and validation logic for improved readability and maintainability.
* Improved: Updated the quiz database table name from fqi3 to fqi3_quizzes for enhanced clarity and consistency.
* Improved: Streamlined access to plugin page slugs using a new getter method, improving overall maintainability.
* Renamed: Updated the plugin name from "Free Quiz i3raab" to "Form Quizz FQI3," along with changes to class names, file names, text domain, constants, and associated hooks.
* Fixed: CSS and JavaScript conflicts with other WordPress admin pages have been resolved.
* Fixed: The "All" filter now correctly reflects the total number of questions, even when a level-specific filter is active.

= 1.2.2 =
* Added: Method initialization improvements, including the introduction of the fqi3_initialize_levels method to ensure proper initialization of quiz levels.
* Fixed: Minor bugs and improvements in JavaScript functionality.
* Improved: Enhanced information display when no questions are available in a level (UX improvement).
* Improved: PHP code optimization.

= 1.2.1 =
* Fixed: Resolved issues with certain options not being saved correctly
* Changed: Renamed option IDs for better consistency and clarity

= 1.2.0 =
* Added: Option to specify the number of free trials per day in the admin panel
* Added: Option to set the number of questions per quiz in the admin panel
* Added: Integration of a timer feature for users who want to enable it
* Added: Social media sharing options for quiz results (Facebook, X (formerly Twitter), LinkedIn)
* Added: Option to disable sharing quiz results on social media in the admin panel
* Added: Option to enable RTL (Right-to-Left) mode for languages like Arabic or Hebrew
* Fixed: Security improvement
* Fixed: Translations missing
* Improved: Enhanced the UI rendering of plugin options pages
* Improved: User experience (UX)

= 1.1.0 =
* Added: Access control option by role
* Added: Limited series of questions for non-logged-in users
* Added: Limit quiz attempts to 3 per day for users with the 'subscriber' role
* Added: Loading indicator for better user experience
* Added: Option to link to a specific sales page, with URL retrieval based on selected page ID
* Fixed: Bug with displaying the total number of questions
* Improved: Performance with the use of `fetch` for data retrieval
* Enhanced: Caching of quiz questions to improve performance

= 1.0.0 =
* Initial release