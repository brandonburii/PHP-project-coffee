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

// Additional product images live in products/ and are matched by product name prefix.
function admin_product_additional_images($name, $old_name = '') {
    $images = get_product_images($name);
    if ($old_name !== '' && strcasecmp($old_name, $name) !== 0) {
        foreach (get_product_images($old_name) as $file) {
            if (!in_array($file, $images, true)) {
                $images[] = $file;
            }
        }
    }
    sort($images);
    return $images;
}

function admin_is_valid_product_image_file($filename, $name, $old_name = '') {
    if (!is_string($filename) || $filename === '' || preg_match('/[\/\\\\]/', $filename)) {
        return false;
    }
    return in_array($filename, admin_product_additional_images($name, $old_name), true);
}

$id = req('id');
$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
$stm->execute([$id]);
$p = $stm->fetch();

if (!$p) {
    redirect('product_list.php');
}

$image_files = admin_product_additional_images($p->name);
$images = [];
foreach ($image_files as $file) {
    $images[] = (object) [
        'image_path' => $file,
        'is_primary' => ($file === $p->photo),
        'id' => $file,
    ];
}

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
    // HANDLE ADDITIONAL IMAGE DELETION (Checkboxes)
    // ------------------------------------------------------------------------
    $delete_image_files = req('delete_images') ?: [];

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

            $stm = $_db->prepare('UPDATE product SET photo = ? WHERE id = ?');
            $stm->execute([$photo_name, $id]);
        }

        // --------------------------------------------------------------------
        // DELETE SELECTED ADDITIONAL IMAGES (products/ folder)
        // --------------------------------------------------------------------
        if (!empty($delete_image_files)) {
            foreach ($delete_image_files as $img_file) {
                if (!admin_is_valid_product_image_file($img_file, $name, $p->name)) {
                    continue;
                }

                $file = "../products/$img_file";
                if (is_file($file)) {
                    unlink($file);
                }

                if ($photo_name === $img_file) {
                    $remaining = admin_product_additional_images($name, $p->name);
                    $remaining = array_values(array_filter($remaining, function ($f) use ($img_file) {
                        return $f !== $img_file;
                    }));
                    $photo_name = $remaining[0] ?? '0.jpg';

                    $stm = $_db->prepare('UPDATE product SET photo = ? WHERE id = ?');
                    $stm->execute([$photo_name, $id]);
                }
            }
        }

        // --------------------------------------------------------------------
        // HANDLE PRIMARY IMAGE SELECTION
        // --------------------------------------------------------------------
        $primary_file = req('primary_image');
        if ($primary_file && admin_is_valid_product_image_file($primary_file, $name, $p->name)) {
            $stm = $_db->prepare('UPDATE product SET photo = ? WHERE id = ?');
            $stm->execute([$primary_file, $id]);
            $photo_name = $primary_file;
        }

        // --------------------------------------------------------------------
        // HANDLE MULTIPLE IMAGE UPLOADS (products/ folder)
        // --------------------------------------------------------------------
        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            $upload_dir = '../products/';
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

                if ($file_error !== UPLOAD_ERR_OK) {
                    continue;
                }

                if (!in_array($file_type, $allowed_types)) {
                    temp('warning', "Skipped '{$file_name}': Only JPG, PNG, WEBP, and GIF allowed");
                    continue;
                }

                if ($file_size > $max_file_size) {
                    temp('warning', "Skipped '{$file_name}': File too large (max 5MB)");
                    continue;
                }

                $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = $name . '_' . uniqid('', true) . '.' . $ext;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $destination)) {
                    if ($photo_name === '' || $photo_name === '0.jpg') {
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
/* Full-width block — .form grid otherwise constrains children to one column (~230px). */
.form > .product-additional-images {
    grid-column: 1 / -1;
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.form .product-additional-images .section-label {
    font-weight: 600;
    color: var(--coffee, #5c4033);
    margin: 0;
}

.form .product-additional-images .field-hint {
    margin: 0;
    font-size: 0.82rem;
    color: var(--muted, #6c757d);
}

.form .product-additional-images .image-upload-area {
    border: 2px dashed #ddd;
    padding: 18px 16px;
    text-align: center;
    border-radius: 8px;
    transition: border-color 0.2s, background 0.2s;
    cursor: pointer;
    width: 100%;
    box-sizing: border-box;
    background: #fafafa;
}

.form .product-additional-images .image-upload-area:hover {
    border-color: #007bff;
    background: #f8f9fa;
}

.form .product-additional-images .image-upload-area input[type="file"] {
    display: none;
}

.form .product-additional-images .image-upload-area .upload-icon {
    font-size: 32px;
    color: #6c757d;
    line-height: 1;
}

.form .product-additional-images .image-upload-area .upload-text {
    color: #6c757d;
    margin-top: 6px;
    font-size: 14px;
    line-height: 1.4;
}

.form .product-additional-images .image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 14px;
    width: 100%;
}

.form .product-additional-images .image-item {
    display: flex;
    flex-direction: column;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form .product-additional-images .image-item:hover {
    border-color: #007bff;
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.12);
}

.form .product-additional-images .image-item.primary {
    border-color: #28a745;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.25);
}

.form .product-additional-images .image-thumb {
    position: relative;
    width: 100%;
    height: 120px;
    background: #f3f4f6;
}

.form .product-additional-images .image-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.form .product-additional-images .image-item .primary-badge {
    position: absolute;
    top: 6px;
    left: 6px;
    background: #28a745;
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    line-height: 1.3;
}

.form .product-additional-images .image-actions {
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    background: #fff;
}

.form .product-additional-images .image-actions button {
    width: 100%;
    padding: 5px 8px;
    font-size: 11px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    background: #fff;
    transition: background 0.2s, color 0.2s;
}

.form .product-additional-images .image-actions .btn-primary-img {
    border-color: #007bff;
    color: #007bff;
}

.form .product-additional-images .image-actions .btn-primary-img:hover {
    background: #007bff;
    color: #fff;
}

.form .product-additional-images .image-actions .btn-success-img {
    border-color: #28a745;
    color: #28a745;
    opacity: 0.85;
}

.form .product-additional-images .image-actions .delete-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: #dc3545;
    cursor: pointer;
    margin: 0;
}

.form .product-additional-images .image-actions .delete-label input {
    width: auto;
    margin: 0;
    cursor: pointer;
}

.form .product-additional-images .no-images-hint {
    color: #666;
    margin: 0;
    font-size: 0.9rem;
}

@media (max-width: 540px) {
    .form .product-additional-images .image-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 360px) {
    .form .product-additional-images .image-grid {
        grid-template-columns: 1fr;
    }
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
    </label>
    <p class="field-hint" style="margin:0;font-size:.82rem;color:var(--muted,#6c757d);">* Replace the main listing image only. Gallery images are managed below.</p>
    <?= err('photo') ?>

    <!-- ================================================================ -->
    <!-- ADDITIONAL PRODUCT IMAGES -->
    <!-- ================================================================ -->
    <section class="product-additional-images">
        <label class="section-label">Additional Product Images</label>
        <p class="field-hint">* Optional gallery images for the product detail page. Does not replace the main image above unless you click Set Primary.</p>

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
                        <div class="image-thumb">
                            <img src="<?= photo_src($img->image_path, '0.jpg', 'products') ?>"
                                 alt="Product image <?= encode($img->image_path) ?>">

                            <?php if ($img->is_primary): ?>
                                <span class="primary-badge">★ Primary</span>
                            <?php endif; ?>
                        </div>

                        <div class="image-actions">
                            <?php if (!$img->is_primary): ?>
                                <button type="submit" name="primary_image" value="<?= encode($img->id) ?>"
                                        class="btn-primary-img">
                                    Set Primary
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-success-img" disabled>Primary</button>
                            <?php endif; ?>

                            <label class="delete-label">
                                <input type="checkbox" name="delete_images[]" value="<?= encode($img->id) ?>">
                                Delete
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="field-hint">* Check Delete on any image, then click Update Product to remove it.</p>
        <?php else: ?>
            <p class="no-images-hint">No additional images uploaded yet.</p>
        <?php endif; ?>
    </section>

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
        this.style.background = '#fafafa';
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#ddd';
        this.style.background = '#fafafa';
        
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