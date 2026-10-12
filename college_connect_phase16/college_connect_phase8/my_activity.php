
<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$pageTitle = 'My Activity';
$uid = (int) $user['id'];
$loadError = false;

$myQuestions = [];
$myAnswers = [];
$savedCount = 0;
$votesCast = 0;

try {
    $q = db()->prepare("
        SELECT id, title, status, created_at
        FROM questions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $q->execute([$uid]);
    $myQuestions = $q->fetchAll();

    $a = db()->prepare("
        SELECT
            a.id,
            a.body,
            a.status,
            a.is_accepted,
            a.created_at,
            q.id AS question_id,
            q.title AS question_title
        FROM answers a
        JOIN questions q ON q.id = a.question_id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $a->execute([$uid]);
    $myAnswers = $a->fetchAll();

    $s = db()->prepare("
        SELECT COUNT(*)
        FROM question_bookmarks
        WHERE user_id = ?
    ");
    $s->execute([$uid]);
    $savedCount = (int) $s->fetchColumn();

    $v = db()->prepare("
        SELECT COUNT(*)
        FROM answer_votes
        WHERE user_id = ?
    ");
    $v->execute([$uid]);
    $votesCast = (int) $v->fetchColumn();

} catch (Throwable $ex) {
    $myQuestions = [];
    $myAnswers = [];
    $savedCount = 0;
    $votesCast = 0;
    $loadError = true;

    error_log('My activity load failed: ' . $ex->getMessage());
}

require __DIR__ . '/includes/header.php';
?>

<style>
/* =========================================
   MY ACTIVITY - AITAM SLACK
   All styles are scoped to this page
========================================= */

.my-activity-page {
    --activity-navy: #0f2854;
    --activity-blue: #1c4d8d;
    --activity-accent: #4988c4;
    --activity-light: #bde8f5;
    --activity-text: #172e55;
    --activity-muted: #657b99;
    --activity-border: #dce7f3;

    width: 100%;
    max-width: 1240px;
    margin: 0 auto;
    padding: 28px 24px 44px;
    box-sizing: border-box;
    color: var(--activity-text);
}

/* Hero */

.my-activity-page .activity-hero {
    position: relative;
    overflow: hidden;
    margin-bottom: 30px;
    padding: 32px;
    border: 1px solid #dce9f6;
    border-radius: 20px;
    background:
        radial-gradient(
            circle at 90% 10%,
            rgba(189, 232, 245, 0.55),
            transparent 35%
        ),
        linear-gradient(135deg, #fff 0%, #edf6ff 100%);
    box-shadow: 0 8px 30px rgba(15, 40, 84, 0.05);
}

.my-activity-page .activity-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 12px;
    color: var(--activity-blue);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.6px;
    text-transform: uppercase;
}

.my-activity-page .activity-eyebrow::before {
    content: "";
    width: 24px;
    height: 3px;
    border-radius: 5px;
    background: var(--activity-accent);
}

.my-activity-page .activity-hero h1 {
    margin: 0 0 12px;
    color: var(--activity-navy);
    font-size: clamp(29px, 4vw, 40px);
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -1px;
}

.my-activity-page .activity-hero p {
    max-width: 650px;
    margin: 0;
    color: var(--activity-muted);
    font-size: 14px;
    line-height: 1.8;
}

/* Statistics grid */

.my-activity-page .activity-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 38px;
}

.my-activity-page .activity-stat-card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 175px;
    padding: 21px;
    overflow: hidden;
    border: 1px solid var(--activity-border);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 5px 18px rgba(15, 40, 84, 0.04);
    box-sizing: border-box;
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        border-color 0.25s ease;
    animation: activityFadeUp 0.45s ease both;
}

.my-activity-page .activity-stat-card:nth-child(2) {
    animation-delay: 0.06s;
}

.my-activity-page .activity-stat-card:nth-child(3) {
    animation-delay: 0.12s;
}

.my-activity-page .activity-stat-card:nth-child(4) {
    animation-delay: 0.18s;
}

.my-activity-page .activity-stat-card:hover {
    transform: translateY(-5px);
    border-color: #a9cbea;
    box-shadow: 0 13px 30px rgba(15, 40, 84, 0.09);
}

.my-activity-page .activity-stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
}

.my-activity-page .activity-stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 43px;
    width: 43px;
    height: 43px;
    border-radius: 13px;
    background: #e9f3ff;
    color: var(--activity-blue);
    font-size: 21px;
}

.my-activity-page .activity-stat-label {
    display: block;
    color: var(--activity-muted);
    font-size: 12px;
    font-weight: 600;
    line-height: 1.5;
}

.my-activity-page .activity-stat-value {
    display: block;
    margin: 0 0 7px;
    color: var(--activity-navy);
    font-size: 32px;
    font-weight: 800;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.my-activity-page .activity-stat-description {
    margin: auto 0 0;
    color: var(--activity-muted);
    font-size: 11px;
    line-height: 1.6;
}

.my-activity-page .activity-stat-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    align-self: flex-start;
    margin-top: auto;
    padding-top: 8px;
    color: var(--activity-blue);
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    transition: color 0.2s ease;
}

.my-activity-page .activity-stat-link:hover {
    color: var(--activity-accent);
}

/* Main sections */

.my-activity-page .activity-section {
    margin-top: 34px;
}

.my-activity-page .activity-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 20px;
}

.my-activity-page .activity-heading-copy {
    min-width: 0;
}

.my-activity-page .activity-section-heading h2 {
    margin: 0 0 7px;
    color: var(--activity-navy);
    font-size: 24px;
    font-weight: 800;
    line-height: 1.35;
    letter-spacing: -0.4px;
}

.my-activity-page .activity-section-heading p {
    margin: 0;
    color: var(--activity-muted);
    font-size: 13px;
    line-height: 1.7;
}

/* Action buttons */

.my-activity-page .activity-action {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 17px;
    border: 1px solid var(--activity-blue);
    border-radius: 10px;
    background: var(--activity-blue);
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    box-sizing: border-box;
    transition:
        transform 0.2s ease,
        background 0.2s ease,
        box-shadow 0.2s ease;
}

.my-activity-page .activity-action:hover {
    transform: translateY(-2px);
    background: var(--activity-navy);
    box-shadow: 0 6px 16px rgba(15, 40, 84, 0.15);
}

.my-activity-page .activity-action:focus-visible,
.my-activity-page a:focus-visible {
    outline: 3px solid #80bde8;
    outline-offset: 3px;
}

/* Activity list */

.my-activity-page .activity-list {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 13px;
    width: 100%;
}

.my-activity-page .activity-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    min-width: 0;
    padding: 21px;
    border: 1px solid var(--activity-border);
    border-radius: 15px;
    background: #fff;
    box-shadow: 0 4px 15px rgba(15, 40, 84, 0.035);
    box-sizing: border-box;
    transition:
        border-color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
    animation: activityFadeUp 0.4s ease both;
}

.my-activity-page .activity-item:hover {
    transform: translateY(-2px);
    border-color: #b7d2ed;
    box-shadow: 0 9px 24px rgba(15, 40, 84, 0.07);
}

.my-activity-page .activity-item-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 44px;
    width: 44px;
    height: 44px;
    border: 1px solid #dceaf9;
    border-radius: 12px;
    background: #edf6ff;
    color: var(--activity-blue);
    font-size: 19px;
}

.my-activity-page .activity-item-content {
    flex: 1;
    min-width: 0;
}

.my-activity-page .activity-item-title {
    margin: 0 0 9px;
    color: var(--activity-navy);
    font-size: 15px;
    font-weight: 800;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.my-activity-page .activity-item-title a {
    color: inherit;
    text-decoration: none;
    transition: color 0.2s ease;
}

.my-activity-page .activity-item-title a:hover {
    color: var(--activity-accent);
}

.my-activity-page .activity-item-description {
    margin: 0 0 10px;
    color: #536b8a;
    font-size: 13px;
    line-height: 1.8;
    overflow-wrap: anywhere;
    white-space: normal;
}

.my-activity-page .activity-item-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
    color: var(--activity-muted);
    font-size: 11px;
    line-height: 1.6;
}

.my-activity-page .activity-meta-dot {
    color: #9db1c9;
}

/* Status labels */

.my-activity-page .activity-status {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    flex: 0 0 auto;
    gap: 6px;
    max-width: 150px;
    padding: 7px 10px;
    border: 1px solid #dce7f3;
    border-radius: 8px;
    background: #f3f7fc;
    color: var(--activity-blue);
    font-size: 10px;
    font-weight: 800;
    line-height: 1.4;
    overflow-wrap: anywhere;
    text-align: center;
}

.my-activity-page .activity-status.published {
    border-color: #c9ecd8;
    background: #eaf8ef;
    color: #247548;
}

.my-activity-page .activity-status.pending {
    border-color: #f2dfb1;
    background: #fff8e5;
    color: #8a6417;
}

.my-activity-page .activity-status.rejected {
    border-color: #f1cccc;
    background: #fff0f0;
    color: #a33b3b;
}

.my-activity-page .activity-status.accepted {
    border-color: #c9ecd8;
    background: #eaf8ef;
    color: #247548;
}

/* Accepted answer label */

.my-activity-page .activity-accepted {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 6px;
    background: #eaf8ef;
    color: #247548;
    font-size: 10px;
    font-weight: 800;
}

/* Empty and error states */

.my-activity-page .activity-empty {
    padding: 36px 22px;
    border: 1px dashed #c6d8eb;
    border-radius: 15px;
    background: #f8fbff;
    text-align: center;
}

.my-activity-page .activity-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    margin: 0 auto 14px;
    border-radius: 15px;
    background: #e8f3ff;
    font-size: 24px;
}

.my-activity-page .activity-empty h3 {
    margin: 0 0 9px;
    color: var(--activity-navy);
    font-size: 18px;
    font-weight: 800;
}

.my-activity-page .activity-empty p {
    max-width: 470px;
    margin: 0 auto 18px;
    color: var(--activity-muted);
    font-size: 13px;
    line-height: 1.8;
}

/* Divider */

.my-activity-page .activity-divider {
    height: 1px;
    margin: 32px 0;
    border: 0;
    background: linear-gradient(
        to right,
        #dce7f3,
        rgba(220, 231, 243, 0.2)
    );
}

/* Entrance animation */

@keyframes activityFadeUp {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Tablet */

@media (max-width: 900px) {
    .my-activity-page {
        padding: 24px 20px 36px;
    }

    .my-activity-page .activity-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .my-activity-page .activity-stat-card {
        min-height: 160px;
    }
}

/* Mobile */

@media (max-width: 520px) {
    .my-activity-page {
        padding: 18px 14px 30px;
    }

    .my-activity-page .activity-hero {
        padding: 23px 18px;
        margin-bottom: 23px;
        border-radius: 15px;
    }

    .my-activity-page .activity-hero h1 {
        font-size: 28px;
    }

    .my-activity-page .activity-hero p {
        font-size: 13px;
    }

    .my-activity-page .activity-stats {
        gap: 10px;
        margin-bottom: 30px;
    }

    .my-activity-page .activity-stat-card {
        min-height: 150px;
        padding: 15px;
        border-radius: 13px;
    }

    .my-activity-page .activity-stat-top {
        gap: 8px;
        margin-bottom: 14px;
    }

    .my-activity-page .activity-stat-icon {
        flex-basis: 36px;
        width: 36px;
        height: 36px;
        font-size: 18px;
    }

    .my-activity-page .activity-stat-value {
        font-size: 27px;
    }

    .my-activity-page .activity-stat-label {
        font-size: 11px;
    }

    .my-activity-page .activity-section-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 13px;
    }

    .my-activity-page .activity-section-heading h2 {
        font-size: 21px;
    }

    .my-activity-page .activity-item {
        gap: 11px;
        padding: 15px;
    }

    .my-activity-page .activity-item-icon {
        flex-basis: 36px;
        width: 36px;
        height: 36px;
        font-size: 16px;
    }

    .my-activity-page .activity-item-title {
        font-size: 13px;
    }

    .my-activity-page .activity-item-description {
        font-size: 12px;
    }

    .my-activity-page .activity-status {
        max-width: 100px;
        padding: 6px 7px;
        font-size: 9px;
    }
}

/* Reduced motion accessibility */

@media (prefers-reduced-motion: reduce) {
    .my-activity-page *,
    .my-activity-page *::before,
    .my-activity-page *::after {
        animation: none !important;
        transition: none !important;
    }
}
</style>

<div class="my-activity-page">

    <section class="activity-hero">
        <span class="activity-eyebrow">
            Your College Connect Journey
        </span>

        <h1>My Activity</h1>

        <p>
            Track your questions, answers, saved content, and community
            participation — all in one organized dashboard.
        </p>
    </section>

    <?php if ($loadError): ?>

        <section class="activity-empty">
            <div class="activity-empty-icon"><?= icon_svg('alert-circle') ?></div>

            <h3>Activity summary is unavailable</h3>

            <p>
                Check that the bookmarks and voting database migrations
                are installed correctly, then reload this page.
            </p>
        </section>

    <?php else: ?>

        <!-- Statistics -->

        <section class="activity-stats">

            <article class="activity-stat-card">
                <div class="activity-stat-top">
                    <span class="activity-stat-label">
                        Questions shown
                    </span>
                    <span class="activity-stat-icon" aria-hidden="true">
                        <?= icon_svg('message') ?>
                    </span>
                </div>

                <strong class="activity-stat-value">
                    <?= count($myQuestions) ?>
                </strong>

                <p class="activity-stat-description">
                    Your 10 most recent questions
                </p>
            </article>

            <article class="activity-stat-card">
                <div class="activity-stat-top">
                    <span class="activity-stat-label">
                        Answers shown
                    </span>
                    <span class="activity-stat-icon" aria-hidden="true">
                        <?= icon_svg('pen-line') ?>
                    </span>
                </div>

                <strong class="activity-stat-value">
                    <?= count($myAnswers) ?>
                </strong>

                <p class="activity-stat-description">
                    Your 10 most recent answers
                </p>
            </article>

            <article class="activity-stat-card">
                <div class="activity-stat-top">
                    <span class="activity-stat-label">
                        Saved questions
                    </span>
                    <span class="activity-stat-icon" aria-hidden="true">
                        <?= icon_svg('bookmark') ?>
                    </span>
                </div>

                <strong class="activity-stat-value">
                    <?= $savedCount ?>
                </strong>

                <a class="activity-stat-link" href="bookmarks.php">
                    View bookmarks <span aria-hidden="true">→</span>
                </a>
            </article>

            <article class="activity-stat-card">
                <div class="activity-stat-top">
                    <span class="activity-stat-label">
                        Votes cast
                    </span>
                    <span class="activity-stat-icon" aria-hidden="true">
                        <?= icon_svg('thumbs-up') ?>
                    </span>
                </div>

                <strong class="activity-stat-value">
                    <?= $votesCast ?>
                </strong>

                <p class="activity-stat-description">
                    Your feedback on answers
                </p>
            </article>

        </section>

        <!-- Questions -->

        <section class="activity-section">

            <div class="activity-section-heading">
                <div class="activity-heading-copy">
                    <h2>My Recent Questions</h2>
                    <p>
                        Review your latest questions and their current statuses.
                    </p>
                </div>

                <a class="activity-action" href="ask_question.php">
                    <span aria-hidden="true">＋</span>
                    Ask a Question
                </a>
            </div>

            <?php if (!$myQuestions): ?>

                <div class="activity-empty">
                    <div class="activity-empty-icon"><?= icon_svg('message') ?></div>

                    <h3>No questions yet</h3>

                    <p>
                        Ask your first question and start building your
                        community activity history.
                    </p>

                    <a class="activity-action" href="ask_question.php">
                        Ask your first question
                    </a>
                </div>

            <?php else: ?>

                <div class="activity-list">

                    <?php foreach ($myQuestions as $question): ?>

                        <?php
                        $status = (string) $question['status'];
                        $statusClass = strtolower($status);
                        ?>

                        <article class="activity-item">

                            <div class="activity-item-icon" aria-hidden="true">
                                <?= icon_svg('message') ?>
                            </div>

                            <div class="activity-item-content">

                                <h3 class="activity-item-title">
                                    <a href="question.php?id=<?= (int) $question['id'] ?>">
                                        <?= e($question['title']) ?>
                                    </a>
                                </h3>

                                <div class="activity-item-meta">
                                    <span>Created
                                        <?= e(date(
                                            'M j, Y',
                                            strtotime($question['created_at'])
                                        )) ?>
                                    </span>

                                    <span class="activity-meta-dot">•</span>

                                    <span>Question #<?= (int) $question['id'] ?></span>
                                </div>

                            </div>

                            <span class="activity-status <?= e($statusClass) ?>">
                                <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                            </span>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <hr class="activity-divider">

        <!-- Answers -->

        <section class="activity-section">

            <div class="activity-section-heading">
                <div class="activity-heading-copy">
                    <h2>My Recent Answers</h2>
                    <p>
                        Track your contributions and accepted solutions
                        across community questions.
                    </p>
                </div>
            </div>

            <?php if (!$myAnswers): ?>

                <div class="activity-empty">
                    <div class="activity-empty-icon"><?= icon_svg('pen-line') ?></div>

                    <h3>No answers yet</h3>

                    <p>
                        Visit community questions and share your knowledge
                        to help other students.
                    </p>

                    <a class="activity-action" href="questions.php">
                        Browse Questions
                    </a>
                </div>

            <?php else: ?>

                <div class="activity-list">

                    <?php foreach ($myAnswers as $answer): ?>

                        <?php
                        $answerStatus = (string) $answer['status'];
                        $answerStatusClass = strtolower($answerStatus);
                        $answerDate = strtotime($answer['created_at']);
                        ?>

                        <article class="activity-item">

                            <div class="activity-item-icon" aria-hidden="true">
                                <?= icon_svg('pen-line') ?>
                            </div>

                            <div class="activity-item-content">

                                <h3 class="activity-item-title">
                                    <a href="question.php?id=<?= (int) $answer['question_id'] ?>">
                                        <?= e($answer['question_title']) ?>
                                    </a>
                                </h3>

                                <p class="activity-item-description">
                                    <?php
                                    $body = (string) $answer['body'];

                                    if (function_exists('mb_strimwidth')) {
                                        echo e(mb_strimwidth(
                                            $body,
                                            0,
                                            180,
                                            '…',
                                            'UTF-8'
                                        ));
                                    } else {
                                        echo e(strlen($body) > 180
                                            ? substr($body, 0, 177) . '...'
                                            : $body);
                                    }
                                    ?>
                                </p>

                                <div class="activity-item-meta">

                                    <span>
                                        <?= e(date('M j, Y', $answerDate)) ?>
                                    </span>

                                    <span class="activity-meta-dot">•</span>

                                    <span class="activity-status <?= e($answerStatusClass) ?>">
                                        <?= e(ucwords(str_replace(
                                            '_',
                                            ' ',
                                            $answerStatus
                                        ))) ?>
                                    </span>

                                    <?php if ((int) $answer['is_accepted'] === 1): ?>
                                        <span class="activity-accepted">
                                            <?= icon_svg('check-circle') ?> Accepted answer
                                        </span>
                                    <?php endif; ?>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>