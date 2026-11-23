<?php

require_once __DIR__ . '/bootstrap.php';

$user = require_login();
$pageTitle = 'Report an issue';
$errors = [];
$reportType = '';
$reportedItemId = '';
$subject = '';
$description = '';

$reportTypes = [
    'question' => 'Inappropriate Question',
    'answer' => 'Inappropriate Answer',
    'user' => 'Report a User',
    'spam' => 'Spam or Fake Content',
    'harassment' => 'Harassment or Abuse',
    'technical' => 'Technical Issue',
    'other' => 'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $reportTypeInput = $_POST['report_type'] ?? '';
    $reportType = is_string($reportTypeInput) ? $reportTypeInput : '';

    $itemIdInput = $_POST['reported_item_id'] ?? '';
    $reportedItemId = is_string($itemIdInput) ? trim($itemIdInput) : '';
    $itemId = null;
    if (!is_string($itemIdInput)) {
        $errors[] = 'Enter a valid reported content ID.';
    } elseif ($reportedItemId !== '') {
        $validatedItemId = filter_var(
            $reportedItemId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($validatedItemId === false) {
            $errors[] = 'Enter a valid reported content ID.';
        } else {
            $itemId = (int) $validatedItemId;
        }
    }

    $subjectInput = $_POST['subject'] ?? '';
    $subject = is_string($subjectInput) ? trim($subjectInput) : '';

    $descriptionInput = $_POST['description'] ?? '';
    $description = is_string($descriptionInput) ? trim($descriptionInput) : '';

    if (!isset($reportTypes[$reportType])) {
        $errors[] = 'Select a valid report category.';
    }

    if (
        !mb_check_encoding($subject, 'UTF-8')
        || mb_strlen($subject) < 5
        || mb_strlen($subject) > 255
    ) {
        $errors[] = 'Subject must contain 5 to 255 valid characters.';
    }

    if (!mb_check_encoding($description, 'UTF-8')) {
        $errors[] = 'Report details contain invalid text.';
    } elseif (mb_strlen($description) < 10 || mb_strlen($description) > 5000) {
        $errors[] = 'Report details must contain 10 to 5,000 characters.';
    }

    if (!$errors) {
        try {
            db()->prepare(
                'INSERT INTO reports
                    (user_id, report_type, reported_item_id, subject, description, status)
                 VALUES (?, ?, ?, ?, ?, "pending")'
            )->execute([
                (int) $user['id'],
                $reportType,
                $itemId,
                $subject,
                $description,
            ]);

            set_flash('success', 'Your report has been submitted successfully.');
            redirect('report.php');
        } catch (PDOException $error) {
            error_log('Report submission failed: ' . $error->getMessage());
            $errors[] = 'Unable to submit your report. Please try again.';
        }
    }
}

require __DIR__ . '/header.php';
?>

<section class="page-hero">
    <span class="eyebrow">COMMUNITY SUPPORT</span>
    <h1>Report an issue</h1>
    <p>Help us maintain a safe and supportive College Connect community.</p>
</section>

<section class="section-block">
    <div class="account-card">
        <?php if ($errors): ?>
            <div class="flash flash-error" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="report.php" class="account-form">
            <?= csrf_field() ?>

            <label for="report-type">Report category</label>
            <select id="report-type" name="report_type" required>
                <option value="">Select a category</option>
                <?php foreach ($reportTypes as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $reportType === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="reported-item-id">Reported content ID (optional)</label>
            <input
                id="reported-item-id"
                name="reported_item_id"
                type="number"
                min="1"
                step="1"
                value="<?= e($reportedItemId) ?>"
                placeholder="Question, answer, or user ID"
            >

            <label for="subject">Report subject</label>
            <input
                id="subject"
                name="subject"
                type="text"
                minlength="5"
                maxlength="255"
                required
                value="<?= e($subject) ?>"
                placeholder="Summarize the issue"
            >

            <label for="description">Describe the issue</label>
            <textarea
                id="description"
                name="description"
                minlength="10"
                maxlength="5000"
                rows="6"
                required
                placeholder="Explain the issue in detail."
            ><?= e($description) ?></textarea>

            <button type="submit" class="btn btn-primary">Submit report</button>
        </form>

        <p class="form-note">
            Your report will be reviewed by the College Connect administration team. Please provide accurate information.
        </p>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
