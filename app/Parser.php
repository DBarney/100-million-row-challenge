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

        // Generate all dates from 2021-01-01 to 2026-12-31
        $dateTemplate = [];
        for ($year = 2021; $year <= 2026; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                for ($day = 1; $day <= 31; $day++) {
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $dateTemplate[$dateStr] = 0;
                }
            }
        }

        $templateTime = microtime(true);

        $allPathsFound = false;
        $linesSinceLastNew = 0;
        $handle = fopen($inputPath, 'r');
        while (($line = fgets($handle)) !== false) {
            $lineLen = strlen($line);
            if ($lineLen < 30) {
                continue;
            }

            $t0 = microtime(true);
            $path = substr($line, 19, $lineLen - 46);
            $pathExtractTime += microtime(true) - $t0;

            $t1 = microtime(true);
            $date = substr($line, $lineLen - 26, 10);
            $dateExtractTime += microtime(true) - $t1;

            $t2 = microtime(true);
            if ($allPathsFound) {
                $data[$path][$date]++;
            } else {
                if (!isset($data[$path])) {
                    $data[$path] = $dateTemplate;
                    $order[] = $path;
                    $linesSinceLastNew = 0;
                } else {
                    $linesSinceLastNew++;
                }
                $data[$path][$date]++;

                if ($linesSinceLastNew > 2000) {
                    $allPathsFound = true;
                }
            }
            $aggTime += microtime(true) - $t2;
        }
        fclose($handle);

        $readTime = microtime(true);
        $readMemory = memory_get_usage(true);

        // Filter out zero counts
        foreach ($data as $path => &$dates) {
            $dates = array_filter($dates, fn($count) => $count > 0);
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
        $templateOnly = $templateTime - $startTime;
        $readTimeOnly = $readTime - $templateTime;
        $sortTimeOnly = $sortTime - $readTime;
        $writeTime = $endTime - $sortTime;
        $peakMemory = memory_get_peak_usage(true);

        echo "\n=== Parser Performance ===" . PHP_EOL;
        echo "Total time:     " . number_format($totalTime, 3) . "s" . PHP_EOL;
        echo "Template gen:   " . number_format($templateOnly, 3) . "s" . PHP_EOL;
        echo "Read time:      " . number_format($readTimeOnly, 3) . "s" . PHP_EOL;
        echo "  - path:       " . number_format($pathExtractTime, 3) . "s" . PHP_EOL;
        echo "  - date:       " . number_format($dateExtractTime, 3) . "s" . PHP_EOL;
        echo "  - agg:         " . number_format($aggTime, 3) . "s" . PHP_EOL;
        echo "Sort/filter:    " . number_format($sortTimeOnly, 3) . "s" . PHP_EOL;
        echo "Write time:     " . number_format($writeTime, 3) . "s" . PHP_EOL;
        echo "Peak memory:    " . number_format($peakMemory / 1024 / 1024, 2) . " MB" . PHP_EOL;
        echo "===========================" . PHP_EOL;
    }
}
