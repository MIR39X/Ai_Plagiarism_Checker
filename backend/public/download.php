<?php

require_once dirname(__DIR__) . '/src/Utils.php';
require_once dirname(__DIR__) . '/src/PythonBridge.php';

Utils::loadEnv(dirname(__DIR__) . '/.env');
Utils::allowCors();

$token = $_GET['token'] ?? null;
if (!$token) {
    Utils::error('Missing download token', 400);
}

$jobsDir = dirname(__DIR__) . '/uploads/jobs';
$jobFile = $jobsDir . '/' . basename($token) . '.json';

if (!file_exists($jobFile)) {
    Utils::error('Invalid or expired download token', 404);
}

$job = Utils::readJson($jobFile);
$sourceFile = $job['source_file'] ?? null;
$segments = $job['segments'] ?? [];

if (!$sourceFile || !file_exists($sourceFile)) {
    Utils::error('Source file not found for this token', 404);
}

// Only PDF annotation is supported.
$ext = strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    Utils::error('Download is currently supported for PDF uploads only.', 501);
}

$annotatedDir = dirname(__DIR__) . '/uploads/annotated';
Utils::ensureDir($annotatedDir);
$outputPath = $annotatedDir . '/' . basename($token) . '.pdf';

// If an annotated file already exists for this token, reuse it.
if (!file_exists($outputPath)) {
    $pythonBin = getenv('PYTHON_BIN') ?: (stripos(PHP_OS_FAMILY, 'Windows') !== false
        ? dirname(__DIR__, 2) . '/python-worker/.venv/Scripts/python.exe'
        : 'python'
    );

    $python = new PythonBridge($pythonBin);
    $python->annotate($sourceFile, $segments, $outputPath, 0.6, 0.7, 0.8);
}

if (!file_exists($outputPath)) {
    Utils::error('Failed to generate annotated file', 500);
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="annotated.pdf"');
header('Content-Length: ' . filesize($outputPath));
readfile($outputPath);
exit;

