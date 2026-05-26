<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$builderDir = __DIR__;
$buildScript = $builderDir . '/build.sh';
$outputDir = $builderDir . '/output';
$apkFile = $outputDir . '/DeviceService.apk';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!file_exists($buildScript)) {
        echo json_encode(['success' => false, 'error' => 'build.sh not found']);
        exit;
    }
    if (file_exists($apkFile)) {
        unlink($apkFile);
    }
    $cmd = "nohup bash $buildScript > /dev/null 2>&1 &";
    exec($cmd);
    echo json_encode(['success' => true, 'message' => 'Build started']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($apkFile)) {
        echo json_encode([
            'success' => true,
            'status' => 'ready',
            'apk_url' => '/builder/output/DeviceService.apk'
        ]);
    } else {
        $running = trim(shell_exec("ps aux | grep -E 'gradle|java.*DeviceService' | grep -v grep | wc -l"));
        echo json_encode([
            'status' => $running > 0 ? 'building' : 'not_built'
        ]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
