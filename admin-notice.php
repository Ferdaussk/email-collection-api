<?php

if (!defined( 'ABSPATH')) {
    exit;
}
class fskSupport {
    private $api_url = '/way-of-api/get-api.php?show_key=true';
    private $api_key;
    private $audience_id = '/way-of-api/get-api.php?show_audience=true';
    private $list_id;

    public function __construct() {
        $this->fsk_fetch_api_key();
        add_action( 'admin_notices', [$this,'fsk_admin_updates_plugin_notice'] );
        add_action('admin_post_handle_fsk_email_subscription', [$this, 'handle_fsk_email_subscription']);
    }

    public function fsk_admin_updates_plugin_notice() {
        if (!get_option('fsk_email_subscription_notice_shown', false)) {
            $admin_email = get_option('admin_email');
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>Thank you for choosing our plugin! We appreciate your trust. <a href="https://lotussk.com" target="_blank">Find us..</a></p>';
            echo '<form class="newsletter-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="email" name="email" value="' . esc_attr($admin_email) . '" style="display:none" required>';
            echo '<input type="hidden" name="action" value="handle_fsk_email_subscription">';
            echo '<button type="submit" class="button button-primary fsk-notice-btn">Hide Notice</button>';
            echo '</form>';
            echo '</div>';
        }
    }
    
    private function fsk_fetch_api_key() {
        $response = file_get_contents($this->api_url);
        $data = json_decode($response, true);
        if (isset($data['api_key'])) {
            $this->api_key = $data['api_key'];
        } else {
            echo "Error: API api key not found.";
        }
        $responseID = file_get_contents($this->audience_id);
        $dataID = json_decode($responseID, true);
        if (isset($dataID['audience_id'])) {
            $this->list_id = $dataID['audience_id'];
        } else {
            echo "Error: API audience id not found.";
        }
    }

    public function handle_fsk_email_subscription() {
        if (isset($_POST['email']) && is_email($_POST['email'])) {
            $email = sanitize_email($_POST['email']);
            $this->add_to_mailchimp($email);
            update_option('fsk_email_subscription_notice_shown', true);
            wp_safe_redirect(admin_url('#'));
            exit;
        } else {
            wp_safe_redirect(admin_url('#'));
            exit;
        }
    }

    private function add_to_mailchimp($email) {
        $data_center = substr($this->api_key, strpos($this->api_key, '-') + 1);
        
        $url = 'https://' . $data_center . '.api.mailchimp.com/3.0/lists/' . $this->list_id . '/members/';

        $current_user_id = get_current_user_id();
        $user_info = get_userdata($current_user_id);
        $site_name = get_bloginfo('name');
        $username = $user_info->user_login;
        if (function_exists('get_plugin_data')) {
            $plugin_data = get_plugin_data(__FILE__);
            $plugin_name = $plugin_data['Name'];
        } else {
            $plugin_name = 'Unknown Plugin';
        }
        $tags = [$plugin_name];

        $merge_fields = array(
            'FNAME' => $username,
            'COMPANY' => $site_name,
        );
        
        $data = array(
            'email_address' => $email,
            'status'        => 'subscribed',
            'merge_fields'  => $merge_fields,
            'tags'          => $tags,
        );
        
        $json_data = json_encode($data);
        
        $args = array(
            'body'        => $json_data,
            'headers'     => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $this->api_key),
                'Content-Type'  => 'application/json',
            ),
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            error_log('Mailchimp error: ' . $response->get_error_message());
        }
    }

}
new fskSupport();
