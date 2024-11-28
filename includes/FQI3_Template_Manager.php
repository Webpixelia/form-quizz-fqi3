<?php
namespace Form_Quizz_FQI3;
/**
 * Template Manager for Quiz Frontend
 *
 * This class handles the rendering of templates for the quiz frontend by including the specified template files
 * and injecting the provided data into them. It ensures templates are loaded from the correct directory 
 * and provides an error message if a template cannot be found.
 *
 * @package Form Quizz FQI3
 * @since 2.0.0
 * @version 2.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template Manager for Quiz Frontend
 */
class FQI3_Template_Manager {
    /** @var string */
    private string $template_path;

    public function __construct() {
        $this->template_path = FQI3_PATH . '/templates/';
    }

    /**
     * Renders a template with the provided data.
     *
     * Includes the specified template file and extracts the given data into individual variables,
     * making them available for use within the template. If the template file is not found, 
     * an error message is returned indicating the missing template.
     *
     * @param string $template_name The name of the template file (without extension) to render.
     * @param array  $data          An associative array of data to pass to the template.
     *                              This data will be extracted and made available as variables within the template.
     *
     * @return string The rendered template content, or an error message if the template is not found.
    */
    public function render(string $template_name, array $data = []): string {
        $template_file = $this->template_path . $template_name . '.php';
        
        if (!file_exists($template_file)) {
            return sprintf(
                /* translators: %s: Template file name */
                esc_html__('Template %s not found.', 'form-quizz-fqi3'),
                esc_html($template_name)
            );
        }

        ob_start();
        extract($data, EXTR_SKIP);
        include $template_file;
        return ob_get_clean();
    }
}