<?php

class PythonBridge
{
    private string $pythonBinary;
    private string $extractorPath;

    public function __construct(?string $pythonBinary = null, ?string $extractorPath = null)
    {
        $this->pythonBinary = $pythonBinary ?? 'python';
        $this->extractorPath = $extractorPath ?? dirname(__DIR__, 2) . '/python-worker/extractor.py';
    }

    /**
    * Run the Python extractor and return the decoded JSON payload.
    */
    public function extract(string $filePath, int $segmentTokens = 80): array
    {
        $cmd = sprintf(
            '%s %s --file %s --segment-tokens %d',
            escapeshellarg($this->pythonBinary),
            escapeshellarg($this->extractorPath),
            escapeshellarg($filePath),
            $segmentTokens
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            Utils::error('Failed to start Python extractor.', 500);
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            Utils::error('Python extractor failed', 500, ['stderr' => trim($stderr)]);
        }

        $decoded = json_decode($stdout, true);
        if (!is_array($decoded)) {
            Utils::error('Invalid JSON from Python extractor.', 500, ['stderr' => trim($stderr)]);
        }

        return $decoded;
    }
}

