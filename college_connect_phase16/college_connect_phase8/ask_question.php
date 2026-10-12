<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/ai_helpers.php';
require_once __DIR__ . '/includes/openai_moderation.php';

$user = require_login();
$pageTitle = 'Ask a question';
$errors = [];
$title = '';
$body = '';
$categoryId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: 0;

    if (mb_strlen($title) < 8 || mb_strlen($title) > 220) {
        $errors[] = 'Title must be between 8 and 220 characters.';
    }

    if (mb_strlen($body) < 20 || mb_strlen($body) > 10000) {
        $errors[] = 'Question details must be between 20 and 10,000 characters.';
    }

    $check = db()->prepare('SELECT id, name FROM categories WHERE id = ? AND is_active = 1');
    $check->execute([$categoryId]);
    $catRow = $check->fetch();

    if (!$catRow) {
        $errors[] = 'Choose a valid topic.';
    }

    if (!$errors) {
        $categoriesList = get_categories();
        $catCheck = cc_openai_check_category($title, $body, $categoryId, $categoriesList);

        if (!$catCheck['matches_selected']) {
            $suggestedName = $catCheck['relevant_category_name'] ?: 'another topic';
            $reasonText = !empty($catCheck['reason'])
                ? ' (' . rtrim($catCheck['reason'], '.') . ')'
                : '';

            $errors[] = 'Your question does not match the selected topic "'
                . $catRow['name'] . '"' . $reasonText
                . '. Please select the relevant topic "' . $suggestedName . '" to proceed.';

            if (!empty($catCheck['relevant_category_id'])) {
                $categoryId = (int) $catCheck['relevant_category_id'];
            }
        }
    }

    if (!$errors) {
        $risk = cc_phase16_risk_scan($title, $body);

        $quality = !empty($risk['api_ok']) && !empty($risk['flagged'])
            ? [
                'decision' => 'review',
                'confidence' => 0.0,
                'reason' => 'Question-quality check skipped after a safety block.',
                'provider' => 'not_run',
                'api_ok' => false,
                'diagnostic' => '',
            ]
            : (empty($risk['api_ok'])
                ? [
                    'decision' => 'review',
                    'confidence' => 0.0,
                    'reason' => 'Question-quality check skipped because safety moderation did not complete.',
                    'provider' => 'not_run',
                    'api_ok' => false,
                    'diagnostic' => $risk['diagnostic'] ?? '',
                ]
                : cc_phase16_question_quality_for_submission($title, $body));

        $apiUnavailable = empty($risk['api_ok'])
            || (!empty($risk['api_ok']) && empty($risk['flagged']) && empty($quality['api_ok']));
        $decision = cc_phase16_question_decision($risk, $quality);

        if (!cc_phase16_question_can_persist($risk, $quality, $decision)) {
            $reasonCode = empty($risk['api_ok'])
                ? 'safety_unavailable'
                : (!empty($risk['flagged'])
                    ? 'safety_flagged'
                    : (empty($quality['api_ok'])
                        ? 'quality_unavailable'
                        : (($quality['decision'] ?? '') === 'block'
                            ? 'quality_blocked'
                            : 'quality_uncertain')));

            if ($apiUnavailable) {
                cc_phase16_log_submission_decision(
                    'question',
                    (int) $user['id'],
                    'retry',
                    $reasonCode,
                    false
                );

                set_flash(
                    'error',
                    'We could not complete the automated safety and quality checks. Your question was not saved; please try again later.'
                );
                redirect('ask_question.php');
            }

            $isBlocked = ($decision['decision'] ?? '') === 'block' || !empty($risk['flagged']);
            $qStatus = $isBlocked ? 'rejected' : 'pending_review';
            $modStatus = $isBlocked ? 'blocked' : 'review';

            try {
                db()->beginTransaction();

                $s = db()->prepare(
                    'INSERT INTO questions (user_id, category_id, title, body, moderation_status, status) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $s->execute([
                    (int) $user['id'],
                    $categoryId,
                    $title,
                    $body,
                    $modStatus,
                    $qStatus,
                ]);

                $id = (int) db()->lastInsertId();
                cc_phase16_record_moderation('question', $id, $risk, $quality, $qStatus);
                db()->commit();

                try {
                    cc_ai_save_insight($id, $title, $body, get_categories());
                } catch (Throwable $e) {
                    // AI insight generation is optional; moderation has already been recorded.
                }
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }

                error_log('AITAM SLACK escalated question insertion failed: ' . $e->getMessage());
            }

            cc_phase16_log_submission_decision(
                'question',
                (int) $user['id'],
                'blocked',
                $reasonCode,
                true
            );

            set_flash(
                'error',
                $isBlocked
                    ? 'Your question was flagged by automated checks as unwanted or inappropriate content and has been blocked from public view.'
                    : 'Your question was flagged for automated review and has been escalated to moderators. It is hidden and will not be displayed publicly until approved.'
            );
            redirect('ask_question.php');
        }

        try {
            db()->beginTransaction();

            $s = db()->prepare(
                'INSERT INTO questions (user_id, category_id, title, body, moderation_status, status) VALUES (?, ?, ?, ?, "allowed", "published")'
            );
            $s->execute([(int) $user['id'], $categoryId, $title, $body]);
            $id = (int) db()->lastInsertId();

            cc_phase16_record_moderation('question', $id, $risk, $quality, 'published');
            db()->commit();
        } catch (Throwable $databaseError) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            error_log('AITAM SLACK question insertion failed: ' . $databaseError->getMessage());
            $errors[] = 'Your question could not be saved right now. Please try again later.';
        }

        if (!$errors) {
            try {
                cc_ai_save_insight($id, $title, $body, get_categories());
            } catch (Throwable $aiError) {
                error_log('AITAM SLACK AI assist: ' . $aiError->getMessage());
            }
        }

        if (!$errors) {
            set_flash(
                'success',
                'Your question has been published after automated safety and question-quality checks. Automated checks can still make mistakes.'
            );
            redirect('question.php?id=' . $id);
        }
    }
}

$categories = get_categories();
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <span class="eyebrow">COMMUNITY KNOWLEDGE</span>
    <h1>Ask the community</h1>
    <p>Share a clear question so students and alumni can help.</p>
</section>

<section class="section-block">
    <div class="account-card">
        <?php if ($errors): ?>
            <div class="flash flash-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="account-form">
            <?= csrf_field() ?>

            <label for="title">Question title</label>
            <input
                id="title"
                name="title"
                maxlength="220"
                minlength="8"
                required
                aria-describedby="title-guidance"
                data-counter="title-counter"
                value="<?= e($title) ?>"
                placeholder="What would you like to know?"
            >
            <small class="field-guidance" id="title-guidance">
                Make it specific and easy to understand (8–220 characters).
            </small>
            <output class="field-counter" id="title-counter" for="title"></output>

            <label for="category_id">Topic</label>
            <select id="category_id" name="category_id" required aria-describedby="category-guidance">
                <option value="">Choose a topic</option>
                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= (int) $category['id'] ?>"
                        <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>
                    >
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="field-guidance" id="category-guidance">
                Choose the topic that best fits your question.
            </small>

            <label for="body">Details</label>
            <textarea
                id="body"
                name="body"
                rows="7"
                minlength="20"
                maxlength="10000"
                required
                aria-describedby="body-guidance"
                data-counter="body-counter"
                placeholder="Add context and details..."
            ><?= e($body) ?></textarea>
            <small class="field-guidance" id="body-guidance">
                Include relevant context and what you have already tried (20–10,000 characters).
            </small>
            <output class="field-counter" id="body-counter" for="body"></output>

            <p class="form-note">
                Questions are checked for safety and quality before they are saved. If a check is unavailable or uncertain, the question will not be saved and you can try again.
            </p>
            <button class="btn btn-primary" type="submit">Publish question ↗</button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
