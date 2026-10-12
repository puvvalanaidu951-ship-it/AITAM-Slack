<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Home';
$categories = get_categories();
$questions = get_recent_questions(5);
$announcements = get_announcements(3);
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="hero-copy">
    <span class="eyebrow"><span class="eyebrow-dot"></span> YOUR COLLEGE COMMUNITY</span>
    <h1>One campus.<br><span>Every answer.</span></h1>
    <p>Connect with students, alumni, faculty, and college leaders. Ask questions, share experiences, and find the information you need.</p>
    <div class="hero-actions">
      <a class="btn btn-primary" href="questions.php">Explore questions <span aria-hidden="true">↗</span></a>
      <a class="btn btn-ghost" href="#categories">Browse topics</a>
    </div>
    <div class="hero-proof"><div class="avatar-stack"><span>ST</span><span>AL</span><span>FC</span><span>+</span></div><span>A community built around helping each other</span></div>
  </div>
  <div class="hero-art" aria-label="Community connection illustration">
    <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
    <div class="orbit-dot dot-one"></div><div class="orbit-dot dot-two"></div><div class="orbit-dot dot-three"></div>
    <div class="center-logo" style="animation:float 3s ease-in-out infinite;"><img src="./assets/25YearsLogoAITAM.png" alt="25 Year Anniversary Logo" width="150" height="150"></div>
    <div class="float-card float-card-top"><span class="mini-icon mint"><?= icon_svg('check-circle') ?></span><div><strong>Helpful answer</strong><small>Community verified</small></div></div>
    <div class="float-card float-card-bottom"><span class="mini-icon violet"><?= icon_svg('sparkles') ?></span><div><strong>Find your people</strong><small>Learn from every journey</small></div></div>
    <div class="person-bubble person-one">ST</div><div class="person-bubble person-two">AL</div><div class="person-bubble person-three">FC</div>
  </div>
</section>
<section class="stats-row">
  <div><strong>One community</strong><span>Students, alumni & faculty</span></div>
  <div><strong>Useful answers</strong><span>Knowledge that stays searchable</span></div>
  <div><strong>Safer discussions</strong><span>Community-first moderation</span></div>
</section>
<section class="section-block" id="categories">
  <div class="section-heading"><div><span class="eyebrow">EXPLORE THE COMMUNITY</span><h2>What can we help you with?</h2><p>Start with a topic, then discover answers from people who know.</p></div><a class="text-link" href="questions.php">View all questions <span>→</span></a></div>
  <div class="category-grid">
    <?php foreach ($categories as $category): ?>
      <a class="category-card" href="questions.php?category=<?= (int)$category['id'] ?>">
        <span class="category-icon <?= e($category['color']) ?>"><?= icon_svg((string) $category['slug']) ?></span>
        <span class="category-title"><?= e($category['name']) ?></span>
        <span class="category-description"><?= e($category['description']) ?></span>
        <span class="category-arrow">↗</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<section class="content-columns">
  <div class="section-block recent-block">
    <div class="section-heading compact"><div><span class="eyebrow">COMMUNITY KNOWLEDGE</span><h2>Recent questions</h2></div><a class="text-link" href="questions.php">Explore all →</a></div>
    <?php if (!$questions): ?><div class="empty-state">No questions have been posted yet. Check back soon.</div><?php else: ?>
      <div class="question-list">
        <?php foreach ($questions as $q): ?>
          <article class="question-row">
            <div class="question-votes"><strong><?= (int)$q['answer_count'] ?></strong><span>answers</span></div>
            <div class="question-main"><a href="questions.php#question-<?= (int)$q['id'] ?>" class="question-title"><?= e($q['title']) ?></a><p><?= e(shorten($q['body'], 125)) ?></p><div class="question-meta"><span class="topic-pill"><?= e($q['category_name']) ?></span><span><?= e($q['author_name']) ?></span><span><?= e(time_ago($q['created_at'])) ?></span></div></div>
            <span class="row-arrow">↗</span>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <aside class="announcements-panel" id="campus-updates">
    <div class="section-heading compact"><div><span class="eyebrow">STAY INFORMED</span><h2>Campus updates</h2></div><span class="live-dot"></span></div>
    <?php foreach ($announcements as $a): ?>
      <article class="announcement"><span class="announcement-label"><?= e($a['label']) ?></span><h3><?= e($a['title']) ?></h3><p><?= e($a['body']) ?></p><time><?= e(date('M j, Y', strtotime($a['created_at']))) ?></time></article>
    <?php endforeach; ?>
  </aside>
</section>
<section class="cta-banner"><div><span class="eyebrow">YOUR EXPERIENCE MATTERS</span><h2 style="color: #6aaffa;">Have a question? Start a conversation.</h2><p>Help make college life easier by sharing what you know.</p></div><a href="questions.php" class="btn btn-light">Explore the community <span>→</span></a></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
