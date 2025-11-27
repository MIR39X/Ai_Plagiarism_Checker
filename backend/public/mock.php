<?php

require_once dirname(__DIR__) . '/src/Utils.php';

$mockPath = dirname(__DIR__) . '/examples/mock_response.json';
Utils::allowCors();

$payload = Utils::readJson($mockPath);
Utils::jsonResponse($payload);

