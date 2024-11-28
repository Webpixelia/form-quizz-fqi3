<?php
namespace Form_Quizz_FQI3;
/**
 * FQI3_Emails Class
 *
 * This class is responsible for handling the customization of email content within the FQI3 plugin.
 * Introduced in version 1.4.0, it allows the modification of email subjects for enhanced communication.
 *
 * @package    Form Quizz FQI3
 * @subpackage Emails
 * @since      1.4.0
 * @version    2.0.0
 */

 if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'FQI3_Emails' ) ) :

class FQI3_Emails {
     /** @var FQI3_Template_Manager */
     private FQI3_Template_Manager $template_manager;

     /** @var string */
     private const CRON_HOOK = 'fqi3_send_daily_email';
    
     /** @var string */
     private const DEFAULT_EMAIL_HOUR = '09:00';
     
     /** @var string */
     private const EMAIL_TIME_FORMAT = '/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/';
     
     /** @var array */
     private $options;

    /**
     * Constructor
     */
    public function __construct() {
        $this->template_manager = new FQI3_Template_Manager();
        $this->options = fqi3_get_options();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        add_action('init', [$this, 'schedule_daily_email']);
        add_action(self::CRON_HOOK, [$this, 'send_daily_reminder']);
        //add_action('admin_post_fqi3_test_cron', [$this, 'debug_cron_execution']);
    }

    /**
     * Log messages with plugin prefix
     */
    private function log(string $message, array $context = []): void {
        $formatted_context = empty($context) ? '' : ' | ' . json_encode($context);
        error_log("FQI3: {$message}{$formatted_context}");
    }

    /**
     * Manually triggers the cron job and logs the process for debugging.
     * 
     * @since 1.6.0
     * 
     * @return void Redirects the user back to the options page after execution.
     */
    public function debug_cron_execution(): void {
        try {
            $this->log('Starting cron debug check');
            
            $next_scheduled = wp_next_scheduled(self::CRON_HOOK);
            $next_time = $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'Not scheduled';
            $this->log('Next scheduled time', ['time' => $next_time]);
            
            $timezone_string = wp_timezone_string();
            $email_hour = $this->options['fqi3_email_hour'] ?? self::DEFAULT_EMAIL_HOUR;
            $this->log('Timezone configuration', [
                'timezone' => $timezone_string,
                'email_hour' => $email_hour
            ]);

            $subscribers = $this->get_subscribers();
            $this->log('Subscriber count', ['count' => count($subscribers)]);

            do_action(self::CRON_HOOK);
            
            $pages = fqi3_get_admin_pages();
            wp_redirect(admin_url('admin.php?page=' . $pages['options']['slug'] . '&debug=1'));
            exit;
        } catch (\Exception $e) {
            $this->log('Debug execution failed', ['error' => $e->getMessage()]);
            wp_die($e->getMessage());
        }
    }

     /**
     * Send daily reminder emails to subscribers
     * 
     * @since 1.4.0
     */
    public function send_daily_reminder(): void {
        try {
            $subscribers = $this->get_subscribers();
            
            if (empty($subscribers)) {
                $this->log('No subscribers found');
                return;
            }

            foreach ($subscribers as $subscriber) {
                $this->process_subscriber_email($subscriber);
                usleep(100000); // Rate limiting
            }
        } catch (\Exception $e) {
            $this->log('Failed to send daily reminders', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Schedule or reschedule daily email
     * 
     * @since 1.4.0
     */
    public function schedule_daily_email(bool $force_reschedule = false): void {
        try {
            $current_schedule = wp_next_scheduled(self::CRON_HOOK);
            $target_time = $this->calculate_next_email_time();

            // Check if we need to reschedule
            if ($force_reschedule || !$current_schedule || $current_schedule !== $target_time) {
                if ($current_schedule) {
                    wp_unschedule_event($current_schedule, self::CRON_HOOK);
                }
                
                wp_schedule_event($target_time, 'daily', self::CRON_HOOK);
                $this->log('Email scheduled', [
                    'time' => date('Y-m-d H:i:s', $target_time),
                    'forced' => $force_reschedule
                ]);
            }
        } catch (\Exception $e) {
            $this->log('Failed to schedule daily email', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send test email to admin
     *   
     * This method is used for testing purposes to manually send an email
     * to admin.
     *
     * @since 1.4.0
     */
    public function send_test_email(): bool {
        try {
            $admin_email = get_bloginfo('admin_email');
            $subject = __('FQI3 Test Email', 'form-quizz-fqi3');
            $message = $this->get_email_message($admin_email, 'test');

            return $this->send_email($admin_email, $subject, $message);
        } catch (\Exception $e) {
            $this->log('Failed to send test email', ['error' => $e->getMessage()]);
            return false;
        }
    }

     /**
     * Process individual subscriber email
     */
    private function process_subscriber_email(\WP_User $subscriber): void {
        try {
            $email = $subscriber->user_email;
            $subject = __('Your Free Quiz Attempts Have Been Reset', 'form-quizz-fqi3');
            $message = $this->get_email_message($subscriber->display_name, 'reset');

            $sent = $this->send_email($email, $subject, $message);
            
            if (!$sent) {
                throw new \Exception("Email sending failed");
            }
            
            $this->log('Email sent successfully', ['email' => $email]);
        } catch (\Exception $e) {
            $this->log('Failed to process subscriber email', [
                'email' => $email ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get email message HTML
     */
    private function get_email_message(string $user_name, string $type): string {
        $template_data = [
            'user_name' => esc_html($user_name),
            'cta_button' => $this->generate_cta_button(),
            'site_name' => esc_html(get_bloginfo('name')),
            'logo_url' => $this->get_logo_url(),
            'message_content' => $this->get_message_content($user_name, $type)
        ];

        return $this->template_manager->render('email-template', $template_data);
    }

    /**
     * Get message content based on type
     */
    private function get_message_content(string $user_name, string $type): string {
        if ($type === 'reset') {
            return sprintf(
                '%s %s!<br><br>%s',
                __('Hi', 'form-quizz-fqi3'),
                esc_html($user_name),
                __('Your wait is over! <br><br> 24 hours have passed, so you have 3 free quizzes available again.', 'form-quizz-fqi3')
            );
        }
        
        if ($type === 'test') {
            return __('Hi admin! Your email sending test is successful.', 'form-quizz-fqi3');
        }
        
        return __('Hello, this is a generic message from the FQI3 plugin.', 'form-quizz-fqi3');
    }

    /**
     * Send email with error handling and logging
     * 
     * @param string $to The recipient's email address.
     * @param string $subject The subject of the email.
     * @param string $message The HTML content of the email.
     * 
     * @since 1.4.0
     */
    private function send_email(string $to, string $subject, string $message): bool {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        try {
            if (!is_email($to)) {
                throw new \Exception('Invalid email address');
            }

            $sent = wp_mail($to, $subject, $message, $headers);
            
            if (!$sent) {
                throw new \Exception('wp_mail() returned false');
            }

            return true;
        } catch (\Exception $e) {
            $this->log('Email sending failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get subscribers
     */
    private function get_subscribers(): array {
        $users = get_users([
            'role' => 'subscriber',
            'fields' => 'ID'
        ]);
        
        $subscribers = [];
        foreach ($users as $user) {
            $user_obj = get_user_by('id', $user);
            if ($user_obj instanceof \WP_User) {
                $subscribers[] = $user_obj;
            }
        }
    
        return $subscribers;
    }

    /**
     * Get logo URL
     */
    private function get_logo_url(): string {
        $logo_id = $this->options['fqi3_email_logo'] ?? '';
        return $logo_id ? 
            wp_get_attachment_url($logo_id) : 
            esc_url(FQI3_ASSETS . '/img/wordpress-logo.jpg');
    } 
    
    /**
    * Calculate next email time
    */
   private function calculate_next_email_time(): int {
       $timezone_string = get_option('timezone_string', 'UTC');
       if (empty($timezone_string)) {
           $timezone_string = 'UTC';
       }
   
       try {
           $timezone = new \DateTimeZone($timezone_string);
       } catch (\Exception $e) {
           // Si le fuseau horaire est invalide, utiliser UTC par défaut
           $timezone = new \DateTimeZone('UTC');
       }        $email_hour = $this->validate_email_hour();
       
       $target_time = new \DateTime('today ' . $email_hour, $timezone);
       $target_time->setTimezone(new \DateTimeZone('UTC'));
       
       if ($target_time->getTimestamp() < time()) {
           $target_time->modify('+1 day');
       }
       
       return $target_time->getTimestamp();
   }

    /**
     * Validate email hour format
     */
    private function validate_email_hour(): string {
        $email_hour = $this->options['fqi3_email_hour'] ?? self::DEFAULT_EMAIL_HOUR;
        
        if (!preg_match(self::EMAIL_TIME_FORMAT, $email_hour)) {
            $this->log('Invalid email hour format', ['email_hour' => $email_hour]);
            return self::DEFAULT_EMAIL_HOUR;
        }
        
        return $email_hour;
    }

    /**
     * Generate CTA button HTML
     */
    private function generate_cta_button(): string {
        $link_id = $this->options['fqi3_email_link_cta'] ?? '';
        $url = $link_id ? get_permalink($link_id) : get_bloginfo('url');
        
        return sprintf(
            '<a href="%s" style="%s">%s</a>',
            esc_url($url),
            $this->get_button_styles(),
            esc_html($this->options['fqi3_email_cta_label'] ?? '')
        );
    }

    /**
     * Get button styles
     */
    private function get_button_styles(): string {
        $bg_color = esc_attr($this->options['fqi3_email_cta_color_bg'] ?? '#000000');
        $text_color = esc_attr($this->options['fqi3_email_cta_color_text'] ?? '#ffffff');
        
        return "display:block;text-align:center;padding:12px 24px;" .
               "background-color:{$bg_color};color:{$text_color};" .
               "font-size:16px;font-weight:bold;text-decoration:none;" .
               "border-radius:5px;margin-top:20px;";
    } 
}

endif;

$options = fqi3_get_options();
if (empty($options['fqi3_disable_emails']) || $options['fqi3_disable_emails'] !== '1') {
    new FQI3_Emails();
}