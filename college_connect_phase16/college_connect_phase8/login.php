
<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('profile.php');
}

$pageTitle = 'Log in';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter your email address and password.';
    } else {
        
        try {
            $stmt = db()->prepare(
                'SELECT id, password_hash, status
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );

            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (
                !$user
                || !password_verify($password, $user['password_hash'])
                || $user['status'] !== 'active'
            ) {
                $error = 'The email or password is incorrect, or the account is unavailable.';
            } else {
                // Upgrade the password hash when necessary.
                if (
                    password_needs_rehash(
                        $user['password_hash'],
                        PASSWORD_DEFAULT
                    )
                ) {
                    $stmt = db()->prepare(
                        'UPDATE users SET password_hash = ? WHERE id = ?'
                    );

                    $stmt->execute([
                        password_hash($password, PASSWORD_DEFAULT),
                        (int) $user['id']
                    ]);
                }

                // Regenerate the session after successful authentication.
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                set_flash('success', 'You are now logged in.');

                redirect('index.php');
            }
        } catch (PDOException $ex) {
            error_log('Login: ' . $ex->getMessage());
            $error = 'Login is unavailable. Check your database setup.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<style>
/* Password visibility toggle */

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
    transition: color 0.2s ease, background-color 0.2s ease;
}

.auth-shell .password-toggle:hover {
    color: #1c4d8d;
    background: #edf6ff;
}

.auth-shell .password-toggle:focus-visible {
    outline: 2px solid #4988c4;
    outline-offset: 2px;
}

.auth-shell .password-wrapper input:focus {
    outline-color: #4988c4;
}

@media (prefers-reduced-motion: reduce) {
    .auth-shell .password-toggle {
        transition: none;
    }
}
</style>

<section class="auth-shell login-shell">

    <!-- Welcome panel -->

    <div class="auth-aside">

        <span class="eyebrow">WELCOME BACK</span>

        <h1>
            Your community.<br>
            Your <span>connection.</span>
        </h1>

        <p>
            Return to the conversations and knowledge that help your
            college community grow.
        </p>

        <div class="auth-benefits">

            <div>
                <span><?= icon_svg('check-circle') ?></span>
                Keep your profile up to date
            </div>

            <div>
                <span><?= icon_svg('check-circle') ?></span>
                Discover useful campus knowledge
            </div>

            <div>
                <span><?= icon_svg('check-circle') ?></span>
                Share your experience
            </div>

        </div>

    </div>

    <!-- Login card -->

    <div class="auth-card login-card">

        <div class="auth-card-heading">

            <span class="eyebrow">MEMBER ACCESS</span>

            <h2>Log in to your account</h2>

            <p>
                New to AITAM SLACK?
                <a href="register.php">Create an account</a>
            </p>

        </div>

        <?php if ($error): ?>

            <div class="form-errors" role="alert">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form
            method="post"
            action="login.php"
            class="form-stack"
        >

            <?= csrf_field() ?>

            <!-- Email -->

            <div class="form-field">

                <label for="email">Email address</label>

                <input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="username"
                    required
                    maxlength="190"
                    value="<?= e($email) ?>"
                    placeholder="you@example.com"
                >

            </div>

            <!-- Password with Show/Hide toggle -->

            <div class="form-field">

                <label for="password">Password</label>

                <div class="password-wrapper">

                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        placeholder="Enter your password"
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        id="password-toggle"
                        aria-label="Show password"
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
                Log in <span>→</span>
            </button>

            <p class="form-legal">
                Only active accounts can log in.
            </p>

        </form>

    </div>

</section>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('password-toggle');

    if (!passwordInput || !toggleButton) {
        return;
    }

    toggleButton.addEventListener('click', function () {

        const shouldShow = passwordInput.type === 'password';

        // Toggle password visibility.
        passwordInput.type = shouldShow ? 'text' : 'password';

        // Update button label.
        toggleButton.textContent = shouldShow ? 'Hide' : 'Show';

        // Update accessibility state.
        toggleButton.setAttribute(
            'aria-pressed',
            shouldShow ? 'true' : 'false'
        );

        toggleButton.setAttribute(
            'aria-label',
            shouldShow ? 'Hide password' : 'Show password'
        );

    });

});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
