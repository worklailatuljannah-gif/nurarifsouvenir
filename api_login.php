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
    echo json_encode(array("status" => "error", "message" => "Kesalahan koneksi database: " . $conn->connect_error));
    exit();
}

// Ambil raw input
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input);

// Debug: Log input (HAPUS SETELAH TESTING)
error_log("Login Input: " . $raw_input);

// Validasi input
if ($data === null || empty($data->email) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Gagal. Data input tidak lengkap (Email atau Kata Sandi hilang)."));
    exit();
}

$email = trim($data->email);
$password = $data->password;

// Validasi format email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Format email tidak valid."));
    exit();
}

// Query user dari database
$stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Gagal menyiapkan query: " . $conn->error));
    exit();
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Verifikasi password
    if (password_verify($password, $user['password'])) {
        http_response_code(200);
        echo json_encode(array(
            "status" => "success",
            "user_id" => $user['id'],
            "name" => $user['name'],
            "email" => $user['email'],
            "message" => "Login berhasil"
        ));
    } else {
        http_response_code(401);
        echo json_encode(array("status" => "error", "message" => "Password salah"));
    }
} else {
    http_response_code(401);
    echo json_encode(array("status" => "error", "message" => "Email tidak ditemukan"));
}

$stmt->close();
$conn->close();
?>
