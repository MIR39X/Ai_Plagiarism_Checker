<?php

class DeepSeekClient
{
    private ?string $apiKey;
    private string $baseUrl;
    private string $model;

    // Optional metadata headers encouraged by OpenRouter
    private ?string $siteUrl;
    private ?string $siteName;

    // Optional CA bundle for TLS verification
    private ?string $caFile;

    public function __construct(?string $apiKey = null, ?string $model = null, string $baseUrl = 'https://openrouter.ai/api/v1')
    {
        $this->apiKey = $apiKey ?? getenv('OPENROUTER_API_KEY') ?: null;
        $this->model = $model ?? getenv('OPENROUTER_MODEL') ?: 'deepseek/deepseek-chat';
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->siteUrl = getenv('OPENROUTER_SITE_URL') ?: null;   // optional HTTP-Referer
        $this->siteName = getenv('OPENROUTER_SITE_NAME') ?: null; // optional X-Title
        $this->caFile = getenv('OPENROUTER_CAINFO') ?: $this->defaultCaPath();
    }

    /**
    * Score segments with the detector. Falls back to a deterministic mock when no API key is present.
    */
    public function scoreSegments(array $segments): array
    {
        if (!$this->apiKey) {
            return $this->mockScores($segments);
        }

        if (empty($segments)) {
            return [];
        }

        $confidenceMap = $this->batchClassify($segments);

        return array_map(function ($seg) use ($confidenceMap) {
            $id = $seg['id'] ?? null;
            $seg['confidence'] = isset($confidenceMap[$id]) ? $confidenceMap[$id] : 0.0;
            return $seg;
        }, $segments);
    }

    /**
     * Deterministic pseudo-scores so UI/flow works without the real API.
     */
    private function mockScores(array $segments): array
    {
        return array_map(function ($seg) {
            $hash = hexdec(substr(hash('sha256', (string) $seg['text']), 0, 8));
            $confidence = ($hash % 100) / 100; // 0.00 - 0.99
            $seg['confidence'] = round($confidence, 2);
            return $seg;
        }, $segments);
    }

    /**
     * Call OpenRouter with a single text and return confidence 0..1.
     */
    private function batchClassify(array $segments): array
    {
        $url = $this->baseUrl . '/chat/completions';

        // Prepare compact payload to reduce chance of rate/timeouts
        $payloadSegments = array_map(function ($seg) {
            return [
                'id' => $seg['id'] ?? '',
                'text' => $seg['text'] ?? '',
            ];
        }, $segments);

        $userContent = "Return JSON array of {id, confidence} for each segment. Confidence is 0-1 (1 = AI-generated). Segments:";
        $userContent .= "\n" . json_encode($payloadSegments, JSON_UNESCAPED_UNICODE);

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an AI-generated text detector. Respond only with JSON array of objects: [{"id": "seg-1", "confidence": 0.42}]. Confidence must be between 0 and 1.',
                ],
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
            'temperature' => 0,
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        if ($this->siteUrl) {
            $headers[] = 'HTTP-Referer: ' . $this->siteUrl;
        }
        if ($this->siteName) {
            $headers[] = 'X-Title: ' . $this->siteName;
        }

        $attempts = 0;
        $maxAttempts = 2;
        $responseBody = null;
        $httpCode = null;
        $curlError = null;

        while ($attempts < $maxAttempts) {
            $attempts++;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_TIMEOUT => 180,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            if ($this->caFile && file_exists($this->caFile)) {
                curl_setopt($ch, CURLOPT_CAINFO, $this->caFile);
            }

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $shouldRetry = false;
            if ($responseBody === false) {
                $shouldRetry = $attempts < $maxAttempts;
            } elseif ($httpCode >= 500 || $httpCode === 429) {
                $shouldRetry = $attempts < $maxAttempts;
            }

            if (!$shouldRetry) {
                break;
            }

            sleep(2);
        }

        if ($responseBody === false) {
            Utils::error('OpenRouter request failed', 502, ['detail' => $curlError]);
        }

        if ($httpCode === 429) {
            Utils::error('OpenRouter rate limit', 502, ['status' => $httpCode, 'body' => $responseBody]);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            Utils::error('OpenRouter returned an error', 502, ['status' => $httpCode, 'body' => $responseBody]);
        }

        $decoded = json_decode($responseBody, true);
        if (!isset($decoded['choices'][0]['message']['content'])) {
            Utils::error('Unexpected OpenRouter response', 502, ['body' => $responseBody]);
        }

        $content = trim($decoded['choices'][0]['message']['content']);

        // Clean up common wrapper tokens and code fences from some models
        $clean = preg_replace('/\[\\?\/?B_INST\]/i', '', $content);
        $clean = preg_replace('/```(?:json)?/i', '', $clean);
        $clean = trim($clean);

        $arr = json_decode($clean, true);

        if (!is_array($arr)) {
            // Try bracket slicing from first '[' to last ']'
            $start = strpos($clean, '[');
            $end = strrpos($clean, ']');
            if ($start !== false && $end !== false && $end > $start) {
                $slice = substr($clean, $start, $end - $start + 1);
                $arr = json_decode($slice, true);
            }
        }

        if (!is_array($arr)) {
            Utils::error('Could not parse confidence list from OpenRouter response', 502, ['body' => $content]);
        }

        $map = [];
        foreach ($arr as $item) {
            if (!isset($item['id'], $item['confidence'])) {
                continue;
            }
            $c = (float) $item['confidence'];
            $c = max(0.0, min(1.0, $c));
            $map[$item['id']] = round($c, 2);
        }

        return $map;
    }

    /** Locate bundled cacert if present in project root. */
    private function defaultCaPath(): ?string
    {
        $root = dirname(__DIR__, 2);
        $candidate = $root . '/cacert.pem';
        return file_exists($candidate) ? $candidate : null;
    }
}

