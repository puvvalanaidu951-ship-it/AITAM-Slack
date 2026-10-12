
<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role(['admin']);
$pageTitle = 'Member management';

// Handle member status updates.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    ) ?: 0;

    $status = (string) ($_POST['status'] ?? '');

    $allowedStatuses = [
        'active',
        'suspended',
        'pending'
    ];

    // Prevent administrators from modifying their own account.
    if (
        $id &&
        $id !== (int) $admin['id'] &&
        in_array($status, $allowedStatuses, true)
    ) {
        db()->prepare(
            'UPDATE users
             SET status = ?
             WHERE id = ?
               AND role <> "admin"'
        )->execute([
            $status,
            $id
        ]);

        set_flash('success', 'Member status updated.');
    }

    redirect('users.php');
}

// Read the search query.
$q = trim((string) ($_GET['q'] ?? ''));

// Build the member search query.
$sql = '
    SELECT
        id,
        full_name,
        email,
        role,
        status,
        department,
        created_at
    FROM users
';

$params = [];

if ($q !== '') {
    $sql .= '
        WHERE full_name LIKE ?
           OR email LIKE ?
    ';

    $params = [
        '%' . $q . '%',
        '%' . $q . '%'
    ];
}

$sql .= '
    ORDER BY created_at DESC
    LIMIT 200
';

$stmt = db()->prepare($sql);
$stmt->execute($params);

$users = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">

    <span class="eyebrow">MEMBER ACCESS</span>

    <h1>Manage members</h1>

    <p>
        Search accounts and update access.
        Admin accounts are protected.
    </p>

</section>

<section class="section-block">

    <!-- Member search form -->
    <form class="admin-search" method="get">

        <input
            type="search"
            name="q"
            value="<?= e($q) ?>"
            placeholder="Search name or email"
        >

        <button
            class="btn btn-primary"
            type="submit"
        >
            Search
        </button>

    </form>

    <!-- Member table -->
    <div class="account-card table-card">

        <div class="admin-table-wrap">

            <table class="admin-table">

                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Update</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($users as $u): ?>
                        <tr>

                            <!-- Member details -->
                            <td>
                                <strong>
                                    <?= e($u['full_name']) ?>
                                </strong>

                                <br>

                                <small>
                                    <?= e($u['email']) ?>
                                </small>
                            </td>

                            <!-- Member role -->
                            <td>
                                <?= e($u['role']) ?>
                            </td>

                            <!-- Current status -->
                            <td>
                                <span class="status-chip status-<?= e((string) $u['status']) ?>">
                                    <?= e($u['status']) ?>
                                </span>
                            </td>

                            <!-- Registration date -->
                            <td>
                                <?= e(date(
                                    'M j, Y',
                                    strtotime($u['created_at'])
                                )) ?>
                            </td>

                            <!-- Status update form -->
                            <td>

                                <?php
                                if (
                                    $u['role'] !== 'admin' &&
                                    (int) $u['id'] !== (int) $admin['id']
                                ):
                                ?>

                                    <form
                                        method="post"
                                        class="user-status-form"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $u['id'] ?>"
                                        >

                                        <select name="status">

                                            <option
                                                value="active"
                                                <?= $u['status'] === 'active' ? 'selected' : '' ?>
                                            >
                                                Active
                                            </option>

                                            <option
                                                value="suspended"
                                                <?= $u['status'] === 'suspended' ? 'selected' : '' ?>
                                            >
                                                Suspended
                                            </option>

                                            <option
                                                value="pending"
                                                <?= $u['status'] === 'pending' ? 'selected' : '' ?>
                                            >
                                                Pending
                                            </option>

                                        </select>

                                        <button
                                            class="btn btn-small btn-secondary"
                                            type="submit"
                                        >
                                            Save
                                        </button>

                                    </form>

                                <?php else: ?>

                                    Protected

                                <?php endif; ?>

                            </td>

                        </tr>
                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
