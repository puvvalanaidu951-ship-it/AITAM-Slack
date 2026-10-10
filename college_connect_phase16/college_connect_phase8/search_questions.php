<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
$pageTitle = 'Search questions';
$q = trim((string)($_GET['q'] ?? ''));
$categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0;
$sort = (string)($_GET['sort'] ?? 'newest');
$sortSql = match ($sort) { 'oldest' => 'q.created_at ASC', 'title' => 'q.title ASC', default => 'q.created_at DESC' };
$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$where = ["q.status = 'published'", "q.moderation_status = 'allowed'"];
$params = [];
if ($q !== '') { $where[] = '(q.title LIKE :term OR q.body LIKE :term)'; $params[':term'] = '%'.$q.'%'; }
if ($categoryId > 0) { $where[] = 'q.category_id = :category'; $params[':category'] = $categoryId; }
$sql = 'SELECT q.id, q.title, q.body, q.created_at, c.name AS category_name, u.full_name, (SELECT COUNT(*) FROM answers a WHERE a.question_id=q.id AND a.status="published") AS answer_count FROM questions q JOIN categories c ON c.id=q.category_id LEFT JOIN users u ON u.id=q.user_id WHERE '.implode(' AND ', $where).' ORDER BY '.$sortSql.' LIMIT 100';
$stmt = db()->prepare($sql); $stmt->execute($params); $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
require __DIR__.'/includes/header.php';
?>
<section class="section"><div class="container">
<div class="section-heading"><div><span class="eyebrow">DISCOVER KNOWLEDGE</span><h1>Search questions</h1><p>Find answers and discussions shared by your college community.</p></div></div>
<form method="get" class="card" style="padding:1.25rem;margin:1.5rem 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;align-items:end">
<label>Keywords<input type="search" name="q" value="<?=e($q)?>" placeholder="Search titles and details…" maxlength="200"></label>
<label>Category<select name="category"><option value="0">All categories</option><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['id']?>" <?= $categoryId===(int)$cat['id']?'selected':''?>><?=e($cat['name'])?></option><?php endforeach; ?></select></label>
<label>Sort by<select name="sort"><option value="newest" <?=$sort==='newest'?'selected':''?>>Newest first</option><option value="oldest" <?=$sort==='oldest'?'selected':''?>>Oldest first</option><option value="title" <?=$sort==='title'?'selected':''?>>Title A–Z</option></select></label>
<button class="btn btn-primary" type="submit">Search</button>
</form>
<p class="muted"><?=count($results)?> result<?=count($results)===1?'':'s'?><?=count($results)===100?' (showing up to 100)':''?></p>
<?php if (!$results): ?><div class="card" style="padding:2rem"><h2>No questions found</h2><p>Try different keywords or choose another category.</p><a class="btn btn-secondary" href="search_questions.php">Clear filters</a></div><?php else: ?><div style="display:grid;gap:1rem">
<?php foreach($results as $item): ?><article class="card" style="padding:1.25rem"><div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:.5rem"><span class="badge"><?=e($item['category_name'])?></span><span class="muted"><?= (int)$item['answer_count']?> answers</span></div><h2 style="margin:.25rem 0"><a href="question.php?id=<?=(int)$item['id']?>"><?=e($item['title'])?></a></h2><p><?=e(mb_strimwidth(strip_tags((string)$item['body']),0,240,'…'))?></p><small class="muted">By <?=e($item['full_name'] ?: 'Community member')?> · <?=e(date('M j, Y', strtotime($item['created_at'])))?></small><div style="display:flex;gap:.65rem;flex-wrap:wrap;margin-top:1rem"><a class="btn btn-primary btn-small" href="question.php?id=<?=(int)$item['id']?>#answer-form">Answer this question ↗</a><a class="btn btn-secondary btn-small" href="question.php?id=<?=(int)$item['id']?>">View discussion</a></div></article><?php endforeach; ?></div><?php endif; ?>
</div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
