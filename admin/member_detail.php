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

// ----------------------------------------------------------------------------
// Handle Block/Unblock Action
// ----------------------------------------------------------------------------
if (is_post() && req('action') == 'toggle_block') {
    // Prevent the admin from blocking themselves (added isset check for safety)
    if (isset($_user) && $id == $_user->id) {
        temp('err', "You cannot block your own account.");
    } else {
        // Toggle the status based on current state
        $new_status = ($m->status === 'Blocked') ? 'Active' : 'Blocked';
        
        $update_stm = $_db->prepare('UPDATE user SET status = ? WHERE id = ?');
        $update_stm->execute([$new_status, $id]);
        
        audit('Admin', 'User Status Update', "Admin changed status of {$m->email} to $new_status");
        temp('info', "Member account is now $new_status.");
    }
    // Refresh the page to show the updated status
    redirect("member_detail.php?id=$id"); 
}
// ----------------------------------------------------------------------------

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
    <img src="/photos/<?= htmlspecialchars($m->photo) ?>" style="width: 150px; height: 150px; object-fit: cover; border: 1px solid #ccc; border-radius: 5px;" alt="Profile Photo">

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
            <td><?= encode($m->role) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td>
                <?php if ($m->status === 'Blocked'): ?>
                    <span style="color: #e74c3c; font-weight: bold; background: #fadbd8; padding: 4px 10px; border-radius: 12px; font-size: 0.85em;">Blocked</span>
                <?php elseif ($m->status === 'Pending'): ?>
                    <span style="color: #f39c12; font-weight: bold; background: #fdebd0; padding: 4px 10px; border-radius: 12px; font-size: 0.85em;">Pending</span>
                <?php else: ?>
                    <span style="color: #27ae60; font-weight: bold; background: #d5f5e3; padding: 4px 10px; border-radius: 12px; font-size: 0.85em;">Active</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<div style="display: flex; gap: 10px; margin-top: 20px;">
    <button type="button" class="secondary" data-get="member_list.php">Back to List</button>

    <!-- Block/Unblock Action Form -->
    <?php if (isset($_user) && $m->id != $_user->id): // Do not show block button for the currently logged-in admin ?>
        <form method="post" style="margin: 0;">
            <input type="hidden" name="action" value="toggle_block">
            
            <?php if ($m->status === 'Blocked'): ?>
                <button type="submit" style="background-color: #27ae60; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                    Unblock Member
                </button>
            <?php else: ?>
                <button type="submit" style="background-color: #e74c3c; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;" onclick="return confirm('Are you sure you want to block <?= encode($m->name) ?>? They will not be able to log in.');">
                    Block Member
                </button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<?php
include '../_foot.php';
?>