
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

/* =====================================================
   COLLEGE CONNECT LOGIN PAGE
   ISOLATED UI STYLES
   Backend and authentication functionality unchanged
===================================================== */

/* Full page background */

.auth-shell.login-shell.cc-login-page {
    position: relative !important;
    isolation: isolate !important;

    width: 100vw !important;
    max-width: none !important;

    min-height: calc(100vh - 65px) !important;

    margin-left: calc(50% - 50vw) !important;
    margin-right: 0 !important;

    padding: 55px 9% !important;

    display: grid !important;
    grid-template-columns: minmax(0, 1.15fr) minmax(360px, 0.85fr) !important;

    align-items: center !important;
    justify-content: space-between !important;

    column-gap: 65px !important;

    box-sizing: border-box !important;

    background-image:
        linear-gradient(
            105deg,
            rgba(9, 35, 76, 0.91) 0%,
            rgba(15, 48, 91, 0.82) 55%,
            rgba(12, 36, 69, 0.62) 100%
        ),
        url('assets/images/aitam-campus.jpeg') !important;

    background-position: center center !important;
    background-size: cover !important;
    background-repeat: no-repeat !important;

    overflow: hidden !important;
}

/* Remove inherited decorative backgrounds */

.cc-login-page .auth-aside,
.cc-login-page .auth-card {
    background-image: none;
}

.cc-login-page .auth-aside::before,
.cc-login-page .auth-aside::after,
.cc-login-page .auth-card::before,
.cc-login-page .auth-card::after {
    content: none !important;
    display: none !important;
}

/* =====================================================
   LEFT SECTION
===================================================== */

.cc-login-page .auth-aside {
    position: relative !important;
    z-index: 1 !important;

    width: 100% !important;
    max-width: 540px !important;

    padding: 0 !important;
    margin: 0 !important;

    background: transparent !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;

    color: #ffffff !important;
}

.cc-login-page .auth-aside .eyebrow {
    display: none !important;
}

/* Welcome heading */

.cc-login-page .auth-aside h1 {
    margin: 0 !important;
    padding: 0 !important;

    color: #ffffff !important;

    font-family: inherit !important;
    font-size: clamp(38px, 4vw, 48px) !important;
    font-weight: 800 !important;

    line-height: 1.2 !important;
    letter-spacing: -1.5px !important;

    background: transparent !important;
}

.cc-login-page .auth-aside h1 span {
    color: #ffc928 !important;
    font-style: italic !important;
}

/* Yellow underline */

.cc-login-page .auth-aside h1::after {
    content: "" !important;

    display: block !important;

    width: 215px !important;
    height: 4px !important;

    margin-top: 18px !important;

    background: linear-gradient(
        90deg,
        #ffc928,
        rgba(255, 201, 40, 0)
    ) !important;

    border-radius: 10px !important;
}

/* Welcome description */

.cc-login-page .auth-aside > p {
    max-width: 530px !important;

    margin: 19px 0 27px !important;
    padding: 0 !important;

    color: #ffffff !important;

    font-size: 16px !important;
    font-weight: 400 !important;
    line-height: 1.75 !important;

    opacity: 1 !important;

    background: transparent !important;
}

/* =====================================================
   GLASS BENEFIT CARDS
===================================================== */

.cc-login-page .auth-benefits {
    display: flex !important;
    flex-direction: column !important;

    gap: 13px !important;

    width: 100% !important;
    max-width: 340px !important;

    margin: 0 !important;
    padding: 0 !important;

    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

.cc-login-page .auth-benefits > div {
    display: flex !important;
    align-items: center !important;

    gap: 15px !important;

    width: 100% !important;
    min-height: 66px !important;

    margin: 0 !important;
    padding: 11px 17px !important;

    box-sizing: border-box !important;

    background: rgba(14, 43, 82, 0.78) !important;

    border: 1px solid rgba(255, 255, 255, 0.20) !important;
    border-radius: 11px !important;

    color: #ffffff !important;

    font-size: 14px !important;
    font-weight: 650 !important;
    line-height: 1.4 !important;

    backdrop-filter: blur(7px);
    -webkit-backdrop-filter: blur(7px);

    box-shadow: none !important;

    transition:
        background 0.25s ease,
        transform 0.25s ease !important;
}

/* Benefit icons */

.cc-login-page .auth-benefits > div > span {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    width: 39px !important;
    height: 39px !important;

    min-width: 39px !important;

    border-radius: 50% !important;

    background: #205397 !important;

    color: #ffffff !important;
}

.cc-login-page .auth-benefits svg {
    width: 19px !important;
    height: 19px !important;
}

.cc-login-page .auth-benefits > div:hover {
    background: rgba(32, 83, 151, 0.90) !important;
    transform: translateX(5px);
}

/* =====================================================
   RIGHT LOGIN CARD
===================================================== */

.cc-login-page .auth-card.login-card {
    position: relative !important;
    z-index: 2 !important;

    width: 100% !important;
    max-width: 440px !important;

    justify-self: center !important;

    margin: 0 !important;
    padding: 32px 35px !important;

    box-sizing: border-box !important;

    background: #ffffff !important;

    border: 1px solid rgba(255,255,255,0.85) !important;
    border-radius: 24px !important;

    box-shadow:
        0 18px 55px rgba(0, 0, 0, 0.22),
        0 4px 12px rgba(0, 0, 0, 0.08) !important;

    color: #172b45 !important;
}

/* =====================================================
   CARD HEADER
===================================================== */

.cc-login-page .auth-card-heading {
    position: relative !important;

    padding-left: 64px !important;
    margin: 0 0 25px !important;

    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

.cc-login-page .auth-card-heading .eyebrow {
    display: none !important;
}

/* Lock icon */

.cc-login-page .auth-card-heading::before {
    content: "" !important;

    position: absolute !important;

    top: 0 !important;
    left: 0 !important;

    width: 48px !important;
    height: 48px !important;

    border-radius: 50% !important;

    background-color: #205397 !important;

    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='11' width='18' height='11' rx='2'/%3E%3Cpath d='M7 11V7a5 5 0 0 1 10 0v4'/%3E%3C/svg%3E") !important;

    background-repeat: no-repeat !important;
    background-position: center !important;
    background-size: 23px !important;
}

.cc-login-page .auth-card-heading h2 {
    margin: 0 0 5px !important;
    padding: 0 !important;

    color: #172b45 !important;

    font-size: 21px !important;
    font-weight: 800 !important;
    line-height: 1.35 !important;

    letter-spacing: -0.5px !important;
}

.cc-login-page .auth-card-heading p {
    margin: 0 !important;
    padding: 0 !important;

    color: #718096 !important;

    font-size: 13px !important;
    line-height: 1.5 !important;
}

.cc-login-page .auth-card-heading p a {
    color: #205397 !important;
    font-weight: 700 !important;
    text-decoration: none !important;
}

/* =====================================================
   ERROR MESSAGE
===================================================== */

.cc-login-page .form-errors {
    padding: 12px 14px !important;
    margin: 0 0 17px !important;

    background: #fff0f0 !important;

    border: 1px solid #ffd2d2 !important;
    border-radius: 9px !important;

    color: #bd2929 !important;

    font-size: 13px !important;
    line-height: 1.5 !important;
}

/* =====================================================
   LOGIN FORM
===================================================== */

.cc-login-page .form-stack {
    display: flex !important;
    flex-direction: column !important;

    gap: 17px !important;

    width: 100% !important;

    margin: 0 !important;
    padding: 0 !important;
}

.cc-login-page .form-field {
    display: flex !important;
    flex-direction: column !important;

    gap: 7px !important;

    width: 100% !important;

    margin: 0 !important;
    padding: 0 !important;
}

.cc-login-page .form-field label {
    display: block !important;

    margin: 0 !important;

    color: #233953 !important;

    font-size: 13px !important;
    font-weight: 700 !important;
}

/* Inputs */

.cc-login-page .form-field input {
    display: block !important;

    width: 100% !important;
    min-width: 0 !important;

    height: 43px !important;

    padding: 0 13px !important;

    box-sizing: border-box !important;

    background: #eaf1fc !important;

    border: 1px solid #dbe6f6 !important;
    border-radius: 10px !important;

    color: #172b45 !important;

    font-family: inherit !important;
    font-size: 13px !important;

    outline: none !important;

    box-shadow: none !important;

    transition: all 0.2s ease !important;
}

.cc-login-page .form-field input::placeholder {
    color: #8998ad !important;
}

.cc-login-page .form-field input:focus {
    background: #ffffff !important;

    border-color: #205397 !important;

    box-shadow: 0 0 0 3px rgba(32, 83, 151, 0.12) !important;
}

/* =====================================================
   PASSWORD FIELD
===================================================== */

.cc-login-page .password-wrapper {
    position: relative !important;

    display: flex !important;
    align-items: center !important;

    width: 100% !important;
    min-width: 0 !important;
}

.cc-login-page .password-wrapper input {
    width: 100% !important;

    padding-right: 76px !important;
}

/* Password visibility */

.cc-login-page .password-toggle {
    position: absolute !important;

    top: 50% !important;
    right: 9px !important;

    z-index: 5 !important;

    transform: translateY(-50%) !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    min-width: 48px !important;
    min-height: 34px !important;

    padding: 6px 8px !important;

    background: transparent !important;

    border: none !important;
    border-radius: 7px !important;

    color: #205397 !important;

    font-family: inherit !important;
    font-size: 12px !important;
    font-weight: 700 !important;

    cursor: pointer !important;

    transition: all 0.2s ease !important;
}

.cc-login-page .password-toggle:hover {
    background: #dce8fa !important;
    color: #153f78 !important;
}

.cc-login-page .password-toggle:focus-visible {
    outline: 2px solid #205397 !important;
    outline-offset: 2px !important;
}

/* =====================================================
   LOGIN BUTTON
===================================================== */

.cc-login-page .btn.btn-primary.btn-wide {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    gap: 8px !important;

    width: 100% !important;
    min-height: 46px !important;

    margin: 2px 0 0 !important;
    padding: 12px 18px !important;

    background: #205397 !important;

    border: 1px solid #205397 !important;
    border-radius: 10px !important;

    color: #ffffff !important;

    font-family: inherit !important;
    font-size: 14px !important;
    font-weight: 750 !important;

    cursor: pointer !important;

    box-shadow: 0 3px 7px rgba(32, 83, 151, 0.16) !important;

    transition: all 0.2s ease !important;
}

.cc-login-page .btn.btn-primary.btn-wide:hover {
    background: #153f78 !important;
    border-color: #153f78 !important;

    transform: translateY(-1px) !important;

    box-shadow: 0 5px 12px rgba(32, 83, 151, 0.23) !important;
}

.cc-login-page .btn.btn-primary.btn-wide span {
    color: #ffffff !important;
    font-size: 17px !important;
}

/* =====================================================
   BOTTOM TEXT
===================================================== */

.cc-login-page .form-legal {
    margin: 0 !important;
    padding: 0 !important;

    color: #718096 !important;

    font-size: 13px !important;

    text-align: center !important;
    line-height: 1.5 !important;
}

/* =====================================================
   TABLET
===================================================== */

@media (max-width: 900px) {

    .auth-shell.login-shell.cc-login-page {
        grid-template-columns: minmax(0, 1fr) minmax(320px, 0.95fr) !important;

        gap: 30px !important;

        padding: 45px 5% !important;
    }

    .cc-login-page .auth-aside h1 {
        font-size: 36px !important;
    }

    .cc-login-page .auth-card.login-card {
        padding: 27px 23px !important;
    }

    .cc-login-page .auth-card-heading h2 {
        font-size: 19px !important;
    }
}

/* =====================================================
   MOBILE
===================================================== */

@media (max-width: 650px) {

    .auth-shell.login-shell.cc-login-page {
        min-height: 100vh !important;

        grid-template-columns: minmax(0, 1fr) !important;

        justify-items: center !important;

        gap: 35px !important;

        padding: 40px 18px !important;

        background-position: center center !important;
    }

    .cc-login-page .auth-aside {
        max-width: 440px !important;
    }

    .cc-login-page .auth-aside h1 {
        font-size: 34px !important;
    }

    .cc-login-page .auth-aside > p {
        font-size: 14px !important;
    }

    .cc-login-page .auth-benefits {
        max-width: 100% !important;
    }

    .cc-login-page .auth-benefits > div {
        min-height: 58px !important;
    }

    .cc-login-page .auth-card.login-card {
        width: 100% !important;
        max-width: 440px !important;

        padding: 26px 22px !important;

        border-radius: 19px !important;
    }
}

/* =====================================================
   SMALL MOBILE
===================================================== */

@media (max-width: 380px) {

    .auth-shell.login-shell.cc-login-page {
        padding: 32px 13px !important;
    }

    .cc-login-page .auth-aside h1 {
        font-size: 29px !important;
    }

    .cc-login-page .auth-card.login-card {
        padding: 22px 16px !important;
    }

    .cc-login-page .auth-card-heading {
        padding-left: 53px !important;
    }

    .cc-login-page .auth-card-heading::before {
        width: 43px !important;
        height: 43px !important;
    }

    .cc-login-page .auth-card-heading h2 {
        font-size: 18px !important;
    }
}

/* Reduced motion */

@media (prefers-reduced-motion: reduce) {
    .cc-login-page *,
    .cc-login-page *::before,
    .cc-login-page *::after {
        transition: none !important;
    }
}
.cc-login-page .auth-benefits > div > span:first-child {
    flex: 0 0 39px !important;
    width: 39px !important;
    height: 39px !important;
}

.cc-login-page .auth-benefits > div > span:last-child {
    width: auto !important;
    height: auto !important;
    min-width: 0 !important;
    background: transparent !important;
    border-radius: 0 !important;
    display: block !important;
    color: #ffffff !important;
}

</style>

<!-- =====================================================
     COLLEGE CONNECT LOGIN UI
===================================================== -->

<section class="auth-shell login-shell cc-login-page" style="margin-top: -65px !important;height:110vh;margin-bottom: 1px !important;">

    <!-- LEFT: WELCOME SECTION -->

    <div class="auth-aside">

        <span class="eyebrow">WELCOME BACK</span>

        <h1>
            Welcome <span>Back!</span>
        </h1>

        <p>
            Login to continue your learning journey with AITAM students,
            faculty, and alumni.
        </p>

        <!-- BENEFIT CARDS -->

        <div class="auth-benefits">

            <!-- Campus network -->

            <div>
                <span>
                    <svg viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         stroke-linecap="round"
                         stroke-linejoin="round">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                    </svg>
                </span>

                <span>Access your<br>campus network</span>
            </div>

            <!-- Progress -->

            <div>
                <span>
                    <svg viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         stroke-linecap="round">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                </span>

                <span>Track your progress</span>
            </div>

            <!-- Community -->

            <div>
                <span style="color:#ffc928;">
                    <svg viewBox="0 0 24 24"
                         fill="currentColor">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                </span>

                <span>Be a part of<br>AITAM Community</span>
            </div>

        </div>

    </div>

    <!-- RIGHT: LOGIN CARD -->

    <div class="auth-card login-card">

        <!-- CARD HEADING -->

        <div class="auth-card-heading">

            <span class="eyebrow">MEMBER ACCESS</span>

            <h2>Login to College Connect</h2>

            <p>Enter your credentials to continue</p>

        </div>

        <!-- ERROR MESSAGE -->

        <?php if ($error): ?>

            <div class="form-errors" role="alert">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <!-- LOGIN FORM -->

        <form
            method="post"
            action="login.php"
            class="form-stack"
        >

            <?= csrf_field() ?>

            <!-- EMAIL -->

            <div class="form-field">

                <label for="email">
                    Email Address <span style="color:#dc3545;">*</span>
                </label>

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

            <!-- PASSWORD -->

            <div class="form-field">

                <label for="password">
                    Password <span style="color:#dc3545;">*</span>
                </label>

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

            <!-- LOGIN BUTTON -->

            <button
                class="btn btn-primary btn-wide"
                type="submit"
            >
                Log in <span>→</span>
            </button>

            <!-- SIGNUP TEXT -->

            <p class="form-legal">
                Don't have an account?
                <a href="register.php">Sign up here</a>
            </p>

        </form>

    </div>

</section>

<!-- =====================================================
     PASSWORD VISIBILITY
     ORIGINAL JAVASCRIPT FUNCTIONALITY PRESERVED
===================================================== -->

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
