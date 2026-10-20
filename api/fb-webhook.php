<?php

declare(strict_types=1);

/**
 * Facebook Messenger webhook for SCiGENIE.
 *
 * Meta for Developers setup:
 * 1. Create a Business app at https://developers.facebook.com/
 * 2. Add the Messenger product and connect the faculty Page
 * 3. Generate a Page access token; copy the App secret
 * 4. Callback URL (case-sensitive):
 *    https://sci.kku.ac.th/app/SCiGENIE/api/fb-webhook.php
 * 5. Verify token must match FACEBOOK_VERIFY_TOKEN in .env
 *    Default: scigenie-kku-fb-2026
 * 6. Subscribe to: messages, messaging_postbacks, message_echoes, messaging_handovers
 * 7. Put FACEBOOK_PAGE_ACCESS_TOKEN and FACEBOOK_APP_SECRET in server .env
 *
 * During development only app admins/testers can chat. Public chat needs
 * App Review for pages_messaging.
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = (string) ($_GET['hub_mode'] ?? '');
    $token = (string) ($_GET['hub_verify_token'] ?? '');
    $challenge = (string) ($_GET['hub_challenge'] ?? '');
    if ($mode === 'subscribe') {
        if (MessengerBot::verifySubscription($token)) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(200);
            echo $challenge;
            exit;
        }
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden';
        exit;
    }
    if (MessengerBot::configured()) {
        MessengerBot::subscribePage();
        MessengerBot::setupPersistentMenu();
    }
    json_response([
        'ok' => true,
        'service' => 'scigenie-facebook',
        'configured' => MessengerBot::configured(),
        'has_page_token' => (string) config('facebook_page_access_token', '') !== '',
        'has_app_secret' => (string) config('facebook_app_secret', '') !== '',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$raw = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '');

if (!MessengerBot::configured()) {
    error_log('SCiGENIE Facebook: not configured');
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"not configured"}';
    exit;
}

if (!MessengerBot::verifySignature($raw, $signature)) {
    json_response(['ok' => false, 'error' => 'invalid signature'], 403);
}

$payload = json_decode($raw, true, 512, JSON_BIGINT_AS_STRING);
flush_http_response(200, '{"ok":true}');
if (is_array($payload)) {
    try {
        MessengerBot::handlePayload($payload);
    } catch (Throwable $e) {
        error_log('SCiGENIE Facebook: ' . $e->getMessage());
    }
}
exit;
