<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/college_updates.php';

$preview = college_update_input_string($_GET, 'preview') === '1';
$admin = $preview ? require_role(['admin']) : null;
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(404);
    $pageTitle = 'Update not found';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section-block"><div class="empty-state"><h1>Update not found</h1><p>This college update is not available.</p><a class="btn btn-secondary" href="college_updates.php">Back to College Updates</a></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$statement = db()->prepare(
    'SELECT
        update_post.*,
        admin.full_name AS publisher_name
     FROM college_updates update_post
     LEFT JOIN users admin ON admin.id = update_post.admin_id
     WHERE update_post.id = ?
     LIMIT 1'
);
$statement->execute([$id]);
$update = $statement->fetch();

if (
    !$update
    || (
        !$preview
        && (
            $update['status'] !== 'published'
            || strtotime((string) ($update['published_at'] ?? $update['created_at'])) > time()
        )
    )
) {
    http_response_code(404);
    $pageTitle = 'Update not found';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section-block"><div class="empty-state"><h1>Update not found</h1><p>This college update is not available.</p><a class="btn btn-secondary" href="college_updates.php">Back to College Updates</a></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$mediaStatement = db()->prepare(
    'SELECT id, file_path, media_type
     FROM college_update_media
     WHERE update_id = ?
     ORDER BY sort_order, id'
);
$mediaStatement->execute([$id]);
$mediaItems = $mediaStatement->fetchAll();

$publisherName = trim((string) ($update['publisher_name'] ?? ''));
if ($publisherName === '') {
    $publisherName = APP_NAME;
}
$pageTitle = (string) $update['title'];

require __DIR__ . '/includes/header.php';
?>

<section class="section-block update-details">
    <a class="update-back-link" href="college_updates.php">&larr; Back to College Updates</a>

    <?php if ($preview && $update['status'] === 'draft'): ?>
        <div class="update-preview-banner" role="status">
            Draft preview. Only administrators can view this unpublished update.
            <a href="admin/college_updates.php?edit=<?= (int) $update['id'] ?>">Return to editing</a>
        </div>
    <?php elseif ($preview): ?>
        <div class="update-preview-banner" role="status">
            Administrator preview.
            <a href="admin/college_updates.php?edit=<?= (int) $update['id'] ?>">Return to editing</a>
        </div>
    <?php endif; ?>

    <header class="update-details-heading">
        <span class="update-category"><?= e($update['category']) ?></span>
        <h1><?= e($update['title']) ?></h1>
        <p>
            Published: <?= e(date('M j, Y', strtotime($update['published_at'] ?? $update['created_at']))) ?>
        </p>
    </header>

    <?php if ($mediaItems): ?>
        <div class="update-gallery" aria-label="Event photos and videos">
            <?php foreach ($mediaItems as $media): ?>
                <figure>
                    <?php if ($media['media_type'] === 'video'): ?>
                        <video controls preload="metadata" playsinline>
                            <source src="<?= e($media['file_path']) ?>" type="video/mp4">
                            Your browser does not support MP4 video playback.
                        </video>
                    <?php else: ?>
                        <img
                            src="<?= e($media['file_path']) ?>"
                            alt="<?= e($update['title']) ?> event image"
                            loading="lazy"
                        >
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="update-content-grid">
        <article class="update-description">
            <h2>About this update</h2>
            <div class="update-description-body"><?= e($update['description']) ?></div>
        </article>

        <aside class="update-facts">
            <h2>Update details</h2>
            <dl>
                <?php if (!empty($update['venue'])): ?>
                    <div>
                        <dt>Venue</dt>
                        <dd><?= e($update['venue']) ?></dd>
                    </div>
                <?php endif; ?>

                <?php if (!empty($update['registration_url'])): ?>
                    <div>
                        <dt>Registration</dt>
                        <dd>
                            <a
                                href="<?= e($update['registration_url']) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >Register for this event</a>
                        </dd>
                    </div>
                <?php endif; ?>

                <?php if (!empty($update['contact_info'])): ?>
                    <div>
                        <dt>Contact</dt>
                        <dd><?= e($update['contact_info']) ?></dd>
                    </div>
                <?php endif; ?>

                <div>
                    <dt>Posted by</dt>
                    <dd><?= e($publisherName) ?></dd>
                </div>

                <div>
                    <dt>Posted on</dt>
                    <dd><?= e(date('M j, Y', strtotime($update['created_at']))) ?></dd>
                </div>

            </dl>
        </aside>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
