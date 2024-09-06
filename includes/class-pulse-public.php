<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package Pulse
 */

class Pulse_Public {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/pulse-public.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/pulse-public.js', array('jquery'), $this->version, false);
    }
	
	public function localize_script() {
    wp_localize_script($this->plugin_name, 'pulse_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pulse_nonce')
    ));
	}

    // Add more public-facing methods here as needed
}