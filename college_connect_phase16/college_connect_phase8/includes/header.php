<?php
$pageTitle = $pageTitle ?? 'Home';
$navUser = current_user();
$flash = get_flash();
$inAdmin = (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin');
$rootPrefix = $inAdmin ? '../' : '';
$currentPage = $inAdmin ? 'admin/' . basename($_SERVER['SCRIPT_NAME'] ?? '') : basename($_SERVER['SCRIPT_NAME'] ?? '');
$isActive = static function (array $pages) use ($currentPage): string {
    return in_array($currentPage, $pages, true) ? ' class="nav-active" aria-current="page"' : '';
};
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="AITAM SLACK college community">
<title><?=e($pageTitle)?> | AITAM SLACK</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?=e($rootPrefix)?>assets/style.css">
<link rel="stylesheet" href="<?=e($rootPrefix)?>assets/phase3.css">
</head>
<body>
<header class="site-header" style="width: 100%; position: sticky; top: 0; z-index: 1000; background-color:  #fff; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
<div class="nav-wrap">
<a class="brand " href="<?=$rootPrefix?>index.php" aria-label="AITAM SLACK home"><span class="" aria-hidden="true"><img src="<?=$rootPrefix?>assets/aitam.png" alt="AITAM SLACK" width="50" height="50"></span><span >AITAM<span class="brand-light"> SLACK</span></span></a>
<button class="menu-toggle" id="menuToggle" type="button" aria-label="Open navigation" aria-controls="mainNav" aria-expanded="false"><span class="menu-icon" aria-hidden="true"></span></button>
<nav class="main-nav" id="mainNav" aria-label="Main navigation" style="margin-left: 20px;">
<a<?=$isActive(['index.php'])?> href="<?=$rootPrefix?>index.php">Home</a>
<a<?=$isActive(['questions.php', 'question.php'])?> href="<?=$rootPrefix?>questions.php">Questions</a>
<a<?=$isActive(['search_questions.php'])?> href="<?=$rootPrefix?>search_questions.php">Search</a>
<a<?=$isActive(['leaderboard.php'])?> href="<?=$rootPrefix?>leaderboard.php">Leaderboard</a>
<a<?=$isActive(['achievements.php'])?> href="<?=$rootPrefix?>achievements.php">Achievements</a>
<a<?=$isActive(['my_activity.php'])?> href="<?=$rootPrefix?>my_activity.php">My activity</a>
<a href="<?=$rootPrefix?>index.php#categories">Topics</a>
<?php if($navUser): ?><a<?=$isActive(['notifications.php'])?> href="<?=$rootPrefix?>notifications.php">Notifications</a><?php if($navUser['role']==='admin'): ?><a<?=$inAdmin?' class="nav-active" aria-current="page"':''?> href="<?=$inAdmin?'index.php':'admin/index.php'?>">Admin</a><?php endif; ?><?php endif; ?>
<a href="<?=$rootPrefix?>index.php#campus-updates">Campus updates</a>
<div class="nav-actions">
<?php if($navUser): ?>
<details class="nav-account"><summary><span class="nav-avatar" aria-hidden="true"><?=e(strtoupper(mb_substr($navUser['full_name'], 0, 1)))?></span><span class="nav-account-name"><?=e($navUser['full_name'])?></span><span class="nav-account-caret" aria-hidden="true">⌄</span></summary>
<div class="account-dropdown"><a href="<?=$rootPrefix?>profile.php">My profile</a><a href="<?=$rootPrefix?>bookmarks.php">Saved questions</a><a href="<?=$rootPrefix?>my_activity.php">My activity</a><form method="post" action="<?=$rootPrefix?>logout.php" class="nav-logout"><?=csrf_field()?><button type="submit">Log out</button></form></div></details>
<?php else: ?>
<a class="nav-login" href="<?=$rootPrefix?>login.php">Log in</a><a class="btn btn-small btn-primary" href="<?=$rootPrefix?>register.php" style="color: white;">Join community <span aria-hidden="true">↗</span></a>
<?php endif; ?>
</div>
</nav></div></header>
<main>
<?php if($flash): ?><div class="flash-wrap"><div class="flash flash-<?=e($flash['type'])?>" role="status"><?=e($flash['message'])?></div></div><?php endif; ?>
