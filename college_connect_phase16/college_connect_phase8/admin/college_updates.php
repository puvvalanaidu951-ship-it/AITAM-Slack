<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/college_updates.php';

$admin = require_role(['admin']);
$pageTitle = 'Manage College Updates';
$categories = college_update_categories();
$errors = [];
$statusCode = 200;
$action = college_update_input_string($_POST, 'action');
$editingIdValue = $_SERVER['REQUEST_METHOD'] === 'POST'
    && $action === 'save'
    ? ($_POST['id'] ?? '')
    : ($_GET['edit'] ?? '');
$editingId = $editingIdValue === ''
    ? 0
    : filter_var($editingIdValue, FILTER_VALIDATE_INT);

if ($editingId === false || $editingId < 0) {
    $editingId = 0;
    $errors[] = 'The requested update ID is invalid.';
    $statusCode = 400;
}

$editingUpdate = null;
$existingMedia = [];

if ($editingId > 0) {
    $editStatement = db()->prepare(
        'SELECT * FROM college_updates WHERE id = ? LIMIT 1'
    );
    $editStatement->execute([$editingId]);
    $editingUpdate = $editStatement->fetch() ?: null;

    if (!$editingUpdate) {
        $errors[] = 'The requested college update could not be found.';
        $statusCode = 404;
        $editingId = 0;
    } else {
        $mediaStatement = db()->prepare(
            'SELECT id, file_path, media_type, sort_order
             FROM college_update_media
             WHERE update_id = ?
             ORDER BY sort_order, id'
        );
        $mediaStatement->execute([$editingId]);
        $existingMedia = $mediaStatement->fetchAll();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (college_update_post_size_exceeded()) {
        $errors[] = 'The request exceeded the PHP post_max_size setting. Reduce the upload size or increase that setting in XAMPP.';
        $statusCode = 413;
    } else {
        verify_csrf();

        if ($action === 'save') {
            $title = trim(college_update_input_string($_POST, 'title'));
            $description = trim(college_update_input_string($_POST, 'description'));
            $category = trim(college_update_input_string($_POST, 'category'));
            $publicationDateInput = trim(college_update_input_string($_POST, 'published_at'));
            $venue = trim(college_update_input_string($_POST, 'venue'));
            $registrationUrl = trim(college_update_input_string($_POST, 'registration_url'));
            $contactInfo = trim(college_update_input_string($_POST, 'contact_info'));
            $publicationStatus = college_update_input_string($_POST, 'status');

            if (mb_strlen($title) < 4 || mb_strlen($title) > 180) {
                $errors[] = 'Title must be 4 to 180 characters.';
            }

            if (mb_strlen($description) < 20 || mb_strlen($description) > 20000) {
                $errors[] = 'Description must be 20 to 20,000 characters.';
            }

            if (!in_array($category, $categories, true)) {
                $errors[] = 'Choose a valid college update category.';
            }

            $publicationDate = DateTimeImmutable::createFromFormat(
                '!Y-m-d\TH:i',
                $publicationDateInput
            );
            $dateErrors = DateTimeImmutable::getLastErrors();
            if (
                !$publicationDate
                || ($dateErrors !== false
                    && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                || $publicationDate->format('Y-m-d\TH:i') !== $publicationDateInput
            ) {
                $errors[] = 'Enter a valid publication date and time.';
            }

            if (mb_strlen($venue) > 180) {
                $errors[] = 'Venue must be 180 characters or fewer.';
            }

            if (
                mb_strlen($registrationUrl) > 2048
                || !college_update_valid_registration_url($registrationUrl)
            ) {
                $errors[] = 'Registration URL must be a valid HTTP or HTTPS link.';
            }

            if (mb_strlen($contactInfo) > 255) {
                $errors[] = 'Contact details must be 255 characters or fewer.';
            }

            if (!in_array($publicationStatus, college_update_statuses(), true)) {
                $errors[] = 'Choose a valid update status.';
            }

            $removeIds = [];
            $requestedRemovals = $_POST['remove_media'] ?? [];
            if (!is_array($requestedRemovals)) {
                $errors[] = 'The selected media removal request is invalid.';
            } else {
                foreach ($requestedRemovals as $requestedId) {
                    $validId = filter_var($requestedId, FILTER_VALIDATE_INT);
                    if ($validId === false || $validId < 1) {
                        $errors[] = 'The selected media removal request is invalid.';
                        continue;
                    }

                    $removeIds[] = (int) $validId;
                }
            }
            $removeIds = array_values(array_unique($removeIds));

            foreach ($removeIds as $removeId) {
                $belongsToUpdate = false;
                foreach ($existingMedia as $media) {
                    if ((int) $media['id'] === $removeId) {
                        $belongsToUpdate = true;
                        break;
                    }
                }
                if (!$belongsToUpdate) {
                    $errors[] = 'A selected media file does not belong to this update.';
                }
            }

            $uploadResult = college_update_collect_uploads();
            $errors = array_merge($errors, $uploadResult['errors']);
            $remainingMedia = array_values(array_filter(
                $existingMedia,
                static fn (array $media): bool => !in_array(
                    (int) $media['id'],
                    $removeIds,
                    true
                )
            ));

            $remainingImages = count(array_filter(
                $remainingMedia,
                static fn (array $media): bool => $media['media_type'] === 'image'
            ));
            $remainingVideos = count(array_filter(
                $remainingMedia,
                static fn (array $media): bool => $media['media_type'] === 'video'
            ));

            if ($remainingImages + $uploadResult['image_count'] > COLLEGE_UPDATE_MAX_IMAGES) {
                $errors[] = 'An update can have up to five images. Remove an existing image before adding more.';
            }

            if ($remainingVideos + $uploadResult['video_count'] > 1) {
                $errors[] = 'An update can have only one MP4 video. Remove the existing video before adding another.';
            }

            $formValues = [
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'published_at' => $publicationDateInput,
                'venue' => $venue,
                'registration_url' => $registrationUrl,
                'contact_info' => $contactInfo,
            ];

            if ($errors) {
                $statusCode = 422;
            } else {
                $storedMedia = [];
                $transactionStarted = false;

                try {
                    $storedMedia = college_update_store_uploads(
                        $uploadResult['files']
                    );

                    db()->beginTransaction();
                    $transactionStarted = true;
                    $publishedAt = $publicationDate->format('Y-m-d H:i:s');
                    $eventAt = $publishedAt;

                    if ($editingUpdate) {
                        db()->prepare(
                            'UPDATE college_updates
                             SET title = ?,
                                 description = ?,
                                 category = ?,
                                 event_at = ?,
                                 venue = ?,
                                 registration_url = ?,
                                 contact_info = ?,
                                 status = ?,
                                 published_at = ?
                             WHERE id = ?'
                        )->execute([
                            $title,
                            $description,
                            $category,
                            $eventAt,
                            $venue === '' ? null : $venue,
                            $registrationUrl === '' ? null : $registrationUrl,
                            $contactInfo === '' ? null : $contactInfo,
                            $publicationStatus,
                            $publishedAt,
                            (int) $editingUpdate['id'],
                        ]);
                        $updateId = (int) $editingUpdate['id'];
                    } else {
                        db()->prepare(
                            'INSERT INTO college_updates (
                                title, description, category, event_at, venue,
                                registration_url, contact_info, admin_id,
                                status, published_at
                             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        )->execute([
                            $title,
                            $description,
                            $category,
                            $eventAt,
                            $venue === '' ? null : $venue,
                            $registrationUrl === '' ? null : $registrationUrl,
                            $contactInfo === '' ? null : $contactInfo,
                            (int) $admin['id'],
                            $publicationStatus,
                            $publishedAt,
                        ]);
                        $updateId = (int) db()->lastInsertId();
                    }

                    $removedPaths = [];
                    foreach ($removeIds as $removeId) {
                        foreach ($existingMedia as $media) {
                            if ((int) $media['id'] === $removeId) {
                                $removedPaths[] = $media['file_path'];
                                break;
                            }
                        }
                        db()->prepare(
                            'DELETE FROM college_update_media
                             WHERE id = ? AND update_id = ?'
                        )->execute([$removeId, $updateId]);
                    }

                    $nextOrder = 0;
                    foreach ($remainingMedia as $media) {
                        $nextOrder = max($nextOrder, (int) $media['sort_order'] + 1);
                    }

                    $insertMedia = db()->prepare(
                        'INSERT INTO college_update_media
                            (update_id, file_path, media_type, sort_order)
                         VALUES (?, ?, ?, ?)'
                    );
                    foreach ($storedMedia as $media) {
                        $insertMedia->execute([
                            $updateId,
                            $media['file_path'],
                            $media['media_type'],
                            $nextOrder++,
                        ]);
                    }

                    db()->commit();
                    $transactionStarted = false;
                    college_update_remove_media_files($removedPaths);

                    audit_log(
                        (int) $admin['id'],
                        $editingUpdate ? 'update_college_update' : 'create_college_update',
                        'college_update',
                        $updateId,
                        'Status: ' . $publicationStatus
                    );

                    set_flash(
                        'success',
                        $publicationStatus === 'published'
                            ? 'College update published.'
                            : ($publicationStatus === 'archived'
                                ? 'College update archived.'
                                : 'College update saved as a draft.')
                    );
                    redirect('college_updates.php');
                } catch (Throwable $exception) {
                    if ($transactionStarted && db()->inTransaction()) {
                        db()->rollBack();
                    }
                    college_update_remove_media_files(
                        array_column($storedMedia, 'file_path')
                    );
                    error_log('College update save failed: ' . $exception->getMessage());
                    $errors[] = 'The update could not be saved. Check the server logs and try again.';
                    $statusCode = 500;
                }
            }
        } elseif ($action === 'set_status') {
            $statusId = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $nextStatus = college_update_input_string($_POST, 'status');

            if ($statusId === false || !in_array($nextStatus, college_update_statuses(), true)) {
                $errors[] = 'Choose a valid update and status.';
                $statusCode = 400;
            } else {
                $existsStatement = db()->prepare(
                    'SELECT status FROM college_updates WHERE id = ? LIMIT 1'
                );
                $existsStatement->execute([$statusId]);
                $previousStatus = $existsStatement->fetchColumn();

                if (!is_string($previousStatus)) {
                    $errors[] = 'The requested college update could not be found.';
                    $statusCode = 404;
                } else {
                    db()->prepare(
                        'UPDATE college_updates SET status = ? WHERE id = ?'
                    )->execute([$nextStatus, $statusId]);
                    audit_log(
                        (int) $admin['id'],
                        $nextStatus === 'published'
                            ? 'publish_college_update'
                            : ($nextStatus === 'archived'
                                ? 'archive_college_update'
                                : 'unpublish_college_update'),
                        'college_update',
                        (int) $statusId,
                        'Status: ' . $previousStatus . ' -> ' . $nextStatus
                    );
                    set_flash(
                        'success',
                        $nextStatus === 'published'
                            ? 'College update published.'
                            : ($nextStatus === 'archived'
                                ? 'College update archived.'
                                : 'College update unpublished and saved as a draft.')
                    );
                    redirect('college_updates.php');
                }
            }
        } elseif ($action === 'delete') {
            $deleteId = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($deleteId === false) {
                $errors[] = 'Choose a valid update to delete.';
                $statusCode = 400;
            } else {
                $transactionStarted = false;
                try {
                    db()->beginTransaction();
                    $transactionStarted = true;
                    $mediaStatement = db()->prepare(
                        'SELECT file_path
                         FROM college_update_media
                         WHERE update_id = ?
                         FOR UPDATE'
                    );
                    $mediaStatement->execute([$deleteId]);
                    $paths = $mediaStatement->fetchAll(PDO::FETCH_COLUMN);

                    $deleteStatement = db()->prepare(
                        'DELETE FROM college_updates WHERE id = ?'
                    );
                    $deleteStatement->execute([$deleteId]);

                    if ($deleteStatement->rowCount() !== 1) {
                        db()->rollBack();
                        $transactionStarted = false;
                        $errors[] = 'The requested college update could not be found.';
                        $statusCode = 404;
                    } else {
                        db()->commit();
                        $transactionStarted = false;
                        college_update_remove_media_files($paths);
                        audit_log(
                            (int) $admin['id'],
                            'delete_college_update',
                            'college_update',
                            (int) $deleteId
                        );
                        set_flash('success', 'College update deleted.');
                        redirect('college_updates.php');
                    }
                } catch (Throwable $exception) {
                    if ($transactionStarted && db()->inTransaction()) {
                        db()->rollBack();
                    }
                    error_log('College update deletion failed: ' . $exception->getMessage());
                    $errors[] = 'The update could not be deleted. Check the server logs and try again.';
                    $statusCode = 500;
                }
            }
        } else {
            $errors[] = 'The requested update action is not supported.';
            $statusCode = 400;
        }
    }
}

$formValues = [
    'title' => (string) ($editingUpdate['title'] ?? ''),
    'description' => (string) ($editingUpdate['description'] ?? ''),
    'category' => (string) ($editingUpdate['category'] ?? $categories[0]),
    'published_at' => $editingUpdate
        ? str_replace(
            ' ',
            'T',
            substr(
                (string) ($editingUpdate['published_at'] ?? $editingUpdate['created_at']),
                0,
                16
            )
        )
        : date('Y-m-d\TH:i'),
    'venue' => (string) ($editingUpdate['venue'] ?? ''),
    'registration_url' => (string) ($editingUpdate['registration_url'] ?? ''),
    'contact_info' => (string) ($editingUpdate['contact_info'] ?? ''),
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save' && isset($formValues)) {
    foreach ($formValues as $key => $value) {
        if (isset($formValues[$key]) && is_string($_POST[$key] ?? null)) {
            $formValues[$key] = trim((string) $_POST[$key]);
        }
    }
}

$checkedRemoveIds = array_map(
    'intval',
    is_array($_POST['remove_media'] ?? null) ? $_POST['remove_media'] : []
);

$searchTitle = trim(college_update_input_string($_GET, 'q'));
$searchTitle = function_exists('mb_substr')
    ? mb_substr($searchTitle, 0, 100)
    : substr($searchTitle, 0, 100);
$selectedCategory = college_update_input_string($_GET, 'category');
$selectedStatus = college_update_input_string($_GET, 'status');
$listWhere = [];
$listParams = [];

if ($searchTitle !== '') {
    $listWhere[] = 'update_post.title LIKE :update_title';
    $listParams[':update_title'] = '%' . $searchTitle . '%';
}
if (in_array($selectedCategory, $categories, true)) {
    $listWhere[] = 'update_post.category = :update_category';
    $listParams[':update_category'] = $selectedCategory;
} else {
    $selectedCategory = '';
}
if (in_array($selectedStatus, college_update_statuses(), true)) {
    $listWhere[] = 'update_post.status = :update_status';
    $listParams[':update_status'] = $selectedStatus;
} else {
    $selectedStatus = '';
}

$listWhereSql = $listWhere ? ' WHERE ' . implode(' AND ', $listWhere) : '';
$listResult = getPaginatedRecords(
    'SELECT COUNT(*) FROM college_updates update_post' . $listWhereSql,
    'SELECT
        update_post.id,
        update_post.title,
        update_post.category,
        COALESCE(update_post.published_at, update_post.created_at) AS publication_date,
        update_post.status,
        update_post.created_at,
        admin.full_name AS publisher_name
     FROM college_updates update_post
     LEFT JOIN users admin ON admin.id = update_post.admin_id
     ' . $listWhereSql . '
     ORDER BY update_post.created_at DESC, update_post.id DESC
     LIMIT :pagination_limit OFFSET :pagination_offset',
    $listParams,
    20
);
$updateItems = $listResult['records'];
$pagination = $listResult['pagination'];

if ($statusCode !== 200) {
    http_response_code($statusCode);
}

require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">
    <span class="eyebrow">CAMPUS CONTENT</span>
    <h1>Manage College Updates</h1>
    <p>Create, preview, publish, and manage official campus updates.</p>
</section>
<?php include __DIR__ . '/admin-nav.php'; ?>
<section class="section-block">
    <?php if ($errors): ?>
        <div class="update-form-errors" role="alert" data-update-errors>
            <strong>We could not complete this action.</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="update-editor-layout">
        <section class="update-editor">
            <h2><?= $editingUpdate ? 'Edit college update' : 'Create college update' ?></h2>

            <?php if ($existingMedia): ?>
                <div>
                    <h3>Current media</h3>
                    <div class="update-media-list">
                        <?php foreach ($existingMedia as $media): ?>
                            <?php
                            $isChecked = in_array(
                                (int) $media['id'],
                                $checkedRemoveIds,
                                true
                            );
                            ?>
                            <div class="update-media-item">
                                <?php if ($media['media_type'] === 'video'): ?>
                                    <video controls preload="metadata">
                                        <source src="<?= e($media['file_path']) ?>" type="video/mp4">
                                    </video>
                                <?php else: ?>
                                    <img src="<?= e($media['file_path']) ?>" alt="Current event media">
                                <?php endif; ?>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="remove_media[]"
                                        value="<?= (int) $media['id'] ?>"
                                        form="college-update-form"
                                        <?= $isChecked ? 'checked' : '' ?>
                                    >
                                    Remove media
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form
                id="college-update-form"
                class="update-form"
                method="post"
                action="college_updates.php"
                enctype="multipart/form-data"
                data-update-form
            >
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($editingUpdate['id'] ?? 0) ?>">
                <input
                    type="hidden"
                    name="status"
                    value="draft"
                    data-publication-status
                >

                <label for="update-title">
                    Update title
                    <input
                        id="update-title"
                        name="title"
                        maxlength="180"
                        minlength="4"
                        required
                        value="<?= e($formValues['title']) ?>"
                    >
                </label>

                <label for="update-description">
                    Full description
                    <textarea
                        id="update-description"
                        name="description"
                        maxlength="20000"
                        minlength="20"
                        required
                    ><?= e($formValues['description']) ?></textarea>
                </label>

                <label for="update-category-select">
                    Category
                    <select id="update-category-select" name="category" required>
                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= e($category) ?>"
                                <?= $formValues['category'] === $category ? 'selected' : '' ?>
                            ><?= e($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label for="update-published-at">
                    Publication date and time
                    <input
                        id="update-published-at"
                        name="published_at"
                        type="datetime-local"
                        required
                        value="<?= e($formValues['published_at']) ?>"
                    >
                </label>

                <label for="update-venue">
                    Venue
                    <input
                        id="update-venue"
                        name="venue"
                        maxlength="180"
                        value="<?= e($formValues['venue']) ?>"
                    >
                </label>

                <label for="update-registration">
                    Registration URL
                    <input
                        id="update-registration"
                        name="registration_url"
                        type="url"
                        maxlength="2048"
                        placeholder="https://"
                        value="<?= e($formValues['registration_url']) ?>"
                    >
                </label>

                <label for="update-contact">
                    Contact details
                    <input
                        id="update-contact"
                        name="contact_info"
                        maxlength="255"
                        value="<?= e($formValues['contact_info']) ?>"
                    >
                </label>

                <label for="update-images">
                    Featured image and optional gallery
                    <input
                        id="update-images"
                        name="images[]"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        multiple
                    >
                </label>
                <p class="update-form-help">JPG, JPEG, PNG, or WebP. Up to five images, 8 MB each.</p>

                <label for="update-video">
                    Event video
                    <input
                        id="update-video"
                        name="video"
                        type="file"
                        accept=".mp4,video/mp4"
                    >
                </label>
                <p class="update-form-help">MP4 only, up to 30 MB. Total new media per save must be 35 MB or less.</p>

                <div class="update-progress-wrap">
                    <progress
                        class="update-progress"
                        value="0"
                        max="100"
                        data-upload-progress
                        hidden
                    ></progress>
                    <span data-upload-progress-label aria-live="polite"></span>
                    <span class="updates-sr-status" data-upload-status role="status"></span>
                </div>

                <div class="update-form-actions">
                    <button class="btn btn-secondary" type="submit" name="status" value="draft">
                        Save as draft
                    </button>
                    <button class="btn btn-primary" type="submit" name="status" value="published">
                        <?= ($editingUpdate['status'] ?? '') === 'published'
                            ? 'Save and publish'
                            : 'Publish update' ?>
                    </button>
                    <button class="btn btn-secondary" type="submit" name="status" value="archived">
                        Save as archived
                    </button>
                    <?php if ($editingUpdate): ?>
                        <a
                            class="btn btn-secondary"
                            href="../college_update.php?id=<?= (int) $editingUpdate['id'] ?><?= $editingUpdate['status'] === 'published' ? '' : '&amp;preview=1' ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >Preview post</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <aside class="update-live-preview">
            <h2>Live preview</h2>
            <?php
            $previewMedia = $existingMedia[0] ?? null;
            ?>
            <div
                class="update-live-media"
                data-preview-media
                <?= $previewMedia
                    ? 'data-existing-src="' . e($previewMedia['file_path'])
                        . '" data-existing-type="' . e($previewMedia['media_type']) . '"'
                    : '' ?>
            >
                <?php if ($previewMedia && $previewMedia['media_type'] === 'video'): ?>
                    <video controls preload="metadata">
                        <source src="<?= e($previewMedia['file_path']) ?>" type="video/mp4">
                    </video>
                <?php elseif ($previewMedia): ?>
                    <img src="<?= e($previewMedia['file_path']) ?>" alt="Current event preview">
                <?php endif; ?>
            </div>
            <span class="update-category" data-preview-category><?= e($formValues['category']) ?></span>
            <h3 data-preview-title><?= e($formValues['title'] ?: 'Update title preview') ?></h3>
            <p data-preview-description><?= e($formValues['description'] ?: 'The event description will appear here.') ?></p>
            <time class="update-meta" data-preview-date>
                <?= e(date('M j, Y, g:i A', strtotime($formValues['published_at']))) ?>
            </time>
        </aside>
    </div>

    <div class="section-heading compact" style="margin-top:42px">
        <div>
            <h2>All college updates</h2>
            <p>Search, filter, and manage published posts, drafts, and archived updates.</p>
        </div>
    </div>

    <form class="updates-toolbar" method="get" action="college_updates.php">
        <label for="admin-update-search">
            Search by title
            <input id="admin-update-search" name="q" type="search" maxlength="100" value="<?= e($searchTitle) ?>">
        </label>
        <label for="admin-update-category">
            Category
            <select id="admin-update-category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label for="admin-update-status">
            Status
            <select id="admin-update-status" name="status">
                <option value="">All statuses</option>
                <?php foreach (college_update_statuses() as $status): ?>
                    <option value="<?= e($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Find updates</button>
        <?php if ($searchTitle !== '' || $selectedCategory !== '' || $selectedStatus !== ''): ?>
            <a class="btn btn-secondary" href="college_updates.php">Clear filters</a>
        <?php endif; ?>
    </form>

    <?php if (!$updateItems): ?>
        <div class="empty-state">
            <h3><?= $searchTitle !== '' || $selectedCategory !== '' || $selectedStatus !== ''
                ? 'No matching college updates'
                : 'No college updates yet' ?></h3>
            <p><?= $searchTitle !== '' || $selectedCategory !== '' || $selectedStatus !== ''
                ? 'Try another search or filter.'
                : 'Create a post above to share a college update.' ?></p>
        </div>
    <?php else: ?>
        <div class="updates-admin-list">
            <?php foreach ($updateItems as $item): ?>
                <?php
                $publisherName = trim((string) ($item['publisher_name'] ?? ''));
                if ($publisherName === '') {
                    $publisherName = APP_NAME;
                }
                ?>
                <article class="updates-admin-row">
                    <div>
                        <span class="update-status-chip"><?= e(ucfirst($item['status'])) ?></span>
                        <span class="update-category"><?= e($item['category']) ?></span>
                        <h3><?= e($item['title']) ?></h3>
                        <p>
                            Publication date: <?= e(date('M j, Y, g:i A', strtotime($item['publication_date']))) ?>
                            | Posted by <?= e($publisherName) ?>
                        </p>
                    </div>

                    <div class="update-admin-actions">
                        <a
                            class="btn btn-small btn-secondary"
                            href="../college_update.php?id=<?= (int) $item['id'] ?><?= $item['status'] === 'published' ? '' : '&amp;preview=1' ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >Preview</a>
                        <a
                            class="btn btn-small btn-secondary"
                            href="college_updates.php?edit=<?= (int) $item['id'] ?>"
                        >Edit</a>
                        <?php if ($item['status'] !== 'published'): ?>
                            <form method="post" action="college_updates.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="status" value="published">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button class="btn btn-small btn-primary" type="submit">Publish</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="college_updates.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="status" value="draft">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button class="btn btn-small btn-secondary" type="submit">Unpublish</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($item['status'] !== 'archived'): ?>
                            <form method="post" action="college_updates.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="status" value="archived">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button class="btn btn-small btn-secondary" type="submit">Archive</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="college_updates.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                            <button
                                class="btn btn-small btn-secondary"
                                type="submit"
                                data-confirm-delete
                            >Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?= renderPagination($pagination, 'page', 'per_page', 'College updates') ?>
    <?php endif; ?>
</section>

<script src="../assets/college-updates.js" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
