<?php
include '_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $email   = req('email');
    $subject = req('subject');
    $body    = req('body');
    $html    = req('html');

    // Validate: email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (strlen($email) > 100) {
        $_err['email'] = 'Maximum 100 characters';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }

    // Validate: subject
    if ($subject == '') {
        $_err['subject'] = 'Required';
    }
    else if (strlen($subject) > 100) {
        $_err['subject'] = 'Maximum 100 characters';
    }

    // Validate: body
    if ($body == '') {
        $_err['body'] = 'Required';
    }
    else if (strlen($body) > 500) {
        $_err['body'] = 'Maximum 500 characters';
    }
    
    // Send email
    if (!$_err) {
        // TODO

        temp('info', 'Email sent');
        redirect();
    }
}

// ----------------------------------------------------------------------------

$_title = 'Demo';
include '_head.php';
?>

<style>
    #body {
        width: 500px;
        height: 200px;
        resize: none;
    }
</style>

<form class="form" method="post">
    <label for="email">Email</label>
    <?= html_text('email', 'maxlength="100"') ?>
    <?= err('email') ?>

    <label for="subject">Subject</label>
    <?= html_text('subject', 'maxlength="100"') ?>
    <?= err('subject') ?>

    <label for="body">Body</label>
    <?= html_textarea('body', 'maxlength="500"') ?>
    <?= err('body') ?>

    <label></label>
    <?= html_checkbox('html', 'HTML Content') ?>
    <br>

    <section>
        <button>Send</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '_foot.php';