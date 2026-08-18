<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

$id = req('id');
$stm = $_db->prepare('SELECT * FROM user WHERE id = ?');
$stm->execute([$id]);
$m = $stm->fetch();

if (!$m) {
    redirect('member_list.php');
}

audit('Admin', 'Viewed Member', "Viewed details for user ID: {$m->id}, Name: {$m->name}, Role: {$m->role}");

$_breadcrumbs = [
    'Dashboard' => '/',
    'Member Maintenance' => 'member_list.php',
    'Member Details' => '',
];
$_title = 'Admin | Member Details';
include '../_head.php';
?>

<div style="display: flex; gap: 20px; align-items: flex-start;">
    <img src="/photos/<?= photo_url($m->photo) ?>" style="width: 150px; height: 150px; object-fit: cover; border: 1px solid #ccc; border-radius: 5px;">

    <table class="table detail">
        <tr>
            <th>Member ID</th>
            <td><?= $m->id ?></td>
        </tr>
        <tr>
            <th>Name</th>
            <td><?= encode($m->name) ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?= encode($m->email) ?></td>
        </tr>
        <tr>
            <th>Role</th>
            <td><?= $m->role ?></td>
        </tr>
    </table>
</div>

<p>
    <button data-get="member_list.php">Back to List</button>
</p>

<?php
include '../_foot.php';
