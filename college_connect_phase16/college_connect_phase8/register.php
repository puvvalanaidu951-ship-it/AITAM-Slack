
<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('profile.php');
}

$pageTitle = 'Create account';

$errors = [];
$fullName = '';
$email = '';
$role = 'student';
$department = '';
$graduationYear = '';

$allowed = ['student', 'alumni', 'prospective'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $role = (string) ($_POST['role'] ?? 'student');
    $department = trim((string) ($_POST['department'] ?? ''));
    $graduationYear = trim((string) ($_POST['graduation_year'] ?? ''));

    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    // Validate full name
    if (strlen($fullName) < 2 || strlen($fullName) > 120) {
        $errors[] = 'Name must be between 2 and 120 characters.';
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    }

    // Validate registration role
    if (!in_array($role, $allowed, true)) {
        $errors[] = 'Choose a permitted registration role.';
    }

    // Validate department
    if (strlen($department) > 120) {
        $errors[] = 'Department must be 120 characters or fewer.';
    }

    // Validate graduation year
    if (
        $graduationYear !== ''
        && (
            !ctype_digit($graduationYear)
            || (int) $graduationYear < 1950
            || (int) $graduationYear > (int) date('Y') + 8
        )
    ) {
        $errors[] = 'Enter a valid graduation year.';
    }

    // Validate password
    if (strlen($password) < 10) {
        $errors[] = 'Password must be at least 10 characters.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Create account
    if (!$errors) {
        try {
            $stmt = db()->prepare(
                'SELECT id FROM users WHERE email = ? LIMIT 1'
            );

            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO users
                    (
                        full_name,
                        email,
                        password_hash,
                        role,
                        department,
                        graduation_year,
                        affiliation_verified,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, 0, "active")'
                );

                $stmt->execute([
                    $fullName,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $role,
                    $department !== '' ? $department : null,
                    $graduationYear !== ''
                        ? (int) $graduationYear
                        : null
                ]);

                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) db()->lastInsertId();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                set_flash(
                    'success',
                    'Welcome to AITAM SLACK. Your account has been created.'
                );

                redirect('index.php');
            }
        } catch (PDOException $ex) {
            error_log('Registration: ' . $ex->getMessage());

            $errors[] =
                'Could not create your account. Check the database setup and try again.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<style>
/* =====================================
   REGISTRATION PAGE ENHANCEMENTS
===================================== */

.auth-shell .password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
    min-width: 0;
}

.auth-shell .password-wrapper input {
    width: 100%;
    min-width: 0;
    padding-right: 76px;
    box-sizing: border-box;
}

.auth-shell .password-toggle {
    position: absolute;
    top: 50%;
    right: 10px;
    z-index: 2;
    transform: translateY(-50%);

    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-width: 48px;
    min-height: 34px;
    padding: 6px 8px;

    border: none;
    border-radius: 7px;
    background: transparent;

    color: #4988c4;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;

    cursor: pointer;
    transition:
        color 0.2s ease,
        background-color 0.2s ease;
}

.auth-shell .password-toggle:hover {
    color: #1c4d8d;
    background-color: #edf6ff;
}

.auth-shell .password-toggle:focus-visible {
    outline: 2px solid #4988c4;
    outline-offset: 2px;
}

.auth-shell .password-toggle:active {
    background-color: #dceeff;
}

/* Prevent long registration content from breaking the layout */

.auth-shell,
.auth-shell * {
    box-sizing: border-box;
}

.auth-shell .form-field {
    min-width: 0;
}

.auth-shell .form-field input,
.auth-shell .form-field select {
    max-width: 100%;
}

/* Smooth focus feedback */

.auth-shell .password-wrapper input:focus {
    outline-color: #4988c4;
}

@media (prefers-reduced-motion: reduce) {
    .auth-shell .password-toggle {
        transition: none;
    }
}
</style>

<section class="auth-shell">

    <!-- Left information panel -->

    <div class="auth-aside">

        <span class="eyebrow">JOIN THE COMMUNITY</span>

        <h1>
            Your next answer<br>
            starts <span>here.</span>
        </h1>

        <p>
            Learn from students, alumni, and faculty. Share what you know
            and help make college life easier for everyone.
        </p>

        <div class="auth-benefits">

            <div>
                <span><?= icon_svg('check-circle') ?></span>
                Ask and explore college questions
            </div>

            <div>
                <span><?= icon_svg('check-circle') ?></span>
                Connect with your campus community
            </div>

            <div>
                <span><?= icon_svg('check-circle') ?></span>
                Build a profile around your journey
            </div>

        </div>

        <div class="auth-aside-note">
            A more connected campus begins with you.
        </div>

    </div>

    <!-- Registration form -->

    <div class="auth-card">

        <div class="auth-card-heading">

            <span class="eyebrow">CREATE YOUR ACCOUNT</span>

            <h2>Join AITAM SLACK</h2>

            <p>
                Already a member?
                <a href="login.php">Log in</a>
            </p>

        </div>

        <!-- Validation errors -->

        <?php if ($errors): ?>

            <div class="form-errors" role="alert">

                <strong>Please check the following:</strong>

                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>

            </div>

        <?php endif; ?>

        <form
            method="post"
            action="register.php"
            class="form-stack"
        >

            <?= csrf_field() ?>

            <!-- Full name -->

            <div class="form-field">

                <label for="full_name">
                    Full name <span>*</span>
                </label>

                <input
                    id="full_name"
                    name="full_name"
                    type="text"
                    autocomplete="name"
                    maxlength="120"
                    required
                    value="<?= e($fullName) ?>"
                    placeholder="Enter your full name"
                >

            </div>

            <!-- Email -->

            <div class="form-field">

                <label for="email">
                    Email address <span>*</span>
                </label>

                <input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    maxlength="190"
                    required
                    value="<?= e($email) ?>"
                    placeholder="you@example.com"
                >

            </div>

            <!-- Role -->

            <div class="form-field">

                <label for="role">
                    I am joining as <span>*</span>
                </label>

                <select id="role" name="role" required>

                    <?php
                    $roleOptions = [
                        'student' => 'Student',
                        'alumni' => 'Alumni member',
                        'prospective' => 'Prospective student'
                    ];
                    ?>

                    <?php foreach ($roleOptions as $value => $label): ?>

                        <option
                            value="<?= e($value) ?>"
                            <?= $role === $value ? 'selected' : '' ?>
                        >
                            <?= e($label) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <small>
                    Faculty, higher authority, and administrator accounts
                    require authorized assignment.
                </small>

            </div>

            <!-- Department and graduation year -->

            <div class="form-grid">

                <div class="form-field">

                    <label for="department">Department</label>

                    <input
                        id="department"
                        name="department"
                        type="text"
                        maxlength="120"
                        value="<?= e($department) ?>"
                        placeholder="e.g. CSE"
                    >

                </div>

                <div class="form-field">

                    <label for="graduation_year">
                        Graduation year
                    </label>

                    <input
                        id="graduation_year"
                        name="graduation_year"
                        type="number"
                        min="1950"
                        max="<?= (int) date('Y') + 8 ?>"
                        value="<?= e($graduationYear) ?>"
                        placeholder="e.g. 2028"
                    >

                </div>

            </div>

            <!-- Password with Show/Hide toggle -->

            <div class="form-field">

                <label for="password">
                    Password <span>*</span>
                </label>

                <div class="password-wrapper">

                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        minlength="10"
                        required
                        placeholder="At least 10 characters"
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        data-target="password"
                        aria-label="Show password"
                        aria-pressed="false"
                    >
                        Show
                    </button>

                </div>

            </div>

            <!-- Confirm password with independent toggle -->

            <div class="form-field">

                <label for="confirm_password">
                    Confirm password <span>*</span>
                </label>

                <div class="password-wrapper">

                    <input
                        id="confirm_password"
                        name="confirm_password"
                        type="password"
                        autocomplete="new-password"
                        minlength="10"
                        required
                        placeholder="Enter password again"
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        data-target="confirm_password"
                        aria-label="Show confirm password"
                        aria-pressed="false"
                    >
                        Show
                    </button>

                </div>

            </div>

            <!-- Submit -->

            <button
                class="btn btn-primary btn-wide"
                type="submit"
            >
                Create account <span>→</span>
            </button>

            <p class="form-legal">
                Participate respectfully in the college community.
            </p>

        </form>

    </div>

</section>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const toggleButtons = document.querySelectorAll(
        '.auth-shell .password-toggle'
    );

    toggleButtons.forEach(function (button) {

        button.addEventListener('click', function () {

            const targetId = button.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);

            if (!passwordInput) {
                return;
            }

            const shouldShow = passwordInput.type === 'password';

            // Toggle password visibility
            passwordInput.type = shouldShow ? 'text' : 'password';

            // Update button text
            button.textContent = shouldShow ? 'Hide' : 'Show';

            // Update accessibility attributes
            button.setAttribute(
                'aria-pressed',
                shouldShow ? 'true' : 'false'
            );

            const fieldLabel = targetId === 'password'
                ? 'password'
                : 'confirm password';

            button.setAttribute(
                'aria-label',
                (shouldShow ? 'Hide ' : 'Show ') + fieldLabel
            );

        });

    });

});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
