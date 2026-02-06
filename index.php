<?php
// [중요] DB 설정 로드
if (file_exists('db_config.php')) {
    include 'db_config.php';
} else {
    $db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'test';
}

// --------------------------------------------------------------------
// [AWS 메타데이터] EC2 정보 (IP, AZ) 가져오기
// --------------------------------------------------------------------
function getAwsMetadata() {
    // 1. 토큰 발급 (IMDSv2)
    $opts = [
        'http' => [
            'method' => 'PUT',
            'header' => "X-aws-ec2-metadata-token-ttl-seconds: 21600\r\n",
            'timeout' => 0.1 
        ]
    ];
    $context = stream_context_create($opts);
    $token = @file_get_contents('http://169.254.169.254/latest/api/token', false, $context);

    // 기본값 설정 (실패 시 Docker IP 표시)
    $data = [
        'ip' => $_SERVER['SERVER_ADDR'] . " (Container IP)",
        'az' => 'Unknown Zone'
    ];

    if ($token) {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "X-aws-ec2-metadata-token: $token\r\n",
                'timeout' => 0.1
            ]
        ];
        $context = stream_context_create($opts);

        // A. 진짜 Private IP 가져오기
        $real_ip = @file_get_contents('http://169.254.169.254/latest/meta-data/local-ipv4', false, $context);
        if ($real_ip) $data['ip'] = $real_ip;

        // B. AZ 가져오기
        $real_az = @file_get_contents('http://169.254.169.254/latest/meta-data/placement/availability-zone', false, $context);
        if ($real_az) $data['az'] = $real_az;
    } 
    // 토큰 발급 실패했지만 IP가 10.100 대역인 경우 (혹시 모를 예외 처리)
    else if (strpos($_SERVER['SERVER_ADDR'], '10.100') === 0) {
        $data['ip'] = $_SERVER['SERVER_ADDR'];
        if (strpos($data['ip'], '10.100.11.') === 0) $data['az'] = 'ap-northeast-2a (Est.)';
        if (strpos($data['ip'], '10.100.12.') === 0) $data['az'] = 'ap-northeast-2c (Est.)';
    }

    return $data;
}

$metadata = getAwsMetadata();
$server_ip = $metadata['ip'];
$current_az = $metadata['az'];
// --------------------------------------------------------------------

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'normal';
$start_time = microtime(true);
$message = "";
$extra_info = "";

switch ($mode) {
    case 'cpu':
        $iterations = 50000; 
        for ($i = 0; $i < $iterations; $i++) {
            hash('sha256', 'kubee_load_test_' . $i);
        }
        $message = "🔥 CPU Load";
        $extra_info = "Hash calculated {$iterations} times.";
        break;

    case 'memory':
        try {
            $chunk = str_repeat('A', 1024 * 1024 * 30); // 30MB
            sleep(3);
            $message = "🧠 Memory Load (Visible)";
            $extra_info = "Allocated 30MB & Held for 3s";
        } catch (Exception $e) {
            $message = "Memory Allocation Failed";
        }
        break;

    case 'latency':
        sleep(2);
        $message = "🐢 Latency Test";
        $extra_info = "Sleep for 2 seconds";
        break;

    case 'error':
        http_response_code(500);
        $message = "❌ 500 Internal Server Error";
        error_log("Kubee Load Tester: Intentional 500 Error");
        break;

    case 'db':
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($conn->connect_error) {
            $message = "💥 DB Connection Failed";
            $extra_info = $conn->connect_error;
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS load_logs (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                log_data VARCHAR(20),
                reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $conn->query($sql);
            for($i=0; $i<50; $i++){
                $conn->query("INSERT INTO load_logs (log_data) VALUES ('L_" . rand() . "')");
            }
            $result = $conn->query("SELECT COUNT(*) as cnt FROM load_logs");
            $row = $result->fetch_assoc();
            $message = "🗄️ DB Load";
            $extra_info = "Inserted 50 rows. Total: " . $row['cnt'];
            $conn->close();
        }
        break;

    case 'normal':
    default:
        $message = "✅ Normal Mode";
        $extra_info = "System is healthy.";
        break;
}

$end_time = microtime(true);
$duration = round($end_time - $start_time, 4);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kubee Safe Load Tester</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; text-align: center; padding: 40px; }
        .container { max-width: 700px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { margin-bottom: 10px; color: #333; }
        .server-info { margin-bottom: 30px; font-size: 1.1em; color: #555; }
        .az-badge { 
            display: inline-block; padding: 5px 12px; border-radius: 20px; color: white; font-weight: bold; margin-left: 10px; 
            text-transform: uppercase; font-size: 0.9em; letter-spacing: 0.5px;
        }
        .az-a { background-color: #3b82f6; box-shadow: 0 2px 5px rgba(59, 130, 246, 0.3); } 
        .az-c { background-color: #10b981; box-shadow: 0 2px 5px rgba(16, 185, 129, 0.3); } 
        .az-unknown { background-color: #6b7280; }
        .result-box { background-color: #eef2ff; padding: 20px; border-radius: 10px; margin-bottom: 30px; border: 1px solid #d0d7de; }
        .btn-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .btn { padding: 15px; border-radius: 8px; text-decoration: none; color: white; font-weight: bold; font-size: 1.1em; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .normal { background-color: #10b981; grid-column: span 2; }
        .cpu { background-color: #ef4444; }
        .memory { background-color: #f59e0b; }
        .db { background-color: #8b5cf6; }
        .latency { background-color: #3b82f6; }
        .error { background-color: #6b7280; grid-column: span 2; margin-top: 10px;}
        .footer { margin-top: 30px; font-size: 0.9em; color: #888; }
        .footer a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛡️ Kubee Safe Load Tester</h1>
        
        <div class="server-info">
            Server IP: <strong><?php echo $server_ip; ?></strong>
            
            <?php 
                $badge_class = 'az-unknown';
                if (strpos($current_az, '2a') !== false) $badge_class = 'az-a';
                if (strpos($current_az, '2c') !== false) $badge_class = 'az-c';
            ?>
            <span class="az-badge <?php echo $badge_class; ?>"><?php echo $current_az; ?></span>
        </div>

        <div class="result-box">
            <h2 style="margin:0;"><?php echo $message; ?></h2>
            <p style="margin:10px 0 0; color:#555;"><?php echo $extra_info; ?></p>
            <p style="margin:5px 0 0; font-size:0.8em; color:#888;">Time: <?php echo $duration; ?>s</p>
        </div>

        <div class="btn-grid">
            <a href="?mode=normal" class="btn normal">✅ Normal (Reset)</a>
            <a href="?mode=cpu" class="btn cpu">🔥 CPU (Lite)</a>
            <a href="?mode=memory" class="btn memory">🧠 RAM (30MB/3s)</a>
            <a href="?mode=db" class="btn db">🗄️ DB (50 Rows)</a>
            <a href="?mode=latency" class="btn latency">🐢 Latency (2s)</a>
            <a href="?mode=error" class="btn error">❌ Error (500)</a>
        </div>

        <div class="footer">
            <p>Monitoring: <a href="/stub_status" target="_blank">Nginx Metrics</a></p>
        </div>
    </div>
</body>
</html>
