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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $name = isset($_POST['name']) ? trim($conn->real_escape_string($_POST['name'])) : '';
    $price = isset($_POST['price']) ? (float) $_POST['price'] : 0;
    $category = isset($_POST['category']) ? trim($conn->real_escape_string($_POST['category'])) : '';

    if ($id <= 0 || empty($name) || empty($price) || empty($category)) {
        echo json_encode(["status" => "error", "message" => "Semua field yang wajib harus diisi (" . empty($name) . empty($price) . empty($category) . ")"]);
        exit;
    }

    $imageQueryPart = "";
    
    // Cek apakah ada file foto yang baru diunggah
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
                
                // Ambil foto lama untuk dihapus dari server
                $old_query = "SELECT image FROM products WHERE id = $id";
                $old_result = $conn->query($old_query);
                if($old_result && $old_result->num_rows > 0){
                    $old_row = $old_result->fetch_assoc();
                    if(!empty($old_row['image']) && file_exists($old_row['image'])){
                        unlink($old_row['image']); // hapus file lama
                    }
                }
                
                $imageQueryPart = ", image = '$targetFilePath'";
            } else {
                echo json_encode(["status" => "error", "message" => "Maaf, error saat mengupload gambar baru."]);
                exit;
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Maaf, hanya format JPG, JPEG, PNG, GIF, & WEBP yang diizinkan."]);
            exit;
        }
    }

    try {
        $sql = "UPDATE products SET name = '$name', price = '$price', category = '$category' $imageQueryPart WHERE id = $id";
        
        if ($conn->query($sql) === TRUE) {
            echo json_encode(["status" => "success", "message" => "Produk berhasil diperbarui"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error memperbarui data: " . $conn->error]);
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Tabel 'products' bermasalah. Detail: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Method tidak diizinkan"]);
}

$conn->close();
?>
