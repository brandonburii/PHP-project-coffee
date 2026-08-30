<?php
include '../_base.php';

// ----------------------------------------------------------------------------

auth();

if (is_get()) {
    $email = $_user->email;
    $name = $_user->name;
}

if (is_post()) {
    $email = req('email');
    $name = req('name');
    $photo = get_file('photo');

    // Validate email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else if ($email !== $_user->email && !is_unique($email, 'user', 'email')) {
        $_err['email'] = 'Email already exists';
    }

    // Validate name
    if ($name == '') {
        $_err['name'] = 'Required';
    }

    // Validate photo (optional update)
    if ($photo && !str_starts_with($photo->type, 'image/')) {
        $_err['photo'] = 'Invalid image type';
    }

    if (!$_err) {
        $photo_name = $_user->photo;

        if ($photo) {
            // Delete old photo if it's not the default placeholder
            if ($photo_name && $photo_name !== '0.jpg' && file_exists("../photos/$photo_name")) {
                unlink("../photos/$photo_name");
            }
            $photo_name = save_photo($photo, '../photos');
        }

        // Update DB
        $stm = $_db->prepare('UPDATE user SET email = ?, name = ?, photo = ? WHERE id = ?');
        $stm->execute([$email, $name, $photo_name, $_user->id]);

        if ($photo) {
            audit('Member', 'Profile Photo Update', "Updated profile photo to: $photo_name");
        }
        audit('Member', 'Profile Update', "Updated name to: $name, email to: $email");

        // Reload user session data
        $stm_user = $_db->prepare('SELECT * FROM user WHERE id = ?');
        $stm_user->execute([$_user->id]);
        $_SESSION['user'] = $stm_user->fetch();

        temp('info', 'Profile updated successfully');
        redirect();
    }
}

// ----------------------------------------------------------------------------

$_title = 'User | Profile';
include '../_head.php';
?>

<?php if ($_user->role == 'Member'): ?>
    <?php
    // Fresh points from DB (session can be stale)
    $stm = $_db->prepare('SELECT points FROM user WHERE id = ?');
    $stm->execute([$_user->id]);
    $points_balance = (int) $stm->fetchColumn();
    $_SESSION['user']->points = $points_balance;
    ?>
    <div class="card" style="display:flex; align-items:center; gap:16px; margin-bottom:22px; max-width:420px;">
        <div style="font-size:2rem;">⭐</div>
        <div>
            <div style="color:var(--muted); font-size:.85rem;">Reward Points</div>
            <div style="font-size:1.6rem; font-weight:700; color:var(--coffee);"><?= $points_balance ?></div>
            <div style="color:var(--muted); font-size:.8rem;">Worth RM <?= sprintf('%.2f', points_value($points_balance)) ?> at checkout</div>
            <a href="/reward/list.php" style="font-size:.85rem; font-weight:600;">Browse Rewards →</a>
        </div>
    </div>
<?php endif ?>

<form method="post" class="form" enctype="multipart/form-data">
    <label for="email">Email</label>
    <?= html_text('email', 'maxlength="100"') ?>
    <?= err('email') ?>

    <label for="name">Name</label>
    <?= html_text('name', 'maxlength="100"') ?>
    <?= err('name') ?>

    <label for="photo">Photo</label>
    <label class="upload">
        <?= html_file('photo', 'image/*') ?>
        <img src="<?= photo_src($_user->photo) ?>">
    </label>
    <?= err('photo') ?>

    <section>
        <button>Update</button>
        <button type="reset">Reset</button>
        <button type="button" class="secondary" data-get="/user/password.php">Change Password</button>
    </section>
</form>

<?php
include '../_foot.php';