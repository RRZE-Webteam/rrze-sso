<?php

defined('ABSPATH') || define('ABSPATH', __DIR__ . '/fixtures/wordpress/');
defined('WP_CONTENT_DIR') || define('WP_CONTENT_DIR', dirname(__DIR__));

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/stubs/simplesamlphp.php';
require __DIR__ . '/stubs/wordpress.php';
