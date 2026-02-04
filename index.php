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
        // [CPU 부하] 암호화 해시 연산 30만번 반복
        $iterations = 300000; 
        for ($i = 0; $i < $iterations; $i++) {
            hash('sha256', 'kubee_load_test_' . $i);
        }
        $message = "🔥 CPU Load Test Done";
        $extra_info = "SHA-256 Hash {$iterations} iterations";
        break;

    case 'memory':
        // [메모리 부하] 50MB 문자열 할당
        try {
            $chunk = str_repeat('A', 1024 * 1024 * 50); 
            $message = "🧠 Memory Load Test Done";
            $extra_info = "Allocated 50MB String to RAM";
        } catch (Exception $e) {
            $message = "Memory Allocation Failed";
        }
        break;

    case 'latency':
        // [지연 시뮬레이션] 2초간 강제 대기
        sleep(2);
        $message = "🐢 Latency Test Done";
        $extra_info = "Sleep for 2 seconds";
        break;

    case 'error':
        // [에러 시뮬레이션] 500 에러 발생
        http_response_code(500);
        $message = "❌ 500 Internal Server Error Generated";
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
                log_data VARCHAR(50),
                reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $conn->query($sql);

            // 2. 데이터 INSERT (부하 발생)
            for($i=0; $i<50; $i++){
                $conn->query("INSERT INTO load_logs (log_data) VALUES ('Load Test Data " . rand() . "')");
            }

            // 3. 데이터 Count (조회)
            $result = $conn->query("SELECT COUNT(*) as cnt FROM load_logs");
            $row = $result->fetch_assoc();
            
            $message = "kys DB Load Test Done (Insert/Select)";
            $extra_info = "Total Rows in DB: " . $row['cnt'];
            
            $conn->close();
        }
        break;

    case 'normal':
    default:
        $message = "✅ Normal Mode";
        $extra_info = "Fast Response (No Load)";
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
    <title>Kubee Load Tester</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f9; color: #333; text-align: center; padding: 50px; }
        h1 { color: #5a5a5a; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .status-box { background-color: #e9ecef; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .btn-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 30px; }
        .btn { padding: 15px; border-radius: 8px; text-decoration: none; color: white; font-weight: bold; transition: 0.3s; display: block; }
        .btn:hover { opacity: 0.8; transform: scale(1.02); }
        .normal { background-color: #28a745; }
        .cpu { background-color: #dc3545; }
        .memory { background-color: #fd7e14; }
        .db { background-color: #6f42c1; }
        .latency { background-color: #17a2b8; }
        .error { background-color: #6c757d; }
        .metrics { margin-top: 30px; font-size: 0.9em; }
        .metrics a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Kubee Load Tester</h1>
        <p>Server IP: <strong><?php echo $_SERVER['SERVER_ADDR']; ?></strong></p>

        <div class="status-box">
            <h2><?php echo $message; ?></h2>
            <p><?php echo $extra_info; ?></p>
            <p><small>Processing Time: <?php echo $duration; ?> sec</small></p>
        </div>

        <h3>Select Load Mode:</h3>
        <div class="btn-grid">
            <a href="?mode=normal" class="btn normal">✅ Normal</a>
            <a href="?mode=cpu" class="btn cpu">🔥 CPU Load</a>
            <a href="?mode=memory" class="btn memory">🧠 Memory Load</a>
            <a href="?mode=db" class="btn db">🗄️ DB Load</a>
            <a href="?mode=latency" class="btn latency">🐢 Latency (2s)</a>
            <a href="?mode=error" class="btn error">❌ 500 Error</a>
        </div>

        <div class="metrics">
            <p>Monitoring Link: <a href="/stub_status" target="_blank">📊 Nginx Metrics (Stub Status)</a></p>
        </div>
    </div>
</body>
</html>
