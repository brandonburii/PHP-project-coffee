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
// Handle updates and deletion
if (is_post()) {
    $btn = req('btn');

    // Delete action
    if ($btn === 'delete') {
        $self = $_SESSION['user']->id ?? null;

        // Count total admins
        $stm = $_db->prepare("SELECT COUNT(*) FROM user WHERE role = 'Admin'");
        $stm->execute();
        $total_admins = (int) $stm->fetchColumn();

        if ((int)$id === (int)$self) {
            temp('info', 'Cannot delete yourself.');
        }
        else if ($m->role === 'Admin' && $total_admins <= 1) {
            temp('info', 'Cannot delete the last admin account.');
        }
        else {
            $stm = $_db->prepare('DELETE FROM user WHERE id = ?');
            $stm->execute([$id]);
            // remove photo file if not default
            if ($m->photo && $m->photo !== '0.jpg' && file_exists(__DIR__ . '/../photos/' . $m->photo)) {
                @unlink(__DIR__ . '/../photos/' . $m->photo);
            }
            audit('Admin', 'Member Deleted', "Deleted user ID: $id, Email: {$m->email}");
            temp('info', 'Member deleted');
            redirect('member_list.php');
        }
    }

    // Save/update action
    if ($btn === 'save') {
        $email = req('email');
        $password = req('password');
        $confirm = req('confirm');
        $name = req('name');
        $role = req('role');
        $photo = get_file('photo');

        // Validate email
        if ($email == '') {
            $_err['email'] = 'Required';
        }
        else if (!is_email($email)) {
            $_err['email'] = 'Invalid email';
        }
        else {
            $stm = $_db->prepare('SELECT COUNT(*) FROM user WHERE email = ? AND id != ?');
            $stm->execute([$email, $id]);
            if ($stm->fetchColumn() > 0) {
                $_err['email'] = 'Email already exists';
            }
        }

        // Validate password if provided
        if ($password !== '') {
            if (strlen($password) < 6) {
                $_err['password'] = 'Min 6 characters';
            }
            if ($confirm === '') {
                $_err['confirm'] = 'Required';
            } else if ($confirm !== $password) {
                $_err['confirm'] = 'Passwords do not match';
            }
        }

        if ($name == '') $_err['name'] = 'Required';
        if ($role != 'Member' && $role != 'Admin') $_err['role'] = 'Invalid role';
        if ($photo && !str_starts_with($photo->type, 'image/')) $_err['photo'] = 'Invalid image type';

        if (!$_err) {
            // Handle photo upload
            $photo_name = $m->photo;
            if ($photo) {
                if ($photo_name && $photo_name !== '0.jpg' && file_exists(__DIR__ . '/../photos/' . $photo_name)) {
                    @unlink(__DIR__ . '/../photos/' . $photo_name);
                }
                $photo_name = save_photo($photo, __DIR__ . '/../photos');
            }

            // Build update query
            if ($password !== '') {
                $stm = $_db->prepare('UPDATE user SET email = ?, password = SHA1(?), name = ?, photo = ?, role = ? WHERE id = ?');
                $stm->execute([$email, $password, $name, $photo_name, $role, $id]);
            } else {
                $stm = $_db->prepare('UPDATE user SET email = ?, name = ?, photo = ?, role = ? WHERE id = ?');
                $stm->execute([$email, $name, $photo_name, $role, $id]);
            }

            audit('Admin', 'Member Updated', "Updated user ID: $id, Email: $email, Role: $role");
            temp('info', 'Member updated successfully');
            redirect('member_detail.php?id=' . $id);
        }
    }
}

// Refresh user data after possible update
$stm = $_db->prepare('SELECT * FROM user WHERE id = ?');
$stm->execute([$id]);
$m = $stm->fetch();

audit('Admin', 'Viewed Member', "Viewed details for user ID: {$m->id}, Name: {$m->name}, Role: {$m->role}");

$_breadcrumbs = [
    'Dashboard' => '/',
    'Member Maintenance' => 'member_list.php',
    'Member Details' => '',
];
$_title = 'Admin | Member Details';

// Prefill form globals for html_* helpers
$GLOBALS['id'] = $m->id;
$GLOBALS['name'] = $m->name;
$GLOBALS['email'] = $m->email;
$GLOBALS['role'] = $m->role;

include '../_head.php';
?>

<form method="post" class="form" enctype="multipart/form-data" style="display:flex; gap:20px; align-items:flex-start;">
    <div style="min-width:150px;">
        <label>Photo</label>
        <br>
        <img src="/photos/<?= $m->photo ?>" style="width:150px;height:150px;object-fit:cover;border:1px solid #ccc;border-radius:5px;">
        <label class="upload" style="display:block;margin-top:8px;">
            <?= html_file('photo', 'image/*') ?>
            <img src="/photos/0.jpg" style="display:none;">
        </label>
        <?= err('photo') ?>
    </div>

    <div style="flex:1;">
        <table class="table detail">
            <tr>
                <th>Member ID</th>
                <td><?= $m->id ?></td>
            </tr>
            <tr>
                <th for="name">Name</th>
                <td>
                    <?= html_text('name', 'maxlength="100"') ?>
                    <?= err('name') ?>
                </td>
            </tr>
            <tr>
                <th for="email">Email</th>
                <td>
                    <?= html_text('email', 'maxlength="100"') ?>
                    <?= err('email') ?>
                </td>
            </tr>
            <tr>
                <th for="password">Password</th>
                <td>
                    <?= html_password('password', 'maxlength="100" placeholder="Leave blank to keep"') ?>
                    <?= err('password') ?>
                </td>
            </tr>
            <tr>
                <th for="confirm">Confirm Password</th>
                <td>
                    <?= html_password('confirm', 'maxlength="100" placeholder="Leave blank to keep"') ?>
                    <?= err('confirm') ?>
                </td>
            </tr>
            <tr>
                <th for="role">Role</th>
                <td>
                    <?= html_select('role', ['Member' => 'Member', 'Admin' => 'Admin'], null) ?>
                    <?= err('role') ?>
                </td>
            </tr>
        </table>

        <section style="margin-top:12px; display:flex; gap:8px;">
            <button name="btn" value="save">Save Changes</button>
            <button type="button" class="danger" onclick="if(confirm('Are you sure you want to delete this member?')) { document.getElementById('delform').submit(); }">Delete Member</button>
            <button data-get="member_list.php" type="button">Back to List</button>
        </section>
    </div>
</form>

<form id="delform" method="post" style="display:none;">
    <?= html_hidden('id') ?>
    <input type="hidden" name="btn" value="delete">
</form>

<?php include '../_foot.php';
