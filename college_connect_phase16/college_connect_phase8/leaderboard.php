<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Community leaderboard';
$period = (string)($_GET['period'] ?? 'all');
if (!in_array($period, ['all','month'], true)) $period = 'all';
$dateFilter = $period === 'month' ? ' AND a.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)' : '';
$sql = "SELECT u.id,u.full_name,u.role,COUNT(DISTINCT a.id) AS answer_count,
        COALESCE(SUM(CASE WHEN v.vote=1 THEN 1 ELSE 0 END),0) AS helpful_votes,
        COALESCE(SUM(CASE WHEN a.is_accepted=1 THEN 1 ELSE 0 END),0) AS accepted_answers
        FROM users u LEFT JOIN answers a ON a.user_id=u.id AND a.status='published' $dateFilter
        LEFT JOIN answer_votes v ON v.answer_id=a.id
        WHERE u.status='active' AND u.role <> 'admin'
        GROUP BY u.id,u.full_name,u.role
        HAVING answer_count > 0 OR helpful_votes > 0
        ORDER BY helpful_votes DESC, accepted_answers DESC, answer_count DESC, u.full_name ASC LIMIT 50";
try { $members = db()->query($sql)->fetchAll(); }
catch (Throwable $ex) { $members = []; $loadError = true; error_log('Leaderboard query failed: '.$ex->getMessage()); }
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><span class="eyebrow">COMMUNITY CONTRIBUTIONS</span><h1>Community leaderboard</h1><p>Recognize members who share useful answers and help others learn.</p></section>
<section class="section-block">
<div class="section-heading compact"><div><h2>Helpful contributors</h2><p>Rankings are based on positive answer votes and accepted answers.</p></div>
<form method="get" class="search-form" style="max-width:240px"><label for="period" class="sr-only">Time period</label><select id="period" name="period" onchange="this.form.submit()"><option value="all" <?=$period==='all'?'selected':''?>>All time</option><option value="month" <?=$period==='month'?'selected':''?>>Last 30 days</option></select><button class="btn btn-primary" type="submit">Apply</button></form></div>
<?php if (!empty($loadError)): ?><div class="empty-state"><h3>Leaderboard is temporarily unavailable</h3><p>Confirm that the Phase 9 answer-voting migration has been imported.</p></div>
<?php elseif (!$members): ?><div class="empty-state"><h3>No contributions yet</h3><p>Published answers and community votes will appear here.</p><a class="btn btn-primary" href="questions.php">Explore questions</a></div>
<?php else: ?><div class="question-list"><?php foreach($members as $i=>$m): ?><article class="question-row"><div class="question-votes"><strong>#<?=($i+1)?></strong><span>rank</span></div><div class="question-main"><h3 class="question-title"><?=e($m['full_name'])?></h3><p><?=e(ucfirst($m['role']))?> · <?= (int)$m['answer_count'] ?> published answers · <?= (int)$m['accepted_answers'] ?> accepted</p></div><div class="topic-pill"><?= icon_svg('thumbs-up') ?> <?= (int)$m['helpful_votes'] ?> helpful</div></article><?php endforeach; ?></div><?php endif; ?>
<p class="muted" style="margin-top:1rem">This is a lightweight community score, not a measure of a member's overall ability. Votes can change over time.</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
