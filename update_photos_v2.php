<?php
// Database connection
try {
    $_db = new PDO('mysql:host=127.0.0.1;dbname=db10', 'root', '');
    $_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Update product photos to use actual files from products folder
$updates = [
    'P001' => 'Ethiopia Yirgacheffe.jpg.png',
    'P002' => 'Colombia Supremo.jpg.png',
    'P003' => 'Brazil Santos.jpg.png',
    'P004' => 'House Blend.jpg.png',
    'P005' => 'matcha.jpg.png',
    'P006' => 'earl grey.jpg.png',
    'P007' => 'jasmine green tea.jpg.png',
    'P008' => 'french press.jpg.png',
    'P009' => 'V60 Dripper.jpg.png',
    'P010' => 'coffee mug.jpg.png',
];

$stm = $_db->prepare('UPDATE product SET photo = ? WHERE id = ?');

foreach ($updates as $id => $photo) {
    $stm->execute([$photo, $id]);
    echo "Updated $id to $photo\n";
}

echo "✓ All product photos updated successfully!\n";
?>
