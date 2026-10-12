
<?php

// LOCAL XAMPP ONLY.
// Promote an existing account, then DELETE this file immediately.

require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'Create first administrator';

$message = '';
$error = '';

// Handle administrator setup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $key = (string) ($_POST['setup_key'] ?? '');

    $email = strtolower(
        trim((string) ($_POST['email'] ?? ''))
    );

    // Verify the setup key
    if (!hash_equals(APP_KEY, $key)) {
        $error = 'Setup key is incorrect. Check APP_KEY in config.php.';

    // Validate the existing account email
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter the email address of an existing account.';

    } else {
        try {
            // Promote the existing account to administrator
            $s = db()->prepare(
                'UPDATE users
                 SET role = "admin",
                     affiliation_verified = 1
                 WHERE email = ?
                 LIMIT 1'
            );

            $s->execute([$email]);

            if ($s->rowCount() === 1) {
                $message = 'Administrator role assigned. '
                    . 'Delete admin/create_admin.php immediately.';
            } else {
                $error = 'No account was changed. Confirm the email '
                    . 'exists and is not already an administrator.';
            }

        } catch (PDOException $x) {
            error_log('Admin setup: ' . $x->getMessage());

            $error = 'Database operation failed.';
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="auth-shell">

    <!-- Setup introduction -->
    <div class="auth-aside">

        <span class="eyebrow">LOCAL SETUP ONLY</span>

        <h1>
            Administrator
            <br>
            <span>bootstrap.</span>
        </h1>

        <p>
            Promotes an existing account. Use only on local XAMPP
            and remove this utility immediately afterwards.
        </p>

    </div>

    <!-- Administrator promotion form -->
    <div class="auth-card">

        <div class="auth-card-heading">

            <h2>Assign administrator role</h2>

            <p>Create your normal account first.</p>

        </div>

        <!-- Success message -->
        <?php if ($message): ?>
            <div class="flash flash-success">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <!-- Error message -->
        <?php if ($error): ?>
            <div class="form-errors">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form-stack">

            <?= csrf_field() ?>

            <div class="form-field">

                <label for="email">
                    Existing account email
                </label>

                <input
                    type="email"
                    name="email"
                    id="email"
                    required
                >

            </div>

            <div class="form-field">

                <label for="setup_key">
                    APP_KEY from config.php
                </label>

                <input
                    type="password"
                    name="setup_key"
                    id="setup_key"
                    required
                >

            </div>

            <button
                class="btn btn-primary btn-wide"
                type="submit"
            >
                Promote existing account
            </button>

        </form>

    </div>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
