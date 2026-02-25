<?php

namespace App;

final class Parser
{
    private const NUM_WORKERS = 4;

    public function parse(string $inputPath, string $outputPath): void
    {
        $startTime = microtime(true);

        $fileSize = filesize($inputPath);
        $chunkSize = (int) ceil($fileSize / self::NUM_WORKERS);

        $pids = [];
        $pipes = [];

        for ($i = 0; $i < self::NUM_WORKERS; $i++) {
            $startByte = $i * $chunkSize;
            $endByte = ($i + 1) * $chunkSize - 1;

            $pipes[$i] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($pipes[$i] === false) {
                die("pipe failed");
            }

            stream_set_chunk_size($pipes[$i][0], 1024 * 1024);
            stream_set_chunk_size($pipes[$i][1], 1024 * 1024);

            $pid = pcntl_fork();
            if ($pid === -1) {
                die("fork failed");
            } elseif ($pid === 0) {
                fclose($pipes[$i][0]);
                $result = $this->processChunk($inputPath, $startByte, $endByte);
                $serialized = serialize($result);
                fwrite($pipes[$i][1], $serialized);
                fclose($pipes[$i][1]);
                exit(0);
            } else {
                $pids[$i] = $pid;
                fclose($pipes[$i][1]);
            }
        }

        $mergedData = [];
        $mergedOrder = [];

        for ($i = 0; $i < self::NUM_WORKERS; $i++) {
            $data = '';
            while (!feof($pipes[$i][0])) {
                $data .= fread($pipes[$i][0], 8192);
            }
            fclose($pipes[$i][0]);
            pcntl_waitpid($pids[$i], $status);

            $result = unserialize($data);
            foreach ($result['order'] as $path) {
                if (!isset($mergedData[$path])) {
                    $mergedData[$path] = [];
                    $mergedOrder[] = $path;
                }
                foreach ($result['data'][$path] as $date => $count) {
                    if ($count > 0) {
                        if (!isset($mergedData[$path][$date])) {
                            $mergedData[$path][$date] = $count;
                        } else {
                            $mergedData[$path][$date] += $count;
                        }
                    }
                }
            }
        }

        foreach ($mergedData as $path => &$dates) {
            ksort($dates, SORT_STRING);
        }

        $sortTime = microtime(true);

        $output = [];
        foreach ($mergedOrder as $path) {
            $output[$path] = $mergedData[$path];
        }

        $json = json_encode($output, JSON_PRETTY_PRINT);
        file_put_contents($outputPath, $json);

        $endTime = microtime(true);
        $peakMemory = memory_get_peak_usage(true);

        $totalTime = $endTime - $startTime;

        echo "\n=== Parser Performance ===" . PHP_EOL;
        echo "Total time:     " . number_format($totalTime, 3) . "s" . PHP_EOL;
        echo "Peak memory:    " . number_format($peakMemory / 1024 / 1024, 2) . " MB" . PHP_EOL;
        echo "===========================" . PHP_EOL;
    }

    private function processChunk(string $inputPath, int $startByte, int $endByte): array
    {
        $order = [];
        $data = [];

        $dateTemplate = [];
        for ($year = 2021; $year <= 2026; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                for ($day = 1; $day <= 31; $day++) {
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $dateTemplate[$dateStr] = 0;
                }
            }
        }

        $handle = fopen($inputPath, 'r');
        fseek($handle, $startByte);

        if ($startByte > 0) {
            fgets($handle);
        }

        while (ftell($handle) <= $endByte && ($line = fgets($handle)) !== false) {
            $lineLen = strlen($line);
            if ($lineLen < 30) {
                continue;
            }

            $path = substr($line, 19, $lineLen - 46);
            $date = substr($line, $lineLen - 26, 10);

            if (!isset($data[$path])) {
                $data[$path] = $dateTemplate;
                $order[] = $path;
            }
            $data[$path][$date]++;
        }

        fclose($handle);

        $filteredData = [];
        foreach ($data as $path => $dates) {
            foreach ($dates as $date => $count) {
                if ($count > 0) {
                    $filteredData[$path][$date] = $count;
                }
            }
        }

        return ['data' => $filteredData, 'order' => $order];
    }
}
