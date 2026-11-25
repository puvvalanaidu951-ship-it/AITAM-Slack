
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

// Profile display data (UI only).
$displayName = '';
$navInitials = '';
$navRole = '';

if ($navUser) {
    $displayName = mb_convert_case(
        mb_strtolower(trim((string) $navUser['full_name'])),
        MB_CASE_TITLE,
        'UTF-8'
    );

   $nameParts = preg_split('/\s+/', $displayName);

    foreach (array_slice($nameParts, 0, 2) as $part) {
        if ($part !== '') {
            $navInitials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
    }

    $navInitials = $navInitials ?: 'U';

    $navRole = ucfirst((string) $navUser['role']);
}

?>

<!doctype html>
<html lang="en" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AITAM SLACK college community">

    <title><?= e($pageTitle) ?> | AITAM SLACK</title>

    <link
        rel="icon"
        type="image/png"
        href="<?= e($rootPrefix) ?>assets/aitam.png"
    >

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

            <!-- Brand -->

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

            <!-- Mobile Menu Button -->

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

            <!-- Main Navigation -->

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

                <!-- <a
                    <?= $isActive(['my_activity.php']) ?>
                    href="<?= $rootPrefix ?>my_activity.php"
                >
                    My activity
                </a> -->

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

                <!-- Account Actions -->

                <div class="nav-actions">

                    <?php if ($navUser): ?>

                        <!-- Updated Profile Pill -->

                        <details class="nav-account cc-profile-menu">

                            <summary class="cc-profile-trigger">

                                <span
                                    class="cc-profile-avatar"
                                    aria-hidden="true"
                                >
                                    <?= e($navInitials) ?>
                                </span>

                                <span class="cc-profile-info">

                                    <span class="cc-profile-name">
                                        <?= e($displayName) ?>
                                    </span>

                                    <span class="cc-profile-role">
                                        <?= e($navRole) ?>
                                    </span>

                                </span>

                                <span
                                    class="cc-profile-arrow"
                                    aria-hidden="true"
                                >
                                    <svg viewBox="0 0 24 24">
                                        <path d="m7 10 5 5 5-5"/>
                                    </svg>
                                </span>

                            </summary>

                            <!-- Existing Dropdown -->

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

            <!-- Theme Toggle -->

            <button
                class="theme-toggle"
                id="themeToggle"
                type="button"
                aria-label="Switch to dark mode"
                aria-pressed="false"
                title="Switch to dark mode"
            >

                <svg
                    class="theme-toggle-icon"
                    data-theme-icon="light"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                >
                    <circle cx="12" cy="12" r="4"></circle>
                    <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path>
                </svg>

                <svg
                    class="theme-toggle-icon"
                    data-theme-icon="dark"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                    hidden
                >
                    <path d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8 8.5 8.5 0 1 0 20.2 15.1Z"></path>
                </svg>

            </button>

        </div>

<style>
/* College Connect profile navbar - direct styling */

.site-header .nav-account.cc-profile-menu {
    position: relative !important;
    display: inline-block !important;
    flex-shrink: 0 !important;
    margin-left: 12px !important;
}

.site-header .cc-profile-menu > summary.cc-profile-trigger {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 11px !important;

    width: 218px !important;
    min-height: 58px !important;
    padding: 6px 12px 6px 7px !important;

    background: #eaf2f9 !important;
    border: 1px solid #dce6ef !important;
    border-radius: 50px !important;

    box-sizing: border-box !important;
    cursor: pointer !important;
    list-style: none !important;
}

.site-header .cc-profile-trigger::-webkit-details-marker {
    display: none !important;
}

.site-header .cc-profile-trigger::marker {
    content: "" !important;
}

/* Circular initials */

.site-header .cc-profile-avatar {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    width: 44px !important;
    height: 44px !important;
    min-width: 44px !important;

    border-radius: 50% !important;
    background: linear-gradient(135deg, #173b69, #3479bb) !important;

    color: #fff !important;
    font-size: 15px !important;
    font-weight: 700 !important;
    line-height: 1 !important;
}

/* Username and role */

.site-header .cc-profile-info {
    display: flex !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    justify-content: center !important;
    flex: 1 !important;
    min-width: 0 !important;
    gap: 2px !important;
}

.site-header .cc-profile-name {
    display: block !important;
    color: #26394f !important;
    font-size: 14px !important;
    font-weight: 700 !important;
    line-height: 18px !important;
    white-space: nowrap !important;
}

.site-header .cc-profile-role {
    display: block !important;
    color: #687b90 !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    line-height: 16px !important;
}

/* Dropdown arrow */

.site-header .cc-profile-arrow {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 16px !important;
    height: 16px !important;
    color: #718196 !important;
    flex-shrink: 0 !important;
}

.site-header .cc-profile-arrow svg {
    display: block !important;
    width: 16px !important;
    height: 16px !important;
    fill: none !important;
    stroke: currentColor !important;
    stroke-width: 2 !important;
    transition: transform .2s ease;
}

.site-header .cc-profile-menu[open] .cc-profile-arrow svg {
    transform: rotate(180deg);
}

/* Hover */

.site-header .cc-profile-trigger:hover {
    background: #e1edf8 !important;
}

/* Keep dropdown positioned correctly */

.site-header .cc-profile-menu .account-dropdown {
    position: absolute !important;
    top: calc(100% + 8px) !important;
    right: 0 !important;
    z-index: 9999 !important;
}

@media (max-width: 768px) {
    .site-header .cc-profile-menu > summary.cc-profile-trigger {
        width: 205px !important;
    }
}
</style>



    </header>

    <main>

        <!-- Flash Messages -->

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
