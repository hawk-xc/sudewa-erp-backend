<?php

use App\Models\SystemLog;
use App\Models\UserLog;
use Illuminate\Support\Facades\Auth;

if (! function_exists('user_log')) {
    function user_log($action, $user_id = null, $email = null, $type = 'auth', $message = null)
    {
        return UserLog::create([
            'user_id' => $user_id,
            'email' => $email,
            'type' => $type,
            'action' => $action,
            'message' => $message,
        ]);
    }
}

if (! function_exists('system_log')) {
    function system_log($type = 'info', $message = null)
    {
        SystemLog::create([
            'type' => $type,
            'message' => $message,
        ]);
    }
}

if (! function_exists('downloadMedia')) {
    function downloadMedia(string $fileName, string $label = 'in')
    {

        $endpoint = rtrim(env('DAHUA_API_ENDPOINT'), '/');
        $username = env('DAHUA_DIGEST_USERNAME');
        $password = env('DAHUA_DIGEST_PASSWORD');

        try {
            $savePath = storage_path('app/public/faceDetection_folder/'.$label.'/'.$fileName);

            if (! file_exists(dirname($savePath))) {
                mkdir(dirname($savePath), 0775, true);
            }

            $jar = new \GuzzleHttp\Cookie\CookieJar();

            $client = new \GuzzleHttp\Client([
                'base_uri' => $endpoint,
                'verify' => false,
                'cookies' => $jar,
                'timeout' => 60,
                'curl' => [
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_FORBID_REUSE => true,
                    CURLOPT_FRESH_CONNECT => true,
                ],
                'headers' => [
                    'User-Agent' => 'curl/7.85.0',
                    'Connection' => 'close',
                    'Accept' => '*/*',
                ],
                'http_errors' => false,
            ]);

            $url = '/cgi-bin/RPC_Loadfile/'.$fileName;

            try {
                $client->get($url, ['http_errors' => false]);
            } catch (\Exception $e) {
                return $e->getMessage();
            }

            // 2) real request with digest auth
            $res = $client->get($url, [
                'auth' => [$username, $password, 'digest'],
                'sink' => $savePath,
                'http_errors' => false,
                'allow_redirects' => false,
            ]);

            $status = $res->getStatusCode();
            if ($status === 200 && file_exists($savePath)) {
                return '/storage/faceDetection_folder/'.$label.'/'.$fileName;
            } else {
                return "Download gagal: HTTP {$status}, val={$val}";
            }
        } catch (\Exception $err) {
            return $err->getMessage();
        }
    }
}

function normalizeFaceImagePath(string $path): array
{
    $path = ltrim($path, '/');

    if (! str_starts_with($path, 'face-detection/')) {
        throw new Exception("Invalid face image path: {$path}");
    }

    return [
        'storage' => $path, // ONLY THIS
    ];
}

/**
 * Curl Multipart (upload file)
 */
if (! function_exists('curlMultipart')) {
    function curlMultipart(string $url, array $fields): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => $fields,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);

        curl_close($ch);

        return [
            'status' => $status,
            'body' => $body,
            'error' => $errno,
        ];
    }
}

/**
 * Curl application/x-www-form-urlencoded
 */
if (! function_exists('curlUrlencoded')) {

    function curlUrlencoded(string $url, array $fields): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => http_build_query($fields),
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);

        curl_close($ch);

        return [
            'status' => $status,
            'body' => $body,
            'error' => $errno,
        ];
    }
}
