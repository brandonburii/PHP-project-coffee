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

if (is_get()) {
    $email  = $a->email;
    $name   = $a->name;
    $active = $a->active;
}

if (is_post()) {
    $email  = req('email');
    $name   = req('name');
    $active = req('active');
    $photo  = get_file('photo');

    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else if ($email !== $a->email && !is_unique($email, 'user', 'email')) {
        $_err['email'] = 'Email already exists';
    }

    if ($name == '') {
        $_err['name'] = 'Required';
    }

    if ($photo && !str_starts_with($photo->type, 'image/')) {
        $_err['photo'] = 'Invalid image type';
    }

    // Safety checks for active Admin state
    if ($a->id == $_user->id && !$active) {
        $_err['active'] = 'You cannot disable your own account';
    }
    if ($a->active && !$active) {
        $stm = $_db->query("SELECT COUNT(*) FROM user WHERE role = 'Admin' AND active = 1");
        $active_admins = (int) $stm->fetchColumn();
        if ($active_admins <= 1) {
            $_err['active'] = 'Cannot disable the last active Admin account';
        }
    }

    if (!$_err) {
        $before = [
            'email' => $a->email,
            'name' => $a->name,
            'photo' => $a->photo,
            'active' => (int) $a->active,
        ];

        $photo_name = $a->photo;
        if ($photo) {
            if ($photo_name && $photo_name !== '0.jpg' && file_exists("../photos/$photo_name")) {
                unlink("../photos/$photo_name");
            }
            $photo_name = save_photo($photo, '../photos');
        }

        $stm = $_db->prepare('
            UPDATE user
            SET email = ?, name = ?, photo = ?, active = ?
            WHERE id = ? AND role = ?
        ');
        $stm->execute([$email, $name, $photo_name, $active ? 1 : 0, $id, 'Admin']);

        if ($id == $_user->id) {
            $stm = $_db->prepare('SELECT * FROM user WHERE id = ?');
            $stm->execute([$id]);
            $_SESSION['user'] = $stm->fetch();
        }

        audit(
            'Admin',
            'Admin Updated',
            "Updated admin ID $id",
            $before,
            [
                'email' => $email,
                'name' => $name,
                'photo' => $photo_name,
                'active' => $active ? 1 : 0,
            ]
        );
        temp('info', 'Admin updated successfully');
        redirect('admin_list.php');
    }
}

$_breadcrumbs = [
    'Dashboard' => '/',
    'Admin Management' => 'admin_list.php',
    'Edit Admin' => '',
];
$_title = 'Admin | Edit Admin';
include '../_head.php';
?>

<form method="post" class="form" enctype="multipart/form-data">
    <label for="email">Email</label>
    <?= html_text('email', 'maxlength="100"') ?>
    <?= err('email') ?>

    <label for="name">Name</label>
    <?= html_text('name', 'maxlength="100"') ?>
    <?= err('name') ?>

    <label>Role</label>
    <b>Admin</b>
    <span></span>

    <label for="active">Status</label>
    <?= html_checkbox('active', 'Active (enabled)') ?>
    <?= err('active') ?>

    <label for="photo">Photo</label>
    <label class="upload">
        <?= html_file('photo', 'image/*') ?>
        <img src="/photos/<?= photo_url($a->photo) ?>">
    </label>
    <?= err('photo') ?>

    <section>
        <button>Update Admin</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php include '../_foot.php';
