<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    // TODO
}

$id  = req('id');
$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
$stm->execute([$id]);
$p = $stm->fetch();
if (!$p) redirect('list.php');

// ----------------------------------------------------------------------------

$_title = 'Product | Detail';
include '../_head.php';
?>

<style>
    #photo {
        display: block;
        border: 1px solid #333;
        width: 200px;
        height: 200px;
    }
</style>

<p>
    <img src="/products/<?= $p->photo ?>" id="photo">
</p>

<table class="table detail">
    <tr>
        <th>Id</th>
        <td><?= $p->id ?></td>
    </tr>
    <tr>
        <th>Name</th>
        <td><?= $p->name ?></td>
    </tr>
    <tr>
        <th>Price</th>
        <td>RM <?= $p->price ?></td>
    </tr>
    <tr>
        <th>Unit</th>
        <td>
            <!-- TODO -->
            <form method="post">
                <!-- TODO ✅ -->
            </form>
        </td>
    </tr>
</table>

<p>
    <button data-get="list.php">List</button>
</p>

<script>
    // TODO
</script>

<?php
include '../_foot.php';