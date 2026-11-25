<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/college_updates.php';

$pageTitle = 'College Updates';
$categories = college_update_categories();
$search = trim(college_update_input_string($_GET, 'q'));
$search = function_exists('mb_substr')
    ? mb_substr($search, 0, 100)
    : substr($search, 0, 100);
$selectedCategory = college_update_input_string($_GET, 'category');

$where = [
    'u.status = "published"',
    'COALESCE(u.published_at, u.created_at) <= NOW()',
];
$params = [];

if ($search !== '') {
    $where[] = 'u.title LIKE :update_title';
    $params[':update_title'] = '%' . $search . '%';
}

if (in_array($selectedCategory, $categories, true)) {
    $where[] = 'u.category = :update_category';
    $params[':update_category'] = $selectedCategory;
} else {
    $selectedCategory = '';
}

$whereSql = implode(' AND ', $where);
$fetchError = false;
$updates = [];
$pagination = null;

try {
    $updatesResult = getPaginatedRecords(
        'SELECT COUNT(*) FROM college_updates u WHERE ' . $whereSql,
        'SELECT
            u.id,
            u.title,
            u.description,
            u.category,
            u.created_at,
            COALESCE(u.published_at, u.created_at) AS publication_date,
            admin.full_name AS publisher_name,
            (
                SELECT media.file_path
                FROM college_update_media media
                WHERE media.update_id = u.id
                ORDER BY media.sort_order, media.id
                LIMIT 1
            ) AS first_media_path
         FROM college_updates u
         LEFT JOIN users admin ON admin.id = u.admin_id
         WHERE ' . $whereSql . '
         ORDER BY COALESCE(u.published_at, u.created_at) DESC, u.id DESC
         LIMIT :pagination_limit OFFSET :pagination_offset',
        $params,
        10
    );
    $updates = $updatesResult['records'];
    $pagination = $updatesResult['pagination'];
} catch (Throwable $exception) {
    error_log('College updates feed could not be loaded: ' . $exception->getMessage());
    http_response_code(500);
    $fetchError = true;
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <span class="eyebrow">WHAT'S HAPPENING ON CAMPUS</span>
    <h1>College Updates</h1>
    <p>Find official college notices, academic updates, examination information, events, and placement news.</p>
</section>

<section class="section-block">
    <form class="updates-toolbar" method="get" action="college_updates.php" data-update-filters>
        <label for="update-search">
            Search by title
            <input
                id="update-search"
                name="q"
                type="search"
                maxlength="100"
                value="<?= e($search) ?>"
                placeholder="Search college updates"
            >
        </label>

        <label for="update-category">
            Category
            <select id="update-category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= e($category) ?>"
                        <?= $category === $selectedCategory ? 'selected' : '' ?>
                    ><?= e($category) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <button class="btn btn-primary" type="submit">Find updates</button>
    </form>
    <div class="updates-sr-status" data-filter-status aria-live="polite"></div>

    <?php if ($fetchError): ?>
        <div class="empty-state" role="alert">
            <h2>College updates are temporarily unavailable</h2>
            <p>Please try again in a few moments.</p>
        </div>
    <?php elseif (!$updates): ?>
        <div class="empty-state">
            <h2>No college updates found</h2>
            <p>
                <?= $search !== '' || $selectedCategory !== ''
                    ? 'Try another search or category.'
                    : 'Published campus updates will appear here.' ?>
            </p>
            <?php if ($search !== '' || $selectedCategory !== ''): ?>
                <a class="btn btn-secondary" href="college_updates.php">Clear filters</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="updates-grid">
            <?php foreach ($updates as $update): ?>
                <?php
                $mediaPath = (string) ($update['first_media_path'] ?? '');
                $isVideo = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION)) === 'mp4';
                $publisherName = trim((string) ($update['publisher_name'] ?? ''));
                if ($publisherName === '') {
                    $publisherName = APP_NAME;
                }
                ?>
                <article class="update-card">
                    <a
                        class="update-card-link"
                        href="college_update.php?id=<?= (int) $update['id'] ?>"
                    >
                        <div class="update-cover">
                            <?php if ($mediaPath !== '' && $isVideo): ?>
                                <video muted playsinline preload="metadata" aria-hidden="true">
                                    <source src="<?= e($mediaPath) ?>" type="video/mp4">
                                </video>
                                <span class="update-video-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="m9 6 10 6-10 6z"></path>
                                    </svg>
                                    Video
                                </span>
                            <?php elseif ($mediaPath !== ''): ?>
                                <img
                                    src="<?= e($mediaPath) ?>"
                                    alt="<?= e($update['title']) ?>"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <span class="update-cover-placeholder">College announcement</span>
                            <?php endif; ?>
                        </div>

                        <div class="update-card-content">
                            <span class="update-category"><?= e($update['category']) ?></span>
                            <h2><?= e($update['title']) ?></h2>
                            <p><?= e(shorten((string) $update['description'], 180)) ?></p>
                            <time class="update-meta" datetime="<?= e(date('c', strtotime($update['publication_date']))) ?>">
                                Published <?= e(date('M j, Y', strtotime($update['publication_date']))) ?>
                            </time>
                            <span class="update-meta">Posted by <?= e($publisherName) ?></span>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>

        <?= renderPagination($pagination, 'page', 'per_page', 'College updates') ?>
    <?php endif; ?>
</section>

<script src="assets/college-updates.js" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
