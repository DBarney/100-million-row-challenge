<?php

namespace App;

final class Parser
{
    public function parse(string $inputPath, string $outputPath): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $data = [];
        $order = [];

        $handle = fopen($inputPath, 'r');
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $commaPos = strpos($line, ',');
            $url = substr($line, 0, $commaPos);
            $timestamp = substr($line, $commaPos + 1);

            $path = $this->extractPath($url);
            $date = $this->extractDate($timestamp);

            if (!isset($data[$path])) {
                $data[$path] = [];
                $order[] = $path;
            }

            if (!isset($data[$path][$date])) {
                $data[$path][$date] = 0;
            }

            $data[$path][$date]++;
        }
        fclose($handle);

        $readTime = microtime(true);
        $readMemory = memory_get_usage(true);

        foreach ($data as $path => &$dates) {
            ksort($dates, SORT_STRING);
        }

        $sortTime = microtime(true);

        $output = [];
        foreach ($order as $path) {
            $output[$path] = $data[$path];
        }

        $json = json_encode($output, JSON_PRETTY_PRINT);
        file_put_contents($outputPath, $json);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $totalTime = $endTime - $startTime;
        $readTimeOnly = $readTime - $startTime;
        $sortTimeOnly = $sortTime - $readTime;
        $writeTime = $endTime - $sortTime;
        $peakMemory = memory_get_peak_usage(true);

        echo "\n=== Parser Performance ===" . PHP_EOL;
        echo "Total time:     " . number_format($totalTime, 3) . "s" . PHP_EOL;
        echo "Read time:      " . number_format($readTimeOnly, 3) . "s" . PHP_EOL;
        echo "Sort time:      " . number_format($sortTimeOnly, 3) . "s" . PHP_EOL;
        echo "Write time:     " . number_format($writeTime, 3) . "s" . PHP_EOL;
        echo "Peak memory:    " . number_format($peakMemory / 1024 / 1024, 2) . " MB" . PHP_EOL;
        echo "===========================" . PHP_EOL;
    }

    private function extractPath(string $url): string
    {
        $parsed = parse_url($url);
        return $parsed['path'] ?? '';
    }

    private function extractDate(string $timestamp): string
    {
        return substr($timestamp, 0, 10);
    }
}