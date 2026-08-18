<?php
include '../_base.php';

auth('Admin');

$id = req('id');
$stm = $_db->prepare("SELECT * FROM user WHERE id = ? AND role = 'Admin'");
$stm->execute([$id]);
$a = $stm->fetch();

if (!$a) {
    redirect('admin_list.php');
}

audit('Admin', 'Viewed Admin', "Viewed details for Admin ID: {$a->id}, Name: {$a->name}");

$_breadcrumbs = [
    'Dashboard' => '/',
    'Admin Management' => 'admin_list.php',
    'Admin Details' => '',
];
$_title = 'Admin | Admin Details';
include '../_head.php';
?>

<div style="display: flex; gap: 20px; align-items: flex-start;">
    <img src="/photos/<?= photo_url($a->photo) ?>" style="width: 150px; height: 150px; object-fit: cover; border: 1px solid #ccc; border-radius: 5px;">

    <table class="table detail">
        <tr>
            <th>Admin ID</th>
            <td><?= $a->id ?></td>
        </tr>
        <tr>
            <th>Name</th>
            <td><?= encode($a->name) ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?= encode($a->email) ?></td>
        </tr>
        <tr>
            <th>Role</th>
            <td><?= encode($a->role) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><?= $a->active ? 'Active' : 'Disabled' ?></td>
        </tr>
        <tr>
            <th>Created</th>
            <td><?= !empty($a->created_at) ? $a->created_at : '-' ?></td>
        </tr>
    </table>
</div>

<p>
    <button data-get="admin_list.php">Back to List</button>
</p>

<?php include '../_foot.php';
