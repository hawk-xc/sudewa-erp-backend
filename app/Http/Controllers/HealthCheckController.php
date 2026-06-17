<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HealthCheckController extends Controller
{
    /**
     * Perform a system health check.
     *
     * @return JsonResponse
     */
    public function check(): JsonResponse
    {
        $status = 'healthy';
        $checks = [];

        // 1. Database Check
        try {
            $dbStartTime = microtime(true);
            // Simple query to test DB connection
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $dbDuration = round((microtime(true) - $dbStartTime) * 1000, 2);

            $checks['database'] = [
                'status' => 'healthy',
                'connection' => DB::getDefaultConnection(),
                'database_name' => DB::connection()->getDatabaseName(),
                'latency_ms' => $dbDuration,
            ];
        } catch (\Exception $e) {
            $status = 'unhealthy';
            $checks['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            Log::error('Health Check - Database Failure: ' . $e->getMessage());
        }

        // 2. Cache Check
        try {
            $cacheKey = 'healthcheck_probe_' . time();
            Cache::put($cacheKey, true, 10);
            $cacheVal = Cache::get($cacheKey);
            Cache::forget($cacheKey);

            if ($cacheVal === true) {
                $checks['cache'] = [
                    'status' => 'healthy',
                    'driver' => config('cache.default'),
                ];
            } else {
                throw new \Exception('Cache read/write test failed.');
            }
        } catch (\Exception $e) {
            $status = 'unhealthy';
            $checks['cache'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            Log::error('Health Check - Cache Failure: ' . $e->getMessage());
        }

        // 3. Storage Check (writable)
        $storagePaths = [
            'app' => storage_path('app'),
            'framework' => storage_path('framework'),
            'logs' => storage_path('logs'),
        ];

        $storageCheck = ['status' => 'healthy', 'paths' => []];
        foreach ($storagePaths as $key => $path) {
            $isWritable = is_writable($path);
            $storageCheck['paths'][$key] = [
                'path' => $path,
                'writable' => $isWritable,
            ];
            if (!$isWritable) {
                $status = 'unhealthy';
                $storageCheck['status'] = 'unhealthy';
            }
        }
        $checks['storage'] = $storageCheck;

        // 4. Server Resources (Disk Usage)
        try {
            $diskFree = disk_free_space(base_path());
            $diskTotal = disk_total_space(base_path());
            $diskUsed = $diskTotal - $diskFree;
            $diskUsedPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 2) : 0;

            $checks['system'] = [
                'disk' => [
                    'free' => $this->formatBytes($diskFree),
                    'total' => $this->formatBytes($diskTotal),
                    'used' => $this->formatBytes($diskUsed),
                    'percentage_used' => $diskUsedPercent . '%',
                ],
                'memory_limit' => ini_get('memory_limit'),
                'php_memory_usage' => $this->formatBytes(memory_get_usage(true)),
            ];

            // CPU Load (if available on non-Windows)
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                if ($load !== false) {
                    $checks['system']['cpu_load'] = [
                        '1min' => $load[0],
                        '5min' => $load[1],
                        '15min' => $load[2],
                    ];
                }
            }
        } catch (\Exception $e) {
            $checks['system'] = [
                'status' => 'warning',
                'error' => $e->getMessage(),
            ];
        }

        // 5. Application Info
        $appInfo = [
            'name' => config('app.name'),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'timestamp' => now()->toIso8601String(),
        ];

        $httpStatus = $status === 'healthy' ? 200 : 503;

        return response()->json([
            'status' => $status,
            'app' => $appInfo,
            'checks' => $checks,
        ], $httpStatus);
    }

    /**
     * Format bytes to readable format.
     *
     * @param float $bytes
     * @param int $precision
     * @return string
     */
    private function formatBytes(float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
