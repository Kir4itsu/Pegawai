<?php
// config.php - Database configuration
$host = 'localhost';
$dbname = 'kantorku';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Fungsi untuk mendapatkan data pegawai berdasarkan ID
function getPegawai($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM pegawais WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Fungsi untuk menyimpan data absensi
function saveAbsensi($pdo, $pegawai_id, $tipe) {
    $stmt = $pdo->prepare("INSERT INTO absensi (pegawai_id, tipe, waktu, tanggal) VALUES (?, ?, NOW(), CURDATE())");
    return $stmt->execute([$pegawai_id, $tipe]);
}

// Fungsi untuk mendapatkan absensi hari ini
function getAbsensiHariIni($pdo, $pegawai_id) {
    $stmt = $pdo->prepare("SELECT * FROM absensi WHERE pegawai_id = ? AND tanggal = CURDATE() ORDER BY waktu");
    $stmt->execute([$pegawai_id]);
    return $stmt->fetchAll();
}

// Fungsi untuk mendapatkan jadwal kerja
function getJadwalKerja($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM harijamkerjas WHERE status = 'Normal' ORDER BY id");
    $stmt->execute();
    return $stmt->fetchAll();
}
?>