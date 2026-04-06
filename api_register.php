<?php
// CORS Headers - HARUS DI BARIS PALING ATAS!
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db_connect.php'; 

// Cek koneksi
if (empty($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(array("message" => "Kesalahan koneksi database: " . $conn->connect_error));
    exit(); 
}

// Ambil raw input
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input);

// Debug: Log input (HAPUS SETELAH TESTING)
error_log("Register Input: " . $raw_input);

// Validasi input
if ($data === null || empty($data->name) || empty($data->email) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(array("message" => "Gagal. Data input tidak lengkap (Nama, Email, atau Kata Sandi hilang)."));
    exit(); 
}

$name = trim($data->name);
$email = trim($data->email);
$password = $data->password;

try {
    $check_sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($check_sql);

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(array("message" => "Gagal menyiapkan query cek email: " . $conn->error));
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(array("message" => "Email sudah terdaftar. Silakan login."));
        $stmt->close();
        $conn->close();
        exit();
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "Error Database (Kemungkinan tabel 'users' belum dibuat): " . $e->getMessage()));
    exit();
}
// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert user baru
$insert_sql = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
$stmt = $conn->prepare($insert_sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(array("message" => "Gagal menyiapkan query insert: " . $conn->error));
    exit();
}

$stmt->bind_param("sss", $name, $email, $hashed_password);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode(array("message" => "Pendaftaran berhasil!", "status" => "success"));
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Pendaftaran gagal: " . $stmt->error));
}

$stmt->close();
$conn->close();
?>