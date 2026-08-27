<?php
/**
 * PAVANCAB GOA TAXI - Firebase Cloud Messaging (FCM) HTTP v1 Engine
 * Location: /app/fcm_v1.php
 * 
 * --------------------------------------------------------------------------
 * EASY-TO-READ CODE DOCUMENTATION FOR BEGINNERS:
 * 1. This file generates OAuth2 Access Tokens using Google Service Account credentials.
 * 2. It formats notification titles, messages, and deep link action URLs.
 * 3. It dispatches FCM Push Notifications directly to Android devices & desktop browsers.
 * --------------------------------------------------------------------------
 */

// Step 1: Define default Firebase Google Cloud Project ID
if (!defined('DEFAULT_FCM_PROJECT_ID')) {
    define('DEFAULT_FCM_PROJECT_ID', 'pavancab-4a1daa');
}

/**
 * Base64 URL safe encoder (converts binary signatures to web-safe strings)
 */
function fcmBase64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Retrieve Firebase Service Account JSON configuration.
 * Checked in order:
 * 1. Database table `app_config` (key: `fcm_service_account_json`)
 * 2. File `app/firebase-service-account.json`
 * 3. File `app/service-account.json`
 */
function getFirebaseServiceAccount() {
    static $cachedConfig = null;
    if ($cachedConfig !== null) return $cachedConfig;

    // 1. Check DB configuration
    try {
        if (function_exists('db')) {
            $conn = db();
            if ($conn) {
                $r = $conn->query("SELECT config_value FROM app_config WHERE config_key = 'fcm_service_account_json' LIMIT 1");
                if ($r && $row = $r->fetch_assoc()) {
                    $val = trim($row['config_value'] ?? '');
                    if (!empty($val)) {
                        $json = json_decode($val, true);
                        if (is_array($json) && !empty($json['client_email']) && !empty($json['private_key'])) {
                            $cachedConfig = $json;
                            return $cachedConfig;
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Fallback to local files
    }

    // 2. Check local JSON files
    $possibleFiles = [
        __DIR__ . '/firebase-service-account.json',
        __DIR__ . '/service-account.json',
        __DIR__ . '/../firebase-service-account.json',
    ];

    foreach ($possibleFiles as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $json = json_decode($content, true);
            if (is_array($json) && !empty($json['client_email']) && !empty($json['private_key'])) {
                $cachedConfig = $json;
                return $cachedConfig;
            }
        }
    }

    return null;
}

/**
 * Generates an OAuth 2.0 Access Token for Google APIs using RSA-SHA256 JWT
 */
function getGoogleOAuth2AccessToken($serviceAccount) {
    if (!$serviceAccount || empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
        return ['error' => 'Invalid Service Account JSON. Missing client_email or private_key.'];
    }

    $conn = function_exists('db') ? db() : null;

    // Check cached token in DB
    if ($conn) {
        try {
            $r = $conn->query("SELECT config_value FROM app_config WHERE config_key = 'fcm_oauth_token_cache' LIMIT 1");
            if ($r && $row = $r->fetch_assoc()) {
                $cache = json_decode($row['config_value'] ?? '', true);
                if (is_array($cache) && !empty($cache['access_token']) && !empty($cache['expires_at'])) {
                    if (time() < ($cache['expires_at'] - 120)) { // 2 minute buffer
                        return ['access_token' => $cache['access_token']];
                    }
                }
            }
        } catch (Exception $e) {}
    }

    $clientEmail = $serviceAccount['client_email'];
    $privateKey  = $serviceAccount['private_key'];
    $now = time();

    // 1. Create JWT Header & Claim Set
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss'   => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600
    ];

    $encodedHeader = fcmBase64UrlEncode(json_encode($header));
    $encodedClaims = fcmBase64UrlEncode(json_encode($claims));
    $signaturePayload = $encodedHeader . '.' . $encodedClaims;

    // 2. Sign with RSA-SHA256
    $signature = '';
    $privateKeyResource = openssl_pkey_get_private($privateKey);
    if (!$privateKeyResource) {
        return ['error' => 'Failed to parse private key. Please check PEM format in Service Account JSON.'];
    }

    $success = openssl_sign($signaturePayload, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
    if (!$success) {
        return ['error' => 'OpenSSL failed to sign OAuth2 JWT assertion.'];
    }

    $jwt = $signaturePayload . '.' . fcmBase64UrlEncode($signature);

    // 3. Exchange JWT with Google OAuth2 Token Endpoint
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['error' => 'cURL OAuth2 Token request failed: ' . $curlErr];
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200 || empty($data['access_token'])) {
        $msg = $data['error_description'] ?? $data['error'] ?? "HTTP $httpCode";
        return ['error' => 'Google OAuth2 Token exchange error: ' . $msg];
    }

    $accessToken = $data['access_token'];
    $expiresIn   = intval($data['expires_in'] ?? 3600);

    // Cache in DB for subsequent requests
    if ($conn) {
        try {
            $cachePayload = json_encode([
                'access_token' => $accessToken,
                'expires_at'   => $now + $expiresIn
            ]);
            $stmt = $conn->prepare("INSERT INTO app_config (config_key, config_value) VALUES ('fcm_oauth_token_cache', ?) ON DUPLICATE KEY UPDATE config_value = ?");
            if ($stmt) {
                $stmt->bind_param('ss', $cachePayload, $cachePayload);
                $stmt->execute();
            }
        } catch (Exception $e) {}
    }

    return ['access_token' => $accessToken];
}

/**
 * Send Push Notification via Firebase Cloud Messaging HTTP v1 API
 *
 * @param array|string $tokens Device registration tokens
 * @param string $title Notification title
 * @param string $body Notification body
 * @param array $data Custom key-value data payload
 * @return array Diagnostic summary ['sent' => int, 'failed' => int, 'errors' => array]
 */
function sendFCMv1Push($tokens, $title, $body, $data = []) {
    if (empty($tokens)) {
        return ['sent' => 0, 'failed' => 0, 'errors' => ['No FCM tokens provided']];
    }

    if (!is_array($tokens)) {
        $tokens = [$tokens];
    }
    $tokens = array_values(array_unique(array_map('trim', array_filter($tokens))));
    if (empty($tokens)) {
        return ['sent' => 0, 'failed' => 0, 'errors' => ['No valid FCM tokens after filtering']];
    }

    $sa = getFirebaseServiceAccount();
    if (!$sa) {
        $err = 'FCM Service Account JSON not configured. Please add it via Admin Dashboard Settings or app/firebase-service-account.json';
        logFcmStatus('error', $err);
        return ['sent' => 0, 'failed' => count($tokens), 'errors' => [$err]];
    }

    $projectId = !empty($sa['project_id']) ? $sa['project_id'] : DEFAULT_FCM_PROJECT_ID;

    // Get OAuth2 Access Token
    $authResult = getGoogleOAuth2AccessToken($sa);
    if (!empty($authResult['error'])) {
        logFcmStatus('error', $authResult['error']);
        return ['sent' => 0, 'failed' => count($tokens), 'errors' => [$authResult['error']]];
    }

    $accessToken = $authResult['access_token'];
    $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    // Prepare stringified data values (FCM requires string values in data map)
    $cleanData = [];
    foreach ($data as $k => $v) {
        $cleanData[strval($k)] = is_scalar($v) ? strval($v) : json_encode($v);
    }
    $cleanData['timestamp'] = strval(time());
    $cleanData['title'] = $title;
    $cleanData['body'] = $body;

    $clickUrl = $cleanData['url'] ?? $cleanData['click_action'] ?? 'https://pavancab.com/app/';
    if (!preg_match('/^https?:\/\//i', $clickUrl)) {
        $clickUrl = 'https://pavancab.com/' . ltrim($clickUrl, '/');
    }
    $cleanData['url'] = $clickUrl;
    $cleanData['click_action'] = $clickUrl;

    // Multi-curl for fast parallel dispatch
    $mh = curl_multi_init();
    $handles = [];
    $tokenMap = [];

    foreach ($tokens as $idx => $token) {
        $messagePayload = [
            'message' => [
                'token' => $token,
                'data' => $cleanData,
                'android' => [
                    'priority' => 'high',
                    'ttl' => '86400s'
                ],
                'webpush' => [
                    'headers' => [
                        'Urgency' => 'high',
                        'TTL'     => '86400'
                    ],
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                        'icon'  => 'https://pavancab.com/app/logo-pavancab.png',
                        'badge' => 'https://pavancab.com/app/logo-pavancab.png',
                        'vibrate' => [200, 100, 200, 100, 300],
                        'requireInteraction' => true,
                        'tag'   => 'pavancab-alert-' . time()
                    ],
                    'fcm_options' => [
                        'link' => $clickUrl
                    ]
                ]
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($messagePayload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        curl_multi_add_handle($mh, $ch);
        $handles[$idx] = $ch;
        $tokenMap[$idx] = $token;
    }

    // Execute all curl handles concurrently
    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($mh, 0.05) == -1) {
            usleep(5000);
        }
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    }

    $sentCount = 0;
    $failCount = 0;
    $errors = [];
    $invalidTokens = [];

    foreach ($handles as $idx => $ch) {
        $res = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $t = $tokenMap[$idx];

        if ($httpCode === 200) {
            $sentCount++;
        } else {
            $failCount++;
            $respData = json_decode($res, true);
            $errMsg = $respData['error']['message'] ?? "HTTP $httpCode: " . substr($res, 0, 150);
            $errors[] = "Token " . substr($t, 0, 15) . "... failed: $errMsg";

            // If token is invalid/unregistered, queue for cleanup
            if ($httpCode === 404 || (isset($respData['error']['details']) && strpos(json_encode($respData['error']['details']), 'UNREGISTERED') !== false)) {
                $invalidTokens[] = $t;
            }
        }

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    // Clean up dead/unregistered tokens from DB
    if (!empty($invalidTokens) && function_exists('db')) {
        try {
            $conn = db();
            foreach ($invalidTokens as $deadToken) {
                $st = $conn->prepare("DELETE FROM app_fcm_tokens WHERE fcm_token = ?");
                if ($st) {
                    $st->bind_param('s', $deadToken);
                    $st->execute();
                }
            }
        } catch (Exception $e) {}
    }

    $summaryMsg = "Dispatched FCM Push: Sent $sentCount, Failed $failCount";
    if (!empty($errors)) {
        $summaryMsg .= " | Errors: " . implode('; ', array_slice($errors, 0, 3));
    }
    logFcmStatus($sentCount > 0 ? 'success' : ($failCount > 0 ? 'warning' : 'info'), $summaryMsg);

    return [
        'sent'    => $sentCount,
        'failed'  => $failCount,
        'errors'  => $errors,
        'summary' => $summaryMsg
    ];
}

/**
 * Log recent FCM dispatch status to app_config
 */
function logFcmStatus($level, $message) {
    try {
        if (!function_exists('db')) return;
        $conn = db();
        if (!$conn) return;

        $payload = json_encode([
            'level'     => $level,
            'message'   => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        $stmt = $conn->prepare("INSERT INTO app_config (config_key, config_value) VALUES ('fcm_last_status', ?) ON DUPLICATE KEY UPDATE config_value = ?");
        if ($stmt) {
            $stmt->bind_param('ss', $payload, $payload);
            $stmt->execute();
        }
    } catch (Exception $e) {}
}
