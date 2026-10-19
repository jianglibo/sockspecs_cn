<?php
/** Prevent development mail delivery; copied only into the local installation. */
if (defined('ABSPATH') && wp_get_environment_type() === 'local') {
    add_filter('pre_wp_mail', static function () { return false; });
}
