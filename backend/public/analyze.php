<?php

require_once dirname(__DIR__) . '/src/Utils.php';
require_once dirname(__DIR__) . '/src/PythonBridge.php';
require_once dirname(__DIR__) . '/src/DeepSeekClient.php';

Utils::loadEnv(dirname(__DIR__) . '/.env');
Utils::allowCors();

// Allow longer processing for larger documents/API calls
set_time_limit(300);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Utils::error('Only POST is allowed', 405);
}

if (!isset($_FILES['file'])) {
    Utils::error('Missing file upload', 400);
}

$upload = $_FILES['file'];
if (!isset($upload['tmp_name']) || $upload['error'] !== UPLOAD_ERR_OK) {
    Utils::error('File upload failed', 400, ['code' => $upload['error'] ?? null]);
}

$segmentTokens = isset($_POST['segment_tokens']) ? (int) $_POST['segment_tokens'] : 80;
$threshold = isset($_POST['threshold']) ? (float) $_POST['threshold'] : 0.5;

$uploadsDir = dirname(__DIR__) . '/uploads';
Utils::ensureDir($uploadsDir);

$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($upload['name']));
$storedPath = $uploadsDir . '/' . uniqid('upload_', true) . '_' . $safeName;

if (!move_uploaded_file($upload['tmp_name'], $storedPath)) {
    Utils::error('Failed to store uploaded file', 500);
}

$pythonBin = getenv('PYTHON_BIN') ?: (stripos(PHP_OS_FAMILY, 'Windows') !== false
    ? dirname(__DIR__, 2) . '/python-worker/.venv/Scripts/python.exe'
    : 'python'
);

$python = new PythonBridge($pythonBin);
$extracted = $python->extract($storedPath, $segmentTokens);

$segments = $extracted['segments'] ?? [];
$totalTokens = $extracted['total_tokens'] ?? 0;

$detector = new DeepSeekClient();
$scoredSegments = $detector->scoreSegments($segments);

$avgConfidence = 0.0;
if (count($scoredSegments) > 0) {
    $sum = array_reduce($scoredSegments, function ($carry, $seg) {
        return $carry + ((float) ($seg['confidence'] ?? 0));
    }, 0.0);
    $avgConfidence = $sum / count($scoredSegments);
}

$aiPercent = round($avgConfidence * 100, 2);
$jobId = Utils::makeToken('job');
$downloadToken = Utils::makeToken('dl');

$jobsDir = dirname(__DIR__) . '/uploads/jobs';
Utils::ensureDir($jobsDir);
$jobFile = $jobsDir . '/' . $downloadToken . '.json';

$jobPayload = [
    'source_file' => $storedPath,
    'segments' => $scoredSegments,
    'ai_percent' => $aiPercent,
    'threshold' => $threshold,
    'created_at' => time(),
];

file_put_contents($jobFile, json_encode($jobPayload, JSON_PRETTY_PRINT));

$response = [
    'job_id' => $jobId,
    'ai_percent' => $aiPercent,
    'total_tokens' => $totalTokens,
    'num_segments' => count($scoredSegments),
    'threshold' => $threshold,
    'segments' => array_map(function ($seg) use ($threshold) {
        $seg['is_ai'] = ($seg['confidence'] ?? 0) >= $threshold;
        return $seg;
    }, $scoredSegments),
    'download_token' => $downloadToken,
];

Utils::jsonResponse($response);
