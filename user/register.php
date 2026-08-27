<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $email = req('email');
    $password = req('password');
    $confirm = req('confirm');
    $name = req('name');
    $photo = get_file('photo');

    // Validate email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else if (!is_unique($email, 'user', 'email')) {
        $_err['email'] = 'Email already exists';
    }

    // Validate password
    if ($password == '') {
        $_err['password'] = 'Required';
    }
    else if (strlen($password) < 6) {
        $_err['password'] = 'Min 6 characters';
    }

    // Validate confirm password
    if ($confirm == '') {
        $_err['confirm'] = 'Required';
    }
    else if ($confirm !== $password) {
        $_err['confirm'] = 'Passwords do not match';
    }

    // Validate name
    if ($name == '') {
        $_err['name'] = 'Required';
    }

    // Validate photo
    if (!$photo) {
        $_err['photo'] = 'Required';
    }
    else if (!str_starts_with($photo->type, 'image/')) {
        $_err['photo'] = 'Invalid image type';
    }

    if (!$_err) {
        $photo_name = save_photo($photo, '../photos');

        $stm = $_db->prepare('INSERT INTO user (email, password, name, photo, role) VALUES (?, SHA1(?), ?, ?, ?)');
        $stm->execute([$email, $password, $name, $photo_name, 'Member']);

        audit('Member', 'Registration', "New member registered: $email, Name: $name");

        temp('info', 'Registration successful! Please login.');
        redirect('/login.php');
    }
}

// ----------------------------------------------------------------------------

$_title = 'User | Register Member';
include '../_head.php';
?>

<style>
/* New wrapper to keep everything in one column */
.photo-upload-group {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    max-width: 280px; 
}

.upload {
    position: relative;
    display: block;
    cursor: pointer;
    text-align: center;
    border: 2px dashed #ccc;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 10px;
    width: 100%;
    box-sizing: border-box;
}

.upload input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}

.upload img,
.upload canvas,
.upload video {
    max-width: 160px;
    max-height: 160px;
    border-radius: 8px;
    display: block;
    margin: 0 auto 8px;
    object-fit: cover;
}

.upload-hint {
    font-size: 13px;
    color: #888;
    line-height: 1.4;
    display: block;
}

.camera-controls, #photo-tools {
    display: flex;
    justify-content: center;
    gap: 6px;
    flex-wrap: wrap;
    width: 100%;
    margin-bottom: 8px;
}

#photo-tools {
    display: none;
}

.camera-controls button, #photo-tools button {
    font-size: 12px;
    padding: 6px 10px;
    border: 1px solid #ccc;
    background: #f7f7f7;
    color: #333;
    border-radius: 6px;
    cursor: pointer;
    flex: 1 1 calc(50% - 6px); /* Makes buttons split nicely into 2 columns */
}

.camera-controls button:hover, #photo-tools button:hover {
    background: #eee;
    color: #000;
}
</style>

<div class="auth-card">
    <div class="auth-card-head">
        <h2>Create your account</h2>
        <p>Join Specialty Coffee &amp; Tea. Fields marked <span class="req">*</span> are required.</p>
    </div>

    <form method="post" class="form auth-form" enctype="multipart/form-data">
        <label for="email">Email <span class="req">*</span></label>
        <?= html_text('email', 'maxlength="100" required placeholder="you@email.com"') ?>
        <?= err('email') ?>

        <label for="password">Password <span class="req">*</span></label>
        <?= html_password('password', 'maxlength="100" required placeholder="Min. 6 characters"') ?>
        <?= err('password') ?>

        <label for="confirm">Confirm Password <span class="req">*</span></label>
        <?= html_password('confirm', 'maxlength="100" required placeholder="Re-enter password"') ?>
        <?= err('confirm') ?>

        <label for="name">Name <span class="req">*</span></label>
        <?= html_text('name', 'maxlength="100" required placeholder="Your full name"') ?>
        <?= err('name') ?>

        <label for="photo">Profile Photo <span class="req">*</span></label>
        
        <!-- NEW WRAPPER ADDED HERE -->
        <div class="photo-upload-group">
            <label class="upload" id="upload-area">
                <?= html_file('photo', 'image/*', 'required') ?>
                <canvas id="photo-canvas" width="200" height="200" style="display:none;"></canvas>
                <video id="webcam-video" autoplay playsinline style="display:none; position:relative; z-index: 3;"></video>
                <img id="photo-placeholder" src="/photos/0.jpg" alt="Preview">
                <span class="upload-hint" id="upload-hint-text">Click to upload image</span>
            </label>

            <div class="camera-controls">
                <button type="button" id="start-camera" style="flex: 1 1 100%;">📷 Start Camera</button>
                <button type="button" id="capture-photo" style="display:none; color: green; font-weight: bold; flex: 1 1 100%;">📸 Capture</button>
                <button type="button" id="stop-camera" style="display:none; color: red; flex: 1 1 100%;">Cancel</button>
            </div>

            <div id="photo-tools">
                <button type="button" id="rotate-left">⟲ Rotate Left</button>
                <button type="button" id="rotate-right">⟳ Rotate Right</button>
                <button type="button" id="flip-h">⇋ Flip H</button>
                <button type="button" id="flip-v">⇅ Flip V</button>
            </div>
            
            <?= err('photo') ?>
        </div>
        <!-- END OF WRAPPER -->

        <section style="margin-top: 20px;">
            <button type="submit" id="submit-btn">Register</button>
            <button type="reset">Reset</button>
            <button type="button" class="secondary" data-get="/login.php">Already have an account?</button>
        </section>
    </form>
</div>

<script>
(function() {
    const fileInput = document.querySelector('input[name="photo"]');
    const canvas = document.getElementById('photo-canvas');
    const ctx = canvas.getContext('2d');
    const placeholder = document.getElementById('photo-placeholder');
    const tools = document.getElementById('photo-tools');
    const hintText = document.getElementById('upload-hint-text');
    const form = document.querySelector('.auth-form');
    const submitBtn = document.getElementById('submit-btn') || form.querySelector('button[type="submit"]');

    // Webcam elements
    const video = document.getElementById('webcam-video');
    const startCameraBtn = document.getElementById('start-camera');
    const capturePhotoBtn = document.getElementById('capture-photo');
    const stopCameraBtn = document.getElementById('stop-camera');
    let stream = null;

    let rotation = 0;      
    let flipH = false;
    let flipV = false;
    let img = new Image();
    let hasEditedImage = false;

    // --- Webcam Logic ---
    startCameraBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } });
            video.srcObject = stream;
            
            // Adjust UI
            video.style.display = 'block';
            placeholder.style.display = 'none';
            canvas.style.display = 'none';
            hintText.style.display = 'none';
            fileInput.style.display = 'none';

            startCameraBtn.style.display = 'none';
            capturePhotoBtn.style.display = 'block';
            stopCameraBtn.style.display = 'block';
            tools.style.display = 'none';
        } catch (err) {
            alert("Camera access denied or not available.");
        }
    });

    stopCameraBtn.addEventListener('click', (e) => {
        if(e) e.preventDefault();
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        
        video.style.display = 'none';
        hintText.style.display = 'block';
        fileInput.style.display = 'block';
        
        startCameraBtn.style.display = 'block';
        capturePhotoBtn.style.display = 'none';
        stopCameraBtn.style.display = 'none';
        
        if (hasEditedImage) {
            canvas.style.display = 'block';
            tools.style.display = 'flex';
        } else {
            placeholder.style.display = 'block';
        }
    });

    capturePhotoBtn.addEventListener('click', (e) => {
        e.preventDefault();
        
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = video.videoWidth;
        tempCanvas.height = video.videoHeight;
        const tempCtx = tempCanvas.getContext('2d');
        tempCtx.drawImage(video, 0, 0, tempCanvas.width, tempCanvas.height);
        
        img.onload = function() {
            rotation = 0;
            flipH = false;
            flipV = false;
            drawImage();
            
            canvas.style.display = 'block';
            placeholder.style.display = 'none';
            tools.style.display = 'flex';
            hintText.textContent = 'Captured from camera. Click to upload file instead.';
            hasEditedImage = true;
            
            stopCameraBtn.click();
            fileInput.required = false; 
        };
        img.src = tempCanvas.toDataURL('image/jpeg');
    });

    // --- File Upload Logic ---
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        
        if (!file) {
            canvas.style.display = 'none';
            placeholder.style.display = 'block';
            tools.style.display = 'none';
            hintText.textContent = 'Click to upload image';
            hasEditedImage = false;
            return;
        }

        if (!file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = function(evt) {
            img.onload = function() {
                rotation = 0;
                flipH = false;
                flipV = false;
                drawImage();
                
                canvas.style.display = 'block';
                placeholder.style.display = 'none';
                tools.style.display = 'flex';
                hintText.textContent = 'Click to change image';
                hasEditedImage = true;
                fileInput.required = false; 
            };
            img.src = evt.target.result;
        };
        reader.readAsDataURL(file);
    });

    // --- Canvas Drawing Logic ---
    function drawImage() {
        canvas.width = 200;
        canvas.height = 200;

        ctx.save();
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.translate(canvas.width / 2, canvas.height / 2);
        ctx.rotate(rotation * Math.PI / 180);
        ctx.scale(flipH ? -1 : 1, flipV ? -1 : 1);

        const size = Math.min(img.width, img.height);
        const sx = (img.width - size) / 2;
        const sy = (img.height - size) / 2;
        const drawSize = canvas.width;
        ctx.drawImage(img, sx, sy, size, size, -drawSize / 2, -drawSize / 2, drawSize, drawSize);

        ctx.restore();
    }

    document.getElementById('rotate-left').addEventListener('click', (e) => {
        e.preventDefault();
        rotation = (rotation - 90 + 360) % 360;
        drawImage();
    });

    document.getElementById('rotate-right').addEventListener('click', (e) => {
        e.preventDefault();
        rotation = (rotation + 90) % 360;
        drawImage();
    });

    document.getElementById('flip-h').addEventListener('click', (e) => {
        e.preventDefault();
        flipH = !flipH;
        drawImage();
    });

    document.getElementById('flip-v').addEventListener('click', (e) => {
        e.preventDefault();
        flipV = !flipV;
        drawImage();
    });

    // --- Form Submission Logic ---
    form.addEventListener('submit', function(e) {
        if (!hasEditedImage) return;

        e.preventDefault();
        
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
        }

        canvas.toBlob(function(blob) {
            const originalName = fileInput.files[0] ? fileInput.files[0].name : 'webcam-profile.jpg';
            const editedFile = new File([blob], originalName, { type: 'image/jpeg' });
            
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(editedFile);
            fileInput.files = dataTransfer.files;
            
            form.submit(); 
        }, 'image/jpeg', 0.92);
    });
})();
</script>

<?php
include '../_foot.php';
?>