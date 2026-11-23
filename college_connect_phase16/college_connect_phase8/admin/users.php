
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

// Apply member search before counting and limiting results.
$whereSql = '';
$params = [];

if ($q !== '') {
    $whereSql = ' WHERE full_name LIKE :user_name
                  OR email LIKE :user_email';
    $params = [
        ':user_name' => '%' . $q . '%',
        ':user_email' => '%' . $q . '%',
    ];
}

$countSql = 'SELECT COUNT(*) FROM users' . $whereSql;
$recordsSql = '
    SELECT
        id,
        full_name,
        email,
        role,
        status,
        department,
        created_at
    FROM users
' . $whereSql . '
    ORDER BY created_at DESC, id DESC
    LIMIT :pagination_limit OFFSET :pagination_offset
';
$userResult = getPaginatedRecords(
    $countSql,
    $recordsSql,
    $params,
    25,
    [10, 20, 25, 50, 100]
);
$users = $userResult['records'];
$pagination = $userResult['pagination'];

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
<?php include __DIR__ . '/admin-nav.php'; ?>

<section class="section-block">

    <!-- Member search form -->
    <form class="admin-search" method="get">

        <input
            type="search"
            name="q"
            value="<?= e($q) ?>"
            placeholder="Search name or email"
        >
        <input
            type="hidden"
            name="per_page"
            value="<?= (int) $pagination['per_page'] ?>"
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

                    <?php if (!$users): ?>
                        <tr>
                            <td colspan="5">No members match this search.</td>
                        </tr>
                    <?php endif; ?>

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

    <?= renderPagination($pagination, 'page', 'per_page', 'Members') ?>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
