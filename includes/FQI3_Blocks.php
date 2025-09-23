<?php
namespace Form_Quizz_FQI3;
/**
 * FQI3 Blocks Main Class
 *
 * This class handles the registration of blocks for the Form Quizz FQI3 plugin.
 * It registers the quiz form block, remaining attempts block, and other shortcode blocks
 * for use in the Gutenberg editor.
 *
 * @package Form Quizz FQI3
 * @since 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use Form_Quizz_FQI3\Utils\BlocksUtils;

if (!class_exists('FQI3_Blocks')) :
    class FQI3_Blocks {
        private $frontend;
        private $statistics;
        private $periodicStatistics;
        
        private const COMMON_SUPPORTS = [
            'html' => false,
            'align' => true,
            'color' => [
                'text' => true,
                'background' => true,
                'gradients' => true,
            ],
            'spacing' => [
                'margin' => true,
                'padding' => true,
            ],
            'typography' => [
                'fontFamily' => true,
                'fontSize' => true,
                'fontStyle' => true,
                'fontWeight' => true,
                'letterSpacing' => true,
                'textDecoration' => true,
                'textTransform' => true,
                'lineHeight' => true,
            ],
            'dimensions' => [
                'width' => true,
                'height' => true,
            ],
            '__experimentalBorder' => [
                'radius' => true,
                'width' => true,
                'color' => true,
                'style' => true,
            ],
            '__experimentalShadow' => true,
        ];

        private static array $QUIZ_FORM_DEFAULTS = [
            'showTimer' => false,
            'timerDuration' => 5,
            'showSocialShare' => true,
            'showIncorrectAnswers' => true,
            'welcomeMessage' => '',
        ];        

        public function __construct(FQI3_Frontend $frontend, FQI3_Statistics $statistics, FQI3_PeriodicStatistics $periodicStatistics) {
            self::$QUIZ_FORM_DEFAULTS['welcomeMessage'] = __('Welcome to the quiz', 'form-quizz-fqi3');
            $this->frontend = $frontend;
            $this->statistics = $statistics;
            $this->periodicStatistics = $periodicStatistics;
            $this->registerBlocks();
            add_filter('block_categories_all', [$this, 'addBlockCategory'], 10, 1);
        }

        /**
         * Set blocks with shortcodes and callbacks
         * 
         * @return array
         */
        private function getShortcodeBlocks(): array {
            $blocks = [
                'fqi3/remaining-attempts' => [
                    'shortcode' => 'fqi3_remaining_attempts',
                    'callback' => [$this, 'renderRemainingAttemptsBlock']
                ],
                'fqi3/user-statistics' => [
                    'shortcode' => 'fqi3_user_statistics',
                    'callback' => [$this, 'renderUserStatisticsBlock']
                ],
                'fqi3/comparative-statistics' => [
                    'shortcode' => 'fqi3_comparative_statistics',
                    'callback' => [$this, 'renderComparativeStatisticsBlock']
                ],
                'fqi3/current-user-badges' => [
                    'shortcode' => 'fqi3_current_user_badges',
                    'callback' => [$this, 'renderCurrentUserBadgesBlock']
                ],
                'fqi3/periodic-stats' => [
                    'shortcode' => 'fqi3_periodic_stats',
                    'callback' => [$this, 'renderPeriodicStatsBlock']
                ],
            ];

            /**
             * Hook to allow addons to add their blocks
             */
            return apply_filters('fqi3_shortcode_blocks', $blocks);
        }

        private function getQuizFormAttributes(): array {
            return [
                'showTimer' => ['type' => 'boolean', 'default' => self::$QUIZ_FORM_DEFAULTS['showTimer']],
                'timerDuration' => ['type' => 'number', 'default' => self::$QUIZ_FORM_DEFAULTS['timerDuration']],
                'showSocialShare' => ['type' => 'boolean', 'default' => self::$QUIZ_FORM_DEFAULTS['showSocialShare']],
                'showIncorrectAnswers' => ['type' => 'boolean', 'default' => self::$QUIZ_FORM_DEFAULTS['showIncorrectAnswers']],
                'welcomeMessage' => ['type' => 'string', 'default' => self::$QUIZ_FORM_DEFAULTS['welcomeMessage']],
            ];
        }

        /**
         * Extract Gutenberg styles and return them in a common format
         * 
         * @return array An array containing 'classes' and 'styles' keys
         */
        private function getGutenbergStyles(): array {
            $gutenberg_styles = BlocksUtils::extractGutenbergStyles();
            
            return [
                'custom_box_classes' => $gutenberg_styles['classes'] ?? '',
                'custom_box_styles' => $gutenberg_styles['styles'] ?? '',
            ];
        }

        /**
         * Render template with common variables
         * 
         * @param string $template_path Path to the template file
         * @param array $vars Array of variables to pass to the template
         * @return string Rendered template HTML
         */
        private function renderTemplate(string $template_path, array $vars = []): string {
            $common_vars = $this->getGutenbergStyles();
            
            // Merge specific variables with common ones
            $template_vars = array_merge($common_vars, $vars);
            
            // Extraire les variables pour le template
            extract($template_vars);
            
            ob_start();
            include $template_path;
            return ob_get_clean();
        }

        /**
         * Register Gutenberg blocks for core plugin shortcodes (SSR)
         * 
         * @return void
         */
        public function registerBlocks(): void {
            if (!function_exists('register_block_type')) {
                return;
            }

            wp_register_script(
                'fqi3-blocks-editor',
                FQI3_ASSETS . '/js/fqi3-blocks-editor.js',
                ['wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-server-side-render', 'wp-components'],
                FQI3_VERSION,
                true
            );

            wp_set_script_translations(
                'fqi3-blocks-editor',
                'form-quizz-fqi3',
                FQI3_PATH . 'languages'
            );        

            $this->registerQuizFormBlock();
            $this->registerShortcodeBlocks();
        }

        /**
         * Register the quiz form block
         * 
         * @return void
         */
        private function registerQuizFormBlock(): void {
            register_block_type('fqi3/quiz-form', [
                'api_version' => 2,
                'editor_script' => 'fqi3-blocks-editor',
                'render_callback' => [$this, 'renderQuizFormBlock'],
                'attributes' => $this->getQuizFormAttributes(),
                'supports' => self::COMMON_SUPPORTS
            ]);
        }

        /**
         * Render the quiz form block
         * 
         * @param array $attributes Block attributes
         * @param string $content Block content
         * @return string Rendered block HTML
         */
        public function renderQuizFormBlock($attributes = [], $content = ''): string {
            $attrs = array_merge(self::$QUIZ_FORM_DEFAULTS, $attributes);

            // Data attributes for JS
            $data_attrs = sprintf(
                'data-show-timer="%s" data-timer-duration="%d" data-show-social-share="%s" data-show-incorrect-answers="%s" data-welcome-message="%s"',
                $attrs['showTimer'] ? 'true' : 'false',
                intval($attrs['timerDuration']),
                $attrs['showSocialShare'] ? 'true' : 'false',
                $attrs['showIncorrectAnswers'] ? 'true' : 'false',
                esc_attr($attrs['welcomeMessage'])
            );

            // Variables for the template
            $template_vars = [
                'data_attrs' => $data_attrs,
                'text_pre_form' => $attrs['welcomeMessage'],
                'title_form' => __('Choose level :', 'form-quizz-fqi3'),
                'levels' => $this->frontend->getLevelsQuiz() ?? [],
                'is_user_logged_in' => is_user_logged_in(),
                'current_user' => wp_get_current_user(),
                'translations' => $this->frontend->getTranslations(),
                'timer_settings' => [
                    'enabled' => $attrs['showTimer'] ?? false,
                    'duration' => intval($attrs['timerDuration'] ?? 5),
                ],
                'showTimer' => $attrs['showTimer'],
                'sharing_enabled' => $attrs['showSocialShare'],
                'show_incorrect_answers' => $attrs['showIncorrectAnswers'],
                'selected_page_url' => get_option('fqi3_sales_page_url', '#'),
            ];

            return $this->renderTemplate(FQI3_PATH . '/templates/quiz-form.php', $template_vars);
        }

        /**
         * Add block category
         * 
         * @param array $categories Array of block categories
         * @return array Modified array of block categories
         */
        public function addBlockCategory(array $categories): array {
            $slug = 'form-quizz-fqi3';
            
            // Check if the category already exists
            foreach ($categories as $cat) {
                if (!empty($cat['slug']) && $cat['slug'] === $slug) {
                    return $categories;
                }
            }
            
            $categories[] = [
                'slug' => $slug,
                'title' => __('Form Quizz FQI3', 'form-quizz-fqi3'),
                'icon' => null,
            ];
            
            return $categories;
        }

        /**
         * Register shortcode-based blocks
         * 
         * @return void
         */
        private function registerShortcodeBlocks(): void {
            $shortcode_blocks = $this->getShortcodeBlocks();
            
            foreach ($shortcode_blocks as $block_name => $config) {
                try {
                    // Support of both formats : old (string) and new (array)
                    if (is_string($config)) {
                        $config = ['shortcode' => $config];
                    }

                    // Skip if block is disabled via filter
                    if (isset($config['enabled']) && $config['enabled'] === false) {
                        continue;
                    }
                    
                    $callback = isset($config['callback']) 
                        ? $config['callback']
                        : $this->createShortcodeCallback($config['shortcode']);
                    
                    // Allow add-ons to modify block registration args
                    $block_args = apply_filters('fqi3_block_registration_args', [
                        'api_version' => 3,
                        'editor_script' => 'fqi3-blocks-editor',
                        'render_callback' => $callback,
                        'attributes' => $config['attributes'] ?? [],
                        'supports' => $config['supports'] ?? self::COMMON_SUPPORTS,
                    ], $block_name, $config);

                    register_block_type($block_name, $block_args);
                } catch (Exception $e) {
                    error_log(sprintf(
                        'Failed to register block %s: %s',
                        $block_name,
                        $e->getMessage()
                    ));
                    continue;
                }
            }
        }

        /**
         * Factory to create shortcode callbacks
         * 
         * @param string $shortcode Shortcode name
         * @return callable Callback function
         */
        private function createShortcodeCallback(string $shortcode): callable {
            return function($attributes = [], $content = '') use ($shortcode) {
                return do_shortcode('[' . $shortcode . ']');
            };
        }

        /**
         * Render the remaining attempts block
         * 
         * @param array $attributes Block attributes
         * @param string $content Block content
         * @return string Rendered block HTML
         */
        public function renderRemainingAttemptsBlock($attributes = [], $content = ''): string {
            $attempts_data = $this->frontend->getAttemptsData();
            $gutenberg_styles = BlocksUtils::extractGutenbergStyles();
            
            // Clean specific color classes for this block
            $custom_box_classes = BlocksUtils::cleanTextColorClasses($gutenberg_styles['classes']);
            $color = BlocksUtils::extractColorFromStyles($gutenberg_styles['styles']);

            $template_vars = [
                'custom_box_classes' => $custom_box_classes,
                'custom_box_styles' => $gutenberg_styles['styles'],
                'color' => $color,
                'is_premium_user' => $attempts_data['is_premium_user'],
                'infos_attempts' => $attempts_data['infos_attempts'],
                'sales_page_url' => $attempts_data['sales_page_url'],
            ];

            return $this->renderTemplate(FQI3_PATH . '/templates/remaining-attempts.php', $template_vars);
        }

        /**
         * Render the user statistics block
         * 
         * @param array $attributes Block attributes
         * @param string $content Block content
         * @return string Rendered block HTML
         */
        public function renderUserStatisticsBlock($attributes = [], $content = ''): string {
            $common_vars = $this->getGutenbergStyles();

            // Constructing content with render_statistics_table()
            return $this->statistics->render_statistics_table(
                $common_vars['custom_box_classes'],
                $common_vars['custom_box_styles'],
            );
        }

        /**
         * Render the comparative statistics block
         * 
         * @param array $attributes Block attributes
         * @param string $content Block content
         * @return string Rendered block HTML
         */
        public function renderComparativeStatisticsBlock($attributes = [], $content = ''): string {
            $common_vars = $this->getGutenbergStyles();

            // Constructing content with display_comparative_stats()
            return $this->statistics->display_comparative_stats(
                $common_vars['custom_box_classes'],
                $common_vars['custom_box_styles'],
            );
        }

        /**
         * Render the current user badges block
         * 
         * @param array $attributes Block attributes
         * @param string $content Block content
         * @return string Rendered block HTML
        */
        public function renderCurrentUserBadgesBlock($attributes = [], $content = ''): string {
            $badges = $this->statistics->get_awards_instance()->display_user_badges();
            $common_vars = $this->getGutenbergStyles();
            
            // Constructing content
            $content = [
                $this->statistics->render_badge_section_title($badges),
                $this->statistics->render_badge_cards($badges),
                $this->statistics->render_badges_legend(),
            ];

            // Constructing content with render_awards_container()
            return $this->statistics->render_awards_container(
                $content,
                $common_vars['custom_box_classes'],
                $common_vars['custom_box_styles'],
            );
        }

        /**
         * Render the periodic statistics block
         * 
         * @param array $attributes Block attributes
         * @param string $content Block content
         * @return string Rendered block HTML
         */
        public function renderPeriodicStatsBlock($attributes = [], $content = ''): string {
            $common_vars = $this->getGutenbergStyles();

            // Constructing content with displayPeriodicStatisticsShortcode()
            return $this->periodicStatistics->displayPeriodicStatisticsShortcode(
                $attributes,
                $common_vars['custom_box_classes'],
                $common_vars['custom_box_styles'],
            );
        }
    }
endif;