<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('wizjo_monitor_token');
