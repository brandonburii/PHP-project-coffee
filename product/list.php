<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $btn = req('btn');

    // Toggle compare selection
    if ($btn == 'compare') {
        $id = req('id');
        if ($id != '' && is_exists($id, 'product', 'id')) {
            if (!toggle_compare($id)) {
                temp('info', 'You can compare up to 3 products only');
            }
        }
        redirect();
    }

    $id   = req('id');
    $unit = req('unit');

    // Block add-to-cart when out of stock
    $stm = $_db->prepare('SELECT stock FROM product WHERE id = ?');
    $stm->execute([$id]);
    $stock = (int) $stm->fetchColumn();
    if ($stock < 1) {
        temp('info', 'This product is out of stock');
        redirect();
    }

    audit('Cart', 'Added product to cart', "Added product ID $id with quantity $unit from product list page");
    update_cart($id, $unit);
    redirect();
}

$q = trim((string) (req('q') ?? ''));
$category = trim((string) (req('category') ?? ''));
$min_price = req('min_price');
$max_price = req('max_price');

// Load available categories from category table (fallback to distinct product tags)
try {
    $cats = get_categories(['active' => 1]);
    $categories = array_map(function($c) { return $c->name; }, $cats);
} catch (Exception $e) {
    $cats_stm = $_db->query("SELECT DISTINCT tag FROM product WHERE tag IS NOT NULL AND tag != '' ORDER BY tag");
    $categories = $cats_stm->fetchAll(PDO::FETCH_COLUMN);
}

// Build dynamic WHERE clauses
$wheres = [];
$params = [];
if ($q !== '') {
    $wheres[] = '(name LIKE ? OR description LIKE ? OR origin LIKE ? OR roast LIKE ? OR tag LIKE ?)';
    $like = "%" . $q . "%";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($category !== '') {
    $wheres[] = 'tag = ?';
    $params[] = $category;
}
if ($min_price !== '' && is_numeric($min_price)) {
    $wheres[] = 'price >= ?';
    $params[] = (float) $min_price;
}
if ($max_price !== '' && is_numeric($max_price)) {
    $wheres[] = 'price <= ?';
    $params[] = (float) $max_price;
}

$sql = 'SELECT * FROM product';
if (!empty($wheres)) {
    $sql .= ' WHERE ' . implode(' AND ', $wheres);
}

$stm = $_db->prepare($sql);
$stm->execute($params);
$arr = $stm->fetchAll(PDO::FETCH_OBJ);

$compare = get_compare();

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    'Products' => '',
];
$_title = 'Product | List';
include '../_head.php';
?>

<form method="get" style="margin-bottom:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
    <input type="search" name="q" placeholder="Search products..." value="<?= encode($q) ?>" style="flex:1; min-width:180px;" />
    <select name="category" style="min-width:160px">
        <option value="">All categories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= encode($cat) ?>" <?= $cat === $category ? 'selected' : '' ?>><?= encode($cat) ?></option>
        <?php endforeach ?>
    </select>
    <input type="number" step="0.01" name="min_price" placeholder="Min price" value="<?= encode((string)$min_price) ?>" style="width:110px;" />
    <input type="number" step="0.01" name="max_price" placeholder="Max price" value="<?= encode((string)$max_price) ?>" style="width:110px;" />
    <button type="submit">Search</button>
    <?php if (!empty($q) || !empty($category) || $min_price !== '' || $max_price !== ''): ?>
        <a href="/product/list.php" class="secondary">Clear</a>
    <?php endif ?>
</form>

<?php if ($q !== ''): ?>
    <div style="margin-bottom:8px; color:var(--muted);">Showing <?= count($arr) ?> result(s) for "<?= encode($q) ?>"</div>
<?php elseif ($category !== '' || $min_price !== '' || $max_price !== ''): ?>
    <div style="margin-bottom:8px; color:var(--muted);">Showing <?= count($arr) ?> result(s) filtered by
        <?= $category !== '' ? ' category: "' . encode($category) . '"' : '' ?>
        <?= ($min_price !== '' || $max_price !== '') ? ' price: ' . ($min_price !== '' ? '≥ ' . sprintf('%.2f', $min_price) : '') . ($max_price !== '' ? ' ≤ ' . sprintf('%.2f', $max_price) : '') : '' ?>
    </div>
<?php endif ?>

<?php if ($compare): ?>
<div class="card" style="margin-bottom:18px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
        <b><?= count($compare) ?></b> product(s) selected for comparison
        <span style="color:var(--muted); font-size:.85rem;">(max 3)</span>
    </div>
    <button data-get="compare.php">Compare Now</button>
</div>
<?php endif ?>

<div id="products">
    <?php if (empty($arr)): ?>
        <div class="card">No products found<?= $q !== '' ? ' for "' . encode($q) . '"' : '' ?>.</div>
    <?php else: ?>
        <?php foreach ($arr as $p): ?>
        <?php
        $cart      = get_cart();
        $id        = $p->id;
        $in_cart   = $cart[$p->id] ?? 0;
        $available = max($p->stock - $in_cart, 0);
        $max_unit  = min($available, 10);
        $in_stock  = $available >= 1;
        $on_sale   = is_on_sale($p);
        $price     = product_price($p);
        $in_compare = in_array($p->id, $compare);
        $img       = photo_url($p->photo);
        ?>
        <div class="product <?= $in_stock ? '' : 'is-soldout' ?>">
            <div class="thumb">
                <?php if ($in_cart): ?>
                    <span class="badge in-cart"><?= $in_cart ?> in cart</span>
                <?php endif ?>
                <?php if (!empty($p->tag)): ?>
                    <span class="badge tag-badge"><?= encode($p->tag) ?></span>
                <?php endif ?>
                <?php if ($on_sale && $in_stock): ?>
                    <span class="badge sale-badge">SALE</span>
                <?php endif ?>
                <img src="/photos/<?= $img ?>"
                     alt="<?= encode($p->name) ?>"
                     data-get="/product/detail.php?id=<?= $p->id ?>">
            </div>

            <div class="info">
                <div class="name"><?= encode($p->name) ?></div>
                <?php if (!empty($p->origin) || !empty($p->roast)): ?>
                    <div class="meta-line">
                        <?= encode(trim(($p->origin ?? '') . (!empty($p->origin) && !empty($p->roast) ? ' · ' : '') . ($p->roast ?? ''))) ?>
                    </div>
                <?php endif ?>

                <div class="price-row">
                    <div class="price">
                        <?php if ($on_sale && $in_stock): ?>
                            <span class="price-was">RM <?= sprintf('%.2f', $p->price) ?></span>
                        <?php endif ?>
                        RM <?= sprintf('%.2f', $price) ?>
                    </div>
                    <div class="avail-box <?= $in_stock ? 'in' : 'out' ?>" data-product-id="<?= encode($p->id) ?>" data-available="<?= $available ?>">
                        <?= $in_stock ? $available . ' available' : 'Out of stock' ?>
                    </div>
                </div>

                <?php if ($in_stock): ?>
                    <form method="post" class="actions ajax-cart" data-cart-mode="add">
                        <?= html_hidden('id', "id='id_$p->id'") ?>
                        <input type="hidden" name="unit" id="unit_<?= $p->id ?>" value="1">
                        <button type="button" data-ask-qty data-max="<?= $max_unit ?>">Add to Cart</button>
                    </form>
                <?php else: ?>
                    <div class="actions">
                        <button type="button" disabled>Sold Out</button>
                    </div>
                <?php endif ?>

                <form method="post" style="margin-top:6px;">
                    <input type="hidden" name="btn" value="compare">
                    <input type="hidden" name="id" value="<?= encode($p->id) ?>">
                    <button type="submit" class="secondary" style="width:100%; font-size:.8rem;">
                        <?= $in_compare ? '✓ In Compare' : '+ Compare' ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach ?>
    <?php endif ?>
</div>

<?php
include '../_foot.php';
