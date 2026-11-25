
<?php

$currentAdminPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

$adminMenu = [
    [
        'title' => 'Dashboard',
        'icon'  => '▦',
        'link'  => 'index.php'
    ],
    [
        'title' => 'Review content',
        'icon'  => '✓',
        'link'  => 'moderation.php'
    ],
    [
        'title' => 'Manage reports',
        'icon'  => '⚑',
        'link'  => 'reports.php'
    ],
    [
        'title' => 'Manage members',
        'icon'  => '♙',
        'link'  => 'users.php'
    ],

    [
        'title' => 'AI question insights',
        'icon'  => '✧',
        'link'  => 'ai_review.php'
    ],
    [
        'title' => 'AI answer review',
        'icon'  => '▣',
        'link'  => 'answer_review.php'
    ],
    [
        'title' => 'Admin audit log',
        'icon'  => '☷',
        'link'  => 'audit_logs.php'
    ]
];

?>

<aside class="admin-sidebar" aria-label="Admin sidebar">

    <!-- Sidebar heading -->
    <div class="admin-sidebar-heading">

        <span class="admin-sidebar-logo">
            AS
        </span>

        <div class="admin-sidebar-brand">
            <strong>ADMIN PANEL</strong>
            <small>AITAM SLACK</small>
        </div>

    </div>

    <!-- Navigation label -->
    <div class="admin-sidebar-label">
        MAIN MENU
    </div>

    <!-- Navigation links -->
    <nav class="admin-sidebar-nav" aria-label="Admin navigation">

        <?php foreach ($adminMenu as $item): ?>

            <?php
                $isActive = ($currentAdminPage === $item['link']);
            ?>

            <a
                href="<?= e($item['link']) ?>"
                class="admin-sidebar-link <?= $isActive ? 'active' : '' ?>"
                <?= $isActive ? 'aria-current="page"' : '' ?>
            >

                <span class="admin-sidebar-icon" aria-hidden="true">
                    <?= e($item['icon']) ?>
                </span>

                <span class="admin-sidebar-text">
                    <?= e($item['title']) ?>
                </span>

            </a>

        <?php endforeach; ?>

    </nav>

    <!-- Sidebar footer -->
    <div class="admin-sidebar-footer">

        <span class="admin-sidebar-status"></span>

        <span>Administrator Access</span>

    </div>

</aside>
