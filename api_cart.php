<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db_connect.php';

// Cek koneksi
if (empty($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => "Kesalahan koneksi database."));
    exit();
}

// Handle GET - Ambil isi keranjang
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "User ID diperlukan."));
        exit();
    }

    $sql = "SELECT c.id as cart_id, c.quantity, p.* 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cart_items = array();
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
    }
    
    echo json_encode(array("status" => "success", "cart" => $cart_items));
    $stmt->close();
    $conn->close();
    exit();
}

// Handle POST - Tambah/Update item di keranjang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!$data || !isset($data->user_id) || !isset($data->product_id) || !isset($data->quantity)) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Data tidak lengkap."));
        exit();
    }

    // Cek apakah produk sudah ada di keranjang
    $check_sql = "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $data->user_id, $data->product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update quantity
        $row = $result->fetch_assoc();
        $new_quantity = $data->quantity; 
        
        $update_sql = "UPDATE cart SET quantity = ? WHERE id = ?";
        $upd_stmt = $conn->prepare($update_sql);
        $upd_stmt->bind_param("ii", $new_quantity, $row['id']);
        $success = $upd_stmt->execute();
    } else {
        // Insert baru
        $insert_sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
        $ins_stmt = $conn->prepare($insert_sql);
        $ins_stmt->bind_param("iii", $data->user_id, $data->product_id, $data->quantity);
        $success = $ins_stmt->execute();
    }

    if ($success) {
        echo json_encode(array("status" => "success", "message" => "Keranjang diperbarui."));
    } else {
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Gagal memperbarui keranjang."));
    }
    
    $conn->close();
    exit();
}

// Handle DELETE - Hapus item dari keranjang
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $cart_id = isset($_GET['cart_id']) ? intval($_GET['cart_id']) : 0;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

    if ($cart_id > 0) {
        $sql = "DELETE FROM cart WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $cart_id);
    } elseif ($user_id > 0 && $product_id > 0) {
        // Hapus item spesifik berdasarkan user dan produk
        $sql = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $product_id);
    } elseif ($user_id > 0) {
        // Hapus semua isi keranjang user (misal setelah checkout)
        $sql = "DELETE FROM cart WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
    } else {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "ID tidak valid."));
        exit();
    }

    if ($stmt->execute()) {
        echo json_encode(array("status" => "success", "message" => "Item dihapus."));
    } else {
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Gagal menghapus item."));
    }
    
    $conn->close();
    exit();
}

http_response_code(405);
echo json_encode(array("status" => "error", "message" => "Method not allowed"));
?>
