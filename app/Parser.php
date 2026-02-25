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

        $pathExtractTime = 0;
        $dateExtractTime = 0;
        $aggTime = 0;

        $handle = fopen($inputPath, 'r');
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $commaPos = strpos($line, ',');
            $url = substr($line, 0, $commaPos);
            $timestamp = substr($line, $commaPos + 1);

            $t0 = microtime(true);
            $path = $this->extractPath($url);
            $pathExtractTime += microtime(true) - $t0;

            $t1 = microtime(true);
            $date = $this->extractDate($timestamp);
            $dateExtractTime += microtime(true) - $t1;

            $t2 = microtime(true);
            if (!isset($data[$path])) {
                $data[$path] = [];
                $order[] = $path;
            }

            if (!isset($data[$path][$date])) {
                $data[$path][$date] = 0;
            }

            $data[$path][$date]++;
            $aggTime += microtime(true) - $t2;
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
        echo "  - path:       " . number_format($pathExtractTime, 3) . "s" . PHP_EOL;
        echo "  - date:       " . number_format($dateExtractTime, 3) . "s" . PHP_EOL;
        echo "  - agg:         " . number_format($aggTime, 3) . "s" . PHP_EOL;
        echo "Sort time:      " . number_format($sortTimeOnly, 3) . "s" . PHP_EOL;
        echo "Write time:     " . number_format($writeTime, 3) . "s" . PHP_EOL;
        echo "Peak memory:    " . number_format($peakMemory / 1024 / 1024, 2) . " MB" . PHP_EOL;
        echo "===========================" . PHP_EOL;
    }

    private function extractPath(string $url): string
    {
        return substr($url, 21);
    }

    private function extractDate(string $timestamp): string
    {
        return substr($timestamp, 0, 10);
    }
}
