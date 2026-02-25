<?php

namespace App;

final class Parser
{
    public function parse(string $inputPath, string $outputPath): void
    {
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

        foreach ($data as $path => &$dates) {
            ksort($dates, SORT_STRING);
        }

        $output = [];
        foreach ($order as $path) {
            $output[$path] = $data[$path];
        }

        $json = json_encode($output, JSON_PRETTY_PRINT);
        file_put_contents($outputPath, $json);
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