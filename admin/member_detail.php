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

// ----------------------------------------------------------------------------

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

<!-- Load Alpine.js for drag and drop functionality -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<style>
/* Drag and Drop Zone Styles */
.photo-dropzone {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 150px;
    min-height: 150px;
    border: 2px dashed #aec2cb;
    background-color: #e6eff2;
    border-radius: 8px;
    padding: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    box-sizing: border-box;
    overflow: hidden;
    position: relative;
}

.photo-dropzone.dragging {
    background-color: #d6e4e9;
    border-color: #8fa3ad;
}

.photo-dropzone img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 4px;
    display: block;
}

.dropzone-placeholder {
    color: #5c7785;
    font-size: 13px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}
</style>

<form method="post" class="form" enctype="multipart/form-data" style="display:flex; gap:20px; align-items:flex-start;">
    
    <!-- Alpine.js Drag & Drop Component -->
    <div style="min-width:150px;" x-data="photoUpload('<?= photo_src($m->photo) ?>')">
        <label style="display:block; margin-bottom:8px;">Photo</label>
        
        <label class="photo-dropzone" 
               :class="{ 'dragging': isDragging }"
               @dragenter.prevent="isDragging = true"
               @dragover.prevent="isDragging = true" 
               @dragleave.prevent="isDragging = false" 
               @drop.prevent="handleDrop">
            
            <!-- Hidden File Input -->
            <?= html_file('photo', 'image/*', '@change="previewImage" x-ref="photoInput" style="display:none;"') ?>
            
            <!-- Empty State / Placeholder -->
            <div x-show="!imagePreview" class="dropzone-placeholder">
                <svg style="width: 30px; height: 30px; margin: 0 auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                <span>Drag & Drop<br>or Click</span>
            </div>

            <!-- Image Preview -->
            <img x-show="imagePreview" :src="imagePreview" alt="Profile Preview">
        </label>
        
        <div style="margin-top: 5px;">
            <?= err('photo') ?>
        </div>
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

<!-- Alpine.js Script for Drag & Drop -->
<script>
function photoUpload(initialImage) {
    return {
        isDragging: false,
        imagePreview: initialImage,

        handleDrop(event) {
            this.isDragging = false;
            const files = event.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = this.$refs.photoInput;
                if (fileInput) {
                    fileInput.files = files; 
                }
                this.processFile(files[0]);
            }
        },

        previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                this.processFile(file);
            }
        },

        processFile(file) {
            if (!file.type.startsWith('image/')) {
                alert('Please upload a valid image file.');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                this.imagePreview = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }
}
</script>

<?php include '../_foot.php'; ?>