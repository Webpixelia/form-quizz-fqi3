<?php
namespace Form_Quizz_FQI3;
/**
 * Class FQI3_Infos_Page
 *
 * Responsible for rendering the "Changelog and User Guide" page in the WordPress admin for the Form Quizz FQI3 plugin.
 *
 * This class handles two main functionalities:
 * 1. Renders the user guide, providing information about the plugin and shortcodes available for use.
 * 2. Displays the changelog, retrieving content from the `readme.txt` file, extracting version history, and formatting it
 *    as HTML. It also manages error handling if the changelog file cannot be read or processed correctly.
 *
 * The changelog is sorted in descending order by version number, and only the latest six entries are shown.
 *
 * @package Form_Quizz_FQI3
 * @subpackage Admin Pages
 * @since      1.0.0
 * @version    2.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if ( ! class_exists( 'FQI3_Infos_Page' ) ) :
    class FQI3_Infos_Page {
        /**
         * @var array Cached shortcode data
         */
        private array $shortcodes;
    
        /**
         * Constructor to initialize the class
         */
        public function __construct() {
            // Initialize properties to avoid "must not be accessed before initialization" error
            $this->shortcodes = [];
            
            add_action('init', [$this, 'init_shortcodes']);
        }
    
        /**
         * Initialize shortcode data
         */
        public function init_shortcodes(): void {
            $this->shortcodes = [
                'fqi3_quizz_form' => [
                    'description' => __('Render the main quiz form interface.', 'form-quizz-fqi3'),
                    'deprecated'  => false,
                ],
                'free_quiz_form' => [
                    'description' => __('Legacy alias for the quiz form (backward compatibility). Prefer [fqi3_quizz_form].', 'form-quizz-fqi3'),
                    'deprecated'  => true,
                ],
                'fqi3_user_statistics' => [
                    'description' => __('Display detailed user statistics across posts, pages, or widgets.', 'form-quizz-fqi3'),
                    'deprecated'  => false,
                ],
                'fqi3_remaining_attempts' => [
                    'description' => __('Show the number of remaining free quiz attempts with a user-friendly message.', 'form-quizz-fqi3'),
                    'deprecated'  => false,
                ],
                'fqi3_comparative_statistics' => [
                    'description' => __('Display comparative statistics between users.', 'form-quizz-fqi3'),
                    'deprecated'  => false,
                ],
                'fqi3_current_user_badges' => [
                    'description' => __('Display the badges awarded to the currently logged-in user.', 'form-quizz-fqi3'),
                    'deprecated'  => false,
                ],
                'fqi3_periodic_stats' => [
                    'description' => __('Display the periodic statistics (weekly and monthly) of a user, including success rates and quiz performance by level.', 'form-quizz-fqi3') . self::get_new_badge(),
                    'deprecated'  => false,
                ],
            ];
        }

        /**
        * Render New badge
        */
        public static function get_new_badge(): string {
            return ' <span class="badge text-bg-warning">' . __('New', 'form-quizz-fqi3') . '</span>';
        }
    
        /**
         * Render the admin page
         */
        public function render_infos_page(): void {
            ?>
            <div class="wrap container-fluid">
                <h2 class="wp-heading-inline page-title"><?php _e('Changelog and User Guide', 'form-quizz-fqi3'); ?></h2>
                
                <?php
                $this->render_user_guide_section();
                $this->render_changelog_section();
                ?>
            </div>
            <?php
        }
    
        /**
         * Render the user guide section
         */
        private function render_user_guide_section(): void {
            ?>
            <div id="guide_doc" class="fqi3-section-options">
                <h3><?php _e('User Guide', 'form-quizz-fqi3'); ?></h3>
                <hr>
                <p><?php _e('Here you can find the user guide for the plugin.', 'form-quizz-fqi3'); ?></p>
                
                <h4><?php _e('Available Shortcodes:', 'form-quizz-fqi3'); ?></h4>
                <ul>
                    <?php $this->render_shortcodes_list(); ?>
                </ul>

                <h4 class="mt-4"><?php _e('Gutenberg Blocks (Server‑Rendered):', 'form-quizz-fqi3'); ?><?php echo self::get_new_badge(); ?></h4>
                <p><?php _e('These blocks are available in the custom category “Form Quizz FQI3”. They render on the server and mirror the shortcodes for better editor UX.', 'form-quizz-fqi3'); ?></p>
                <ul>
                    <li><strong><?php _e('Quiz Form', 'form-quizz-fqi3'); ?></strong> — <code>fqi3/quiz-form</code> → <code>[fqi3_quizz_form]</code></li>
                    <li><strong><?php _e('Remaining Attempts', 'form-quizz-fqi3'); ?></strong> — <code>fqi3/remaining-attempts</code> → <code>[fqi3_remaining_attempts]</code></li>
                    <li><strong><?php _e('User Statistics', 'form-quizz-fqi3'); ?></strong> — <code>fqi3/user-statistics</code> → <code>[fqi3_user_statistics]</code></li>
                    <li><strong><?php _e('Comparative Statistics', 'form-quizz-fqi3'); ?></strong> — <code>fqi3/comparative-statistics</code> → <code>[fqi3_comparative_statistics]</code></li>
                    <li><strong><?php _e('Current User Badges', 'form-quizz-fqi3'); ?></strong> — <code>fqi3/current-user-badges</code> → <code>[fqi3_current_user_badges]</code></li>
                    <li><strong><?php _e('Periodic Statistics', 'form-quizz-fqi3'); ?></strong> — <code>fqi3/periodic-stats</code> → <code>[fqi3_periodic_stats]</code></li>
                </ul>
                <p class="description"><?php _e('Hooks are documented in the Developer → Hooks section below.', 'form-quizz-fqi3'); ?></p>

                <div class="notice notice-info inline">
                    <p><strong><?php _e('Attempts storage', 'form-quizz-fqi3'); ?>:</strong> <?php _e('Core creates a lightweight table wp_fqi3_quiz_attempts with columns (id, user_id, level, score, started_at, completed_at). The current attempt_id is also passed via AJAX responses for frontend integrations.', 'form-quizz-fqi3'); ?></p>
                </div>

                <h4 class="mt-4"><?php _e('Admin: Attempts (Audit)', 'form-quizz-fqi3'); ?><?php echo self::get_new_badge(); ?></h4>
                <p><?php _e('An admin page is available to audit quiz attempts.', 'form-quizz-fqi3'); ?></p>
                <ul>
                    <li><strong><?php _e('Location', 'form-quizz-fqi3'); ?>:</strong> <?php _e('Form Quizz FQI3 → Attempts (Audit)', 'form-quizz-fqi3'); ?></li>
                    <li><strong><?php _e('Filters', 'form-quizz-fqi3'); ?>:</strong> <?php _e('Level (dropdown from fqi3_quiz_levels), User ID, Date range (From/To)', 'form-quizz-fqi3'); ?></li>
                    <li><strong><?php _e('Columns', 'form-quizz-fqi3'); ?>:</strong> <?php _e('ID, User, Level, Score, Started, Completed', 'form-quizz-fqi3'); ?></li>
                    <li><strong><?php _e('Pagination', 'form-quizz-fqi3'); ?>:</strong> <?php _e('Built-in with per‑page controls (Screen Options)', 'form-quizz-fqi3'); ?></li>
                    <li><strong><?php _e('Export', 'form-quizz-fqi3'); ?>:</strong> <?php _e('CSV export respects active filters', 'form-quizz-fqi3'); ?></li>
                </ul>
                <?php if (is_plugin_active('form-quizz-fqi3-question-bank-addon/form-quizz-fqi3-question-bank-addon.php')): ?>
                <h4 class="mt-4"><?php _e('Question Bank Pro (Add‑on): Core Settings Integration', 'form-quizz-fqi3'); ?><?php echo self::get_new_badge(); ?></h4>
                <p class="description"><?php _e('When the Question Bank Pro add‑on is active, a new “Question Bank” tab appears inside the core Settings page.', 'form-quizz-fqi3'); ?></p>
                <ul>
                    <li><strong><?php _e('Where', 'form-quizz-fqi3'); ?>:</strong> <?php _e('Form Quizz FQI3 → Settings → Question Bank', 'form-quizz-fqi3'); ?></li>
                    <li><strong><?php _e('Fields', 'form-quizz-fqi3'); ?>:</strong> <?php _e('Enable Question Bank, Allowed roles, Levels config (JSON).', 'form-quizz-fqi3'); ?></li>
                    <li><strong><?php _e('Storage', 'form-quizz-fqi3'); ?>:</strong> <code>fqi3_qb_settings</code> <?php _e('(saved through the core form).', 'form-quizz-fqi3'); ?></li>
                </ul>
                <div class="notice notice-info inline">
                    <p><strong><?php _e('Example JSON', 'form-quizz-fqi3'); ?>:</strong> <code>{"free":{"max":10,"mode":"balanced"},"pro":{"max":15,"mode":"strict"}}</code></p>
                </div>
                <?php endif; ?>
            </div>
            <?php
        }
    
        /**
         * Render the shortcodes list
         */
        private function render_shortcodes_list(): void {
            // Ensure shortcodes are initialized
            if (empty($this->shortcodes)) {
                $this->init_shortcodes();
            }
            
            foreach ($this->shortcodes as $shortcode => $data) {
                $description = $data['description'] ?? '';
                $deprecated  = !empty($data['deprecated']);
                ?>
                <li>
                    <?php if (!$deprecated) : ?>
                        <span class="copy-icon" data-shortcode="[<?php echo esc_attr($shortcode); ?>]">
                            <i class="bi bi-copy" title="<?php esc_attr_e('Copy shortcode', 'form-quizz-fqi3'); ?>"></i>
                        </span>
                    <?php else : ?>
                        <span class="badge text-bg-danger"><?php _e('Deprecated', 'form-quizz-fqi3'); ?></span>
                    <?php endif; ?>

                    <strong>[<?php echo esc_html($shortcode); ?>]</strong>
                    <?php echo wp_kses_post($description); ?>
                </li>
                <?php
            }
        }

        /**
         * Renders the changelog section in the admin interface
         * 
         * @return void
         */
        private function render_changelog_section(): void {
            ?>
            <div id="changelog_doc" class="fqi3-section-options overflow-y h500">
                <h3><?php _e('Changelog', 'form-quizz-fqi3'); ?></h3>
                <hr>
                <div class="changelog-container">
                    <?php 
                    echo '<div class="changelog-content">';
                    echo self::get_changelog();
                    echo '</div>';
                    ?>
                </div>
            </div>
            <?php
        }
    
        /**
         * Get and parse changelog content
         *
         * @return string Formatted changelog HTML
         */
        public static function get_changelog(string $path = FQI3_PATH): string {
            try {
                //$readme_file = FQI3_PATH . '/readme.txt';
                $readme_file = rtrim($path, '/') . '/readme.txt';
                if (!is_readable($readme_file)) {
                    throw new \Exception(__('Changelog file is not readable.', 'form-quizz-fqi3'));
                }
    
                $readme_content = file_get_contents($readme_file);
                if ($readme_content === false) {
                    throw new \Exception(__('Failed to read changelog file.', 'form-quizz-fqi3'));
                }
    
                return self::parse_changelog($readme_content);
            } catch (\Exception $e) {
                return sprintf('<p class="alert alert-danger">%s</p>', esc_html($e->getMessage()));
            }
        }      
    
        /**
         * Parse changelog content from readme file
         *
         * @param string $content Raw readme content
         * @return string Formatted changelog HTML
         */
        private static function parse_changelog(string $content): string {
            $changelog_pattern = '/== Changelog ==(.*?)(?=== |$)/s';
            if (!preg_match($changelog_pattern, $content, $matches)) {
                return '<p>' . __('No changelog section found.', 'form-quizz-fqi3') . '</p>';
            }
    
            $changelog_content = $matches[1];
            $version_pattern = '/=\s*(\d+\.\d+\.\d+)\s*=(.*?)(?=\=\s*\d+\.\d+\.\d+\s*\=|$)/s';
            
            preg_match_all($version_pattern, $changelog_content, $matches, PREG_SET_ORDER);
            
            if (empty($matches)) {
                return '<p>' . __('No version entries found.', 'form-quizz-fqi3') . '</p>';
            }
    
            $changelog_html = '';
            $count = 0;
            
            foreach ($matches as $match) {
                if ($count >= 6) break;
                
                $version = esc_html($match[1]);
                $changes = nl2br(esc_html(trim($match[2])));

                $version_label = $count === 0 
                    ? sprintf('Version %s %s', $version, self::get_new_badge())
                    : sprintf('Version %s', $version);
            
                $changelog_html .= sprintf(
                    '<h4 class="changelog-version">%s</h4><div class="changelog-entry">%s</div>',
                    $version_label,
                    $changes
                );
                
                $count++;
            }
    
            return $changelog_html ?: '<p>' . __('No changelog entries found.', 'form-quizz-fqi3') . '</p>';
        }
    }

endif;