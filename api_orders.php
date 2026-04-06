<?php
// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
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

// Handle GET request - Get orders
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $order_number = isset($_GET['order_number']) ? trim($_GET['order_number']) : '';
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

    if (!empty($order_number)) {
        $sql = "SELECT * FROM orders WHERE order_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $order_number);
    } elseif ($user_id > 0) {
        $sql = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
    } else {
        $sql = "SELECT * FROM orders ORDER BY created_at DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
    }

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Gagal menyiapkan query: " . $conn->error));
        exit();
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $orders = array();
    while ($row = $result->fetch_assoc()) {
        $row['items'] = json_decode($row['items'], true);
        $row['customer_data'] = json_decode($row['customer_data'], true);

        if (!isset($row['customer'])) {
            $row['customer'] = $row['customer_data'];
        }

        $row['orderNumber'] = $row['order_number'];
        $row['shippingCost'] = (float) $row['shipping_cost'];
        $row['subtotal'] = (float) $row['subtotal'];
        $row['tax'] = (float) $row['tax'];
        $row['total'] = (float) $row['total'];

        $orders[] = $row;
    }

    echo json_encode(array("status" => "success", "orders" => $orders));
    $stmt->close();
    $conn->close();
    exit();
}

// Handle POST request - Create order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents("php://input");
    $data = json_decode($raw_input);

    // Validasi input
    if ($data === null) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Data input tidak valid."));
        exit();
    }

    if (empty($data->orderNumber) || empty($data->customer) || empty($data->items)) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Data order tidak lengkap."));
        exit();
    }

    $order_number = $data->orderNumber;
    $user_id = isset($data->userId) ? intval($data->userId) : null;
    $customer_data = json_encode($data->customer);
    $items = json_encode($data->items);
    $subtotal = isset($data->subtotal) ? floatval($data->subtotal) : 0;
    $shipping_cost = isset($data->shippingCost) ? floatval($data->shippingCost) : 0;
    $tax = round($subtotal * 0.11);
    $total = $subtotal + $shipping_cost + $tax;
    $status = 'pending';

    // Insert order ke database
    $sql = "INSERT INTO orders (order_number, user_id, customer_data, items, subtotal, shipping_cost, tax, total, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Gagal menyiapkan query: " . $conn->error));
        exit();
    }

    $stmt->bind_param("sissdddds", $order_number, $user_id, $customer_data, $items, $subtotal, $shipping_cost, $tax, $total, $status);

    if ($stmt->execute()) {
        $order_id = $stmt->insert_id;

        // ---- FITUR KIRIM EMAIL OTOMATIS ----
        $to_customer = isset($data->customer->email) ? trim($data->customer->email) : '';
        if (!empty($to_customer) && filter_var($to_customer, FILTER_VALIDATE_EMAIL)) {
            $customerName = isset($data->customer->name) ? $data->customer->name : 'Pelanggan';
            $subject = "Tagihan Pesanan - NurArif Souvenir (" . $order_number . ")";

            $email_message = "Halo " . $customerName . ",\n\n";
            $email_message .= "Terima kasih telah berbelanja di NurArif Souvenir. Pesanan Anda telah kami terima!\n\n";
            $email_message .= "--- DETAIL PESANAN ---\n";
            $email_message .= "Nomor Pesanan: " . $order_number . "\n";
            $email_message .= "Total Tagihan: Rp" . number_format($total, 0, ',', '.') . "\n\n";
            $email_message .= "Daftar Barang:\n";

            foreach ($data->items as $i) {
                $email_message .= "> " . $i->quantity . "x " . $i->name . " (Rp" . number_format($i->price * $i->quantity, 0, ',', '.') . ")\n";
            }

            $email_message .= "\nPengiriman Ke:\n";
            $email_message .= ($data->customer->address ?? '') . " - " . ($data->customer->city ?? '') . "\n\n";
            $email_message .= "Silakan lakukan pembayaran dan konfirmasi melalui WhatsApp kami.\n";
            $email_message .= "\nSalam hangat,\nTim NurArif Souvenir";

            // Header Email
            $headers = "From: noreply@nurarifsouvenir.com\r\n";
            $headers .= "Reply-To: cs@nurarifsouvenir.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            // 1. Kirim ke Customer (pakai @ agar tidak crash jika di localhost belum setting SMTP)
            @mail($to_customer, $subject, $email_message, $headers);


            $admin_email = "work.lailatuljannah@gmail.com";
            @mail($admin_email, "[PESANAN BARU] " . $order_number, "Ada pesanan baru masuk dari " . $customerName . " senilai Rp" . number_format($total, 0, ',', '.') . ".\nLogin ke dashboard untuk mengecek.", $headers);
        }

        http_response_code(201);
        echo json_encode(array(
            "status" => "success",
            "message" => "Order berhasil disimpan",
            "order_id" => $order_id,
            "order_number" => $order_number
        ));
    } else {
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => "Gagal menyimpan order: " . $stmt->error));
    }

    $stmt->close();
    $conn->close();
    exit();
}

// Method not allowed
http_response_code(405);
echo json_encode(array("status" => "error", "message" => "Method not allowed"));
$conn->close();
?>