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
        // Insert into database
        $stm = $_db->prepare('INSERT INTO user (email, password, name, photo, role, active) VALUES (?, SHA1(?), ?, ?, ?, 1)');
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
    transition: background-color 0.2s, border-color 0.2s;
}

.upload.dragging {
    border-color: #5c7785;
    background: #f0f4f6;
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

/* --- Password field: input + eye icon + generate button, all in ONE grid cell --- */
.password-field-wrapper {
    width: 100%;
}

.password-row-inner {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.input-with-eye {
    position: relative;
    flex: 1;
    min-width: 0; /* allows flex child to shrink properly */
}

.input-with-eye input {
    width: 100%;
    padding-right: 38px;
    box-sizing: border-box;
}

.eye-toggle {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    color: #888;
    line-height: 0;
}


.generate-btn {
    flex-shrink: 0;
    font-size: 12px;
    padding: 8px 12px;
    border: 1px solid #ccc;
    background: #5c7785;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    white-space: nowrap;
}

.generate-btn:hover {
    background: #46606c;
}

/* Password strength meter: 5 blocks, fills red -> orange -> green */
.strength-meter {
    display: flex;
    gap: 4px;
    margin-top: 8px;
}

.strength-block {
    flex: 1;
    height: 6px;
    border-radius: 3px;
    background: #e0e0e0;
    transition: background-color 0.2s ease;
}

.strength-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin-top: 4px;
    min-height: 15px; /* reserves space so nothing shifts when empty */
}

/* Hint always reserves its own line height (visibility, not display)
   so it never overlaps the next field, whether shown or hidden */
.generate-hint {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: #27ae60;
    visibility: hidden;
    line-height: 1.3;
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
        <div class="password-field-wrapper">
            <div class="password-row-inner">
                <div class="input-with-eye">
                    <?= html_password('password', 'id="password-field" maxlength="100" required placeholder="Min. 6 characters"') ?>
                    <button type="button" class="eye-toggle" id="toggle-password" title="Show/Hide password">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
                <button type="button" id="generate-password-btn" class="generate-btn">🎲 Generate</button>
            </div>
            <div class="strength-meter" id="strength-meter">
                <div class="strength-block"></div>
                <div class="strength-block"></div>
                <div class="strength-block"></div>
                <div class="strength-block"></div>
                <div class="strength-block"></div>
            </div>
            <span class="strength-label" id="strength-label"></span>
            <span class="generate-hint" id="generate-hint">✓ Strong password generated</span>
        </div>
        <?= err('password') ?>

        <label for="confirm">Confirm Password <span class="req">*</span></label>
        <div class="password-field-wrapper">
            <div class="password-row-inner">
                <div class="input-with-eye">
                    <?= html_password('confirm', 'id="confirm-field" maxlength="100" required placeholder="Re-enter password"') ?>
                    <button type="button" class="eye-toggle" id="toggle-confirm" title="Show/Hide password">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
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
                <button type="button" id="flip-h">⇋ Flip Left/Right</button>
                <button type="button" id="flip-v">⇅ Flip Up/Down</button>
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

    // --- Eye toggle (show/hide password) ---
    function setupEyeToggle(toggleId, fieldId) {
        const toggleBtn = document.getElementById(toggleId);
        const field = document.getElementById(fieldId);
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            field.type = field.type === 'password' ? 'text' : 'password';
        });
    }
    setupEyeToggle('toggle-password', 'password-field');
    setupEyeToggle('toggle-confirm', 'confirm-field');

    // --- Password Strength Meter ---
    const strengthBlocks = document.querySelectorAll('#strength-meter .strength-block');
    const strengthLabel = document.getElementById('strength-label');

    function updateStrengthMeter(value) {
        if (value.length === 0) {
            strengthBlocks.forEach(block => block.style.background = '#e0e0e0');
            strengthLabel.textContent = '';
            return;
        }

        let score = 0;
        if (value.length >= 6) score++;
        if (value.length >= 10) score++;
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
        if (/[0-9]/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;

        let color, label;
        if (score <= 2) {
            color = '#e74c3c'; label = 'Weak';
        } else if (score === 3) {
            color = '#f39c12'; label = 'Medium';
        } else {
            color = '#27ae60'; label = 'Strong';
        }

        strengthBlocks.forEach((block, i) => {
            block.style.background = i < score ? color : '#e0e0e0';
        });

        strengthLabel.textContent = label;
        strengthLabel.style.color = color;
    }

    document.getElementById('password-field').addEventListener('input', function() {
        updateStrengthMeter(this.value);
    });

    // --- Password Generator Logic ---
    const generateBtn = document.getElementById('generate-password-btn');
    const passwordField = document.getElementById('password-field');
    const confirmField = document.getElementById('confirm-field');
    const generateHint = document.getElementById('generate-hint');
    const togglePasswordBtn = document.getElementById('toggle-password');
    const toggleConfirmBtn = document.getElementById('toggle-confirm');
    let hintTimeout = null;

    generateBtn.addEventListener('click', function(e) {
        e.preventDefault();

        const length = 12;
        const lowercase = 'abcdefghijklmnopqrstuvwxyz';
        const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const numbers = '0123456789';
        const symbols = '!@#$%^&*';
        const all = lowercase + uppercase + numbers + symbols;

        // Guarantee at least one of each character type
        let chars = [
            lowercase[Math.floor(Math.random() * lowercase.length)],
            uppercase[Math.floor(Math.random() * uppercase.length)],
            numbers[Math.floor(Math.random() * numbers.length)],
            symbols[Math.floor(Math.random() * symbols.length)],
        ];

        // Fill the rest randomly
        for (let i = chars.length; i < length; i++) {
            chars.push(all[Math.floor(Math.random() * all.length)]);
        }

        // Shuffle so guaranteed characters aren't always in the same position
        chars = chars.sort(() => Math.random() - 0.5);
        const generatedPassword = chars.join('');

        // Fill both fields
        passwordField.value = generatedPassword;
        confirmField.value = generatedPassword;
        updateStrengthMeter(generatedPassword);

        // Briefly reveal as plain text so user can see/copy it
        passwordField.type = 'text';
        confirmField.type = 'text';

        generateHint.style.visibility = 'visible';
        if (hintTimeout) clearTimeout(hintTimeout);
        hintTimeout = setTimeout(() => { generateHint.style.visibility = 'hidden'; }, 3000);

        setTimeout(() => {
            passwordField.type = 'password';
            confirmField.type = 'password';
        }, 3000);
    });

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

    // --- Drag and Drop Logic ---
    const uploadArea = document.getElementById('upload-area');
    if (uploadArea) {
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragging');
        });

        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragging');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragging');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                const event = new Event('change');
                fileInput.dispatchEvent(event);
            }
        });
    }

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