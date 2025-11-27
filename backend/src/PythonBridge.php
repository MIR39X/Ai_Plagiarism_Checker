<?php

class PythonBridge
{
    private string $pythonBinary;
    private string $extractorPath;
    private string $annotatorPath;

    public function __construct(?string $pythonBinary = null, ?string $extractorPath = null, ?string $annotatorPath = null)
    {
        $this->pythonBinary = $pythonBinary ?? 'python';
        $this->extractorPath = $extractorPath ?? dirname(__DIR__, 2) . '/python-worker/extractor.py';
        $this->annotatorPath = $annotatorPath ?? dirname(__DIR__, 2) . '/python-worker/annotator.py';
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
        return $this->runJsonCommand($cmd, 'Python extractor failed');
    }

    /**
     * Generate an annotated PDF highlighting AI segments.
     */
    public function annotate(string $filePath, array $segments, string $outputPath, float $blue = 0.6, float $yellow = 0.7, float $red = 0.8): array
    {
        $tmpSegments = tempnam(sys_get_temp_dir(), 'segments_');
        file_put_contents($tmpSegments, json_encode($segments));

        $cmd = sprintf(
            '%s %s --file %s --segments %s --output %s --blue-threshold %F --yellow-threshold %F --red-threshold %F',
            escapeshellarg($this->pythonBinary),
            escapeshellarg($this->annotatorPath),
            escapeshellarg($filePath),
            escapeshellarg($tmpSegments),
            escapeshellarg($outputPath),
            $blue,
            $yellow,
            $red
        );

        $result = $this->runJsonCommand($cmd, 'Python annotator failed');
        @unlink($tmpSegments);
        return $result;
    }

    /**
     * Shared runner for JSON-returning python utilities.
     */
    private function runJsonCommand(string $cmd, string $errorLabel): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            Utils::error('Failed to start python process.', 500);
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            Utils::error($errorLabel, 500, ['stderr' => trim($stderr)]);
        }

        $decoded = json_decode($stdout, true);
        if (!is_array($decoded)) {
            Utils::error('Invalid JSON from python process.', 500, ['stderr' => trim($stderr)]);
        }

        return $decoded;
    }
}
