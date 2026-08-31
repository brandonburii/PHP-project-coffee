<?php
include '../_base.php';

auth('Admin');

// Ensure table exists
$_db->exec("CREATE TABLE IF NOT EXISTS category (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$id = req('id');
$stm = $_db->prepare('SELECT * FROM category WHERE id = ?');
$stm->execute([$id]);
$c = $stm->fetch();
if (!$c) redirect('category_list.php');

if (is_get()) {
    $name = $c->name;
    $slug = $c->slug;
    $sort = $c->sort_order;
    $active = $c->active;
}

if (is_post()) {
    $name = req('name');
    $slug = req('slug');
    $sort = req('sort_order');
    $active = req('active');

    if ($name == '') $_err['name'] = 'Required';
    if ($slug == '') $_err['slug'] = 'Required';
    if ($sort === '' || filter_var($sort, FILTER_VALIDATE_INT) === false) $_err['sort_order'] = 'Must be an integer';

    if (!$_err) {
        $stm = $_db->prepare('UPDATE category SET name = ?, slug = ?, active = ?, sort_order = ? WHERE id = ?');
        $stm->execute([$name, $slug, $active ? 1 : 0, (int)$sort, $id]);
        audit('Categories', 'Category Updated', "Updated category: $name");
        temp('info', 'Category updated successfully');
        redirect('category_list.php');
    }
}

$_breadcrumbs = [
    'Dashboard' => '/',
    'Category Maintenance' => 'category_list.php',
    'Edit Category' => '',
];
$_title = 'Admin | Edit Category';
include '../_head.php';
?>

<form method="post" class="form">
    <label for="name">Name</label>
    <?= html_text('name', 'maxlength="100"') ?>
    <?= err('name') ?>

    <label for="slug">Slug</label>
    <?= html_text('slug', 'maxlength="100"') ?>
    <?= err('slug') ?>

    <label for="sort_order">Sort Order</label>
    <?= html_number('sort_order', 0, 9999, 1) ?>
    <?= err('sort_order') ?>

    <label for="active">Active</label>
    <?= html_checkbox('active', 'Active') ?>

    <section>
        <button>Save Changes</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php include '../_foot.php';
