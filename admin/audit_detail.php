<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

$id = req('id');
$stm = $_db->prepare('SELECT * FROM audit_log WHERE id = ?');
$stm->execute([$id]);
$l = $stm->fetch();

if (!$l) {
    redirect('audit_log.php');
}

audit('Admin', 'Viewed Audit Detail', "Viewed audit entry ID: {$l->id} (Action: {$l->action}, Module: {$l->module})");

$_breadcrumbs = [
    'Dashboard' => '/',
    'Audit Log' => 'audit_log.php',
    'Audit Detail' => '',
];
$_title = 'Admin | Audit Detail';
include '../_head.php';
?>

<table class="table detail">
    <tr>
        <th>Log ID</th>
        <td><?= $l->id ?></td>
    </tr>
    <tr>
        <th>Timestamp</th>
        <td><?= $l->created_at ?></td>
    </tr>
    <tr>
        <th>User</th>
        <td>
            <?php if ($l->user_id): ?>
                <b><?= encode($l->username) ?></b> (ID: <?= $l->user_id ?>, Role: <?= encode($l->user_role) ?>)
            <?php else: ?>
                Guest
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>IP Address</th>
        <td><?= encode($l->ip_address) ?></td>
    </tr>
    <tr>
        <th>Module</th>
        <td><?= encode($l->module) ?></td>
    </tr>
    <tr>
        <th>Action</th>
        <td><?= encode($l->action) ?></td>
    </tr>
    <tr>
        <th>Full Description</th>
        <td><pre style="white-space: pre-wrap; font-family: inherit; margin: 0;"><?= encode($l->description) ?></pre></td>
    </tr>
</table>

<p>
    <button data-get="audit_log.php">Back to List</button>
</p>

<?php
include '../_foot.php';
