<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

// ----------------------------------------------------------------------------
// Handle Quick Block/Unblock Action
// ----------------------------------------------------------------------------
if (is_post() && req('toggle_status')) {
    $target_id = req('target_id');
    $new_active = req('new_active'); // 1 for Active, 0 for Blocked

    // Security check: Prevent blocking an Admin
    $stm_check = $_db->prepare('SELECT role FROM user WHERE id = ?');
    $stm_check->execute([$target_id]);
    $target_role = $stm_check->fetchColumn();

    if ($target_role === 'Admin' && (int)$new_active === 0) {
        temp('err', 'Cannot block an Administrator account.');
    } else {
        $stm = $_db->prepare('UPDATE user SET active = ? WHERE id = ?');
        $stm->execute([$new_active, $target_id]);

        // If blocked, clear remember token to force logout immediately
        if ((int)$new_active === 0) {
            $_db->prepare('UPDATE user SET remember_token = NULL WHERE id = ?')->execute([$target_id]);
        }

        $status_text = (int)$new_active === 1 ? 'Unblocked' : 'Blocked';
        audit('Admin', 'Status Update', "$status_text user ID: $target_id");
        temp('info', "User has been $status_text.");
    }
    
    redirect(); // Refresh the page to show the new status
}
// ----------------------------------------------------------------------------

// Get sorting, searching and pagination parameters
$fields = [
    'id'     => 'ID',
    'email'  => 'Email',
    'name'   => 'Name',
    'role'   => 'Role',
    'active' => 'Status',
];

$sort = req('sort', 'id');
$dir  = req('dir', 'asc');
if (!array_key_exists($sort, $fields)) $sort = 'id';
if ($dir != 'asc' && $dir != 'desc') $dir = 'asc';

$search = req('search');

// Search and sorting query setup
$params = [];
$query = "SELECT * FROM user WHERE 1=1";
if ($search != '') {
    $query .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY $sort $dir";

// ----------------------------------------------------------------------------
// Handle CSV Export
// ----------------------------------------------------------------------------
if (req('export')) {
    // Run the query to get ALL matching results (ignoring pagination limits)
    $stm = $_db->prepare($query);
    $stm->execute($params);
    $all_members = $stm->fetchAll();

    // Log the action
    audit('Admin', 'Export Data', 'Exported member list to CSV');

    // Set headers to force the browser to download a CSV file
    $filename = "Member_List_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add CSV column headers
    fputcsv($output, ['ID', 'Email', 'Name', 'Role', 'Status', 'Points Balance']);

    // Loop through results and add rows to the CSV
    foreach ($all_members as $m) {
        fputcsv($output, [
            $m->id,
            $m->email,
            $m->name,
            $m->role,
            (int)$m->active === 0 ? 'Blocked' : 'Active',
            $m->points ?? 0
        ]);
    }

    // Close the stream and stop the script so HTML doesn't print into the file
    fclose($output);
    exit();
}
// ----------------------------------------------------------------------------

$limit = 5; // Items per page for the web view
$page = req('page', 1);

require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, $limit, $page);
$arr = $pager->result;

$_breadcrumbs = [
    'Dashboard' => '/',
    'Member Maintenance' => '',
];
$_title = 'Admin | Member Maintenance';
include '../_head.php';
?>

<p>
    <button data-get="member_register.php">Register New Member</button>
</p>

<!-- Search and Export Form -->
<form method="get" class="search-form" style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px;">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Search name or email"') ?>
    
    <!-- Keep the sorting parameters active when submitting the form -->
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
    
    <button type="submit">Search</button>
    
    <!-- The Export Button -->
    <button type="submit" name="export" value="1" style="background-color: #27ae60; color: white; border: none; margin-left: auto;">
        📥 Export to CSV
    </button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">👥</span>
        <p class="title">No members found</p>
        <p class="hint">Try another keyword, or register a new member.</p>
    </div>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <th>Photo</th>
            <?php table_headers($fields, $sort, $dir, "search=" . urlencode($search)); ?>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($arr as $m): ?>
        <tr>
            <td>
                <img src="<?= photo_src($m->photo) ?>" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #ccc; border-radius: 5px;">
            </td>
            <td><?= $m->id ?></td>
            <td><?= encode($m->email) ?></td>
            <td><?= encode($m->name) ?></td>
            <td><?= encode($m->role) ?></td>
            
            <!-- Status Badge Display -->
            <td>
                <?php if ((int)$m->active === 0): ?>
                    <span style="color: #e74c3c; font-weight: bold; background: #fadbd8; padding: 2px 8px; border-radius: 12px; font-size: 0.85em;">Blocked</span>
                <?php else: ?>
                    <span style="color: #27ae60; font-weight: bold; background: #d5f5e3; padding: 2px 8px; border-radius: 12px; font-size: 0.85em;">Active</span>
                <?php endif; ?>
            </td>
            
            <td>
                <!-- ONLY show block/unblock if the user is a Member (Not Admin) -->
                <?php if ($m->role !== 'Admin'): ?>
                    <form method="post" style="display: inline-block; margin: 0;">
                        <input type="hidden" name="toggle_status" value="1">
                        <input type="hidden" name="target_id" value="<?= $m->id ?>">
                        
                        <?php if ((int)$m->active === 1): ?>
                            <input type="hidden" name="new_active" value="0">
                            <button type="submit" style="background-color: #e74c3c; color: white; border: none; padding: 4px 8px; font-size: 0.85em; cursor: pointer; border-radius: 4px;" onclick="return confirm('Are you sure you want to block this user?');">Block</button>
                        <?php else: ?>
                            <input type="hidden" name="new_active" value="1">
                            <button type="submit" style="background-color: #27ae60; color: white; border: none; padding: 4px 8px; font-size: 0.85em; cursor: pointer; border-radius: 4px;" onclick="return confirm('Are you sure you want to unblock this user?');">Unblock</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <button data-get="member_detail.php?id=<?= $m->id ?>" style="padding: 4px 8px; font-size: 0.85em; margin-left: 4px; border-radius: 4px;">Details</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<br>
<?php $pager->html("sort=$sort&dir=$dir&search=" . urlencode($search)); ?>

<?php
include '../_foot.php';
?>