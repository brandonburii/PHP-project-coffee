<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

$tag_items = [
    'NEW'        => 'NEW',
    'BEST VALUE' => 'BEST VALUE',
    'LIMITED'    => 'LIMITED',
];
$roast_items = [
    'Light'  => 'Light',
    'Medium' => 'Medium',
    'Dark'   => 'Dark',
];

if (is_post()) {
    $id          = req('id');
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

    // Validate ID
    if ($id == '') {
        $_err['id'] = 'Required';
    }
    else if (!preg_match('/^P\d{3}$/', $id)) {
        $_err['id'] = 'Invalid format (use P000 format)';
    }
    else if (!is_unique($id, 'product', 'id')) {
        $_err['id'] = 'ID already exists';
    }

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

    // Validate Photo
    if (!$photo) {
        $_err['photo'] = 'Required';
    }
    else if (!str_starts_with($photo->type, 'image/')) {
        $_err['photo'] = 'Invalid image type';
    }

    if (!$_err) {
        $photo_name = save_photo($photo, '../photos');

        // --------------------------------------------------------------------
        // INSERT PRODUCT
        // --------------------------------------------------------------------
        $stm = $_db->prepare('
            INSERT INTO product
                (id, name, description, origin, roast, tag, price, sale_price, sale_start, sale_end, photo, stock)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stm->execute([
            $id, $name, $description,
            $origin ?: null, $roast ?: null, $tag ?: null,
            $price,
            $sale_price,
            $sale_start ? date('Y-m-d H:i:s', strtotime($sale_start)) : null,
            $sale_end   ? date('Y-m-d H:i:s', strtotime($sale_end))   : null,
            $photo_name, $stock,
        ]);

        // --------------------------------------------------------------------
        // INSERT PRIMARY IMAGE INTO product_image TABLE
        // --------------------------------------------------------------------
        $stm = $_db->prepare('
            INSERT INTO product_image (product_id, image_path, is_primary, sort_order)
            VALUES (?, ?, 1, 0)
        ');
        $stm->execute([$id, $photo_name]);

        // --------------------------------------------------------------------
        // HANDLE ADDITIONAL IMAGE UPLOADS
        // --------------------------------------------------------------------
        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            $upload_dir = '../photos/';
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            
            $uploaded_files = $_FILES['product_images'];
            $total_files = count($uploaded_files['name']);
            
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
                    // Insert into database (not primary)
                    $sort_order = $i + 1;
                    
                    $stm = $_db->prepare('
                        INSERT INTO product_image (product_id, image_path, is_primary, sort_order)
                        VALUES (?, ?, 0, ?)
                    ');
                    $stm->execute([$id, $new_filename, $sort_order]);
                } else {
                    temp('warning', "Failed to upload '{$file_name}'");
                }
            }
        }

        log_stock($id, 'added', 0, $stock);

        audit('Products', 'Product Created', "Created product ID: $id, Name: $name, Price: RM$price, Stock: $stock");

        temp('info', 'Product created successfully');
        redirect('product_list.php');
    }
}

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    'Product Maintenance' => 'product_list.php',
    'Create Product' => '',
];
$_title = 'Admin | Create Product';
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
</style>

<form method="post" class="form" enctype="multipart/form-data">
    <label for="id">Product ID</label>
    <?= html_text('id', 'maxlength="4" placeholder="P000"') ?>
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
        <img src="/photos/0.jpg">
    </label>
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

    <!-- ================================================================ -->
    <!-- FORM ACTIONS -->
    <!-- ================================================================ -->
    <section style="margin-top: 20px;">
        <button>Create Product</button>
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
            text.innerHTML = `<strong>${this.files.length} file(s) selected</strong> - Click "Create Product" to upload`;
        }
    });
});
</script>

<?php
include '../_foot.php';
?>