<?php
include '_base.php';

// ----------------------------------------------------------------------------

$arr = $_db->query('SELECT * FROM holiday ORDER BY id DESC')->fetchAll();

// ----------------------------------------------------------------------------

$_title = 'Demo 3 : Holiday List';
include '_head.php';
?>

<form method="post" id="f">
    <button formaction="restore.php">Restore</button>
    <button formaction="delete.php">Delete</button>
</form>

<p><?= count($arr) ?> record(s)</p>

<table class="table">
    <tr>
        <th></th>
        <th>Id</th>
        <th>Date</th>
        <th>Name</th>
        <th></th>
    </tr>
    
    <?php foreach ($arr as $h): ?>
        <tr>
            <td>
                <!-- (1) Checkbox -->
                <!-- TODO -->
            </td>
            <td><?= $h->id ?></td>
            <td><?= $h->date ?></td>
            <td><?= $h->name ?></td>
            <td>
                <button data-post="delete.php?id=<?= $h->id ?>">Delete</button>
            </td>
        </tr>
    <?php endforeach ?>
</table>

<?php
include '_foot.php';