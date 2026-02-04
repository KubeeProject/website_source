<?php
// [중요] 보안을 위해 DB 접속 정보는 외부 파일에서 불러옵니다.
// 이 파일은 Ansible이 서버 배포 시 자동으로 생성해줍니다.
// 로컬 개발 시에는 가짜 db_config.php를 만들어서 테스트하세요.
if (file_exists('db_config.php')) {
    include 'db_config.php';
} else {
    // 설정 파일이 없을 경우 기본값 (혹은 에러 처리)
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'test';
}

// 모드 설정 (기본값: normal)
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'normal';
$start_time = microtime(true);
$message = "";
$extra_info = "";

// 로직 분기
switch ($mode) {
    case 'cpu':
        // [CPU 부하] 암호화 해시 연산 5만번 반복 -> 크레딧 우려로 30만에서 축소
        $iterations = 50000; 
        for ($i = 0; $i < $iterations; $i++) {
            hash('sha256', 'kubee_load_test_' . $i);
        }
        $message = "🔥 CPU Load (Safe Mode)";
        $extra_info = "Hash calculated {$iterations} times.";
        break;

    case 'memory':
        // [메모리 부하] 50MB 문자열 할당 -> 5mb로 축소 (프리 티어인 1gb 내에서 여러 팀원이 동시에 눌러도 서버가 죽지 않도록 조절)
        try {
            $chunk = str_repeat('A', 1024 * 1024 * 5); 
            $message = "🧠 Memory Load (Safe Mode)";
            $extra_info = "Allocated 5MB String to RAM";
        } catch (Exception $e) {
            $message = "Memory Allocation Failed";
        }
        break;

    case 'latency':
        // [지연 시뮬레이션] 2초간 강제 대기 -> 1초만 지연
        sleep(1);
        $message = "🐢 Latency Test";
        $extra_info = "Sleep for 1 seconds";
        break;

    case 'error':
        // [에러 시뮬레이션] 500 에러 발생 (에러율 모니터링)
        http_response_code(500);
        $message = "❌ 500 Internal Server Error";
        error_log("Kubee Load Tester: Intentional 500 Error");
        break;

    case 'db':
        // [DB 부하] RDS 접속 -> 테이블 생성 -> 데이터 삽입 -> 조회
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        
        if ($conn->connect_error) {
            $message = "💥 DB Connection Failed";
            $extra_info = $conn->connect_error;
        } else {
            // 1. 로그 테이블이 없으면 생성
            $sql = "CREATE TABLE IF NOT EXISTS load_logs (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                log_data VARCHAR(20),
                reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $conn->query($sql);

            // 2. 데이터 INSERT (부하 발생) -> 50회에서 5회로 축소
            for($i=0; $i<5; $i++){
                $conn->query("INSERT INTO load_logs (log_data) VALUES ('Load Test Data " . rand() . "')");
            }

            // 3. 데이터 조회 (Query Count 모니터링용)
            $result = $conn->query("SELECT COUNT(*) as cnt FROM load_logs");
            $row = $result->fetch_assoc();
            
            $message = "🗄️ DB Load (Safe Mode)";
            $extra_info = "Inserted 5 rows. Total Rows: " . $row['cnt'];
            
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
        body { font-family: sans-serif; background-color: #f0f2f5; text-align: center; padding: 40px; }
        .container { max-width: 700px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { margin-bottom: 10px; color: #333; }
        .server-ip { color: #666; margin-bottom: 30px; }
        .result-box { background-color: #eef2ff; padding: 20px; border-radius: 10px; margin-bottom: 30px; border: 1px solid #d0d7de; }
        .btn-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .btn { padding: 15px; border-radius: 8px; text-decoration: none; color: white; font-weight: bold; font-size: 1.1em; transition: 0.2s; }
        .btn:hover { opacity: 0.9; transform: scale(1.02); }
        .normal { background-color: #10b981; grid-column: span 2; }
        .cpu { background-color: #ef4444; }
        .memory { background-color: #f59e0b; }
        .db { background-color: #8b5cf6; }
        .latency { background-color: #3b82f6; }
        .error { background-color: #6b7280; grid-column: span 2; margin-top: 10px;}
        .footer { margin-top: 30px; font-size: 0.9em; }
        .footer a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛡️ Kubee Safe Load Tester</h1>
        <p class="server-ip">Server IP: <?php echo $_SERVER['SERVER_ADDR']; ?></p>

        <div class="result-box">
            <h2 style="margin:0;"><?php echo $message; ?></h2>
            <p style="margin:10px 0 0; color:#555;"><?php echo $extra_info; ?></p>
            <p style="margin:5px 0 0; font-size:0.8em; color:#888;">Time: <?php echo $duration; ?>s</p>
        </div>

        <div class="btn-grid">
            <a href="?mode=normal" class="btn normal">✅ Normal (Reset)</a>
            <a href="?mode=cpu" class="btn cpu">🔥 CPU (Lite)</a>
            <a href="?mode=memory" class="btn memory">🧠 RAM (5MB)</a>
            <a href="?mode=db" class="btn db">🗄️ DB (5 Rows)</a>
            <a href="?mode=latency" class="btn latency">🐢 Latency (1s)</a>
            <a href="?mode=error" class="btn error">❌ Error (500)</a>
        </div>

        <div class="footer">
            <p>Monitoring: <a href="/stub_status" target="_blank">Nginx Metrics</a></p>
        </div>
    </div>
</body>
</html>
