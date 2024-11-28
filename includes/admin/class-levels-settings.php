<?php
namespace Form_Quizz_FQI3;
/**
 * FQI3_Levels_Settings Class
 *
 * This class handles the badges settings of the FQI3 plugin.
 * It allows customization of badges content and settings.
 *
 * @package    Form Quizz FQI3
 * @subpackage Settings
 * @since      1.5.0
 * @version    1.5.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'FQI3_Levels_Settings' ) ) :

    class FQI3_Levels_Settings {

        public function register_settings() {
            $this->register_fields_settings();
            $this->add_settings_section();
        }

        public function register_fields_settings () {
            register_setting(
                'fqi3_options_group',
                'fqi3_quiz_levels',
                [ $this, 'sanitize_levels_options' ]
            );
        }

        public function add_settings_section() {
            $settings = fqi3_options_settings_sections();
            $sections = $settings['sections']['levels_settings'];
            add_settings_section(
                $sections['id'],
                $sections['title'],
                null,
                fqi3_get_options_page_slug(),
                array(
                    'before_section' => '<div id="' . esc_attr(array_search($sections, $settings['sections'])) .'" class="fqi3-section-options-page">',
                    'after_section' => '</div>'
                )
            );

            $this->add_levels_settings_fields();
        }

        public function add_levels_settings_fields() {
            add_settings_field(
                'fqi3_set_levels',
                __('Set Quiz Levels', 'form-quizz-fqi3'),
                [$this, 'render_quiz_levels'],
                fqi3_get_options_page_slug(),
                'fqi3_levels_section'
            );
        }

        /**
         * Renders fields for quiz levels dynamically.
         *
         * This method generates HTML form fields for defining quiz levels, including level names,
         * labels, and whether they are free. It allows the dynamic addition of levels using 
         * JavaScript and supports a specified number of levels based on the provided count.
         *
         * The function includes a hidden template for adding new levels when required via an 
         * "Add Level" button, ensuring that the input meets specific formatting requirements.
         *
         * @param string $section_title   The title for the levels section (e.g., "Quiz Levels").
         * @param string $level_id      The key for the level names in the options array.
         * @param string $level_name     The key for the level labels in the options array.
         * @param string $level_free      The key for indicating if the level is free in the options array.
         * @param int    $level_count     The number of initial levels to render.
         *
         * @since 1.5.0
         *
         * This function allows administrators to manage quiz levels effectively, specifying 
         * names, labels, and free status for each level. It ensures the format of the level names 
         * is consistent and provides functionality to remove or add levels dynamically.
         */
        public function render_fields_quiz_levels($section_title, $level_id, $level_name, $level_free, $level_count) {
            $levels_options = get_option('fqi3_quiz_levels', []);
        
            echo '<div class="levels-container">';
            for ($i = 0; $i < $level_count; $i++) {
                $level_slug = esc_attr($levels_options[$level_id][$i] ?? '');
                $level_slug_display = esc_html($level_slug);
        
                echo '<div class="level-group">';
                echo '<p class="sub-section mb-3">' . esc_html__($section_title . ' ' . ($i + 1), 'form-quizz-fqi3') . '</p>';
        
                echo '<div class="group-sub-section mb-3">';
                echo '<div class="d-flex flex-row align-items-center">';
                echo '<label class="form-label mb-0 mr-3" style="width: 150px;">' . esc_html__('Level ID:', 'form-quizz-fqi3') . '</label>';
                echo '<input id="badge_name_' . esc_attr($level_id) . '_' . $i . '" class="form-control-sm" type="text" name="fqi3_quiz_levels[' . esc_attr($level_id) . '][]" value="' . esc_attr($level_slug_display) . '" oninput="this.value = sanitizeSlug(this.value)">';
                echo '</div>';
                echo '<p class="text-danger"><small>' . esc_html__('Your input will be automatically formatted to ensure it meets the requirements: Use lowercase letters only and no spaces or accents; hyphens (-) are allowed.', 'form-quizz-fqi3') . '</small></p>';
                echo '</div>'; 
        
                echo '<div class="group-sub-section mb-3">';
                echo '<div class="d-flex flex-row align-items-center">';
                echo '<label class="form-label mb-0 mr-3" style="width: 150px;">' . esc_html__('Level Name:', 'form-quizz-fqi3') . '</label>';
                echo '<input id="badge_name_' . esc_attr($level_name) . '_' . $i . '" class="form-control-sm" type="text" name="fqi3_quiz_levels[' . esc_attr($level_name) . '][]" value="' . esc_attr($levels_options[$level_name][$i] ?? '') . '">';
                echo '</div></div>';

                echo '<div class="group-sub-section mb-3">';
                echo '<div class="form-check form-switch d-flex flex-row align-items-center p-0">';
                echo '<label class="form-check-label" style="width: 150px;">' . esc_html__('Is Free?', 'form-quizz-fqi3') . '</label>';
                echo '<input id="badge_name_' . esc_attr($level_free) . '_' . $i . '" class="form-check-input" type="checkbox" name="fqi3_quiz_levels[' . esc_attr($level_free) . '][' . $i . ']" value="1" ' . checked(1, $levels_options[$level_free][$i] ?? 0, false) . '>';
                echo '</div></div>';

                echo '<div class="d-flex justify-content-end">';
                echo '<button type="button" class="remove-level btn btn-danger btn-sm p-1" data-index="' . esc_attr($i) . '">' . esc_html__('Remove this level', 'form-quizz-fqi3') . '</button>';
                echo '</div>';

                echo '</div>';
            }
            echo '</div>';
        
            echo '<button type="button" class="add-level btn btn-success btn-sm p-1">' . esc_html__('Add a level', 'form-quizz-fqi3') . '</button>';
        
            echo '<div class="level-template" style="display:none">';
            echo '<div class="level-group">';
            echo '<p class="sub-section mb-3">' . esc_html__($section_title . ' ', 'form-quizz-fqi3') . '<span class="level-number"></span></p>';
        
            echo '<div class="group-sub-section mb-3">';
            echo '<div class="d-flex flex-row align-items-center">';
            echo '<label class="form-label mb-0 mr-3" style="width: 150px;">' . esc_html__('Level ID:', 'form-quizz-fqi3') . '</label>';
            echo '<input class="form-control-sm" type="text" name="fqi3_quiz_levels[' . esc_attr($level_id) . '][]" value="" oninput="this.value = sanitizeSlug(this.value)">';
            echo '</div>';
            echo '<p class="text-danger"><small>' . esc_html__('Your input will be automatically formatted to ensure it meets the requirements: Use lowercase letters only and no spaces or accents; hyphens (-) are allowed.', 'form-quizz-fqi3') . '</small></p>';
            echo '</div>';
        
            echo '<div class="group-sub-section mb-3">';
            echo '<div class="d-flex flex-row align-items-center">';
            echo '<label class="form-label mb-0 mr-3" style="width: 150px;">' . esc_html__('Level Name:', 'form-quizz-fqi3') . '</label>';
            echo '<input class="form-control-sm" type="text" name="fqi3_quiz_levels[' . esc_attr($level_name) . '][]" value="">';
            echo '</div></div>';
        
            echo '<div class="group-sub-section mb-3">';
            echo '<div class="form-check form-switch d-flex flex-row align-items-center p-0">';
            echo '<label class="form-check-label" style="width: 150px;">' . esc_html__('Is Free?', 'form-quizz-fqi3') . '</label>';
            echo '<input class="form-check-input" type="checkbox" name="fqi3_quiz_levels[' . esc_attr($level_free) . '][]" value="1">';
            echo '</div></div>';
        
            echo '<div class="d-flex justify-content-end">';
            echo '<button type="button" class="remove-level btn btn-danger btn-sm p-1">' . esc_html__('Remove this level', 'form-quizz-fqi3') . '</button>';
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
        }  

        /**
         * Render the quiz levels fields on the plugin options page.
         * 
         * This method retrieves the existing quiz levels from the options or initializes them with default values. 
         * It then calls another method to display the fields for each quiz level, such as Level Name, Level Label, 
         * and whether the level is marked as "Free".
         * 
         * @uses render_fields_quiz_levels() to render the actual levels fields.
         * 
         * @since 1.5.0
         */
        public function render_quiz_levels() {
            $levels_options = get_option('fqi3_quiz_levels', []);

            if (!is_array($levels_options)) {
                $levels_options = [];
            }
            
            //$level_count = count($levels_options['fqi3_quiz_levels_name'] ?? [0]);

            // Utilisez count() sur le tableau de noms de niveaux
            $level_count = !empty($levels_options['fqi3_quiz_levels_name']) 
            ? count($levels_options['fqi3_quiz_levels_name'])
            : 0;

            // Si aucun niveau n'est défini, utilisez 0 comme valeur par défaut
            $level_count = max($level_count, 0);
            
            $this->render_fields_quiz_levels(
                'Quiz Levels',
                'fqi3_quiz_levels_name',
                'fqi3_quiz_levels_label',
                'fqi3_quiz_levels_is_free',
                $level_count
            );
        }    

        /**
         * Sanitize the quiz levels options before saving them to the database.
         * 
         * This method sanitizes the quiz levels configured by the user. It processes the following fields for each level:
         * - 'Level Name' : A unique identifier for the quiz level.
         * - 'Level Label' : A user-friendly label for the quiz level, which may be displayed in the UI.
         * - 'Is Free?' : A boolean (checkbox) indicating if the level is available without restrictions.
         * 
         * The method ensures that only valid and non-empty values are saved. If any essential fields like 'Level Name' or 
         * 'Level Label' are missing or empty, the corresponding level is omitted from the sanitized data.
         * 
         * @param array $input The input values to sanitize.
         * @return array The sanitized values, or an empty array if no valid levels are provided.
         * 
         * @since 1.5.0
         */
        public static function sanitize_levels_options($input) { 
            $sanitized_input = [];
        
            $sanitized_input['fqi3_quiz_levels_name'] = array_filter(array_map('sanitize_title', $input['fqi3_quiz_levels_name'] ?? []));
            $sanitized_input['fqi3_quiz_levels_label'] = array_filter(array_map('sanitize_text_field', $input['fqi3_quiz_levels_label'] ?? []));

            $nb_levels = max(count($input['fqi3_quiz_levels_label'] ?? []) - 1, 0);

            $sanitized_input['fqi3_quiz_levels_is_free'] = [];

            // Boucle pour chaque niveau
            for ($i = 0; $i < $nb_levels; $i++) {
                $sanitized_input['fqi3_quiz_levels_is_free'][$i] = isset($input['fqi3_quiz_levels_is_free'][$i]) && $input['fqi3_quiz_levels_is_free'][$i] == 1 ? 1 : 0;
            }

            if (empty($sanitized_input['fqi3_quiz_levels_name']) || empty($sanitized_input['fqi3_quiz_levels_label'])) {
                return [];
            }

            return $sanitized_input;
        }
        public static function new_sanitize_levels_options($input) {
            // Ajoutez un log de débogage
            error_log('Sanitizing levels options: ' . print_r($input, true));

            $sanitized_input = [];
        
            // Filtrez les valeurs vides et sanitizez
            $sanitized_input['fqi3_quiz_levels_name'] = array_filter(array_map('sanitize_title', $input['fqi3_quiz_levels_name'] ?? []));
            $sanitized_input['fqi3_quiz_levels_label'] = array_filter(array_map('sanitize_text_field', $input['fqi3_quiz_levels_label'] ?? []));
        
            // Calculez le nombre de niveaux en fonction des labels non vides
            $nb_levels = count($sanitized_input['fqi3_quiz_levels_label']);
        
            $sanitized_input['fqi3_quiz_levels_is_free'] = [];
        
            // Boucle pour chaque niveau
            for ($i = 0; $i < $nb_levels; $i++) {
                $sanitized_input['fqi3_quiz_levels_is_free'][$i] = isset($input['fqi3_quiz_levels_is_free'][$i]) && $input['fqi3_quiz_levels_is_free'][$i] == 1 ? 1 : 0;
            }

            // Log du résultat sanitizé
            error_log('Sanitized levels options: ' . print_r($sanitized_input, true));
        
            if (empty($sanitized_input['fqi3_quiz_levels_name']) || empty($sanitized_input['fqi3_quiz_levels_label'])) {
                return [];
            }
        
            return $sanitized_input;
        }

        /**
         * Retrieves the default options for quiz levels.
         *
         * This method returns an array containing default quiz level names, labels (translated),
         * and an indication of whether each level is free (0 for false, 1 for true).
         *
         * @return array The default options for quiz levels including level names, translated labels, and free status.
         */
        public function get_default_options_levels() {
            return [
                'fqi3_quiz_levels_name' => ['beginner', 'intermediate', 'advance'],
                'fqi3_quiz_levels_label' => [
                    __('Beginner', 'form-quizz-fqi3'),
                    __('Intermediate', 'form-quizz-fqi3'),
                    __('Advance', 'form-quizz-fqi3'),
                ],
                'fqi3_quiz_levels_is_free' => [0, 0, 0], 
            ];
        }

        /**
         * Sets the default options for quiz levels in the WordPress options table.
         *
         * This method checks if the 'fqi3_quiz_levels' option already exists in the database. 
         * If it doesn't exist, the method will add the default quiz level options using add_option().
         */
        public function set_default_options_levels() {
            $default_options = $this->get_default_options_levels();
        
            $existing_option = get_option('fqi3_quiz_levels', false); 

            if ($existing_option === false) {
                add_option('fqi3_quiz_levels', $default_options);
            }
        } 
    }

endif;