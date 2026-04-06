<?php
error_reporting(0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json; charset=UTF-8");

include 'db_connect.php';

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database error."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "ID tidak valid"]);
        exit;
    }

    // Ambil info file image
    $sql_check = "SELECT image FROM products WHERE id = $id";
    $res = $conn->query($sql_check);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (!empty($row['image']) && file_exists($row['image'])) {
            unlink($row['image']); // Hapus file dari server
        }
    }

    $sql = "DELETE FROM products WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(["status" => "success", "message" => "Produk dihapus"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error koneksi: " . $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan"]);
}

$conn->close();
?>
