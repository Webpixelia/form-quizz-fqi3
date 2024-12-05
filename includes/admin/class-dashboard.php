<?php
namespace Form_Quizz_FQI3;
/**
 * FQI3_Dashboard_Page Class
 *
 * This class handles the display and management of quiz questions for the FQI3 plugin.
 * It allows administrators to view, filter, edit, and delete questions. 
 * Additionally, it provides features for exporting questions and paginating through large sets of questions.
 *
 * @package    Form Quizz FQI3
 * @subpackage Admin Pages
 * @since      1.2.0
 * @version    2.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'FQI3_Dashboard_Page' ) ) :

class FQI3_Dashboard_Page {
    private ?FQI3_Awards $awards = null;

    public function __construct(
        private FQI3_Backend $backend,
        private array $levelsQuiz,
    ) {
        $this->levelsQuiz = $backend->get_levels_quiz();
    }

    public function get_awards_instance(): FQI3_Awards {
        return $this->awards ??= new FQI3_Awards();
    }

    private static function getUserRoles(): array {
        return [
            'premium_member' => __('Premium members', 'form-quizz-fqi3'),
            'subscriber' => __('Subscribers', 'form-quizz-fqi3'),
        ];
    }

    public function render_dashboard_page(): void {
        $table_name = $this->backend->get_quiz_table_name();
        $levels = array_keys($this->levelsQuiz);
        $questions_count = $this->backend->get_questions_count_by_level($table_name, $levels);
        ?>
        <div class="wrap container-fluid">
            <h2 class="wp-heading-inline page-title">
                <?php echo fqi3_get_admin_pages()['dashboard']['title']; ?>
            </h2>
            <div class="row">
                <!-- Postbox 1: Latest registrants -->
                <div class="col-md-6 mb-4">
                    <div class="card mw-100">
                        <div class="card-header bg-primary text-white fw-semibold">
                            <i class="bi bi-people"></i> <?php _e('Latest registrants', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-pills nav-fill" id="userTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="all-tab" data-bs-target="all">
                                        <?php _e('All', 'form-quizz-fqi3'); ?>
                                    </button>
                                </li>
                            <?php foreach (self::getUserRoles() as $role_key => $role_value): ?>                            
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" 
                                            id="<?php echo esc_attr($role_key); ?>-tab" 
                                            data-bs-toggle="tab" 
                                            data-bs-target="#<?php echo esc_attr($role_key); ?>" 
                                            type="button" 
                                            role="tab" 
                                            aria-controls="<?php echo esc_attr($role_key); ?>" 
                                            aria-selected="false">
                                        <?php esc_html_e(ucfirst($role_value)); ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                            <div class="table-responsive mt-3 overflow-x-auto">
                                <?php $this->render_users_table(); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Postbox 2 -->
                <div class="col-md-6 mb-4">
                    <div class="card mw-100">
                        <div class="card-header bg-secondary text-white fw-semibold">
                            <i class="bi bi-question-square"></i> <?php _e('Overview of the questions', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive mt-3 overflow-x-auto">
                                <table class="table table-striped table-bordered table-sm" id="questions-table" role="tablist">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Level', 'form-quizz-fqi3'); ?></th>
                                            <th><?php _e('Access', 'form-quizz-fqi3'); ?></th>
                                            <th><?php _e('Number of questions', 'form-quizz-fqi3'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                    <?php foreach ($this->levelsQuiz as $level_key => $level_array): 
                                        $access = (empty($level_array['free']) || $level_array['free'] == 0) ? __('Premium', 'form-quizz-fqi3') : __('Free', 'form-quizz-fqi3');
                                        ?>
                                        <tr class="fit-row">
                                            <td><?php esc_html_e($level_array['label']); ?></td>
                                            <td><?php esc_html_e($access); ?></td>
                                            <td class="text-center"><?php esc_html_e($questions_count[$level_key]); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Postbox 3 -->
                <div class="col-md-6 mb-4">
                    <div class="card mw-100">
                        <div class="card-header bg-success text-white fw-semibold">
                            <i class="bi bi-award"></i> <?php _e('List of badges', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive mt-3 overflow-x-auto">
                                <table class="table table-striped table-bordered table-sm" id="badges-table" role="tablist">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Badge Type', 'form-quizz-fqi3'); ?></th>
                                            <th><?php _e('Names', 'form-quizz-fqi3'); ?></th>
                                            <th><?php _e('Thresholds', 'form-quizz-fqi3'); ?></th>
                                            <th><?php _e('Unit', 'form-quizz-fqi3'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                    <?php 
                                    $badges = $this->getProcessedBadges();
                                    foreach ($badges as $badge): ?>
                                        <tr class="fit-row">
                                            <td><?php esc_html_e($badge['type']); ?></td>
                                            <td>
                                                <ul>
                                                <?php 
                                                // Liste les noms des badges
                                                foreach ($badge['names'] as $name): ?>
                                                    <li class="badge bg-secondary me-1"><?php esc_html_e($name); ?></li>
                                                <?php endforeach; ?>
                                                </ul>
                                            </td>
                                            <td>
                                                <ul>
                                                <?php 
                                                // Liste les seuils
                                                foreach ($badge['thresholds'] as $threshold): ?>
                                                    <li class="badge bg-info text-dark me-1"><?php esc_html_e($threshold); ?></li>
                                                <?php endforeach; ?>
                                                </ul>
                                            </td>
                                            <td><?php esc_html_e($badge['unity']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Postbox 4 -->
                <div class="col-md-6 mb-4">
                    <div class="card mw-100">
                        <div class="card-header bg-info text-white fw-semibold">
                            <i class="bi bi-info-square"></i> <?php _e('Need help?', 'form-quizz-fqi3'); ?>
                        </div>
                        <div class="card-body">
                            <p><?php _e('Need help setting up Form Quizz FQI3 or have any questions about the plugin?', 'form-quizz-fqi3'); ?></p>
                            <a href="https://webpixelia.com/en/contact/" target="_blank" class="btn btn-info">
                                <?php _e('Contact the Developer', 'form-quizz-fqi3'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function getProcessedBadges() {
        $badges_data = $this->get_awards_instance()->prepare_badges_data();

        return $badges_data;
    }

    /**
     * Renders a table of users for a specific role.
     * 
     * @param string|null $role Role to filter users. If null, all users are shown.
     */
    private function render_users_table(): void {
        $roles = array_keys(self::getUserRoles());
        $users = get_users([
            'number' => 10,
            'orderby' => 'registered',
            'order' => 'DESC',
            'role__in' => $roles,
        ]);
    
        if (empty($users)) {
            echo '<p>' . __('No users found.', 'form-quizz-fqi3') . '</p>';
            return;
        }
    
        ?>
        <table class="table table-striped table-sm" id="users-table">
            <thead>
                <tr>
                    <th><?php _e('Name', 'form-quizz-fqi3'); ?></th>
                    <th><?php _e('Email', 'form-quizz-fqi3'); ?></th>
                    <th><?php _e('Register Date', 'form-quizz-fqi3'); ?></th>
                </tr>
            </thead>
            <tbody class="table-group-divider">
                <?php foreach ($users as $user): 
                    $user_roles = $user->roles;
                    $primary_role = reset($user_roles);
                ?>
                    <tr class="user-row fit-row" data-role="<?php echo esc_attr($primary_role); ?>">
                        <td><?php echo esc_html(ucwords(strtolower($user->display_name))); ?></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($user->user_registered))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}

endif;