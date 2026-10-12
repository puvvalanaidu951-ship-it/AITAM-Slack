
<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role(['admin']);
$pageTitle = 'Announcements';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    // Create a new announcement
    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $label = (string) ($_POST['label'] ?? 'COMMUNITY');

        // Validate title
        if (mb_strlen($title) < 4 || mb_strlen($title) > 180) {
            $errors[] = 'Title must be 4–180 characters.';
        }

        // Validate announcement body
        if (mb_strlen($body) < 10 || mb_strlen($body) > 10000) {
            $errors[] = 'Body must be 10–10,000 characters.';
        }

        // Validate announcement label
        if (
            !in_array(
                $label,
                ['COMMUNITY', 'ACADEMIC', 'EVENT', 'IMPORTANT'],
                true
            )
        ) {
            $label = 'COMMUNITY';
        }

        // Insert announcement if validation passes
        if (!$errors) {
            db()->prepare(
                'INSERT INTO announcements
                    (title, body, label, created_by, is_published)
                 VALUES (?, ?, ?, ?, 1)'
            )->execute([
                $title,
                $body,
                $label,
                (int) $admin['id']
            ]);

            set_flash('success', 'Announcement published.');
            redirect('announcements.php');
        }

    // Toggle announcement visibility
    } elseif ($action === 'toggle') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

        db()->prepare(
            'UPDATE announcements
             SET is_published = 1 - is_published
             WHERE id = ?'
        )->execute([$id]);

        set_flash('success', 'Announcement visibility updated.');
        redirect('announcements.php');
    }
}

// Fetch recent announcements
$items = db()->query(
    'SELECT id, title, label, is_published, created_at
     FROM announcements
     ORDER BY created_at DESC
     LIMIT 100'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">
    <span class="eyebrow">CAMPUS UPDATES</span>

    <h1>Announcements</h1>

    <p>Publish notices and manage visibility.</p>
</section>

<section class="section-block">

    <!-- Create announcement form -->
    <div class="account-card">
        <h2>New announcement</h2>

        <?php if ($errors): ?>
            <div class="flash flash-error">
                <?php foreach ($errors as $err): ?>
                    <p><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="account-form">
            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="create"
            >

            <label for="title">Title</label>
            <input
                id="title"
                name="title"
                maxlength="180"
                required
            >

            <label for="label">Label</label>
            <select id="label" name="label">
                <option>COMMUNITY</option>
                <option>ACADEMIC</option>
                <option>EVENT</option>
                <option>IMPORTANT</option>
            </select>

            <label for="body">Details</label>
            <textarea
                id="body"
                name="body"
                rows="5"
                maxlength="10000"
                required
            ></textarea>

            <button class="btn btn-primary" type="submit">
                Publish
            </button>
        </form>
    </div>

    <!-- Display existing announcements -->
    <?php foreach ($items as $item): ?>
        <article class="account-card report-card">

            <div>
                <div class="question-meta">

                    <span class="status-chip">
                        <?= e($item['label']) ?>
                    </span>

                    <span
                        class="status-chip <?= $item['is_published']
                            ? 'status-published'
                            : 'status-hidden' ?>"
                    >
                        <?= $item['is_published']
                            ? 'Published'
                            : 'Hidden' ?>
                    </span>

                </div>

                <h3><?= e($item['title']) ?></h3>

                <small>
                    <?= e(time_ago($item['created_at'])) ?>
                </small>
            </div>

            <!-- Toggle visibility form -->
            <form method="post">
                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="toggle"
                >

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $item['id'] ?>"
                >

                <button
                    class="btn btn-small btn-secondary"
                    type="submit"
                >
                    <?= $item['is_published']
                        ? 'Hide'
                        : 'Publish' ?>
                </button>
            </form>

        </article>
    <?php endforeach; ?>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
