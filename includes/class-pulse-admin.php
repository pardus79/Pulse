<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package Pulse
 */

class Pulse_Admin {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/pulse-admin.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/pulse-admin.js', array('jquery'), $this->version, false);
    }

    // Add more admin-specific methods here as needed
}