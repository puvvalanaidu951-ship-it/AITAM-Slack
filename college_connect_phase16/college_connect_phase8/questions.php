
<?php

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Questions';

$selectedCategory = filter_input(
    INPUT_GET,
    'category',
    FILTER_VALIDATE_INT
) ?: 0;

$search = trim((string) ($_GET['q'] ?? ''));
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin = require_role(['admin']);
    verify_csrf();

    if (($_POST['action'] ?? '') !== 'delete_question') {
        set_flash('error', 'Invalid question action.');
        redirect('questions.php');
    }

    $questionId = filter_input(
        INPUT_POST,
        'question_id',
        FILTER_VALIDATE_INT
    );

    if (!is_int($questionId) || $questionId < 1) {
        set_flash('error', 'Choose a valid question to delete.');
        redirect('questions.php');
    }

    try {
        db()->beginTransaction();

        $question = db()->prepare(
            'SELECT id, status
             FROM questions
             WHERE id = ?
             FOR UPDATE'
        );

        $question->execute([$questionId]);
        $existingQuestion = $question->fetch();

        if (!$existingQuestion) {
            throw new RuntimeException('Question does not exist.');
        }

        if ($existingQuestion['status'] === 'archived') {
            throw new RuntimeException('Question has already been deleted.');
        }

        db()->prepare(
            'UPDATE questions
             SET status = "archived",
                 moderation_status = "blocked"
             WHERE id = ?'
        )->execute([$questionId]);

        db()->prepare(
            'INSERT INTO moderation_logs
                (
                    content_type,
                    content_id,
                    provider,
                    decision,
                    reason,
                    reviewed_by,
                    reviewed_at
                )
             VALUES
                (
                    "question",
                    ?,
                    "manual-admin",
                    "block",
                    "Question soft-deleted by administrator; dependent records retained.",
                    ?,
                    NOW()
                )'
        )->execute([
            $questionId,
            (int) $admin['id']
        ]);

        db()->commit();

        audit_log(
            (int) $admin['id'],
            'question_deleted',
            'question',
            $questionId,
            'Question soft-deleted; dependent records retained.'
        );

        set_flash(
            'success',
            'Question deleted from public listings. Its related records and moderation history were retained.'
        );

    } catch (Throwable $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        error_log(
            'AITAM SLACK admin question deletion failed: '
            . $error->getMessage()
        );

        set_flash(
            'error',
            'Could not delete that question. It may have already been removed or is no longer available.'
        );
    }

    $query = http_build_query(
        array_filter(
            [
                'q' => $search,
                'category' => $selectedCategory > 0
                    ? $selectedCategory
                    : null,
            ],
            static fn ($value) => $value !== null && $value !== ''
        )
    );

    redirect(
        'questions.php' . ($query !== '' ? '?' . $query : '')
    );
}

$categories = get_categories();
$questions = search_questions($search, $selectedCategory);

require __DIR__ . '/includes/header.php';

?>

<section class="page-hero">
    <span class="eyebrow">COMMUNITY KNOWLEDGE</span>

    <h1>Questions &amp; answers</h1>

    <p>
        Explore campus questions and learn from students, alumni, and faculty.
    </p>
</section>

<section class="section-block question-explorer">

    <div class="section-heading compact">
        <div>
            <h2>Join the discussion</h2>
            <p>Ask a question or explore community answers.</p>
        </div>

        <a class="btn btn-primary" href="ask_question.php">
            Ask a question ↗
        </a>
    </div>

    <form
        class="search-form"
        method="get"
        action="questions.php"
    >
        <label class="sr-only" for="q">
            Search questions
        </label>

        <span class="search-symbol">⌕</span>

        <input
            id="q"
            name="q"
            value="<?= e($search) ?>"
            placeholder="Search questions, topics, or keywords..."
        >

        <button class="btn btn-primary" type="submit">
            Search
        </button>
    </form>

    <div class="filter-row">

        <a
            class="filter-chip <?= $selectedCategory === 0 ? 'active' : '' ?>"
            href="questions.php"
        >
            All topics
        </a>

        <?php foreach ($categories as $c): ?>

            <a
                class="filter-chip <?= $selectedCategory === (int) $c['id'] ? 'active' : '' ?>"
                href="questions.php?category=<?= (int) $c['id'] ?>"
            >
                <?= e($c['name']) ?>
            </a>

        <?php endforeach; ?>

    </div>

    <div class="section-heading compact result-heading">
        <div>
            <h2>
                <?= count($questions) ?>
                <?= count($questions) === 1 ? 'question' : 'questions' ?>
                found
            </h2>

            <p>
                Browse the latest discussions from the community.
            </p>
        </div>
    </div>

    <?php if (!$questions): ?>

        <div class="empty-state">
            <span class="empty-icon">⌕</span>

            <h3>No questions found</h3>

            <p>
                Try a different keyword or choose another category.
            </p>
        </div>

    <?php else: ?>

        <div class="question-list">

            <?php foreach ($questions as $q): ?>

                <article
                    class="question-row"
                    id="question-<?= (int) $q['id'] ?>"
                >

                    <div class="question-votes">
                        <strong>
                            <?= (int) $q['answer_count'] ?>
                        </strong>

                        <span>answers</span>
                    </div>

                    <div class="question-main">

                        <h3 class="question-title">
                            <a href="question.php?id=<?= (int) $q['id'] ?>">
                                <?= e($q['title']) ?>
                            </a>
                        </h3>

                        <p>
                            <?= e($q['body']) ?>
                        </p>

                        <div class="question-meta">

                            <span class="topic-pill">
                                <?= e($q['category_name']) ?>
                            </span>

                            <span>
                                <?= e($q['author_name']) ?>
                            </span>

                            <span>
                                <?= e(time_ago($q['created_at'])) ?>
                            </span>

                        </div>
                    </div>

                    <div class="question-row-actions">

                        <a
                            class="btn btn-primary btn-small"
                            href="question.php?id=<?= (int) $q['id'] ?>#answer-form"
                        >
                            Answer
                        </a>

                        <?php if ($user && $user['role'] === 'admin'): ?>

                            <form
                                method="post"
                                action="questions.php"
                                class="question-delete-form"
                                onsubmit="return confirm('Delete this question from public listings? Answers, votes, bookmarks, notifications, and moderation history will be retained.');"
                            >
                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete_question"
                                >

                                <input
                                    type="hidden"
                                    name="question_id"
                                    value="<?= (int) $q['id'] ?>"
                                >

                                <button
                                    class="btn btn-small question-delete-button"
                                    type="submit"
                                    aria-label="Delete question: <?= e($q['title']) ?>"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        focusable="false"
                                    >
                                        <path d="M4 7h16M10 11v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3" />
                                    </svg>

                                    <span>Delete</span>
                                </button>
                            </form>

                        <?php endif; ?>

                        <a
                            class="row-arrow"
                            aria-label="View question"
                            href="question.php?id=<?= (int) $q['id'] ?>"
                        >
                            ↗
                        </a>

                    </div>
                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
