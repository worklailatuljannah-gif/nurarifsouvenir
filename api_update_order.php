<?php
error_reporting(0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json; charset=UTF-8");

include 'db_connect.php';

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal"]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $order_number = isset($_POST['order_number']) ? trim($conn->real_escape_string($_POST['order_number'])) : '';
    $status = isset($_POST['status']) ? trim($conn->real_escape_string($_POST['status'])) : '';

    if (empty($order_number) || empty($status)) {
        echo json_encode(["status" => "error", "message" => "Nomor pesanan dan status harus diisi"]);
        exit;
    }

    try {
        $sql = "UPDATE orders SET status = '$status' WHERE order_number = '$order_number'";
        
        if ($conn->query($sql) === TRUE) {
            echo json_encode(["status" => "success", "message" => "Status pesanan berhasil diubah menjadi " . $status]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error update database: " . $conn->error]);
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Method tidak diizinkan"]);
}

$conn->close();
?>
