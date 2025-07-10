<?php
/*
Plugin Name: WooCommerce ChatGPT Assistant
Description: Adds a chatbox in the WordPress admin that uses the OpenAI API to perform WooCommerce tasks like adding products.
Version: 0.1.1
Author: Codex
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class WC_ChatGPT_Assistant {
    const OPTION_API_KEY = 'wc_chatgpt_api_key';
    const OPTION_HISTORY = 'wc_chatgpt_history';
    const SYSTEM_PROMPT = 'You are a WooCommerce assistant. When the user asks to perform a store task such as adding a product, respond in natural language and also include a JSON block describing the action. Use the format {"command":"add_product","name":"NAME","price":PRICE}. If no action is needed, just reply normally.';

    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_wc_chatgpt_send_message', array($this, 'ajax_send_message'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function admin_menu() {
        add_menu_page('Woo Chat Assistant', 'Woo Chat Assistant', 'manage_woocommerce', 'wc-chatgpt-assistant', array($this, 'chat_page'));
        add_submenu_page('wc-chatgpt-assistant', 'Settings', 'Settings', 'manage_options', 'wc-chatgpt-assistant-settings', array($this, 'settings_page'));
    }

    public function register_settings() {
        register_setting('wc_chatgpt_settings', self::OPTION_API_KEY);
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_wc-chatgpt-assistant') {
            return;
        }
        wp_enqueue_script('wc-chatgpt-admin', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), '1.0', true);
        wp_localize_script('wc-chatgpt-admin', 'WCChatGPT', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wc_chatgpt_nonce'),
        ));
        wp_enqueue_style('wc-chatgpt-admin', plugin_dir_url(__FILE__) . 'admin.css');
    }

    public function chat_page() {
        ?>
        <div class="wrap">
            <h1>WooCommerce ChatGPT Assistant</h1>
            <div id="wc-chatgpt-log" style="border:1px solid #ccc;height:300px;overflow:auto;padding:10px;margin-bottom:10px;"></div>
            <textarea id="wc-chatgpt-input" rows="3" style="width:100%;"></textarea>
            <button id="wc-chatgpt-send" class="button button-primary">Send</button>
        </div>
        <?php
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>ChatGPT Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_chatgpt_settings');
                do_settings_sections('wc_chatgpt_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">OpenAI API Key</th>
                        <td><input type="text" name="<?php echo esc_attr(self::OPTION_API_KEY); ?>" value="<?php echo esc_attr(get_option(self::OPTION_API_KEY)); ?>" size="50" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function get_api_key() {
        return trim(get_option(self::OPTION_API_KEY));
    }

    public function ajax_send_message() {
        check_ajax_referer('wc_chatgpt_nonce');
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        if (!$message) {
            wp_send_json_error('Empty message');
        }
        $history = get_option(self::OPTION_HISTORY, array());
        $history[] = array('role' => 'user', 'content' => $message);
        $response_text = $this->query_openai($history);
        $history[] = array('role' => 'assistant', 'content' => $response_text);
        update_option(self::OPTION_HISTORY, $history);
        $this->maybe_execute_command($response_text);
        wp_send_json_success(array('response' => $response_text));
    }

    private function query_openai($messages) {
        $api_key = $this->get_api_key();
        if (!$api_key) {
            return 'API key not set.';
        }
        array_unshift($messages, array('role' => 'system', 'content' => self::SYSTEM_PROMPT));
        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body'    => wp_json_encode(array(
                'model'    => 'gpt-3.5-turbo',
                'messages' => $messages,
            )),
            'timeout' => 30,
        );
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }
        return 'No response from API';
    }

    private function maybe_execute_command($text) {
        if (preg_match('/```json\s*(\{[^`]+\})\s*```/i', $text, $m) ||
            preg_match('/\{\s*"command"\s*:\s*"add_product"[^}]*\}/i', $text, $m)) {
            $json = json_decode($m[1] ?? $m[0], true);
            if ($json && !empty($json['name'])) {
                $product_id = wp_insert_post(array(
                    'post_title'  => sanitize_text_field($json['name']),
                    'post_type'   => 'product',
                    'post_status' => 'publish',
                ));
                if ($product_id && !is_wp_error($product_id)) {
                    if (isset($json['price'])) {
                        update_post_meta($product_id, '_price', floatval($json['price']));
                        update_post_meta($product_id, '_regular_price', floatval($json['price']));
                    }
                }
            }
        }
    }
}

new WC_ChatGPT_Assistant();
