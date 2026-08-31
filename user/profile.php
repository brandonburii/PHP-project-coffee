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
    $email_lower = strtolower($email);

    // Validate email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    // Maintain strict rule from registration
    else if (!str_ends_with($email_lower, '@gmail.com') && !str_ends_with($email_lower, '@yahoo.com')) {
        $_err['email'] = 'Only @gmail.com and @yahoo.com addresses are allowed';
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
            // Image is already rotated/flipped client-side via canvas
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

<!-- Load Alpine.js for real-time reactivity -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<style>
/* --- Clean UI Styles for the Photo Upload Section --- */
.photo-section {
    grid-column: 1 / -1; 
    width: 100%;
    max-width: 340px; 
    margin-top: 10px;
    margin-bottom: 20px;
}

.photo-dropzone {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 220px;
    border: 2px dashed #aec2cb;
    background-color: #e6eff2;
    border-radius: 8px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    box-sizing: border-box;
}

.photo-dropzone.dragging {
    background-color: #d6e4e9;
    border-color: #8fa3ad;
}

.photo-dropzone img,
.photo-dropzone canvas,
.photo-dropzone video {
    max-width: 100%;
    max-height: 180px;
    object-fit: contain;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin: 0 auto;
    display: block;
}

.photo-tools {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
    width: 100%;
}

.photo-tools button {
    flex: 1 1 calc(50% - 4px); 
    font-size: 13px;
    padding: 8px 10px;
    border: 1px solid #ccc;
    background: #f7f7f7;
    color: #333;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.15s ease;
}

.photo-tools button:hover {
    background: #e2e2e2;
}

.photo-tools button.primary {
    background: #5c7785;
    color: white;
    border: none;
}
.photo-tools button.primary:hover {
    background: #4a626f;
}

.photo-tools button.danger {
    background: #e74c3c;
    color: white;
    border: none;
}
.photo-tools button.danger:hover {
    background: #c0392b;
}

/* Helper for single full-width button */
.photo-tools button.full-width {
    flex: 1 1 100%;
}
</style>

<?php if ($_user->role == 'Member'): ?>
    <?php
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

<div x-data="profileForm()">
    <form method="post" class="form" enctype="multipart/form-data" @submit.prevent="submitForm($event)">
        
        <label for="email">Email</label>
        <?= html_text('email', 'x-model="email" @input="validateEmail" maxlength="100"') ?>
        <?= err('email') ?>
        <span class="err" x-show="errors.email" x-text="errors.email" style="display: none; color: red;"></span>

        <label for="name">Name</label>
        <?= html_text('name', 'x-model="name" @input="validateName" maxlength="100"') ?>
        <?= err('name') ?>
        <span class="err" x-show="errors.name" x-text="errors.name" style="display: none; color: red;"></span>

        <!-- Tidy Photo Section -->
        <div class="photo-section">
            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Profile Photo</label>
            
            <!-- 1. The Interactive Dropzone -->
            <label class="photo-dropzone" 
                   :class="{ 'dragging': isDragging }"
                   @dragenter.prevent="isDragging = true"
                   @dragover.prevent="isDragging = true" 
                   @dragleave.prevent="isDragging = false" 
                   @drop.prevent="handleDrop">
                
                <!-- Hidden File Input -->
                <?= html_file('photo', 'image/*', '@change="previewImage" x-ref="photoInput" style="display:none;"') ?>
                
                <!-- Default State: Icon & Text (Only if no original image and no camera) -->
                <div x-show="!imagePreview && !isCameraOpen" style="pointer-events: none;">
                    <svg style="width: 40px; height: 40px; color: #a4bac4; margin: 0 auto 10px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    <span style="color: #5c7785; font-size: 14px; text-align: center; display: block;">
                        <strong style="color: #163645;">Choose a file</strong> or drag it here.
                    </span>
                </div>

                <!-- Webcam Stream State -->
                <div x-show="isCameraOpen" style="width: 100%;">
                    <video x-ref="videoElement" autoplay playsinline style="background: #000;"></video>
                </div>
                
                <!-- Image/Canvas Preview -->
                <div x-show="imagePreview && !isCameraOpen" style="width: 100%;">
                    <!-- Original DB Image Preview -->
                    <img x-show="imagePreview === originalPhoto" :src="originalPhoto" alt="Preview">
                    <!-- New/Edited Canvas Preview -->
                    <canvas x-show="imagePreview === 'editing'" x-ref="photoCanvas"></canvas>
                </div>
            </label>
            
            <div style="margin-top: 4px;">
                <?= err('photo') ?>
                <span class="err" x-show="errors.photo" x-text="errors.photo" style="display: none; color: red; font-size: 13px;"></span>
            </div>

            <!-- 2. The Tools Panel (Wrapper is ALWAYS visible now) -->
            <div class="photo-tools">
                
                <!-- Editing Tools (Only show when a NEW image is loaded/captured) -->
                <button type="button" x-show="imagePreview === 'editing'" @click.prevent="rotate(-90)">⟲ Rotate Left</button>
                <button type="button" x-show="imagePreview === 'editing'" @click.prevent="rotate(90)">⟳ Rotate Right</button>
                <button type="button" x-show="imagePreview === 'editing'" @click.prevent="flip('h')">⇋ Flip Left/Right</button>
                <button type="button" x-show="imagePreview === 'editing'" @click.prevent="flip('v')">⇅ Flip Up/Down</button>

                <!-- Camera Open Actions -->
                <button type="button" class="primary full-width" x-show="isCameraOpen" @click.prevent="takeSnapshot">📸 Capture Photo</button>
                <button type="button" class="danger full-width" x-show="isCameraOpen" @click.prevent="stopCamera">Cancel Camera</button>

                <!-- Use Webcam Button: Show when camera is CLOSED. Make it full-width if no editing is happening. -->
                <button type="button" class="primary" :class="{ 'full-width': imagePreview !== 'editing' }" x-show="!isCameraOpen" @click.prevent="startCamera">
                    📷 Use Webcam
                </button>

                <!-- Cancel New Photo: Show when editing a new photo. -->
                <button type="button" class="danger" x-show="imagePreview === 'editing' && !isCameraOpen" @click.prevent="clearImage">
                    Cancel New Photo
                </button>

            </div>
        </div>

        <section style="margin-top: 24px; grid-column: 1 / -1;">
            <button type="submit" x-ref="submitBtn" :disabled="Object.keys(errors).length > 0" :style="Object.keys(errors).length > 0 ? 'opacity: 0.5; cursor: not-allowed;' : ''">Update</button>
            <button type="reset" @click="resetForm">Reset</button>
            
            <!-- FIX: Use standard onclick and point to a new dedicated file -->
            <button type="button" class="secondary" onclick="window.location.href='change_password.php'">Change Password</button>
        </section>
    </form>
</div>

<!-- Alpine.js Application Logic -->
<script>
function profileForm() {
    return {
        email: '<?= htmlspecialchars($_user->email, ENT_QUOTES) ?>',
        name: '<?= htmlspecialchars($_user->name, ENT_QUOTES) ?>',
        originalPhoto: '/photos/<?= htmlspecialchars($_user->photo, ENT_QUOTES) ?>',
        imagePreview: '/photos/<?= htmlspecialchars($_user->photo, ENT_QUOTES) ?>',
        errors: {},
        isDragging: false,
        isCameraOpen: false,
        videoStream: null,

        // Image Processing States
        rotation: 0,
        flipH: false,
        flipV: false,
        hasEditedImage: false,
        imgObj: new Image(),

        validateEmail() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (this.email === '') {
                this.errors.email = 'Required';
            } else if (!emailRegex.test(this.email)) {
                this.errors.email = 'Invalid email format';
            } else {
                const emailLower = this.email.toLowerCase();
                if (!emailLower.endsWith('@gmail.com') && !emailLower.endsWith('@yahoo.com')) {
                    this.errors.email = 'Only @gmail.com and @yahoo.com addresses are allowed';
                } else {
                    delete this.errors.email;
                }
            }
        },

        validateName() {
            if (this.name === '') {
                this.errors.name = 'Required';
            } else {
                delete this.errors.name;
            }
        },

        previewImage(event) {
            const file = event.target.files[0];
            this.processFile(file);
        },

        handleDrop(event) {
            this.isDragging = false;
            if (this.isCameraOpen) return;
            
            const files = event.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = this.$refs.photoInput;
                if (fileInput) {
                    fileInput.files = files; 
                }
                this.processFile(files[0]);
            }
        },

        processFile(file) {
            const maxSizeMB = 2;
            const maxSizeBytes = maxSizeMB * 1024 * 1024;
            const fileInput = this.$refs.photoInput;

            if (!file) return;

            if (!file.type.startsWith('image/')) {
                this.imagePreview = this.originalPhoto;
                this.errors.photo = 'Invalid format. Please upload an image file.';
                if (fileInput) fileInput.value = ''; 
                this.hasEditedImage = false;
                return;
            }

            if (file.size > maxSizeBytes) {
                this.imagePreview = this.originalPhoto;
                this.errors.photo = `File is too large. Maximum size is ${maxSizeMB}MB.`;
                if (fileInput) fileInput.value = ''; 
                this.hasEditedImage = false;
                return;
            }

            delete this.errors.photo;
            
            const reader = new FileReader();
            reader.onload = (e) => {
                this.imgObj.onload = () => {
                    this.rotation = 0;
                    this.flipH = false;
                    this.flipV = false;
                    this.hasEditedImage = true;
                    this.imagePreview = 'editing'; 
                    
                    this.$nextTick(() => {
                        this.drawImage();
                    });
                };
                this.imgObj.src = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        drawImage() {
            const canvas = this.$refs.photoCanvas;
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            
            canvas.width = 200;
            canvas.height = 200;

            ctx.save();
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.translate(canvas.width / 2, canvas.height / 2);
            ctx.rotate(this.rotation * Math.PI / 180);
            ctx.scale(this.flipH ? -1 : 1, this.flipV ? -1 : 1);

            const size = Math.min(this.imgObj.width, this.imgObj.height);
            const sx = (this.imgObj.width - size) / 2;
            const sy = (this.imgObj.height - size) / 2;
            const drawSize = canvas.width;
            
            ctx.drawImage(this.imgObj, sx, sy, size, size, -drawSize / 2, -drawSize / 2, drawSize, drawSize);
            ctx.restore();
        },

        rotate(deg) {
            this.rotation = (this.rotation + deg + 360) % 360;
            this.drawImage();
        },

        flip(dir) {
            if (dir === 'h') this.flipH = !this.flipH;
            if (dir === 'v') this.flipV = !this.flipV;
            this.drawImage();
        },

        submitForm(event) {
            const formElement = event.target;
            
            if (Object.keys(this.errors).length > 0) return;

            if (!this.hasEditedImage) {
                formElement.submit();
                return;
            }

            const btn = this.$refs.submitBtn;
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Updating...';
            }

            this.$refs.photoCanvas.toBlob((blob) => {
                const fileInput = this.$refs.photoInput;
                const originalName = fileInput.files[0] ? fileInput.files[0].name : 'profile.jpg';
                const editedFile = new File([blob], originalName, { type: 'image/jpeg' });
                
                const dt = new DataTransfer();
                dt.items.add(editedFile);
                fileInput.files = dt.files;
                
                formElement.submit();
            }, 'image/jpeg', 0.92);
        },

        startCamera() {
            this.isCameraOpen = true;
            this.imagePreview = null; 
            
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(stream => {
                    this.videoStream = stream;
                    this.$refs.videoElement.srcObject = stream;
                })
                .catch(err => {
                    console.error("Camera access error:", err);
                    alert("Could not access the webcam. Please ensure permissions are granted.");
                    this.isCameraOpen = false;
                    this.imagePreview = this.originalPhoto;
                });
        },

        takeSnapshot() {
            const video = this.$refs.videoElement;
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                const file = new File([blob], "webcam_snapshot.jpg", { type: "image/jpeg" });
                
                const dt = new DataTransfer();
                dt.items.add(file);
                
                const fileInput = this.$refs.photoInput;
                if (fileInput) {
                    fileInput.files = dt.files;
                }
                
                this.processFile(file);
                this.stopCamera();
            }, 'image/jpeg');
        },

        stopCamera() {
            if (this.videoStream) {
                this.videoStream.getTracks().forEach(track => track.stop());
            }
            this.videoStream = null;
            this.isCameraOpen = false;
            
            if (!this.imagePreview && !this.hasEditedImage) {
                this.imagePreview = this.originalPhoto;
            }
        },

        clearImage() {
            const fileInput = this.$refs.photoInput;
            if (fileInput) fileInput.value = '';
            
            this.hasEditedImage = false;
            this.imagePreview = this.originalPhoto; 
            delete this.errors.photo;
        },

        resetForm() {
            this.email = '<?= htmlspecialchars($_user->email, ENT_QUOTES) ?>';
            this.name = '<?= htmlspecialchars($_user->name, ENT_QUOTES) ?>';
            this.clearImage();
            this.stopCamera();
            this.errors = {};
            this.isDragging = false;
        }
    }
}
</script>
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
?>