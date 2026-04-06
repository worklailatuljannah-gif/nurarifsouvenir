<?php
error_reporting(0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json; charset=UTF-8");

include 'db_connect.php';

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal: " . $conn->connect_error]);
    exit;
}

$response = array("status" => "error", "message" => "Unknown error");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = isset($_POST['name']) ? $conn->real_escape_string($_POST['name']) : '';
    $price = isset($_POST['price']) ? (int) $_POST['price'] : 0;
    $category = isset($_POST['category']) ? $conn->real_escape_string($_POST['category']) : '';

    if (empty($name) || empty($price) || empty($category)) {
        echo json_encode(["status" => "error", "message" => "Semua field harus diisi"]);
        exit;
    }

    $imagePath = "";
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $targetDir = "images/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        $allowTypes = array('jpg', 'png', 'jpeg', 'gif', 'webp');
        if (in_array(strtolower($fileType), $allowTypes)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $imagePath = $targetFilePath;
            } else {
                echo json_encode(["status" => "error", "message" => "Maaf, error saat upload gambar."]);
                exit;
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Maaf, hanya format JPG, JPEG, PNG, GIF, & WEBP yang diizinkan."]);
            exit;
        }
    }

    try {
        $sql = "INSERT INTO products (name, price, category, image) VALUES ('$name', '$price', '$category', '$imagePath')";
        
        if ($conn->query($sql) === TRUE) {
            $response = ["status" => "success", "message" => "Produk berhasil ditambahkan"];
        } else {
            $response = ["status" => "error", "message" => "Error database: " . $conn->error];
        }
    } catch (Exception $e) {
        $response = ["status" => "error", "message" => "Tabel 'products' belum dibuat di database atau tidak cocok. Detail: " . $e->getMessage()];
    }
} else {
    $response = ["status" => "error", "message" => "Method tidak diizinkan"];
}

$conn->close();
echo json_encode($response);
?>