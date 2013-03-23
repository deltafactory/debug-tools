<?php

$use_auth = function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) ? 0 : 1;
define( 'USE_AUTHENTICATION', $use_auth );
