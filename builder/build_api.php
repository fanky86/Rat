<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$builderDir = __DIR__;
$buildScript = $builderDir . '/build.sh';
$outputDir = $builderDir . '/output';
$logDir = $builderDir . '/logs';
$apkFile = $outputDir . '/DeviceService.apk';

// Endpoint: trigger build
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pastikan script ada
    if (!file_exists($buildScript)) {
        echo json_encode(['success' => false, 'error' => 'build.sh not found']);
        exit;
    }

    // Hapus output lama agar tidak dianggap selesai
    if (file_exists($apkFile)) {
        unlink($apkFile);
    }

    // Jalankan build di background (nohup) agar tidak timeout
    $logFile = $logDir . '/build_' . date('Ymd_His') . '.log';
    $cmd = "nohup bash $buildScript > $logFile 2>&1 &";
    exec($cmd);

    echo json_encode([
        'success' => true,
        'message' => 'Build started in background',
        'log_file' => $logFile
    ]);
    exit;
}

// Endpoint: cek status build (apakah APK sudah ada)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($apkFile)) {
        $apkSize = filesize($apkFile);
        $apkDate = date('Y-m-d H:i:s', filemtime($apkFile));
        echo json_encode([
            'success' => true,
            'status' => 'ready',
            'apk_url' => '/builder/output/DeviceService.apk',
            'size' => round($apkSize / 1048576, 2) . ' MB',
            'date' => $apkDate
        ]);
    } else {
        // Cek apakah build sedang berjalan (ada proses gradle)
        exec("ps aux | grep -E 'gradle|java.*DeviceService' | grep -v grep", $output);
        $running = !empty($output);
        echo json_encode([
            'success' => true,
            'status' => $running ? 'building' : 'not_built',
            'message' => $running ? 'Build in progress...' : 'No APK available. Click Build to start.'
        ]);
    }
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
