<?php
include '../_base.php';

auth('Admin');

ensure_reward_product_id_column();

$reward_types = [
    'product' => 'Existing Product',
    'new'     => 'New Reward',
];

$product_catalog = $_db->query('
    SELECT id, name, description, price, photo
    FROM product
    ORDER BY name ASC
')->fetchAll(PDO::FETCH_ASSOC);

$product_items = [];
$product_catalog_json = [];
$placeholder_src = photo_src('0.jpg');

foreach ($product_catalog as $row) {
    $product_items[$row['id']] = encode($row['name']) . ' — RM ' . sprintf('%.2f', $row['price']);
    $product_catalog_json[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'description' => $row['description'],
        'price' => (float) $row['price'],
        'photo_src' => photo_src($row['photo']),
        'placeholder_src' => $placeholder_src,
    ];
}

if (is_get()) {
    $reward_type = req('reward_type', 'new');
    if (!array_key_exists($reward_type, $reward_types)) {
        $reward_type = 'new';
    }
    $product_id  = req('product_id', '');
    $active      = 1;
    $sort_order  = 0;
    $stock       = 0;
}

if (is_post()) {
    $reward_type = req('reward_type', 'new');
    $product_id  = req('product_id');
    $name        = req('name');
    $description = req('description');
    $points      = req('points');
    $stock       = req('stock');
    $sort_order  = req('sort_order');
    $active      = req('active');
    $photo       = get_file('photo');

    if (!array_key_exists($reward_type, $reward_types)) {
        $reward_type = 'new';
    }

    if ($points == '') {
        $_err['points'] = 'Required';
    }
    else if (filter_var($points, FILTER_VALIDATE_INT) === false || $points < 1) {
        $_err['points'] = 'Must be a positive integer';
    }

    if ($stock === '' || filter_var($stock, FILTER_VALIDATE_INT) === false || $stock < 0) {
        $_err['stock'] = 'Must be a non-negative integer';
    }

    if ($sort_order === '' || filter_var($sort_order, FILTER_VALIDATE_INT) === false) {
        $_err['sort_order'] = 'Must be an integer';
    }

    $product = null;

    if ($reward_type === 'product') {
        if ($product_id == '' || !is_exists($product_id, 'product', 'id')) {
            $_err['product_id'] = 'Select a valid product';
        }
        else {
            $stm = $_db->prepare('SELECT COUNT(*) FROM reward WHERE product_id = ?');
            $stm->execute([$product_id]);
            if ((int) $stm->fetchColumn() > 0) {
                $_err['product_id'] = 'This product is already registered as a reward';
            }
            else {
                $stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
                $stm->execute([$product_id]);
                $product = $stm->fetch();
            }
        }

        if ($photo && !str_starts_with($photo->type, 'image/')) {
            $_err['photo'] = 'Invalid image type';
        }
    }
    else {
        if ($name == '') {
            $_err['name'] = 'Required';
        }
        if ($description == '') {
            $_err['description'] = 'Required';
        }
        if (!$photo) {
            $_err['photo'] = 'Required';
        }
        else if (!str_starts_with($photo->type, 'image/')) {
            $_err['photo'] = 'Invalid image type';
        }
    }

    if (!$_err) {
        if ($reward_type === 'product' && $product) {
            $name = $product->name;
            $description = $product->description;
            $photo_name = $product->photo;

            if ($photo) {
                $photo_name = save_photo($photo, '../photos');
            }

            $stm = $_db->prepare('
                INSERT INTO reward (product_id, name, description, photo, points, stock, active, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stm->execute([
                $product_id, $name, $description, $photo_name,
                $points, $stock, $active ? 1 : 0, $sort_order,
            ]);
        }
        else {
            $photo_name = save_photo($photo, '../photos');
            $stm = $_db->prepare('
                INSERT INTO reward (product_id, name, description, photo, points, stock, active, sort_order)
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stm->execute([
                $name, $description, $photo_name,
                $points, $stock, $active ? 1 : 0, $sort_order,
            ]);
        }

        $new_id = (int) $_db->lastInsertId();
        audit(
            'Rewards',
            'Reward Created',
            $reward_type === 'product'
                ? "Created product-based reward from product $product_id: $name ($points pts)"
                : "Created custom reward: $name ($points pts)",
            null,
            [
                'id' => $new_id,
                'product_id' => $reward_type === 'product' ? $product_id : null,
                'name' => $name,
                'description' => $description,
                'points' => (int) $points,
                'stock' => (int) $stock,
                'active' => $active ? 1 : 0,
                'sort_order' => (int) $sort_order,
                'type' => $reward_type === 'product' ? 'product' : 'custom',
            ]
        );
        temp('info', 'Reward created successfully');
        redirect('reward_list.php');
    }
}

$show_product_fields = ($reward_type ?? 'new') === 'product';
$preview_product = null;
if ($show_product_fields && $product_id && is_exists($product_id, 'product', 'id')) {
    $stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
    $stm->execute([$product_id]);
    $preview_product = $stm->fetch();
}

$_breadcrumbs = [
    'Dashboard' => '/',
    'Reward Maintenance' => 'reward_list.php',
    'Create Reward' => '',
];
$_title = 'Admin | Create Reward';
include '../_head.php';
?>

<style>
.reward-create-page {
    max-width: 920px;
    margin: 0 auto;
}

.reward-create-card {
    background: var(--cream, #eae6dc);
    border: 1px solid var(--line, #d8cfc0);
    border-radius: var(--radius, 12px);
    padding: 24px;
    box-shadow: var(--shadow-sm, 0 1px 3px rgba(0, 0, 0, 0.08));
}

.reward-create-card h2 {
    margin: 0 0 6px;
    color: var(--coffee-dark, #3e2a1f);
    font-size: 1.35rem;
}

.reward-create-intro {
    margin: 0 0 20px;
    color: var(--muted, #6c757d);
    font-size: 0.9rem;
}

.reward-type-cards {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.reward-type-card {
    position: relative;
    display: block;
    padding: 16px 18px;
    border: 2px solid var(--line, #d8cfc0);
    border-radius: var(--radius-sm, 8px);
    background: #fff;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}

.reward-type-card input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.reward-type-card strong {
    display: block;
    color: var(--coffee, #5c4033);
    font-size: 1rem;
    margin-bottom: 4px;
}

.reward-type-card span {
    display: block;
    color: var(--muted, #6c757d);
    font-size: 0.82rem;
    line-height: 1.4;
}

.reward-type-card.is-selected {
    border-color: var(--coffee, #5c4033);
    background: #fffdf8;
    box-shadow: 0 0 0 3px rgba(92, 64, 51, 0.12);
}

.reward-section {
    background: #fff;
    border: 1px solid var(--line, #d8cfc0);
    border-radius: var(--radius-sm, 8px);
    padding: 18px;
    margin-bottom: 18px;
}

.reward-section h3 {
    margin: 0 0 6px;
    color: var(--coffee, #5c4033);
    font-size: 1.05rem;
}

.reward-hint {
    margin: 0 0 14px;
    font-size: 0.82rem;
    color: var(--muted, #6c757d);
    line-height: 1.45;
}

.reward-form-grid {
    display: grid;
    gap: 14px 16px;
}

.reward-form-grid-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.reward-form-grid-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.reward-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.reward-field label {
    font-weight: 600;
    color: var(--coffee, #5c4033);
    font-size: 0.92rem;
}

.reward-field select,
.reward-field input[type="text"],
.reward-field input[type="number"],
.reward-field textarea {
    width: 100%;
    box-sizing: border-box;
}

.reward-field textarea {
    min-height: 120px;
    resize: vertical;
}

.reward-product-preview {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    padding: 14px;
    margin-top: 12px;
    border: 1px solid var(--line, #d8cfc0);
    border-radius: var(--radius-sm, 8px);
    background: var(--cream, #f7f4ef);
}

.reward-product-preview.is-empty {
    display: none;
}

.reward-product-preview img {
    width: 88px;
    height: 88px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid var(--line, #d8cfc0);
    background: #fff;
    flex-shrink: 0;
}

.reward-product-preview .preview-body strong {
    display: block;
    color: var(--coffee-dark, #3e2a1f);
    font-size: 1rem;
    margin-bottom: 4px;
}

.reward-product-preview .preview-price {
    color: var(--coffee, #5c4033);
    font-weight: 600;
    font-size: 0.92rem;
    margin-bottom: 6px;
}

.reward-product-preview .preview-desc {
    color: var(--muted, #6c757d);
    font-size: 0.85rem;
    line-height: 1.45;
}

.reward-image-block {
    margin-top: 14px;
}

.reward-image-block label.upload {
    display: inline-block;
}

.reward-image-block label.upload img {
    width: 180px;
    height: 180px;
    object-fit: cover;
}

.reward-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
}

.reward-actions .btn-secondary {
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

@media (max-width: 768px) {
    .reward-type-cards,
    .reward-form-grid-2,
    .reward-form-grid-3 {
        grid-template-columns: 1fr;
    }
}
</style>

<form method="post" class="reward-create-page" id="rewardCreateForm" enctype="multipart/form-data">
    <div class="reward-create-card">
        <h2>Create Reward</h2>
        <p class="reward-create-intro">Choose whether to link an existing shop product or create a standalone reward item.</p>

        <div class="reward-type-cards" id="rewardTypeCards">
            <?php foreach ($reward_types as $type_key => $type_label): ?>
                <label class="reward-type-card <?= ($reward_type ?? 'new') === $type_key ? 'is-selected' : '' ?>">
                    <input type="radio" name="reward_type" value="<?= encode($type_key) ?>"
                        <?= ($reward_type ?? 'new') === $type_key ? 'checked' : '' ?>>
                    <strong><?= encode($type_label) ?></strong>
                    <span>
                        <?= $type_key === 'product'
                            ? 'Reuse a product from Product Maintenance as a reward.'
                            : 'Create a reward that is not sold in the shop catalog.' ?>
                    </span>
                </label>
            <?php endforeach ?>
        </div>
        <?= err('reward_type') ?>

        <div id="rewardFieldsProduct" class="reward-section" style="<?= $show_product_fields ? '' : 'display:none;' ?>">
            <h3>Existing Product</h3>
            <p class="reward-hint">* Links to a product in Product Maintenance. Does not create or duplicate a shop product.</p>

            <div class="reward-field">
                <label for="product_id">Select Product</label>
                <?= html_select('product_id', $product_items, '- Select product -', 'id="product_id" class="reward-product-select"') ?>
                <?= err('product_id') ?>
            </div>

            <div class="reward-product-preview <?= $preview_product ? '' : 'is-empty' ?>" id="productPreview">
                <img id="productPreviewImg"
                     src="<?= $preview_product ? photo_src($preview_product->photo) : $placeholder_src ?>"
                     alt=""
                     data-placeholder="<?= encode($placeholder_src) ?>"
                     onerror="this.onerror=null;this.src=this.dataset.placeholder;">
                <div class="preview-body">
                    <strong id="productPreviewName"><?= $preview_product ? encode($preview_product->name) : '' ?></strong>
                    <div class="preview-price" id="productPreviewPrice">
                        <?= $preview_product ? 'RM ' . sprintf('%.2f', $preview_product->price) : '' ?>
                    </div>
                    <div class="preview-desc" id="productPreviewDesc">
                        <?= $preview_product ? encode($preview_product->description) : '' ?>
                    </div>
                </div>
            </div>

            <div class="reward-image-block">
                <label for="photo_product">Reward Image (optional)</label>
                <p class="reward-hint">* Uses the product image by default. You may upload a custom image to override.</p>
                <label class="upload">
                    <?= html_file('photo', 'image/*', 'id="photo_product"') ?>
                    <img id="productRewardImg"
                         src="<?= $preview_product ? photo_src($preview_product->photo) : $placeholder_src ?>"
                         alt="Reward image preview"
                         data-placeholder="<?= encode($placeholder_src) ?>"
                         onerror="this.onerror=null;this.src=this.dataset.placeholder;">
                </label>
                <?php if (($reward_type ?? 'new') === 'product'): ?><?= err('photo') ?><?php endif ?>
            </div>
        </div>

        <div id="rewardFieldsNew" class="reward-section" style="<?= $show_product_fields ? 'display:none;' : '' ?>">
            <h3>New Custom Reward</h3>
            <p class="reward-hint">* Independent from the product catalog — not sold in the shop.</p>

            <div class="reward-form-grid reward-form-grid-2">
                <div class="reward-field">
                    <label for="name">Reward Name</label>
                    <?= html_text('name', 'maxlength="100"') ?>
                    <?= err('name') ?>
                </div>
                <div class="reward-field">
                    <label for="description">Description</label>
                    <?= html_textarea('description', 'maxlength="500"') ?>
                    <?= err('description') ?>
                </div>
            </div>

            <div class="reward-image-block">
                <label for="photo_new">Reward Image</label>
                <label class="upload">
                    <?= html_file('photo', 'image/*', 'id="photo_new"') ?>
                    <img id="newRewardImg"
                         src="<?= photo_src('0.jpg') ?>"
                         alt="Reward image preview"
                         data-placeholder="<?= encode($placeholder_src) ?>"
                         onerror="this.onerror=null;this.src=this.dataset.placeholder;">
                </label>
                <?php if (($reward_type ?? 'new') !== 'product'): ?><?= err('photo') ?><?php endif ?>
            </div>
        </div>

        <div class="reward-section">
            <div class="reward-form-grid reward-form-grid-3">
                <div class="reward-field">
                    <label for="points">Points Required</label>
                    <?= html_number('points', 1, 999999, 1) ?>
                    <?= err('points') ?>
                </div>
                <div class="reward-field">
                    <label for="stock">Stock</label>
                    <?= html_number('stock', 0, 9999, 1) ?>
                    <?= err('stock') ?>
                </div>
                <div class="reward-field">
                    <label for="sort_order">Display Order</label>
                    <?= html_number('sort_order', 0, 9999, 1) ?>
                    <?= err('sort_order') ?>
                </div>
            </div>

            <div class="reward-field" style="margin-top:14px;">
                <label for="active">Status</label>
                <?= html_checkbox('active', 'Active (enabled)') ?>
                <?= err('active') ?>
            </div>
        </div>

        <div class="reward-actions">
            <button type="submit">Create Reward</button>
            <button type="reset">Reset</button>
            <a href="reward_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const productCatalog = <?= json_encode($product_catalog_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const placeholderSrc = <?= json_encode($placeholder_src, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const productPanel = document.getElementById('rewardFieldsProduct');
    const newPanel = document.getElementById('rewardFieldsNew');
    const typeCards = document.querySelectorAll('.reward-type-card');
    const radios = document.querySelectorAll('input[name="reward_type"]');
    const productSelect = document.getElementById('product_id');
    const productPreview = document.getElementById('productPreview');
    const productPreviewImg = document.getElementById('productPreviewImg');
    const productPreviewName = document.getElementById('productPreviewName');
    const productPreviewPrice = document.getElementById('productPreviewPrice');
    const productPreviewDesc = document.getElementById('productPreviewDesc');
    const productRewardImg = document.getElementById('productRewardImg');
    const newRewardImg = document.getElementById('newRewardImg');
    const photoProduct = document.getElementById('photo_product');
    const photoNew = document.getElementById('photo_new');

    function findProduct(id) {
        return productCatalog.find(function(item) { return item.id === id; });
    }

    function setImageSrc(img, src) {
        if (!img) return;
        img.src = src || placeholderSrc;
    }

    function updateProductPreview(productId) {
        const product = findProduct(productId);
        if (!product) {
            productPreview.classList.add('is-empty');
            setImageSrc(productPreviewImg, placeholderSrc);
            setImageSrc(productRewardImg, placeholderSrc);
            productPreviewName.textContent = '';
            productPreviewPrice.textContent = '';
            productPreviewDesc.textContent = '';
            return;
        }

        productPreview.classList.remove('is-empty');
        setImageSrc(productPreviewImg, product.photo_src);
        if (!photoProduct || !photoProduct.files || !photoProduct.files.length) {
            setImageSrc(productRewardImg, product.photo_src);
        }
        productPreviewName.textContent = product.name;
        productPreviewPrice.textContent = 'RM ' + product.price.toFixed(2);
        productPreviewDesc.textContent = product.description || '';
    }

    function updateTypeCards() {
        const checked = document.querySelector('input[name="reward_type"]:checked');
        typeCards.forEach(function(card) {
            const input = card.querySelector('input[name="reward_type"]');
            card.classList.toggle('is-selected', input && input.checked);
        });
    }

    function togglePanels() {
        const type = document.querySelector('input[name="reward_type"]:checked')?.value || 'new';
        const isProduct = type === 'product';
        productPanel.style.display = isProduct ? '' : 'none';
        newPanel.style.display = isProduct ? 'none' : '';

        productPanel.querySelectorAll('input, textarea, select').forEach(function(el) {
            el.disabled = !isProduct;
        });
        newPanel.querySelectorAll('input, textarea, select').forEach(function(el) {
            el.disabled = isProduct;
        });

        if (photoProduct) photoProduct.disabled = !isProduct;
        if (photoNew) photoNew.disabled = isProduct;

        updateTypeCards();
        if (isProduct && productSelect) {
            updateProductPreview(productSelect.value);
        }
    }

    radios.forEach(function(radio) {
        radio.addEventListener('change', togglePanels);
    });

    if (productSelect) {
        productSelect.addEventListener('change', function() {
            updateProductPreview(this.value);
        });
        updateProductPreview(productSelect.value);
    }

    function bindUploadPreview(input, img) {
        if (!input || !img) return;
        input.addEventListener('change', function() {
            const file = this.files && this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    bindUploadPreview(photoProduct, productRewardImg);
    bindUploadPreview(photoNew, newRewardImg);

    togglePanels();
});
</script>

<?php include '../_foot.php';
