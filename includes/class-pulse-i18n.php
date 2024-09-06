<?php
/**
 * Define the internationalization functionality
 *
 * @package Pulse
 */

class Pulse_i18n {

    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'pulse',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}