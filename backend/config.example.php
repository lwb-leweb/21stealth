<?php
// Copy this file to config.php and fill in your API keys.
// config.php must NEVER be committed to Git.

return [
    'coingecko_api_key'  => '',   // https://coingecko.com/api
    'trongrid_api_key'   => '',   // https://trongrid.io
    'alchemy_api_key'    => '',   // https://alchemy.com
    'blockchair_api_key' => '',   // https://blockchair.com/api
    'db_host'            => 'localhost',
    'db_name'            => '',   // MySQL database name
    'db_user'            => '',   // MySQL user
    'db_pass'            => '',   // MySQL password
    'app_key'            => '',   // generate with: openssl rand -hex 32
    'admin_password'     => '',   // login for stats-dashboard.php; empty = locked
];
