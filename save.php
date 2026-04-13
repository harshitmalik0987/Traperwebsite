<?php
/**
 * save.php — Self-hosted backend for VoidAdmin
 * Upload this to: /var/www/html/api/save.php  (or wherever your web root is)
 *
 * GET  /api/save.php          → returns full JSON data
 * POST /api/save.php          → saves JSON data (requires write password)
 *
 * SETUP:
 *  1. Change WRITE_PASSWORD below to something secret
 *  2. Upload to your VM's web server
 *  3. Make sure the web server user (www-data) can write to this directory
 *     Run: chown www-data:www-data /var/www/html/api/
 *          chmod 755 /var/www/html/api/
 */

// ── CONFIG ────────────────────────────────────────────────
define('WRITE_PASSWORD', 'your_secret_write_password');  // CHANGE THIS
define('DATA_FILE', __DIR__ . '/data.json');              // JSON stored next to this file
// ──────────────────────────────────────────────────────────

// CORS — allow your domain (or * for open access)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── GET: return the data file ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists(DATA_FILE)) {
        // Return empty default structure on first run
        echo json_encode(['config' => (object)[], 'requests' => [], 'members' => []]);
    } else {
        // Stream the file directly — fastest possible, no parsing
        readfile(DATA_FILE);
    }
    exit;
}

// ── POST: save data ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        http_response_code(400);
        echo json_encode(['error' => 'Empty body']);
        exit;
    }

    $body = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    // Check write password
    $pass = isset($body['password']) ? $body['password'] : '';
    if ($pass !== WRITE_PASSWORD) {
        http_response_code(403);
        echo json_encode(['error' => 'Wrong write password']);
        exit;
    }

    $data = isset($body['data']) ? $body['data'] : null;
    if ($data === null) {
        http_response_code(400);
        echo json_encode(['error' => 'No data field in body']);
        exit;
    }

    // Write atomically: write to temp file then rename (prevents corruption)
    $tmp = DATA_FILE . '.tmp';
    $written = file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE));
    if ($written === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to write file. Check folder permissions.']);
        exit;
    }
    rename($tmp, DATA_FILE);

    echo json_encode(['ok' => true]);
    exit;
}

// Other methods not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
