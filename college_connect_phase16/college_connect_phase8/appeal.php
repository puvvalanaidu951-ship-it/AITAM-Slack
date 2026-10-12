<?php

require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$pageTitle = 'Appeal a moderation decision';
$errors = [];

$type = (string) ($_GET['type'] ?? $_POST['type'] ?? '');
$id = (int) ($_GET['id'] ?? $_POST['content_id'] ?? 0);

if (!in_array($type, ['question', 'answer'], true) || $id < 1) {
    http_response_code(400);
    exit('Invalid content reference.');
}

if ($type === 'question') {
    $statement = db()->prepare(
        'SELECT id, title, status, user_id FROM questions WHERE id = ?'
    );
} else {
    $statement = db()->prepare(
        'SELECT id, body AS title, status, user_id FROM answers WHERE id = ?'
    );
}

$statement->execute([$id]);
$item = $statement->fetch();

if (!$item || (int) $item['user_id'] !== (int) $user['id']) {
    http_response_code(403);
    exit('You can only appeal your own content.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $reason = trim((string) ($_POST['reason'] ?? ''));

    if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
        $errors[] = 'Explain the appeal in 10–1,000 characters.';
    }

    $duplicate = db()->prepare(
        'SELECT id
         FROM content_appeals
         WHERE user_id = ?
           AND content_type = ?
           AND content_id = ?
           AND status = "open"
         LIMIT 1'
    );
    $duplicate->execute([(int) $user['id'], $type, $id]);

    if ($duplicate->fetch()) {
        $errors[] = 'You already have an open appeal for this content.';
    }

    if (!$errors) {
        db()->prepare(
            'INSERT INTO content_appeals (user_id, content_type, content_id, reason)
             VALUES (?, ?, ?, ?)'
        )->execute([(int) $user['id'], $type, $id, $reason]);

        set_flash(
            'success',
            'Your appeal has been submitted for administrator review.'
        );
        redirect('profile.php');
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <span class="eyebrow">FAIR REVIEW</span>
    <h1>Appeal a moderation decision</h1>
    <p>
        Explain why you believe your <?= e($type) ?> was incorrectly blocked or held.
    </p>
</section>

<section class="section-block">
    <div class="account-card">
        <h2><?= e(shorten((string) $item['title'], 180)) ?></h2>

        <p>
            Current status:
            <strong><?= e($item['status']) ?></strong>
        </p>

        <?php if ($errors): ?>
            <div class="flash flash-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="account-form">
            <?= csrf_field() ?>

            <input type="hidden" name="type" value="<?= e($type) ?>">
            <input type="hidden" name="content_id" value="<?= $id ?>">

            <label for="reason">Why should this content be reviewed?</label>
            <textarea
                id="reason"
                name="reason"
                rows="6"
                minlength="10"
                maxlength="1000"
                required
                placeholder="Explain the context and why the content follows the community rules."
            ></textarea>

            <button class="btn btn-primary" type="submit">
                Submit appeal
            </button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
