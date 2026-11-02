<?php
// admin/organizations.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$message = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            $error = 'Organization name is required.';
        } else {
            $stmt = mysqli_prepare($conn, 'INSERT INTO organizations (name, description) VALUES (?, ?)');
            mysqli_stmt_bind_param($stmt, 'ss', $name, $desc);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Organization added.';
            } else {
                $error = 'Failed to add organization (name may already exist).';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($id <= 0 || $name === '') {
            $error = 'Invalid data.';
        } else {
            $stmt = mysqli_prepare($conn, 'UPDATE organizations SET name = ?, description = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'ssi', $name, $desc, $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Organization updated.';
            } else {
                $error = 'Failed to update organization.';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, 'DELETE FROM organizations WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Organization deleted.';
            } else {
                $error = 'Failed to delete organization.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Edit mode
$editOrg = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = mysqli_prepare($conn, 'SELECT id, name, description FROM organizations WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $editOrg = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

// List
$orgs = [];
$res = mysqli_query($conn, 'SELECT id, name, description, created_at FROM organizations ORDER BY name');
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $orgs[] = $row;
    }
    mysqli_free_result($res);
}

$pageTitle = 'Manage Organizations';
include __DIR__ . '/../includes/header.php';
?>
    <h1>Manage Organizations</h1>
    <?php if ($message !== ''): ?><div class="alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card">
        <h3><?php echo $editOrg ? 'Edit Organization' : 'Add Organization'; ?></h3>
        <form method="post" action="">
            <input type="hidden" name="action" value="<?php echo $editOrg ? 'update' : 'add'; ?>">
            <?php if ($editOrg): ?>
                <input type="hidden" name="id" value="<?php echo (int) $editOrg['id']; ?>">
            <?php endif; ?>
            <label>Name *</label><br>
            <input type="text" name="name" required value="<?php echo $editOrg ? htmlspecialchars($editOrg['name']) : ''; ?>"><br>
            <label>Description</label><br>
            <textarea name="description" rows="3"><?php echo $editOrg ? htmlspecialchars($editOrg['description']) : ''; ?></textarea><br>
            <input type="submit" value="<?php echo $editOrg ? 'Update' : 'Add'; ?>">
            <?php if ($editOrg): ?>
                <a href="<?php echo BASE_URL; ?>admin/organizations.php" style="margin-left:10px;">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <h2>Organization List</h2>
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($orgs as $o): ?>
            <tr>
                <td><?php echo (int) $o['id']; ?></td>
                <td><?php echo htmlspecialchars($o['name']); ?></td>
                <td><?php echo htmlspecialchars($o['description']); ?></td>
                <td>
                    <a href="<?php echo BASE_URL; ?>admin/organizations.php?edit=<?php echo (int) $o['id']; ?>">Edit</a>
                    <form method="post" action="" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $o['id']; ?>">
                        <button type="submit" onclick="return confirm('Delete this organization?');">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($orgs)): ?>
            <tr><td colspan="4">No organizations yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
<?php include __DIR__ . '/../includes/footer.php'; ?>
