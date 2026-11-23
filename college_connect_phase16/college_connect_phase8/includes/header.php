
<?php

$pageTitle = $pageTitle ?? 'Home';
$navUser = current_user();
$flash = get_flash();

$inAdmin = (
    basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin'
);

$rootPrefix = $inAdmin ? '../' : '';

$currentPage = $inAdmin
    ? 'admin/' . basename($_SERVER['SCRIPT_NAME'] ?? '')
    : basename($_SERVER['SCRIPT_NAME'] ?? '');

$isActive = static function (array $pages) use ($currentPage): string {
    return in_array($currentPage, $pages, true)
        ? ' class="nav-active" aria-current="page"'
        : '';
};

?>
<!doctype html>
<html lang="en" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AITAM SLACK college community">

    <title><?= e($pageTitle) ?> | AITAM SLACK</title>
    <link rel="icon" type="image/png" href="<?= e($rootPrefix) ?>assets/aitam.png">

    <script>
        (() => {
            try {
                const theme = localStorage.getItem('aitam-theme');
                if (theme === 'dark' || theme === 'light') {
                    document.documentElement.setAttribute('data-theme', theme);
                }
            } catch (error) {
                console.warn('Theme preference could not be restored.', error);
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="<?= e($rootPrefix) ?>assets/style.css"
    >

    <link
        rel="stylesheet"
        href="<?= e($rootPrefix) ?>assets/phase3.css"
    >

    <link
        rel="stylesheet"
        href="<?= e($rootPrefix) ?>assets/pagination.css"
    >

    <?php if (in_array(
        $currentPage,
        ['college_updates.php', 'college_update.php', 'admin/college_updates.php'],
        true
    )): ?>
        <link
            rel="stylesheet"
            href="<?= e($rootPrefix) ?>assets/college-updates.css"
        >
    <?php endif; ?>
</head>

<body>

    <header class="site-header">
        <div class="nav-wrap">

            <a
                class="brand"
                href="<?= $rootPrefix ?>index.php"
                aria-label="AITAM SLACK home"
            >
                <span aria-hidden="true">
                    <img
                        src="<?= $rootPrefix ?>assets/aitam.png"
                        alt="AITAM SLACK"
                        width="50"
                        height="50"
                    >
                </span>

                <span>
                    AITAM<span class="brand-light"> SLACK</span>
                </span>
            </a>

            <button
                class="menu-toggle"
                id="menuToggle"
                type="button"
                aria-label="Open navigation"
                aria-controls="mainNav"
                aria-expanded="false"
            >
                <span class="menu-icon" aria-hidden="true"></span>
            </button>

            <nav
                class="main-nav"
                id="mainNav"
                aria-label="Main navigation"
                style="margin-left: 60px;"
            >
                <a
                    <?= $isActive(['index.php']) ?>
                    href="<?= $rootPrefix ?>index.php"
                >
                    Home
                </a>

                <a
                    <?= $isActive(['questions.php', 'question.php']) ?>
                    href="<?= $rootPrefix ?>questions.php"
                >
                    Questions
                </a>

                <a
                    <?= $isActive(['search_questions.php']) ?>
                    href="<?= $rootPrefix ?>search_questions.php"
                >
                    Search
                </a>

                <a
                    <?= $isActive(['college_updates.php', 'college_update.php']) ?>
                    href="<?= $rootPrefix ?>college_updates.php"
                >
                    College updates
                </a>

                <a
                    <?= $isActive(['leaderboard.php']) ?>
                    href="<?= $rootPrefix ?>leaderboard.php"
                >
                    Leaderboard
                </a>

                <a
                    <?= $isActive(['achievements.php']) ?>
                    href="<?= $rootPrefix ?>achievements.php"
                >
                    Achievements
                </a>

                <a
                    <?= $isActive(['my_activity.php']) ?>
                    href="<?= $rootPrefix ?>my_activity.php"
                >
                    My activity
                </a>

               <a
                    <?= $isActive(['report.php']) ?>
                    href="<?= e($rootPrefix) ?>report.php"
                >
                    Report
                </a>

                <?php if ($navUser): ?>

                    <a
                        <?= $isActive(['notifications.php']) ?>
                        href="<?= $rootPrefix ?>notifications.php"
                    >
                        Notifications
                    </a>

                    <?php if ($navUser['role'] === 'admin'): ?>

                        <a
                            <?= $inAdmin
                                ? ' class="nav-active" aria-current="page"'
                                : '' ?>
                            href="<?= $inAdmin
                                ? 'index.php'
                                : 'admin/index.php' ?>"
                        >
                            Admin
                        </a>

                    <?php endif; ?>

                <?php endif; ?>

                <!-- <a href="<?= $rootPrefix ?>index.php#campus-updates">
                    Campus updates
                </a> -->

                <div class="nav-actions">
                    <?php if ($navUser): ?>

                        <details class="nav-account">

                            <summary>
                                <span
                                    class="nav-avatar"
                                    aria-hidden="true"
                                >
                                    <?= e(
                                        strtoupper(
                                            mb_substr(
                                                $navUser['full_name'],
                                                0,
                                                1
                                            )
                                        )
                                    ) ?>
                                </span>

                                <span class="nav-account-name">
                                    <?= e($navUser['full_name']) ?>
                                </span>

                                <span
                                    class="nav-account-caret"
                                    aria-hidden="true"
                                >
                                    ⌄
                                </span>
                            </summary>

                            <div class="account-dropdown">

                                <a href="<?= $rootPrefix ?>profile.php">
                                    My profile
                                </a>

                                <a href="<?= $rootPrefix ?>bookmarks.php">
                                    Saved questions
                                </a>

                                <a href="<?= $rootPrefix ?>my_activity.php">
                                    My activity
                                </a>

                                <form
                                    method="post"
                                    action="<?= $rootPrefix ?>logout.php"
                                    class="nav-logout"
                                >
                                    <?= csrf_field() ?>

                                    <button type="submit">
                                        Log out
                                    </button>
                                </form>

                            </div>
                        </details>

                    <?php else: ?>

                        <a
                            class="nav-login"
                            href="<?= $rootPrefix ?>login.php"
                        >
                            Log in
                        </a>

                        <a
                            class="btn btn-small btn-primary"
                            href="<?= $rootPrefix ?>register.php"
                        >
                            Join community
                            <span aria-hidden="true">↗</span>
                        </a>

                    <?php endif; ?>

                </div>
            </nav>

            <button
                class="theme-toggle"
                id="themeToggle"
                type="button"
                aria-label="Switch to dark mode"
                aria-pressed="false"
                title="Switch to dark mode"
            >
                <svg class="theme-toggle-icon" data-theme-icon="light" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="12" r="4"></circle>
                    <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path>
                </svg>
                <svg class="theme-toggle-icon" data-theme-icon="dark" viewBox="0 0 24 24" aria-hidden="true" focusable="false" hidden>
                    <path d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8 8.5 8.5 0 1 0 20.2 15.1Z"></path>
                </svg>
            </button>

        </div>
    </header>

    <main>

        <?php if ($flash): ?>

            <div class="flash-wrap">

                <div
                    class="flash flash-<?= e($flash['type']) ?>"
                    role="status"
                >
                    <?= e($flash['message']) ?>
                </div>

            </div>

        <?php endif; ?>
