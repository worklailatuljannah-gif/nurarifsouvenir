<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include 'db_connect.php';

$sql = "SELECT id, name, price, category, image FROM products";
$result = $conn->query($sql);

$products_array = array();

if ($result && $result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {

        $icon = "📦";
        $hasImage = false;
        $imageUrl = "";

        if (!empty($row['image']) && file_exists($row['image'])) {
            $hasImage = true;
            $imageUrl = $row['image'];
        } else {
            $icon = match ($row['category']) {
                'Gelas' => '🍷',
                'Tas/Pouch' => '👜',
                'Kipas' => '🪭',
                'Gantungan Kunci' => '🔑',
                default => '🎁',
            };
        }

        $products_array[] = array(
            "id" => $row['id'],
            "name" => $row['name'],
            "price" => (int) $row['price'],
            "category" => $row['category'],
            "icon" => $icon,
            "hasImage" => $hasImage,
            "imageUrl" => $imageUrl
        );
    }
}

$conn->close();

echo json_encode($products_array);
?>