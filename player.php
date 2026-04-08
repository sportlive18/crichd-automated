<?php
error_reporting(0);
require_once 'channels.php'; // Use the central channel list

// --- SCRIPT ROUTER ---
$action = isset($_GET['action']) ? $_GET['action'] : 'proxy';

switch ($action) {
    case 'get_channels':
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($channels, JSON_UNESCAPED_UNICODE);
        break;

    case 'get_link':
        $channel_id = isset($_GET['channel']) ? $_GET['channel'] : 'skysme';
        get_and_echo_master_link($channel_id);
        break;

    case 'proxy':
    default:
        $channel_id = isset($_GET['channel']) ? $_GET['channel'] : 'skysme';
        proxy_hls_stream($channel_id);
        break;
}
exit;

// --- CORE FUNCTIONS ---

function get_master_stream_link($channel_id) {
    global $channels;
    if (!isset($channels[$channel_id])) return null;

    $selected_channel = $channels[$channel_id];
    $premium_php_url = "https://profamouslife.com/premium.php?player=desktop&live={$channel_id}";
    $initial_referer_url = $selected_channel['referer'];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $premium_php_url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer: ' . $initial_referer_url
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response && preg_match('/return\(\[(.*?)\]\.join/', $response, $matches)) {
        $char_list_str = str_replace(array("\'", '"'), '', $matches[1]);
        $char_list = explode(',', $char_list_str);
        return str_replace('\\/', '/', implode('', $char_list));
    }
    return null;
}

function get_and_echo_master_link($channel_id) {
    header("Content-Type: text/plain");
    $link = get_master_stream_link($channel_id);
    if ($link) {
        echo $link;
    } else {
        http_response_code(502);
        echo "Error: Could not fetch stream link.";
    }
}

function proxy_hls_stream($channel_id) {
    global $channels;
    if (!isset($channels[$channel_id])) { http_response_code(404); die("Channel not found."); }
    
    $self_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . strtok($_SERVER["REQUEST_URI"], '?');
    $stream_referer_url = "https://profamouslife.com/premium.php?player=desktop&live={$channel_id}";
    $url_to_proxy = '';

    if (isset($_GET['proxy_url'])) {
        $decoded_url_b64 = base64_decode(trim($_GET['proxy_url']), true);
        if ($decoded_url_b64) $url_to_proxy = $decoded_url_b64;
    } else {
        $url_to_proxy = get_master_stream_link($channel_id);
    }

    if (!$url_to_proxy || !filter_var($url_to_proxy, FILTER_VALIDATE_URL)) {
        http_response_code(400); die("Invalid or missing URL to proxy.");
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url_to_proxy,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Referer: ' . $stream_referer_url, 'User-Agent: Mozilla/5.0']
    ]);
    $content = curl_exec($ch);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($content === false) { http_response_code(502); die("Fetch failed."); }

    header("Access-Control-Allow-Origin: *");
    header("Content-Type: " . $content_type);

    if (strpos($content_type, 'mpegurl') !== false) {
        $base_url = substr($url_to_proxy, 0, strrpos($url_to_proxy, '/') + 1);
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && $line[0] !== '#') {
                $absolute_url = (preg_match('#^https?://#i', $line)) ? $line : $base_url . $line;
                echo $self_url . '?channel=' . $channel_id . '&proxy_url=' . base64_encode($absolute_url) . "\n";
            } else {
                echo $line . "\n";
            }
        }
    } else {
        echo $content;
    }
}
?>
