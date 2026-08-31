<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

$tag_items = [
    'NEW'        => 'NEW',
    'BEST VALUE' => 'BEST VALUE',
    'LIMITED'    => 'LIMITED',
];
// Replace tag items with categories from DB when available
try {
    $cats = $_db->query("SELECT name FROM category WHERE active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_COLUMN);
    if ($cats) {
        $tag_items = array_combine($cats, $cats);
    }
} catch (Exception $e) {
    // Category table may not exist yet — ignore
}
$roast_items = [
    'Light'  => 'Light',
    'Medium' => 'Medium',
    'Dark'   => 'Dark',
];

$id = req('id');
$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
$stm->execute([$id]);
$p = $stm->fetch();

if (!$p) {
    redirect('product_list.php');
}

// Get product images
$stm = $_db->prepare('
    SELECT * FROM product_image 
    WHERE product_id = ? 
    ORDER BY is_primary DESC, sort_order ASC
');
$stm->execute([$id]);
$images = $stm->fetchAll();

if (is_get()) {
    $name        = $p->name;
    $description = $p->description;
    $origin      = $p->origin ?? '';
    $roast       = $p->roast ?? '';
    $tag         = $p->tag ?? '';
    $price       = $p->price;
    $stock       = $p->stock;
    $sale_price  = $p->sale_price ?? '';
    $sale_start  = !empty($p->sale_start) ? date('Y-m-d\TH:i', strtotime($p->sale_start)) : '';
    $sale_end    = !empty($p->sale_end)   ? date('Y-m-d\TH:i', strtotime($p->sale_end))   : '';
}

if (is_post()) {
    $name        = req('name');
    $description = req('description');
    $origin      = req('origin');
    $roast       = req('roast');
    $tag         = req('tag');
    $price       = req('price');
    $stock       = req('stock');
    $sale_price  = req('sale_price');
    $sale_start  = req('sale_start');
    $sale_end    = req('sale_end');
    $photo       = get_file('photo');

    // Validate Name
    if ($name == '') {
        $_err['name'] = 'Required';
    }

    // Validate Description
    if ($description == '') {
        $_err['description'] = 'Required';
    }

    // Validate Price
    if ($price == '') {
        $_err['price'] = 'Required';
    }
    else if (!is_numeric($price) || $price < 0) {
        $_err['price'] = 'Must be a positive number';
    }

    // Validate Stock
    if ($stock == '') {
        $_err['stock'] = 'Required';
    }
    else if (filter_var($stock, FILTER_VALIDATE_INT) === false || $stock < 0) {
        $_err['stock'] = 'Must be a non-negative integer';
    }

    // Validate tag (optional)
    if ($tag != '' && !array_key_exists($tag, $tag_items)) {
        $_err['tag'] = 'Invalid tag';
    }

    // Validate roast (optional)
    if ($roast != '' && !array_key_exists($roast, $roast_items)) {
        $_err['roast'] = 'Invalid roast';
    }

    // Validate flash sale (optional — all or nothing)
    if ($sale_price != '' || $sale_start != '' || $sale_end != '') {
        if ($sale_price == '' || !is_numeric($sale_price) || $sale_price < 0) {
            $_err['sale_price'] = 'Required (positive number) when setting a sale';
        }
        else if (is_numeric($price) && $sale_price >= $price) {
            $_err['sale_price'] = 'Must be less than normal price';
        }
        if ($sale_start == '') {
            $_err['sale_start'] = 'Required when setting a sale';
        }
        if ($sale_end == '') {
            $_err['sale_end'] = 'Required when setting a sale';
        }
        else if ($sale_start != '' && strtotime($sale_end) < strtotime($sale_start)) {
            $_err['sale_end'] = 'Must be after start datetime';
        }
    }
    else {
        $sale_price = null;
        $sale_start = null;
        $sale_end   = null;
    }

    // Validate Photo (optional update)
    if ($photo && !str_starts_with($photo->type, 'image/')) {
        $_err['photo'] = 'Invalid image type';
    }

    // ------------------------------------------------------------------------
    // HANDLE IMAGE DELETION (Checkboxes)
    // ------------------------------------------------------------------------
    $delete_image_ids = req('delete_images') ?: [];

    if (!$_err) {
        $before = [
            'name' => $p->name,
            'description' => $p->description,
            'origin' => $p->origin,
            'roast' => $p->roast,
            'tag' => $p->tag,
            'price' => (float) $p->price,
            'sale_price' => $p->sale_price !== null ? (float) $p->sale_price : null,
            'sale_start' => $p->sale_start,
            'sale_end' => $p->sale_end,
            'stock' => (int) $p->stock,
            'photo' => $p->photo,
        ];

        $photo_name = $p->photo;

        // Handle main photo upload
        if ($photo) {
            if ($photo_name && $photo_name !== '0.jpg' && file_exists("../photos/$photo_name")) {
                unlink("../photos/$photo_name");
            }
            $photo_name = save_photo($photo, '../photos');
            
            // Update the product photo
            $stm = $_db->prepare('UPDATE product SET photo = ? WHERE id = ?');
            $stm->execute([$photo_name, $id]);
        }

        // --------------------------------------------------------------------
        // DELETE SELECTED IMAGES
        // --------------------------------------------------------------------
        if (!empty($delete_image_ids)) {
            foreach ($delete_image_ids as $img_id) {
                $stm = $_db->prepare('SELECT image_path FROM product_image WHERE id = ? AND product_id = ?');
                $stm->execute([$img_id, $id]);
                $img = $stm->fetch();
                
                if ($img) {
                    // Delete file from server
                    $file = "../photos/{$img->image_path}";
                    if (file_exists($file) && $img->image_path !== '0.jpg') {
                        unlink($file);
                    }
                    
                    // Delete from database
                    $stm = $_db->prepare('DELETE FROM product_image WHERE id = ?');
                    $stm->execute([$img_id]);
                }
            }
            
            // After deleting, check if we need to set a new primary image
            $stm = $_db->prepare('SELECT COUNT(*) FROM product_image WHERE product_id = ? AND is_primary = 1');
            $stm->execute([$id]);
            $has_primary = $stm->fetchColumn() > 0;
            
            if (!$has_primary) {
                // Set the first image as primary
                $stm = $_db->prepare('SELECT id, image_path FROM product_image WHERE product_id = ? ORDER BY sort_order LIMIT 1');
                $stm->execute([$id]);
                $new_primary = $stm->fetch();
                
                if ($new_primary) {
                    $stm = $_db->prepare('UPDATE product_image SET is_primary = 1 WHERE id = ?');
                    $stm->execute([$new_primary->id]);
                    
                    // Update product photo
                    $stm = $_db->prepare('UPDATE product SET photo = ? WHERE id = ?');
                    $stm->execute([$new_primary->image_path, $id]);
                    $photo_name = $new_primary->image_path;
                }
            }
        }

        // --------------------------------------------------------------------
        // HANDLE PRIMARY IMAGE SELECTION
        // --------------------------------------------------------------------
        $primary_id = req('primary_image');
        if ($primary_id) {
            // Reset all primary flags
            $stm = $_db->prepare('UPDATE product_image SET is_primary = 0 WHERE product_id = ?');
            $stm->execute([$id]);
            
            // Set new primary
            $stm = $_db->prepare('UPDATE product_image SET is_primary = 1 WHERE id = ? AND product_id = ?');
            $stm->execute([$primary_id, $id]);
            
            // Update product photo
            $stm = $_db->prepare('SELECT image_path FROM product_image WHERE id = ?');
            $stm->execute([$primary_id]);
            $img = $stm->fetch();
            
            if ($img) {
                $stm = $_db->prepare('UPDATE product SET photo = ? WHERE id = ?');
                $stm->execute([$img->image_path, $id]);
                $photo_name = $img->image_path;
            }
        }

        // --------------------------------------------------------------------
        // HANDLE MULTIPLE IMAGE UPLOADS
        // --------------------------------------------------------------------
        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            $upload_dir = '../photos/';
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            
            $uploaded_files = $_FILES['product_images'];
            $total_files = count($uploaded_files['name']);
            
            // Get existing images count for sort order
            $stm = $_db->prepare('SELECT COUNT(*) FROM product_image WHERE product_id = ?');
            $stm->execute([$id]);
            $current_count = (int)$stm->fetchColumn();
            
            for ($i = 0; $i < $total_files; $i++) {
                $file_name = $uploaded_files['name'][$i];
                $file_tmp = $uploaded_files['tmp_name'][$i];
                $file_error = $uploaded_files['error'][$i];
                $file_type = mime_content_type($file_tmp);
                $file_size = $uploaded_files['size'][$i];
                
                // Skip if upload error
                if ($file_error !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                // Validate file type
                if (!in_array($file_type, $allowed_types)) {
                    temp('warning', "Skipped '{$file_name}': Only JPG, PNG, WEBP, and GIF allowed");
                    continue;
                }
                
                // Validate file size
                if ($file_size > $max_file_size) {
                    temp('warning', "Skipped '{$file_name}': File too large (max 5MB)");
                    continue;
                }
                
                // Generate unique filename
                $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = 'product_' . $id . '_' . time() . '_' . $i . '.' . $ext;
                $destination = $upload_dir . $new_filename;
                
                // Move file
                if (move_uploaded_file($file_tmp, $destination)) {
                    // Insert into database
                    $is_primary = ($current_count + $i === 0) ? 1 : 0; // First image is primary
                    $sort_order = $current_count + $i;
                    
                    $stm = $_db->prepare('
                        INSERT INTO product_image (product_id, image_path, is_primary, sort_order)
                        VALUES (?, ?, ?, ?)
                    ');
                    $stm->execute([$id, $new_filename, $is_primary, $sort_order]);
                    
                    // If this is the first image and no primary exists, update product photo
                    if ($is_primary && empty($photo_name) || $photo_name == '0.jpg') {
                        $stm = $_db->prepare('UPDATE product SET photo = ? WHERE id = ?');
                        $stm->execute([$new_filename, $id]);
                        $photo_name = $new_filename;
                    }
                } else {
                    temp('warning', "Failed to upload '{$file_name}'");
                }
            }
        }

        // --------------------------------------------------------------------
        // UPDATE PRODUCT DETAILS
        // --------------------------------------------------------------------
        $stm = $_db->prepare('
            UPDATE product SET
                name = ?, description = ?, origin = ?, roast = ?, tag = ?,
                price = ?, sale_price = ?, sale_start = ?, sale_end = ?,
                stock = ?
            WHERE id = ?
        ');
        $stm->execute([
            $name, $description,
            $origin ?: null, $roast ?: null, $tag ?: null,
            $price,
            $sale_price,
            $sale_start ? date('Y-m-d H:i:s', strtotime($sale_start)) : null,
            $sale_end   ? date('Y-m-d H:i:s', strtotime($sale_end))   : null,
            $stock, $id,
        ]);

        if ((int) $stock !== (int) $p->stock) {
            log_stock($id, 'edited', $p->stock, $stock);
        }

        audit(
            'Products',
            'Product Updated',
            "Updated product ID: $id, Name: $name, Price: RM$price, Stock: $stock",
            $before,
            [
                'name' => $name,
                'description' => $description,
                'origin' => $origin ?: null,
                'roast' => $roast ?: null,
                'tag' => $tag ?: null,
                'price' => (float) $price,
                'sale_price' => $sale_price !== null ? (float) $sale_price : null,
                'sale_start' => $sale_start ? date('Y-m-d H:i:s', strtotime($sale_start)) : null,
                'sale_end' => $sale_end ? date('Y-m-d H:i:s', strtotime($sale_end)) : null,
                'stock' => (int) $stock,
                'photo' => $photo_name,
            ]
        );

        temp('info', 'Product updated successfully');
        redirect("product_edit.php?id=$id");
    }
}

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    'Product Maintenance' => 'product_list.php',
    'Edit Product' => '',
];
$_title = 'Admin | Edit Product';
include '../_head.php';
?>

<style>
.form .image-upload-area {
    border: 2px dashed #ddd;
    padding: 20px;
    text-align: center;
    border-radius: 8px;
    transition: all 0.3s;
    cursor: pointer;
    margin-top: 10px;
}
.form .image-upload-area:hover {
    border-color: #007bff;
    background: #f8f9fa;
}
.form .image-upload-area input[type="file"] {
    display: none;
}
.form .image-upload-area .upload-icon {
    font-size: 36px;
    color: #6c757d;
}
.form .image-upload-area .upload-text {
    color: #6c757d;
    margin-top: 5px;
    font-size: 14px;
}

.form .image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 15px;
}
.form .image-item {
    position: relative;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    background: #f8f9fa;
    transition: all 0.2s;
}
.form .image-item:hover {
    border-color: #007bff;
}
.form .image-item.primary {
    border-color: #28a745;
    box-shadow: 0 0 0 3px rgba(40,167,69,0.2);
}
.form .image-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    display: block;
}
.form .image-item .image-actions {
    padding: 8px;
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    background: white;
}
.form .image-item .image-actions button,
.form .image-item .image-actions input {
    flex: 1;
    padding: 4px 6px;
    font-size: 11px;
    border: 1px solid #ddd;
    border-radius: 3px;
    cursor: pointer;
    background: white;
    transition: all 0.2s;
}
.form .image-item .image-actions .btn-primary-img {
    border-color: #007bff;
    color: #007bff;
}
.form .image-item .image-actions .btn-primary-img:hover {
    background: #007bff;
    color: white;
}
.form .image-item .image-actions .btn-danger-img {
    border-color: #dc3545;
    color: #dc3545;
}
.form .image-item .image-actions .btn-danger-img:hover {
    background: #dc3545;
    color: white;
}
.form .image-item .image-actions .btn-success-img {
    border-color: #28a745;
    color: #28a745;
}
.form .image-item .image-actions .btn-success-img:hover {
    background: #28a745;
    color: white;
}
.form .image-item .primary-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #28a745;
    color: white;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}
.form .image-item .delete-checkbox {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 18px;
    height: 18px;
    cursor: pointer;
}
</style>

<form method="post" class="form" enctype="multipart/form-data">
    <label for="id">Product ID</label>
    <b><?= $p->id ?></b>
    <input type="hidden" name="id" value="<?= $p->id ?>">
    <?= err('id') ?>

    <label for="name">Product Name</label>
    <?= html_text('name', 'maxlength="100"') ?>
    <?= err('name') ?>

    <label for="description">Description</label>
    <?= html_textarea('description', 'maxlength="1000"') ?>
    <?= err('description') ?>

    <label for="origin">Origin</label>
    <?= html_text('origin', 'maxlength="100" placeholder="e.g. Ethiopia"') ?>
    <?= err('origin') ?>

    <label for="roast">Roast</label>
    <?= html_select('roast', $roast_items) ?>
    <?= err('roast') ?>

    <label for="tag">Tag</label>
    <?= html_select('tag', $tag_items) ?>
    <?= err('tag') ?>

    <label for="price">Price (RM)</label>
    <?= html_text('price', 'maxlength="10"') ?>
    <?= err('price') ?>

    <label for="stock">Stock Quantity</label>
    <?= html_number('stock', 0, 9999, 1) ?>
    <?= err('stock') ?>

    <label for="sale_price">Flash Sale Price (RM)</label>
    <?= html_text('sale_price', 'maxlength="10" placeholder="Optional"') ?>
    <?= err('sale_price') ?>

    <label for="sale_start">Sale Start</label>
    <input type="datetime-local" id="sale_start" name="sale_start" value="<?= encode($sale_start ?? '') ?>">
    <?= err('sale_start') ?>

    <label for="sale_end">Sale End</label>
    <input type="datetime-local" id="sale_end" name="sale_end" value="<?= encode($sale_end ?? '') ?>">
    <?= err('sale_end') ?>

    <!-- ================================================================ -->
    <!-- MAIN PHOTO UPLOAD (Single) -->
    <!-- ================================================================ -->
    <label for="photo">Main Product Image</label>
    <label class="upload">
        <?= html_file('photo', 'image/*') ?>
        <img src="<?= photo_src($p->photo) ?>">
        <img src="/photos/<?= photo_url($p->photo) ?>">
    </label>
    <small style="color: #666;">Replace the main image only</small>
    <?= err('photo') ?>

    <!-- ================================================================ -->
    <!-- MULTIPLE IMAGE UPLOAD -->
    <!-- ================================================================ -->
    <label>Additional Product Images</label>
    <div class="image-upload-area" id="imageUploadArea">
        <div class="upload-icon">📷</div>
        <div class="upload-text">
            <strong>Click to upload multiple images</strong> or drag and drop<br>
            <small style="color: #999;">Supported: JPG, PNG, WEBP, GIF (Max 5MB each)</small>
        </div>
        <input type="file" name="product_images[]" id="productImages" 
               multiple accept="image/*">
    </div>

    <?php if (!empty($images)): ?>
        <div class="image-grid">
            <?php foreach ($images as $img): ?>
                <div class="image-item <?= $img->is_primary ? 'primary' : '' ?>">
                    <img src="/photos/<?= photo_url($img->image_path) ?>" 
                         alt="Product image <?= $img->id ?>">
                    
                    <?php if ($img->is_primary): ?>
                        <span class="primary-badge">★ Primary</span>
                    <?php endif; ?>
                    
                    <div class="image-actions">
                        <?php if (!$img->is_primary): ?>
                            <button type="submit" name="primary_image" value="<?= $img->id ?>" 
                                    class="btn-primary-img">
                                Set Primary
                            </button>
                        <?php else: ?>
                            <button class="btn-success-img" disabled>Primary</button>
                        <?php endif; ?>
                        
                        <label style="display: flex; align-items: center; gap: 4px; font-size: 11px; cursor: pointer; flex: 1;">
                            <input type="checkbox" name="delete_images[]" value="<?= $img->id ?>" 
                                   class="delete-checkbox" style="width: auto;">
                            Delete
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <small style="color: #999;">Check "Delete" and save to remove images</small>
    <?php else: ?>
        <p style="color: #666; margin-top: 10px;">No additional images uploaded yet.</p>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FORM ACTIONS -->
    <!-- ================================================================ -->
    <section style="margin-top: 20px;">
        <button>Update Product</button>
        <button type="reset">Reset</button>
        <a href="product_list.php" class="btn btn-secondary">Cancel</a>
    </section>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('imageUploadArea');
    const fileInput = document.getElementById('productImages');
    
    // Click to upload
    uploadArea.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = '#007bff';
        this.style.background = '#e3f2fd';
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.borderColor = '#ddd';
        this.style.background = 'transparent';
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#ddd';
        this.style.background = 'transparent';
        
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            // Auto-submit to upload
            this.closest('form').submit();
        }
    });
    
    // Show file count on selection
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            const text = this.closest('.image-upload-area').querySelector('.upload-text');
            text.innerHTML = `<strong>${this.files.length} file(s) selected</strong> - Click "Update Product" to upload`;
        }
    });
});
</script>

<?php
include '../_foot.php';
?>