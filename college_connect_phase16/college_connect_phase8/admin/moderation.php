
<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role(['admin']);
$pageTitle = 'Content moderation';

// Handle moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $type = (string) ($_POST['type'] ?? '');
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
    $action = (string) ($_POST['action'] ?? '');

    $validQuestionActions = [
        'publish',
        'review',
        'block',
        'dismiss'
    ];

    $validAnswerActions = [
        'publish',
        'review',
        'block'
    ];

    $validAction =
        (
            $type === 'question' &&
            in_array($action, $validQuestionActions, true)
        ) ||
        (
            $type === 'answer' &&
            in_array($action, $validAnswerActions, true)
        );

    if ($id > 0 && $validAction) {
        try {
            db()->beginTransaction();

            // Moderate questions
            if ($type === 'question') {
                $map = [
                    'publish' => ['published', 'allowed'],
                    'review'  => ['pending_review', 'review'],
                    'block'   => ['rejected', 'blocked'],
                    'dismiss' => ['rejected', 'blocked']
                ];

                [$st, $md] = $map[$action];

                $update = db()->prepare(
                    'UPDATE questions
                     SET status = ?,
                         moderation_status = ?
                     WHERE id = ?'
                );

                $update->execute([$st, $md, $id]);

                // Verify that the question exists
                if ($update->rowCount() === 0) {
                    $exists = db()->prepare(
                        'SELECT id FROM questions WHERE id = ?'
                    );

                    $exists->execute([$id]);

                    if (!$exists->fetchColumn()) {
                        throw new RuntimeException(
                            'Question does not exist.'
                        );
                    }
                }

            } else {
                // Moderate answers
                $map = [
                    'publish' => 'published',
                    'review'  => 'pending_review',
                    'block'   => 'blocked'
                ];

                $answerStatus = $map[$action];

                $update = db()->prepare(
                    'UPDATE answers
                     SET status = ?
                     WHERE id = ?'
                );

                $update->execute([$answerStatus, $id]);

                // Verify that the answer exists
                if ($update->rowCount() === 0) {
                    $exists = db()->prepare(
                        'SELECT id FROM answers WHERE id = ?'
                    );

                    $exists->execute([$id]);

                    if (!$exists->fetchColumn()) {
                        throw new RuntimeException(
                            'Answer does not exist.'
                        );
                    }
                }

                // Update the corresponding AI review record
                db()->prepare(
                    'UPDATE ai_answer_reviews
                     SET decision = ?,
                         reviewed_by = ?,
                         reviewed_at = NOW()
                     WHERE answer_id = ?'
                )->execute([
                    $answerStatus === 'published'
                        ? 'published'
                        : (
                            $answerStatus === 'blocked'
                                ? 'blocked'
                                : 'pending_review'
                        ),
                    (int) $admin['id'],
                    $id
                ]);
            }

            // Prepare the moderation audit record
            $loggedDecision = $action === 'publish'
                ? 'allow'
                : (
                    $action === 'review'
                        ? 'review'
                        : 'block'
                );

            $reason = 'Manual admin action: ' . $action;

            if ($action === 'dismiss') {
                $reason .=
                    '; question hidden; dependent records retained.';
            }

            // Save the moderation log
            db()->prepare(
                'INSERT INTO moderation_logs (
                    content_type,
                    content_id,
                    provider,
                    decision,
                    reason,
                    reviewed_by,
                    reviewed_at
                ) VALUES (
                    ?, ?, "manual-admin", ?, ?, ?, NOW()
                )'
            )->execute([
                $type,
                $id,
                $loggedDecision,
                $reason,
                (int) $admin['id']
            ]);

            db()->commit();

            set_flash(
                'success',
                $action === 'dismiss'
                    ? 'Question dismissed and removed from public listings. Related records were retained.'
                    : 'Moderation action saved.'
            );

        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            error_log(
                'AITAM SLACK admin moderation action failed: '
                . $e->getMessage()
            );

            set_flash(
                'error',
                'Could not save this moderation action.'
            );
        }
    }

    redirect('moderation.php');
}

// Fetch questions for moderation
$questions = db()->query(
    'SELECT
        q.id,
        q.title,
        q.body,
        q.status,
        q.moderation_status,
        COALESCE(
            u.full_name,
            "Community member"
        ) AS author,
        (
            SELECT ml.reason
            FROM moderation_logs ml
            WHERE ml.content_type = "question"
              AND ml.content_id = q.id
              AND ml.provider IN ("openai", "manual-admin")
            ORDER BY ml.id DESC
            LIMIT 1
        ) AS moderation_reason
     FROM questions q
     LEFT JOIN users u
        ON u.id = q.user_id
     ORDER BY
        (q.status = "pending_review") DESC,
        (q.moderation_status = "review") DESC,
        q.created_at DESC
     LIMIT 100'
)->fetchAll();

// Fetch answers for moderation
$answers = db()->query(
    'SELECT
        a.id,
        a.question_id,
        a.body,
        a.status,
        q.title AS question_title,
        COALESCE(
            u.full_name,
            "Community member"
        ) AS author,
        (
            SELECT ml.reason
            FROM moderation_logs ml
            WHERE ml.content_type = "answer"
              AND ml.content_id = a.id
              AND ml.provider = "openai"
            ORDER BY ml.id DESC
            LIMIT 1
        ) AS moderation_reason
     FROM answers a
     JOIN questions q
        ON q.id = a.question_id
     LEFT JOIN users u
        ON u.id = a.user_id
     ORDER BY
        (a.status = "pending_review") DESC,
        (a.status = "blocked") DESC,
        a.created_at DESC
     LIMIT 100'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">

    <span class="eyebrow">SAFETY & QUALITY</span>

    <h1>Content moderation</h1>

    <p>
        Publish, send for review, block, or dismiss questions
        and answers.
    </p>

</section>

<section class="section-block">

    <!-- Questions moderation -->
    <h2>Questions</h2>

    <?php foreach ($questions as $q): ?>

        <article class="account-card moderation-card">

            <div class="moderation-content">

                <div class="question-meta">

                    <span class="status-chip status-<?= e(
                        (string) $q['status']
                    ) ?>">
                        <?= e($q['status']) ?>
                    </span>

                    <span class="status-chip status-<?= e(
                        (string) $q['moderation_status']
                    ) ?>">
                        <?= e($q['moderation_status']) ?>
                    </span>

                    <span>
                        <?= e($q['author']) ?>
                    </span>

                </div>

                <h3><?= e($q['title']) ?></h3>

                <p>
                    <?= e(shorten($q['body'], 300)) ?>
                </p>

                <?php if ($q['moderation_reason']): ?>
                    <p>
                        <strong>Automated review:</strong>
                        <?= e($q['moderation_reason']) ?>
                    </p>
                <?php endif; ?>

                <a href="../question.php?id=<?= (int) $q['id'] ?>">
                    Open question ↗
                </a>

            </div>

            <!-- Question moderation buttons -->
            <div class="moderation-buttons">

                <?php
                foreach (
                    [
                        'publish' => 'Publish',
                        'review'  => 'Send to review',
                        'block'   => 'Block/reject',
                        'dismiss' => 'Dismiss'
                    ] as $act => $label
                ):
                ?>

                    <form
                        method="post"
                        <?= $act === 'dismiss'
                            ? 'onsubmit="return confirm(&quot;Dismiss this question from public listings? Existing answers, votes, bookmarks, and appeal records will be retained.&quot;)"'
                            : '' ?>
                    >

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="type"
                            value="question"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $q['id'] ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="<?= e($act) ?>"
                        >

                        <button
                            class="btn btn-small btn-secondary"
                            type="submit"
                        >
                            <?= e($label) ?>
                        </button>

                    </form>

                <?php endforeach; ?>

            </div>

        </article>

    <?php endforeach; ?>

    <!-- Answers moderation -->
    <h2>Answers</h2>

    <?php foreach ($answers as $a): ?>

        <article class="account-card moderation-card">

            <div class="moderation-content">

                <div class="question-meta">

                    <span class="status-chip status-<?= e(
                        (string) $a['status']
                    ) ?>">
                        <?= e($a['status']) ?>
                    </span>

                    <span>
                        <?= e($a['author']) ?>
                    </span>

                </div>

                <h3>
                    Answer to: <?= e($a['question_title']) ?>
                </h3>

                <p>
                    <?= e(shorten($a['body'], 280)) ?>
                </p>

                <?php if ($a['moderation_reason']): ?>
                    <p>
                        <strong>Automated review:</strong>
                        <?= e($a['moderation_reason']) ?>
                    </p>
                <?php endif; ?>

                <a href="../question.php?id=<?= (int) $a['question_id'] ?>#answers">
                    Open discussion ↗
                </a>

            </div>

            <!-- Answer moderation buttons -->
            <div class="moderation-buttons">

                <?php
                foreach (
                    [
                        'publish' => 'Publish',
                        'review'  => 'Send to review',
                        'block'   => 'Block'
                    ] as $act => $label
                ):
                ?>

                    <form method="post">

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="type"
                            value="answer"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $a['id'] ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="<?= e($act) ?>"
                        >

                        <button
                            class="btn btn-small btn-secondary"
                            type="submit"
                        >
                            <?= e($label) ?>
                        </button>

                    </form>

                <?php endforeach; ?>

            </div>

        </article>

    <?php endforeach; ?>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
