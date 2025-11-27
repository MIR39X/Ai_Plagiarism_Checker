<?php

require_once dirname(__DIR__) . '/src/Utils.php';

Utils::allowCors();

$token = $_GET['token'] ?? null;
if (!$token) {
    Utils::error('Missing download token', 400);
}

// Placeholder: annotated files are not generated yet.
Utils::error('Download endpoint not implemented yet', 501, ['token' => $token]);

