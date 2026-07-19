<?php
/**
 * Little Stars Pre School — FCM Push Notification Helper (V1 API)
 *
 * SETUP:
 *   1. service-account.json file 
 *   2. FCM_SERVICE_ACCOUNT_PATH correct path 
 *   3. FCM_PROJECT_ID  Firebase project ID 
 *   4.  require_once 'fcm_notifications.php';
 */

// ═══════════════════════════════════════════════
//  CONFIG —
// ═══════════════════════════════════════════════
define('FCM_SERVICE_ACCOUNT_PATH', __DIR__ . '/service-account.json');
define('FCM_PROJECT_ID', 'pre-school-management-sy-689f0');  // Firebase Project ID


// ═══════════════════════════════════════════════
//  ACCESS TOKEN — Service Account JSON 
// ═══════════════════════════════════════════════

function getFCMAccessToken(): string
{
    $serviceAccount = json_decode(file_get_contents(FCM_SERVICE_ACCOUNT_PATH), true);

    $now    = time();
    $expiry = $now + 3600;

    // JWT Header
    $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $header = rtrim(strtr($header, '+/', '-_'), '=');

    // JWT Claim
    $claim = base64_encode(json_encode([
        'iss'   => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $expiry,
    ]));
    $claim = rtrim(strtr($claim, '+/', '-_'), '=');

    // Sign JWT
    $signingInput = "$header.$claim";
    $privateKey   = $serviceAccount['private_key'];
    openssl_sign($signingInput, $signature, $privateKey, 'SHA256');
    $signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    $jwt = "$signingInput.$signature";

    // Exchange JWT for Access Token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($response['access_token'])) {
        throw new \RuntimeException('FCM Access Token ගන්න බැරි වුණා: ' . json_encode($response));
    }

    return $response['access_token'];
}


// ═══════════════════════════════════════════════
//  CORE FUNCTION — Single notification
// ═══════════════════════════════════════════════

/**
 * FCM token notification  (V1 API)
 *
 * @param string $token   Parent/Admin FCM device token
 * @param string $title   Notification title
 * @param string $body    Notification message
 * @param array  $data    Extra data (optional)
 * @return array          ['success' => bool, 'response' => ...]
 */
function sendFCMNotification(string $token, string $title, string $body, array $data = []): array
{
    try {
        $accessToken = getFCMAccessToken();
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }

    $url = 'https://fcm.googleapis.com/v1/projects/' . FCM_PROJECT_ID . '/messages:send';

    $payload = [
        'message' => [
            'token'        => $token,
            'notification' => [
                'title' => $title,
                'body'  => $body,
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'color' => '#E91E8C',
                    'sound' => 'default',
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => ['sound' => 'default', 'badge' => 1],
                ],
            ],
            'data' => array_map('strval', $data),
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode($response, true);
    $success = ($httpCode === 200 && isset($decoded['name']));

    return [
        'success'  => $success,
        'http'     => $httpCode,
        'response' => $decoded,
        'error'    => $error ?: null,
    ];
}


// ═══════════════════════════════════════════════
//  CORE FUNCTION — Multiple tokens 
// ═══════════════════════════════════════════════

/**යවන්න
 *
 * @param array  $tokens  FCM token array
 * @param string $title
 * @param string $body
 * @param array  $data
 * @return array
 */
function sendFCMToMultiple(array $tokens, string $title, string $body, array $data = []): array
{
    if (empty($tokens)) return ['success' => false, 'error' => 'No tokens provided'];

    $results       = ['success_count' => 0, 'failure_count' => 0, 'errors' => []];
    $accessToken   = null;

    try {
        $accessToken = getFCMAccessToken();
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }

    $url = 'https://fcm.googleapis.com/v1/projects/' . FCM_PROJECT_ID . '/messages:send';

    foreach ($tokens as $token) {
        $payload = [
            'message' => [
                'token'        => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'android'      => ['priority' => 'high', 'notification' => ['color' => '#E91E8C', 'sound' => 'default']],
                'data'         => array_map('strval', $data),
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = json_decode(curl_exec($ch), true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && isset($response['name'])) {
            $results['success_count']++;
        } else {
            $results['failure_count']++;
            $results['errors'][] = $response['error']['message'] ?? 'Unknown error';
        }
    }

    $results['success'] = $results['success_count'] > 0;
    return $results;
}


// ═══════════════════════════════════════════════
//  PRESCHOOL SPECIFIC HELPERS
// ═══════════════════════════════════════════════

/**
 * 💳 Payment Alert — Parent- payment reminder
 */
function notifyPaymentDue(string $token, string $studentName, string $amount, string $dueDate = ''): array
{
    $duePart = $dueDate ? " | Due: $dueDate" : ' | No due date set';
    return sendFCMNotification(
        $token,
        '💳 Payment Reminder — Little Stars',
        "$studentName — $amount pending$duePart",
        ['type' => 'payment', 'student' => $studentName]
    );
}

/**
 * 📋 Absent Alert — Admin absent notification
 */
function notifyStudentAbsent(string $token, string $studentName, string $className, string $note = '', string $date = ''): array
{
    $date     = $date ?: date('d M Y');
    $notePart = $note ? " — Note: $note" : '';
    return sendFCMNotification(
        $token,
        '📋 Student Absent — Little Stars',
        "$studentName ($className) is absent today$notePart | $date",
        ['type' => 'absent', 'student' => $studentName, 'class' => $className]
    );
}

/**
 * 🎂 Birthday Alert — Admin/Teacher- birthday reminder
 */
function notifyBirthday(string $token, string $studentName, string $age, string $className): array
{
    return sendFCMNotification(
        $token,
        '🎂 Birthday Today — Little Stars',
        "$studentName from $className is turning $age today! 🎉",
        ['type' => 'birthday', 'student' => $studentName]
    );
}

/**
 * 📢 General Announcement — all parents broadcast
 */
function notifyAnnouncement(array $tokens, string $title, string $message): array
{
    return sendFCMToMultiple($tokens, "📢 $title", $message, ['type' => 'announcement']);
}


// ═══════════════════════════════════════════════
//  DATABASE TOKEN HELPERS
// ═══════════════════════════════════════════════

/**
 * Parent/Admin FCM token DB save
 * 
 * SQL:
 * CREATE TABLE fcm_tokens (
 *   id         INT AUTO_INCREMENT PRIMARY KEY,
 *   user_id    INT NOT NULL,
 *   token      TEXT NOT NULL,
 *   updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *   UNIQUE KEY unique_user (user_id)
 * );
 */
function saveFCMToken(PDO $pdo, int $userId, string $token): void
{
    $stmt = $pdo->prepare("
        INSERT INTO fcm_tokens (user_id, token, updated_at)
        VALUES (:uid, :token, NOW())
        ON DUPLICATE KEY UPDATE token = :token, updated_at = NOW()
    ");
    $stmt->execute([':uid' => $userId, ':token' => $token]);
}

/**
 * User ID එකෙන් FCM token 
 */
function getFCMToken(PDO $pdo, int $userId): ?string
{
    $stmt = $pdo->prepare("SELECT token FROM fcm_tokens WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['token'] : null;
}

/**
 * සියලු Admin tokens
 */
function getAllAdminTokens(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT token FROM fcm_tokens WHERE role = 'admin'");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}


/*
 * ════════════════════════════════════════════════════════
 *  USAGE EXAMPLES — 
 * ════════════════════════════════════════════════════════
 *
 * require_once 'fcm_notifications.php';
 *
 * // 1. Payment alert
 * $result = notifyPaymentDue($parentToken, 'Ranulya Ishali', 'LKR 5,000');
 *
 * // 2. Absent alert
 * $result = notifyStudentAbsent($adminToken, 'Nathasha Dewmini', 'Rainbow', 'Sick');
 *
 * // 3. Birthday
 * $result = notifyBirthday($adminToken, 'Ranulya Ishali', '3', 'Sunflower');
 *
 * // 4. Announcement to all parents
 * $allTokens = ['token1', 'token2', 'token3'];
 * $result = notifyAnnouncement($allTokens, 'School Closed', 'School will be closed tomorrow.');
 *
 * // 5. Result check
 * if ($result['success']) {
 *     echo "✅ Notification sent!";
 * } else {
 *     error_log("FCM Error: " . print_r($result, true));
 * }
 * ════════════════════════════════════════════════════════
 */
