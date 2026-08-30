<?php
// Database connection
try {
    $_db = new PDO('mysql:host=127.0.0.1;dbname=db10', 'root', '');
    $_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Update product photos to use actual files that exist
$updates = [
    'P001' => '1.jpg',
    'P002' => '2.jpg',
    'P003' => '3.jpg',
    'P004' => '4.jpg',
    'P005' => '1.jpg',
    'P006' => '2.jpg',
    'P007' => '3.jpg',
    'P008' => '4.jpg',
    'P009' => '1.jpg',
    'P010' => '2.jpg',
];

$stm = $_db->prepare('UPDATE product SET photo = ? WHERE id = ?');

foreach ($updates as $id => $photo) {
    $stm->execute([$photo, $id]);
    echo "Updated $id to $photo\n";
}

echo "✓ All product photos updated successfully!\n";
?>
