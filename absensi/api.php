<?php
// api.php - API endpoints untuk aplikasi absensi
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Function untuk logging debug
function logDebug($message) {
    error_log(date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, 3, 'absensi_debug.log');
}

$method = $_SERVER['REQUEST_METHOD'];
$request = $_GET['request'] ?? '';
$action = $_GET['action'] ?? '';

// Log request untuk debugging
logDebug("Request Method: $method");
logDebug("Action: $action");
logDebug("Request: $request");

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle both request and action parameters
$endpoint = $action ?: $request;

switch ($endpoint) {
    case 'getUser':
    case 'user-info':
        handleGetUser();
        break;
    case 'absensi':
    case 'absen-masuk':
        if ($method === 'POST') {
            handleAbsenMasuk();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    case 'pulang':
    case 'absen-pulang':
        if ($method === 'POST') {
            handleAbsenPulang();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
    case 'getTodayAttendance':
    case 'status-hari-ini':
        handleStatusHariIni();
        break;
    case 'getSchedule':
    case 'jadwal-hari-ini':
        handleJadwalHariIni();
        break;
    case 'getRiwayatAbsensi':
        handleGetRiwayatAbsensi();
        break;
    case 'kinerja-stats':
        handleKinerjaStats();
        break;
    case 'kinerja-calendar':
        handleKinerjaCalendar();
        break;
    case 'kinerja-history':
        handleKinerjaHistory();
        break;
    case 'login':
        if ($method === 'POST') {
            handleLogin();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'register':
        if ($method === 'POST') {
            handleRegister();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'checkUser':
        handleCheckUser();
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found: ' . $endpoint]);
}

function handleGetUser() {
    global $pdo;
    
    $userId = $_GET['id'] ?? 1; // Default ke ID 1
    
    logDebug("Getting user data for ID: $userId");
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM pegawais WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'nama' => $user['nama'],
                    'jabatan' => $user['jabatan'],
                    'email' => $user['email']
                ]
            ]);
            logDebug("User data found for ID: $userId");
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
            logDebug("User not found for ID: $userId");
        }
    } catch (Exception $e) {
        logDebug("Error getting user: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function handleAbsenMasuk() {
    global $pdo;
    
    logDebug("handleAbsenMasuk called");
    
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    logDebug("Raw input: " . file_get_contents('php://input'));
    logDebug("Decoded input: " . json_encode($input));
    
    $userId = $input['user_id'] ?? $_POST['user_id'] ?? 1;
    $latitude = $input['latitude'] ?? $_POST['latitude'] ?? null;
    $longitude = $input['longitude'] ?? $_POST['longitude'] ?? null;
    $timestamp = date('Y-m-d H:i:s');
    
    logDebug("Processing absen masuk for user: $userId, lat: $latitude, lng: $longitude");
    
    try {
        // Cek apakah sudah absen masuk hari ini
        $stmt = $pdo->prepare("SELECT * FROM absensi WHERE pegawai_id = ? AND tanggal = CURDATE()");
        $stmt->execute([$userId]);
        $existing = $stmt->fetch();
        
        logDebug("Existing record: " . json_encode($existing));
        
        if ($existing && $existing['jam_masuk']) {
            logDebug("Already checked in today");
            echo json_encode([
                'success' => false,
                'message' => 'Anda sudah absen masuk hari ini'
            ]);
            return;
        }
        
        if ($existing) {
            // Update jika record sudah ada tapi belum absen masuk
            $stmt = $pdo->prepare("
                UPDATE absensi 
                SET jam_masuk = ?, latitude_masuk = ?, longitude_masuk = ?, updated_at = ?
                WHERE id = ?
            ");
            $result = $stmt->execute([date('H:i:s'), $latitude, $longitude, $timestamp, $existing['id']]);
            logDebug("Update result: " . ($result ? 'success' : 'failed'));
        } else {
            // Insert baru
            $stmt = $pdo->prepare("
                INSERT INTO absensi 
                (pegawai_id, tanggal, jam_masuk, latitude_masuk, longitude_masuk, created_at, updated_at)
                VALUES (?, CURDATE(), ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([$userId, date('H:i:s'), $latitude, $longitude, $timestamp, $timestamp]);
            logDebug("Insert result: " . ($result ? 'success' : 'failed'));
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                logDebug("SQL Error: " . json_encode($errorInfo));
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Absen masuk berhasil',
            'data' => [
                'waktu' => date('H:i:s'),
                'tanggal' => date('Y-m-d')
            ]
        ]);
        
        logDebug("Absen masuk completed successfully");
        
    } catch (Exception $e) {
        logDebug("Error in handleAbsenMasuk: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function handleAbsenPulang() {
    global $pdo;
    
    logDebug("handleAbsenPulang called");
    
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    logDebug("Raw input: " . file_get_contents('php://input'));
    logDebug("Decoded input: " . json_encode($input));
    
    $userId = $input['user_id'] ?? $_POST['user_id'] ?? 1;
    $latitude = $input['latitude'] ?? $_POST['latitude'] ?? null;
    $longitude = $input['longitude'] ?? $_POST['longitude'] ?? null;
    $timestamp = date('Y-m-d H:i:s');
    
    logDebug("Processing absen pulang for user: $userId, lat: $latitude, lng: $longitude");
    
    try {
        // Cek apakah sudah absen masuk
        $stmt = $pdo->prepare("SELECT * FROM absensi WHERE pegawai_id = ? AND tanggal = CURDATE()");
        $stmt->execute([$userId]);
        $absen = $stmt->fetch();
        
        logDebug("Existing record: " . json_encode($absen));
        
        if (!$absen || !$absen['jam_masuk']) {
            echo json_encode([
                'success' => false,
                'message' => 'Anda belum absen masuk hari ini'
            ]);
            return;
        }
        
        if ($absen['jam_pulang']) {
            echo json_encode([
                'success' => false,
                'message' => 'Anda sudah absen pulang hari ini'
            ]);
            return;
        }
        
        // Update absen pulang
        $stmt = $pdo->prepare("
            UPDATE absensi 
            SET jam_pulang = ?, latitude_pulang = ?, longitude_pulang = ?, updated_at = ?
            WHERE id = ?
        ");
        $result = $stmt->execute([date('H:i:s'), $latitude, $longitude, $timestamp, $absen['id']]);
        
        logDebug("Update result: " . ($result ? 'success' : 'failed'));
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            logDebug("SQL Error: " . json_encode($errorInfo));
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Absen pulang berhasil',
            'data' => [
                'waktu' => date('H:i:s'),
                'tanggal' => date('Y-m-d')
            ]
        ]);
        
        logDebug("Absen pulang completed successfully");
        
    } catch (Exception $e) {
        logDebug("Error in handleAbsenPulang: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function handleStatusHariIni() {
    global $pdo;
    
    $userId = $_GET['user_id'] ?? 1;
    
    logDebug("Getting today's attendance for user: $userId");
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(jam_masuk, '%H:%i') as jam_masuk,
                DATE_FORMAT(jam_pulang, '%H:%i') as jam_pulang
            FROM absensi 
            WHERE pegawai_id = ? AND tanggal = CURDATE()
        ");
        $stmt->execute([$userId]);
        $attendance = $stmt->fetch();
        
        logDebug("Attendance found: " . json_encode($attendance));
        
        echo json_encode([
            'success' => true,
            'attendance' => $attendance ?: [
                'jam_masuk' => null,
                'jam_pulang' => null
            ]
        ]);
    } catch (Exception $e) {
        logDebug("Error getting attendance: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function handleJadwalHariIni() {
    global $pdo;
    
    $userId = $_GET['user_id'] ?? 1;
    
    logDebug("Getting today's schedule for user: $userId");
    
    try {
        // Cek apakah tabel jadwal ada
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'jadwal'");
        $stmt->execute();
        $tableExists = $stmt->fetch();
        
        if ($tableExists) {
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    DATE_FORMAT(jam_mulai, '%H:%i') as jam,
                    judul,
                    lokasi,
                    'upcoming' as status
                FROM jadwal
                WHERE pegawai_id = ? AND tanggal = CURDATE()
                ORDER BY jam_mulai
            ");
            $stmt->execute([$userId]);
            $schedule = $stmt->fetchAll();
            
            logDebug("Schedule found: " . json_encode($schedule));
        } else {
            logDebug("Table jadwal not found, using dummy data");
            $schedule = [];
        }
        
        if (empty($schedule)) {
            // Data dummy jika tabel jadwal tidak ada atau kosong
            $schedule = [
                [
                    'id' => 1,
                    'jam' => '09:00',
                    'judul' => 'Rapat Tim',
                    'lokasi' => 'Ruang Rapat A',
                    'status' => 'upcoming'
                ],
                [
                    'id' => 2,
                    'jam' => '13:00',
                    'judul' => 'Presentasi Proyek',
                    'lokasi' => 'Ruang Seminar',
                    'status' => 'upcoming'
                ]
            ];
        }
        
        echo json_encode([
            'success' => true,
            'schedule' => $schedule,
            'count' => count($schedule)
        ]);
        
    } catch (Exception $e) {
        logDebug("Error getting schedule: " . $e->getMessage());
        // Fallback ke data dummy jika error
        echo json_encode([
            'success' => true,
            'schedule' => [
                [
                    'id' => 1,
                    'jam' => '09:00',
                    'judul' => 'Rapat Tim',
                    'lokasi' => 'Ruang Rapat A',
                    'status' => 'upcoming'
                ]
            ],
            'count' => 1
        ]);
    }
}

function handleGetRiwayatAbsensi() {
    global $pdo;
    
    $userId = $_GET['user_id'] ?? 1;
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    logDebug("Getting attendance history for user: $userId from $startDate to $endDate");
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                tanggal,
                DATE_FORMAT(jam_masuk, '%H:%i') as jam_masuk,
                DATE_FORMAT(jam_pulang, '%H:%i') as jam_pulang,
                CASE 
                    WHEN jam_masuk IS NULL THEN 'Tidak Masuk'
                    WHEN jam_pulang IS NULL THEN 'Belum Pulang'
                    ELSE 'Lengkap'
                END as status
            FROM absensi 
            WHERE pegawai_id = ? AND tanggal BETWEEN ? AND ? 
            ORDER BY tanggal DESC
        ");
        $stmt->execute([$userId, $startDate, $endDate]);
        $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logDebug("Attendance history found: " . count($riwayat) . " records");
        
        echo json_encode([
            'success' => true,
            'riwayat' => $riwayat,
            'count' => count($riwayat),
            'periode' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
        
    } catch (Exception $e) {
        logDebug("Error getting attendance history: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

// Function untuk membuat tabel jika belum ada
function createTablesIfNotExists() {
    global $pdo;
    
    try {
        // Buat tabel pegawais jika belum ada
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pegawais (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                jabatan VARCHAR(50) NULL,
                email VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Buat tabel absensi jika belum ada
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS absensi (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pegawai_id INT NOT NULL,
                tanggal DATE NOT NULL,
                jam_masuk TIME NULL,
                jam_pulang TIME NULL,
                latitude_masuk VARCHAR(50) NULL,
                longitude_masuk VARCHAR(50) NULL,
                latitude_pulang VARCHAR(50) NULL,
                longitude_pulang VARCHAR(50) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (pegawai_id) REFERENCES pegawais(id)
            )
        ");
        
        // Insert dummy user jika belum ada
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pegawais");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $pdo->exec("
                INSERT INTO pegawais (nama, jabatan, email) VALUES 
                ('John Doe', 'Staff', 'john@example.com'),
                ('Jane Smith', 'Manager', 'jane@example.com')
            ");
            logDebug("Dummy users created");
        }
        
    } catch (Exception $e) {
        logDebug("Error creating tables: " . $e->getMessage());
    }
}

// Panggil function untuk create tables
createTablesIfNotExists();

// Test endpoint
if ($endpoint === 'test') {
    echo json_encode([
        'success' => true,
        'message' => 'API is working',
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $method,
        'endpoint' => $endpoint
    ]);
}

function handleKinerjaStats() {
    global $pdo;
    
    $userId = $_GET['user_id'] ?? 1;
    $month = $_GET['month'] ?? date('n');
    $year = $_GET['year'] ?? date('Y');
    
    logDebug("Getting kinerja stats for user: $userId, month: $month, year: $year");
    
    try {
        // Hitung total kehadiran (yang memiliki jam_masuk)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_kehadiran
            FROM absensi 
            WHERE pegawai_id = ? 
            AND MONTH(tanggal) = ? 
            AND YEAR(tanggal) = ?
            AND jam_masuk IS NOT NULL
        ");
        $stmt->execute([$userId, $month, $year]);
        $totalKehadiran = $stmt->fetchColumn();
        
        // Hitung tepat waktu (masuk sebelum jam 08:00)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as tepat_waktu
            FROM absensi 
            WHERE pegawai_id = ? 
            AND MONTH(tanggal) = ? 
            AND YEAR(tanggal) = ?
            AND jam_masuk IS NOT NULL
            AND jam_masuk <= '08:00:00'
        ");
        $stmt->execute([$userId, $month, $year]);
        $tepatWaktu = $stmt->fetchColumn();
        
        // Hitung terlambat (masuk setelah jam 08:00)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as terlambat
            FROM absensi 
            WHERE pegawai_id = ? 
            AND MONTH(tanggal) = ? 
            AND YEAR(tanggal) = ?
            AND jam_masuk IS NOT NULL
            AND jam_masuk > '08:00:00'
        ");
        $stmt->execute([$userId, $month, $year]);
        $terlambat = $stmt->fetchColumn();
        
        // Hitung hari kerja dalam bulan (Senin-Jumat)
        $firstDay = "$year-$month-01";
        $lastDay = date('Y-m-t', strtotime($firstDay));
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as hari_kerja
            FROM (
                SELECT DATE_ADD(?, INTERVAL seq.seq DAY) as tanggal
                FROM (
                    SELECT 0 as seq UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
                    SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL
                    SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL
                    SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL
                    SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL
                    SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL
                    SELECT 30
                ) seq
            ) dates
            WHERE dates.tanggal <= ?
            AND WEEKDAY(dates.tanggal) < 5
        ");
        $stmt->execute([$firstDay, $lastDay]);
        $hariKerja = $stmt->fetchColumn();
        
        // Hitung tidak hadir
        $tidakHadir = $hariKerja - $totalKehadiran;
        
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_kehadiran' => (int)$totalKehadiran,
                'tepat_waktu' => (int)$tepatWaktu,
                'terlambat' => (int)$terlambat,
                'tidak_hadir' => (int)$tidakHadir,
                'hari_kerja' => (int)$hariKerja
            ],
            'month_name' => $monthNames[(int)$month],
            'year' => (int)$year
        ]);
        
        logDebug("Kinerja stats loaded successfully");
        
    } catch (Exception $e) {
        logDebug("Error getting kinerja stats: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function handleKinerjaCalendar() {
    global $pdo;
    
    $userId = $_GET['user_id'] ?? 1;
    $month = $_GET['month'] ?? date('n');
    $year = $_GET['year'] ?? date('Y');
    
    logDebug("Getting kinerja calendar for user: $userId, month: $month, year: $year");
    
    try {
        // Ambil data absensi untuk bulan ini
        $stmt = $pdo->prepare("
            SELECT 
                DAY(tanggal) as day,
                jam_masuk,
                jam_pulang,
                CASE 
                    WHEN jam_masuk IS NULL THEN 'tidak-hadir'
                    WHEN jam_masuk > '08:00:00' THEN 'terlambat'
                    ELSE 'hadir'
                END as status
            FROM absensi 
            WHERE pegawai_id = ? 
            AND MONTH(tanggal) = ? 
            AND YEAR(tanggal) = ?
            ORDER BY tanggal
        ");
        $stmt->execute([$userId, $month, $year]);
        $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Konversi ke format yang dibutuhkan frontend
        $calendar = [];
        foreach ($attendanceRecords as $record) {
            $calendar[(int)$record['day']] = [
                'status' => $record['status'],
                'jam_masuk' => $record['jam_masuk'],
                'jam_pulang' => $record['jam_pulang']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'calendar' => $calendar,
            'month' => (int)$month,
            'year' => (int)$year
        ]);
        
        logDebug("Kinerja calendar loaded successfully");
        
    } catch (Exception $e) {
        logDebug("Error getting kinerja calendar: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function handleKinerjaHistory() {
    global $pdo;
    
    $userId = $_GET['user_id'] ?? 1;
    $month = $_GET['month'] ?? date('n');
    $year = $_GET['year'] ?? date('Y');
    $limit = $_GET['limit'] ?? 50;
    
    logDebug("Getting kinerja history for user: $userId, month: $month, year: $year, limit: $limit");
    
    try {
        // Convert limit to integer to prevent SQL injection
        $limit = (int)$limit;
        
        // Prepare the base query without LIMIT
        $query = "
            SELECT 
                tanggal,
                jam_masuk,
                jam_pulang,
                CASE 
                    WHEN jam_masuk IS NULL THEN 'Tidak Hadir'
                    WHEN jam_masuk > '08:00:00' THEN 'Terlambat'
                    ELSE 'Hadir'
                END as status,
                CASE 
                    WHEN jam_masuk IS NULL THEN 'tidak-hadir'
                    WHEN jam_masuk > '08:00:00' THEN 'terlambat'
                    ELSE 'hadir'
                END as status_class
            FROM absensi 
            WHERE pegawai_id = ? 
            AND MONTH(tanggal) = ? 
            AND YEAR(tanggal) = ?
            ORDER BY tanggal DESC
        ";
        
        // Add LIMIT clause directly (after sanitizing the value)
        $query .= " LIMIT " . $limit;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $month, $year]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Rest of the function remains the same...
        $history = [];
        foreach ($records as $record) {
            $details = '';
            if ($record['jam_masuk']) {
                $details .= 'Masuk: ' . substr($record['jam_masuk'], 0, 5);
            }
            if ($record['jam_pulang']) {
                $details .= ' | Pulang: ' . substr($record['jam_pulang'], 0, 5);
            }
            if (empty($details)) {
                $details = 'Tidak ada data kehadiran';
            }
            
            $history[] = [
                'tanggal' => $record['tanggal'],
                'status' => $record['status'],
                'status_class' => $record['status_class'],
                'details' => $details,
                'jam_masuk' => $record['jam_masuk'],
                'jam_pulang' => $record['jam_pulang']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'history' => $history,
            'count' => count($history),
            'month' => (int)$month,
            'year' => (int)$year
        ]);
        
        logDebug("Kinerja history loaded successfully: " . count($history) . " records");
        
    } catch (Exception $e) {
        logDebug("Error getting kinerja history: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function handleLogin() {
    global $pdo;
    
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    
    logDebug("Login attempt for email: $email");
    
    try {
        // Cari user berdasarkan email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode([
                'success' => false,
                'message' => 'Email tidak ditemukan'
            ]);
            return;
        }
        
        // Verifikasi password
        if (password_verify($password, $user['password'])) {
            // Login berhasil
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'nama' => $user['name'],
                    'email' => $user['email']
                ],
                'message' => 'Login berhasil'
            ]);
            logDebug("Login successful for user ID: " . $user['id']);
        } else {
            // Password salah
            echo json_encode([
                'success' => false,
                'message' => 'Password salah'
            ]);
            logDebug("Login failed - wrong password for email: $email");
        }
    } catch (Exception $e) {
        logDebug("Login error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function handleRegister() {
    global $pdo;
    
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    $name = $input['name'] ?? '';
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    
    logDebug("Registration attempt for email: $email");
    
    try {
        // Cek apakah email sudah terdaftar
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'Email sudah terdaftar'
            ]);
            return;
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user baru
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, created_at, updated_at) 
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $result = $stmt->execute([$name, $email, $hashedPassword]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            echo json_encode([
                'success' => true,
                'message' => 'Registrasi berhasil',
                'user_id' => $userId
            ]);
            logDebug("Registration successful for user ID: $userId");
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mendaftarkan user'
            ]);
        }
    } catch (Exception $e) {
        logDebug("Registration error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function handleCheckUser() {
    global $pdo;
    
    $email = $_GET['email'] ?? '';
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'nama' => $user['name'],
                    'email' => $user['email']
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}
?>