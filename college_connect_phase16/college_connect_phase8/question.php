<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/multilingual.php';
require_once __DIR__ . '/includes/ai_helpers.php';
require_once __DIR__ . '/includes/openai_moderation.php';

function cc_redirect_question_answers(int $questionId, string $fragment = 'answers'): never
{
    $requestedPage = filter_var(
        $_GET['answers_page'] ?? 1,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $page = $requestedPage === false ? 1 : $requestedPage;
    $query = buildPaginationUrl(
        $page,
        ['id' => $questionId],
        'answers_page'
    );

    redirect('question.php' . $query . '#' . $fragment);
}

function cc_redirect_question_state(
    int $questionId,
    array $state,
    string $fragment
): never {
    $requestedPage = filter_var(
        $_GET['answers_page'] ?? 1,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($requestedPage !== false && $requestedPage > 1) {
        $state['answers_page'] = $requestedPage;
    }
    $requestedPerPage = filter_var(
        $_GET['answers_per_page'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if (
        $requestedPerPage !== false
        && in_array($requestedPerPage, [10, 20, 50, 100], true)
    ) {
        $state['answers_per_page'] = $requestedPerPage;
    }
    $requestedQuestionTranslation = $_GET['question_translation'] ?? null;
    if (
        !isset($state['question_translation'])
        && is_string($requestedQuestionTranslation)
        && isset(cc_multilingual_languages()[$requestedQuestionTranslation])
    ) {
        $state['question_translation'] = $requestedQuestionTranslation;
    }
    $state['id'] = $questionId;
    redirect(
        'question.php?' . http_build_query($state)
        . '#' . rawurlencode($fragment)
    );
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && array_key_exists('ui_language', $_GET)
) {
    $_SESSION['cc_interface_language'] = cc_multilingual_normalize_language(
        $_GET['ui_language']
    );
    redirect('question.php?id=' . $id);
}
$interfaceLanguage = cc_multilingual_current_interface_language();
$answerDraftKey = 'cc_answer_draft_' . $id;
$answerBody = is_string($_SESSION[$answerDraftKey] ?? null)
    ? $_SESSION[$answerDraftKey]
    : '';
$user = current_user();
$errors = [];

$stmt = db()->prepare(
    'SELECT
        q.*,
        c.name AS category_name,
        COALESCE(u.full_name, "Community member") AS author_name,
        u.id AS author_id
     FROM questions q
     JOIN categories c ON c.id = q.category_id
     LEFT JOIN users u ON u.id = q.user_id
     WHERE q.id = ?
       AND q.status = "published"
       AND q.moderation_status = "allowed"
     LIMIT 1'
);

$stmt->execute([$id]);
$question = $stmt->fetch();

if (!$question) {
    http_response_code(404);
    $pageTitle = 'Question not found';

    require __DIR__ . '/includes/header.php';

    echo '<section class="section-block">
        <div class="empty-state">
            <h2>Question not found</h2>
            <a class="btn btn-primary" href="questions.php">Browse questions</a>
        </div>
    </section>';

    require __DIR__ . '/includes/footer.php';
    exit;
}
$questionLanguage = cc_multilingual_effective_language(
    $question,
    (string) $question['title'] . "\n" . (string) $question['body']
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    verify_csrf();

    $interfaceLanguage = cc_multilingual_normalize_language(
        $_POST['interface_language'] ?? $interfaceLanguage
    );
    $_SESSION['cc_interface_language'] = $interfaceLanguage;
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'translate_question') {
        $targetLanguage = $_POST['target_language'] ?? null;
        if (!is_string($targetLanguage) || !isset(cc_multilingual_languages()[$targetLanguage])) {
            set_flash('error', cc_multilingual_message('unsupported_language', $interfaceLanguage));
            cc_redirect_question_answers($id, 'question-translation');
        }
        if ($targetLanguage === $questionLanguage) {
            set_flash('error', cc_multilingual_message('same_language', $interfaceLanguage));
            cc_redirect_question_answers($id, 'question-translation');
        }

        $cachedTranslation = db()->prepare(
            'SELECT id FROM question_translations
             WHERE question_id = ? AND language_code = ?
             LIMIT 1'
        );
        $cachedTranslation->execute([$id, $targetLanguage]);
        if ($cachedTranslation->fetchColumn()) {
            set_flash('success', cc_multilingual_message('translation_saved', $interfaceLanguage));
            cc_redirect_question_state(
                $id,
                ['question_translation' => $targetLanguage],
                'question-translation-result'
            );
        }

        try {
            $translated = cc_openai_translate_question(
                (string) $question['title'],
                (string) $question['body'],
                $questionLanguage,
                $targetLanguage
            );
            $translationRisk = cc_phase16_risk_scan(
                $translated['title'],
                $translated['body']
            );
            if (empty($translationRisk['api_ok']) || !empty($translationRisk['flagged'])) {
                throw new RuntimeException('Translated question did not pass safety checks.');
            }

            db()->prepare(
                'INSERT INTO question_translations
                    (question_id, language_code, title, body, created_by)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    body = VALUES(body),
                    created_by = VALUES(created_by)'
            )->execute([
                $id,
                $targetLanguage,
                $translated['title'],
                $translated['body'],
                (int) $user['id'],
            ]);
            set_flash('success', cc_multilingual_message('translation_saved', $interfaceLanguage));
            cc_redirect_question_state(
                $id,
                ['question_translation' => $targetLanguage],
                'question-translation-result'
            );
        } catch (Throwable $error) {
            error_log('AITAM SLACK question translation failed: ' . cc_openai_sanitize_error($error->getMessage()));
            set_flash('error', cc_multilingual_message('translation_failed', $interfaceLanguage));
            cc_redirect_question_answers($id, 'question-translation');
        }
    } elseif ($action === 'translate_answer') {
        $answerId = filter_var(
            $_POST['answer_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $targetLanguage = $_POST['target_language'] ?? null;
        if (
            $answerId === false
            || !is_string($targetLanguage)
            || !isset(cc_multilingual_languages()[$targetLanguage])
        ) {
            set_flash('error', cc_multilingual_message('unsupported_language', $interfaceLanguage));
            cc_redirect_question_answers($id, 'answers');
        }

        $answerSource = db()->prepare(
            'SELECT id, body, language_code
             FROM answers
             WHERE id = ? AND question_id = ? AND status = "published"
             LIMIT 1'
        );
        $answerSource->execute([$answerId, $id]);
        $sourceAnswer = $answerSource->fetch();
        if (!$sourceAnswer) {
            set_flash('error', cc_multilingual_message('translation_failed', $interfaceLanguage));
            cc_redirect_question_answers($id, 'answers');
        }
        $sourceLanguage = cc_multilingual_effective_language(
            $sourceAnswer,
            (string) $sourceAnswer['body']
        );
        if ($targetLanguage === $sourceLanguage) {
            set_flash('error', cc_multilingual_message('same_language', $interfaceLanguage));
            cc_redirect_question_answers($id, 'answer-' . $answerId);
        }

        $cachedTranslation = db()->prepare(
            'SELECT id FROM answer_translations
             WHERE answer_id = ? AND language_code = ?
             LIMIT 1'
        );
        $cachedTranslation->execute([$answerId, $targetLanguage]);
        if ($cachedTranslation->fetchColumn()) {
            set_flash('success', cc_multilingual_message('translation_saved', $interfaceLanguage));
            cc_redirect_question_state(
                $id,
                [
                    'translated_answer_id' => $answerId,
                    'answer_translation' => $targetLanguage,
                ],
                'answer-translation-' . $answerId
            );
        }

        try {
            $translatedText = cc_openai_translate_text(
                (string) $sourceAnswer['body'],
                $sourceLanguage,
                $targetLanguage
            );
            $translationRisk = cc_phase16_risk_scan('', $translatedText);
            if (empty($translationRisk['api_ok']) || !empty($translationRisk['flagged'])) {
                throw new RuntimeException('Translated answer did not pass safety checks.');
            }

            db()->prepare(
                'INSERT INTO answer_translations
                    (answer_id, language_code, translated_text, created_by)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    translated_text = VALUES(translated_text),
                    created_by = VALUES(created_by)'
            )->execute([
                (int) $answerId,
                $targetLanguage,
                $translatedText,
                (int) $user['id'],
            ]);
            set_flash('success', cc_multilingual_message('translation_saved', $interfaceLanguage));
            cc_redirect_question_state(
                $id,
                [
                    'translated_answer_id' => $answerId,
                    'answer_translation' => $targetLanguage,
                ],
                'answer-translation-' . $answerId
            );
        } catch (Throwable $error) {
            error_log('AITAM SLACK answer translation failed: ' . cc_openai_sanitize_error($error->getMessage()));
            set_flash('error', cc_multilingual_message('translation_failed', $interfaceLanguage));
            cc_redirect_question_answers($id, 'answer-' . (int) $answerId);
        }
    } elseif ($action === 'answer' || $action === 'generate_ai_answer') {
        $diagnosticId = cc_phase16_diagnostic_id();

        cc_phase16_log_diagnostic(
            $diagnosticId,
            'answer_submission',
            [
                'stage' => 'submission_start',
                'request_reached_openai' => false
            ]
        );

        $isAiGenerated = $action === 'generate_ai_answer';
        $submittedAnswer = $_POST['body'] ?? '';
        $body = is_string($submittedAnswer) ? $submittedAnswer : '';

        if ($isAiGenerated) {
            $requestedLanguage = $_POST['answer_language'] ?? 'original';
            $answerLanguage = $requestedLanguage === 'original'
                ? $questionLanguage
                : (is_string($requestedLanguage) ? $requestedLanguage : '');
            if (!isset(cc_multilingual_languages()[$answerLanguage])) {
                set_flash('error', cc_multilingual_message('unsupported_language', $interfaceLanguage));
                cc_redirect_question_answers($id, 'ai-answer-form');
            }
            try {
                $body = cc_openai_generate_answer(
                    (string) $question['title'],
                    (string) $question['body'],
                    $questionLanguage,
                    $answerLanguage
                );
            } catch (Throwable $error) {
                error_log('AITAM SLACK AI answer generation failed: ' . cc_openai_sanitize_error($error->getMessage()));
                set_flash('error', cc_multilingual_message('generation_failed', $interfaceLanguage));
                cc_redirect_question_answers($id, 'ai-answer-form');
            }
        } else {
            $answerLanguage = cc_multilingual_detect_language($body);
            $_SESSION[$answerDraftKey] = $body;
            $answerBody = $body;
        }

        if (!mb_check_encoding($body, 'UTF-8')) {
            $errors[] = cc_multilingual_message('invalid_encoding', $interfaceLanguage);
        } elseif (mb_strlen(trim($body)) < 5 || mb_strlen($body) > 10000) {
            $errors[] = cc_multilingual_message('answer_length', $interfaceLanguage);
        }

        if (!$errors) {
            $risk = cc_phase16_risk_scan(
                '',
                $body,
                null,
                $diagnosticId
            );

            $relevance = !empty($risk['api_ok']) && !empty($risk['flagged'])
                ? [
                    'score' => 0.0,
                    'classification' => 'uncertain',
                    'relevant' => false,
                    'spam' => false,
                    'abusive' => false,
                    'confidence' => 0.0,
                    'matched' => [],
                    'reason' => 'Relevance check skipped after a safety block.',
                    'api_ok' => false,
                    'diagnostic' => ''
                ]
                : (
                    empty($risk['api_ok'])
                    ? [
                        'score' => 0.0,
                        'classification' => 'uncertain',
                        'relevant' => false,
                        'spam' => false,
                        'abusive' => false,
                        'confidence' => 0.0,
                        'matched' => [],
                        'reason' => 'Relevance check skipped because safety moderation did not complete.',
                        'api_ok' => false,
                        'diagnostic' => $risk['diagnostic'] ?? ''
                    ]
                    : cc_phase16_answer_relevance(
                        (string) $question['title'],
                        (string) $question['body'],
                        $body,
                        null,
                        $diagnosticId
                    )
                );

            $safetyUnavailable = empty($risk['api_ok']);

            $decision = cc_phase16_answer_decision(
                $risk,
                $relevance
            );

            $checksUnavailable = $safetyUnavailable
                || (
                    !empty($risk['api_ok'])
                    && empty($risk['flagged'])
                    && empty($relevance['api_ok'])
                );

            if (!cc_phase16_answer_can_persist($risk, $relevance, $decision)) {
                $reasonCode = $safetyUnavailable
                    ? 'safety_unavailable'
                    : (
                        !empty($risk['flagged'])
                        ? 'safety_flagged'
                        : (
                            empty($relevance['api_ok'])
                            ? 'relevance_unavailable'
                            : (
                                !empty($relevance['spam']) || !empty($relevance['abusive'])
                                ? 'spam_or_abuse'
                                : (
                                    (
                                        $relevance['classification']
                                        ?? (!empty($relevance['relevant']) ? 'relevant' : 'irrelevant')
                                    ) === 'irrelevant'
                                    ? 'answer_irrelevant'
                                    : 'relevance_uncertain'
                                )
                            )
                        )
                    );

                if ($checksUnavailable) {
                    cc_phase16_log_submission_decision(
                        'answer',
                        (int) $user['id'],
                        'retry',
                        $reasonCode,
                        false
                    );

                    cc_phase16_log_diagnostic(
                        $diagnosticId,
                        'answer_submission',
                        [
                            'stage' => 'checks_rejected_or_unavailable',
                            'moderation_flagged' => !empty($risk['flagged']),
                            'relevance_decision_valid' => !empty($relevance['api_ok']),
                            'database_insert_succeeded' => false,
                            'error_category' => $reasonCode
                        ]
                    );

                    set_flash(
                        'error',
                        cc_multilingual_message('answer_checks_unavailable', $interfaceLanguage)
                        . ' Reference: ' . $diagnosticId
                    );

                    cc_redirect_question_answers($id);
                }

                $ansStatus = ($decision === 'blocked')
                    ? 'blocked'
                    : 'pending_review';

                $escalatedAnswerId = 0;

                try {
                    db()->beginTransaction();

                    $parentCheck = db()->prepare(
                        'SELECT id
                         FROM questions
                         WHERE id = ?
                           AND status = "published"
                           AND moderation_status = "allowed"
                         FOR UPDATE'
                    );

                    $parentCheck->execute([$id]);

                    if (!$parentCheck->fetchColumn()) {
                        throw new RuntimeException(
                            'The parent question is no longer available for answers.'
                        );
                    }

                    $ins = db()->prepare(
                        'INSERT INTO answers
                            (question_id, user_id, body, language_code, is_ai_generated, status)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );

                    $ins->execute([
                        $id,
                        (int) $user['id'],
                        $body,
                        $answerLanguage,
                        $isAiGenerated ? 1 : 0,
                        $ansStatus
                    ]);

                    $escalatedAnswerId = (int) db()->lastInsertId();

                    cc_ai_save_answer_review(
                        $escalatedAnswerId,
                        $id,
                        $risk,
                        $relevance,
                        $decision
                    );

                    cc_phase16_record_moderation(
                        'answer',
                        $escalatedAnswerId,
                        $risk,
                        $relevance,
                        $ansStatus
                    );

                    db()->commit();
                } catch (Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }

                    error_log(
                        'AITAM SLACK escalated answer insertion failed: '
                        . $e->getMessage()
                    );
                }

                if ($escalatedAnswerId < 1) {
                    cc_phase16_log_submission_decision(
                        'answer',
                        (int) $user['id'],
                        'retry',
                        $reasonCode,
                        true
                    );
                    set_flash(
                        'error',
                        cc_multilingual_message('answer_save_failed', $interfaceLanguage)
                    );
                    cc_redirect_question_answers($id);
                }
                unset($_SESSION[$answerDraftKey]);

                cc_phase16_log_submission_decision(
                    'answer',
                    (int) $user['id'],
                    'blocked',
                    $reasonCode,
                    true
                );

                cc_phase16_log_diagnostic(
                    $diagnosticId,
                    'answer_submission',
                    [
                        'stage' => 'checks_escalated_or_blocked',
                        'moderation_flagged' => !empty($risk['flagged']),
                        'relevance_decision_valid' => !empty($relevance['api_ok']),
                        'database_insert_succeeded' => $escalatedAnswerId > 0,
                        'error_category' => $reasonCode
                    ]
                );

                if (
                    !empty($risk['flagged'])
                    || !empty($relevance['spam'])
                    || !empty($relevance['abusive'])
                ) {
                    set_flash(
                        'error',
                        cc_multilingual_message('answer_blocked', $interfaceLanguage)
                    );
                } elseif (
                    (
                        $relevance['classification']
                        ?? (!empty($relevance['relevant']) ? 'relevant' : 'irrelevant')
                    ) === 'irrelevant'
                ) {
                    set_flash(
                        'error',
                        cc_multilingual_message('answer_irrelevant', $interfaceLanguage)
                    );
                } else {
                    set_flash(
                        'error',
                        cc_multilingual_message('answer_review', $interfaceLanguage)
                    );
                }

                cc_redirect_question_answers($id);
            }

            try {
                db()->beginTransaction();

                $parentCheck = db()->prepare(
                    'SELECT id
                     FROM questions
                     WHERE id = ?
                       AND status = "published"
                       AND moderation_status = "allowed"
                     FOR UPDATE'
                );

                $parentCheck->execute([$id]);

                if (!$parentCheck->fetchColumn()) {
                    throw new RuntimeException(
                        'The parent question is no longer available for answers.'
                    );
                }

                $ins = db()->prepare(
                    'INSERT INTO answers
                        (question_id, user_id, body, language_code, is_ai_generated, status)
                     VALUES (?, ?, ?, ?, ?, "published")'
                );

                $ins->execute([
                    $id,
                    (int) $user['id'],
                    $body,
                    $answerLanguage,
                    $isAiGenerated ? 1 : 0,
                ]);

                $answerId = (int) db()->lastInsertId();

                cc_ai_save_answer_review(
                    $answerId,
                    $id,
                    $risk,
                    $relevance,
                    $decision
                );

                cc_phase16_record_moderation(
                    'answer',
                    $answerId,
                    $risk,
                    $relevance,
                    'published'
                );

                if (
                    !empty($question['author_id'])
                    && (int) $question['author_id'] !== (int) $user['id']
                ) {
                    db()->prepare(
                        'INSERT INTO notifications
                            (user_id, type, title, message, related_type, related_id)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([
                        (int) $question['author_id'],
                        'answer',
                        cc_multilingual_message(
                            'answer_notification_title',
                            $questionLanguage
                        ),
                        ($isAiGenerated
                            ? cc_multilingual_message('ai_answer_notification', $questionLanguage)
                            : cc_multilingual_message('new_answer_notification', $questionLanguage))
                            . $question['title'],
                        'question',
                        $id
                    ]);
                }

                db()->commit();
                unset($_SESSION[$answerDraftKey]);

                cc_phase16_log_diagnostic(
                    $diagnosticId,
                    'answer_submission',
                    [
                        'stage' => 'database_commit',
                        'database_insert_succeeded' => true,
                        'moderation_flagged' => false,
                        'relevance_decision_valid' => true
                    ]
                );

                set_flash(
                    'success',
                    cc_multilingual_message('answer_saved', $interfaceLanguage)
                );

                cc_redirect_question_answers($id);
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }

                $redact = [
                    (string) $body,
                    (string) $question['title'],
                    (string) $question['body']
                ];

                $redact = array_values(
                    array_filter(
                        $redact,
                        static fn($value) => $value !== ''
                    )
                );

                $safeError = cc_openai_sanitize_error(
                    str_replace(
                        $redact,
                        '[content redacted]',
                        $e->getMessage()
                    )
                );

                cc_phase16_log_diagnostic(
                    $diagnosticId,
                    'answer_submission',
                    [
                        'stage' => 'database_or_transaction_failure',
                        'database_insert_succeeded' => false,
                        'sqlstate' => $e instanceof PDOException
                            ? (string) $e->getCode()
                            : '',
                        'error_category' => get_class($e),
                        'error_message' => $safeError
                    ]
                );

                $errors[] = cc_multilingual_message(
                    'answer_save_failed',
                    $interfaceLanguage
                ) . ' Reference: ' . $diagnosticId;
            }
        }
    } elseif ($action === 'bookmark') {
        $check = db()->prepare(
            'SELECT id
             FROM question_bookmarks
             WHERE user_id = ? AND question_id = ?'
        );

        $check->execute([(int) $user['id'], $id]);

        if ($check->fetch()) {
            db()->prepare(
                'DELETE FROM question_bookmarks
                 WHERE user_id = ? AND question_id = ?'
            )->execute([(int) $user['id'], $id]);

            set_flash('success', 'Question removed from your saved list.');
        } else {
            db()->prepare(
                'INSERT INTO question_bookmarks (user_id, question_id)
                 VALUES (?, ?)'
            )->execute([(int) $user['id'], $id]);

            set_flash('success', 'Question saved to your bookmarks.');
        }

        cc_redirect_question_answers($id);
    } elseif ($action === 'vote') {
        $aid = filter_input(INPUT_POST, 'answer_id', FILTER_VALIDATE_INT) ?: 0;
        $vote = (int) ($_POST['vote'] ?? 0);

        if (!in_array($vote, [-1, 1], true)) {
            set_flash('error', 'Invalid vote.');
            cc_redirect_question_answers($id);
        }

        $check = db()->prepare(
            'SELECT id, user_id
             FROM answers
             WHERE id = ? AND question_id = ? AND status = "published"'
        );

        $check->execute([$aid, $id]);
        $votedAnswer = $check->fetch();

        if (!$votedAnswer) {
            set_flash('error', 'That answer is no longer available.');
            cc_redirect_question_answers($id);
        }

        if ((int) $votedAnswer['user_id'] === (int) $user['id']) {
            set_flash('error', 'You cannot vote on your own answer.');
            cc_redirect_question_answers($id);
        }

        $existing = db()->prepare(
            'SELECT vote
             FROM answer_votes
             WHERE answer_id = ? AND user_id = ?'
        );

        $existing->execute([$aid, (int) $user['id']]);
        $old = $existing->fetchColumn();

        if ((int) $old === $vote) {
            db()->prepare(
                'DELETE FROM answer_votes
                 WHERE answer_id = ? AND user_id = ?'
            )->execute([$aid, (int) $user['id']]);
        } else {
            db()->prepare(
                'INSERT INTO answer_votes (answer_id, user_id, vote)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE vote = VALUES(vote)'
            )->execute([$aid, (int) $user['id'], $vote]);
        }

        set_flash('success', 'Your feedback has been saved.');
        cc_redirect_question_answers($id, 'answer-' . $aid);
    } elseif ($action === 'accept') {
        if (
            (int) ($question['author_id'] ?? 0) !== (int) $user['id']
            && $user['role'] !== 'admin'
        ) {
            http_response_code(403);
            exit('Only the question author can accept an answer.');
        }

        $aid = filter_input(INPUT_POST, 'answer_id', FILTER_VALIDATE_INT) ?: 0;

        $check = db()->prepare(
            'SELECT id, user_id
             FROM answers
             WHERE id = ? AND question_id = ? AND status = "published"'
        );

        $check->execute([$aid, $id]);
        $a = $check->fetch();

        if ($a) {
            db()->beginTransaction();

            try {
                db()->prepare(
                    'UPDATE answers SET is_accepted = 0 WHERE question_id = ?'
                )->execute([$id]);

                db()->prepare(
                    'UPDATE answers
                     SET is_accepted = 1
                     WHERE id = ? AND question_id = ?'
                )->execute([$aid, $id]);

                if (
                    $a['user_id']
                    && (int) $a['user_id'] !== (int) $user['id']
                ) {
                    db()->prepare(
                        'INSERT INTO notifications
                            (user_id, type, title, message, related_type, related_id)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([
                        (int) $a['user_id'],
                        'accepted',
                        'Your answer was accepted',
                        'Your answer was accepted for: ' . $question['title'],
                        'question',
                        $id
                    ]);
                }

                db()->commit();

                set_flash('success', 'Accepted answer updated.');
            } catch (Throwable $e) {
                db()->rollBack();
                set_flash('error', 'Could not update accepted answer.');
            }
        }

        cc_redirect_question_answers($id);
    }
}

$answerResult = getPaginatedRecords(
    'SELECT COUNT(*)
     FROM answers
     WHERE question_id = :answer_count_question_id
       AND status = "published"',
    'SELECT
        a.id,
        a.question_id,
        a.user_id,
        a.body,
        a.language_code,
        a.is_ai_generated,
        a.status,
        a.is_accepted,
        a.created_at,
        COALESCE(u.full_name, "Community member") AS author_name,
        u.id AS author_id,
        u.role AS author_role,
        (
            SELECT COALESCE(SUM(v.vote), 0)
            FROM answer_votes v
            WHERE v.answer_id = a.id
        ) AS vote_score,
        (
            SELECT v.vote
            FROM answer_votes v
           WHERE v.answer_id = a.id
             AND v.user_id = :answer_vote_user_id
        ) AS my_vote
     FROM answers a
     LEFT JOIN users u ON u.id = a.user_id
     WHERE a.question_id = :answer_question_id
       AND a.status = "published"
     ORDER BY a.is_accepted DESC, a.created_at ASC, a.id ASC
     LIMIT :pagination_limit OFFSET :pagination_offset',
    [
        ':answer_vote_user_id' => $user ? (int) $user['id'] : 0,
        ':answer_question_id' => $id,
    ],
    10,
    [10, 20, 50, 100],
    'answers_page',
    'answers_per_page',
    [':answer_count_question_id' => $id]
);
$answers = $answerResult['records'];
$answerPagination = $answerResult['pagination'];
$questionTranslation = null;
$requestedQuestionTranslation = $_GET['question_translation'] ?? '';
if (
    is_string($requestedQuestionTranslation)
    && isset(cc_multilingual_languages()[$requestedQuestionTranslation])
) {
    $translationStatement = db()->prepare(
        'SELECT title, body
         FROM question_translations
         WHERE question_id = ? AND language_code = ?
         LIMIT 1'
    );
    $translationStatement->execute([$id, $requestedQuestionTranslation]);
    $questionTranslation = $translationStatement->fetch() ?: null;
}
$answerTranslation = null;
$requestedTranslatedAnswerId = filter_var(
    $_GET['translated_answer_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$requestedAnswerTranslation = $_GET['answer_translation'] ?? '';
if (
    $requestedTranslatedAnswerId !== false
    && is_string($requestedAnswerTranslation)
    && isset(cc_multilingual_languages()[$requestedAnswerTranslation])
) {
    foreach ($answers as $answer) {
        if ((int) $answer['id'] === (int) $requestedTranslatedAnswerId) {
            $translationStatement = db()->prepare(
                'SELECT translated_text
                 FROM answer_translations
                 WHERE answer_id = ? AND language_code = ?
                 LIMIT 1'
            );
            $translationStatement->execute([
                (int) $requestedTranslatedAnswerId,
                $requestedAnswerTranslation,
            ]);
            $answerTranslation = $translationStatement->fetchColumn();
            break;
        }
    }
}

$pageTitle = 'Question details';
$saved = false;

if ($user) {
    $bs = db()->prepare(
        'SELECT id
         FROM question_bookmarks
         WHERE user_id = ? AND question_id = ?'
    );

    $bs->execute([(int) $user['id'], $id]);
    $saved = (bool) $bs->fetch();
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <span class="eyebrow"><?= e($question['category_name']) ?></span>
    <h1><?= e($question['title']) ?></h1>
    <p>
        Asked by <?= e($question['author_name']) ?>
        · <?= e(time_ago($question['created_at'])) ?>
    </p>
</section>

<section class="section-block">
    <form method="get" action="question.php" class="inline-form">
        <input type="hidden" name="id" value="<?= $id ?>">
        <label for="ui-language">Language for messages</label>
        <select id="ui-language" name="ui_language">
            <?php foreach (cc_multilingual_languages() as $code => $name): ?>
                <option value="<?= e($code) ?>" <?= $interfaceLanguage === $code ? 'selected' : '' ?>>
                    <?= e($name) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-small btn-secondary" type="submit">Set language</button>
    </form>

    <article class="account-card question-detail">
        <p><?= nl2br(e($question['body'])) ?></p>
        <p class="question-meta">
            <span class="topic-pill">
                <?= e(cc_multilingual_languages()[$questionLanguage]) ?>
            </span>
            Original question
        </p>

        <?php if ($user): ?>
            <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="interface_language" value="<?= e($interfaceLanguage) ?>">
                <input type="hidden" name="action" value="bookmark">

                <button class="btn btn-secondary btn-small" type="submit">
                    <?= icon_svg('bookmark') ?>
                    <?= $saved ? 'Saved - remove bookmark' : 'Save question' ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($questionTranslation): ?>
            <section id="question-translation-result" class="account-card">
                <h2>Translation: <?= e(cc_multilingual_languages()[$requestedQuestionTranslation]) ?></h2>
                <h3><?= e($questionTranslation['title']) ?></h3>
                <p><?= nl2br(e($questionTranslation['body'])) ?></p>
                <p class="muted">Original question remains unchanged.</p>
            </section>
        <?php endif; ?>

        <?php if ($user): ?>
            <form method="post" class="inline-form" id="question-translation">
                <?= csrf_field() ?>
                <input type="hidden" name="interface_language" value="<?= e($interfaceLanguage) ?>">
                <input type="hidden" name="action" value="translate_question">
                <label for="question-translation-language">Translate question to</label>
                <select id="question-translation-language" name="target_language">
                    <?php $questionTranslationDefault = $questionLanguage === 'en' ? 'te' : 'en'; ?>
                    <?php foreach (cc_multilingual_languages() as $code => $name): ?>
                        <?php if ($code !== $questionLanguage): ?>
                            <option value="<?= e($code) ?>" <?= $code === $questionTranslationDefault ? 'selected' : '' ?>>
                                <?= e($name) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-small btn-secondary" type="submit">Translate question</button>
            </form>
        <?php else: ?>
            <p><a href="login.php">Log in</a> to translate this question.</p>
        <?php endif; ?>

        <div class="question-meta">
            <span class="topic-pill"><?= e($question['category_name']) ?></span>
            <span><?= (int) $answerPagination['total'] ?> answers</span>
        </div>
    </article>

    <div id="answers" class="section-heading compact">
        <div>
            <h2>Community answers</h2>
            <p>Share helpful, respectful guidance.</p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$answers): ?>
        <div class="empty-state">
            <h3>No answers yet</h3>
            <p>Be the first to help.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($answers as $answer): ?>
        <?php
        $answerLanguage = cc_multilingual_effective_language(
            $answer,
            (string) $answer['body']
        );
        $answerTranslationDefault = $answerLanguage === 'en' ? 'te' : 'en';
        ?>
        <article
            id="answer-<?= (int) $answer['id'] ?>"
            class="account-card answer-card"
        >
            <?php if ($answer['is_accepted']): ?>
                <span class="topic-pill accepted-pill">
                    <?= icon_svg('check-circle') ?>
                    Accepted answer
                </span>
            <?php endif; ?>

            <p><?= nl2br(e($answer['body'])) ?></p>
            <p class="question-meta">
                <span class="topic-pill"><?= e(cc_multilingual_languages()[$answerLanguage]) ?></span>
                <?= $answer['is_ai_generated'] ? 'AI-generated answer' : 'Original answer' ?>
            </p>

            <?php if (
                $answerTranslation !== null
                && (int) $answer['id'] === (int) $requestedTranslatedAnswerId
            ): ?>
                <section
                    class="account-card"
                    id="answer-translation-<?= (int) $answer['id'] ?>"
                >
                    <h3>Translation: <?= e(cc_multilingual_languages()[$requestedAnswerTranslation]) ?></h3>
                    <p><?= nl2br(e((string) $answerTranslation)) ?></p>
                    <p class="muted">Original answer remains unchanged.</p>
                </section>
            <?php endif; ?>

            <?php if ($user): ?>
                <form
                    method="post"
                    class="inline-form"
                    id="translate-answer-form-<?= (int) $answer['id'] ?>"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="interface_language" value="<?= e($interfaceLanguage) ?>">
                    <input type="hidden" name="action" value="translate_answer">
                    <input type="hidden" name="answer_id" value="<?= (int) $answer['id'] ?>">
                    <label for="answer-translation-language-<?= (int) $answer['id'] ?>">Translate answer to</label>
                    <select
                        id="answer-translation-language-<?= (int) $answer['id'] ?>"
                        name="target_language"
                    >
                        <?php foreach (cc_multilingual_languages() as $code => $name): ?>
                            <?php if ($code !== $answerLanguage): ?>
                                <option value="<?= e($code) ?>" <?= $code === $answerTranslationDefault ? 'selected' : '' ?>>
                                    <?= e($name) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-small btn-secondary" type="submit">Translate answer</button>
                </form>
            <?php endif; ?>

            <div class="question-meta">
                <span><?= e($answer['author_name']) ?></span>
                <span><?= e($answer['author_role'] ?? 'community') ?></span>
                <span><?= e(time_ago($answer['created_at'])) ?></span>
                <span>Helpful score: <?= (int) $answer['vote_score'] ?></span>
            </div>

            <?php if ($user && (int) ($answer['author_id'] ?? 0) !== (int) $user['id']): ?>
                <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="interface_language" value="<?= e($interfaceLanguage) ?>">
                    <input type="hidden" name="action" value="vote">
                    <input
                        type="hidden"
                        name="answer_id"
                        value="<?= (int) $answer['id'] ?>"
                    >

                    <button
                        class="btn btn-small btn-secondary"
                        name="vote"
                        value="1"
                        type="submit"
                        <?= ((int) ($answer['my_vote'] ?? 0) === 1) ? 'disabled' : '' ?>
                    >
                        <?= icon_svg('thumbs-up') ?>
                        Helpful
                    </button>

                    <button
                        class="btn btn-small btn-secondary"
                        name="vote"
                        value="-1"
                        type="submit"
                        <?= ((int) ($answer['my_vote'] ?? 0) === -1) ? 'disabled' : '' ?>
                    >
                        <?= icon_svg('thumbs-down') ?>
                        Not helpful
                    </button>

                    <?php if ($answer['my_vote']): ?>
                        <span class="topic-pill">
                            Your vote:
                            <?= ((int) $answer['my_vote'] === 1) ? 'Helpful' : 'Not helpful' ?>
                        </span>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <?php if (
                $user
                && (
                    (int) ($question['author_id'] ?? 0) === (int) $user['id']
                    || $user['role'] === 'admin'
                )
                && !$answer['is_accepted']
            ): ?>
                <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="interface_language" value="<?= e($interfaceLanguage) ?>">
                    <input type="hidden" name="action" value="accept">
                    <input
                        type="hidden"
                        name="answer_id"
                        value="<?= (int) $answer['id'] ?>"
                    >
                    <button class="btn btn-small btn-secondary" type="submit">
                        Accept answer
                    </button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <?= renderPagination(
        $answerPagination,
        'answers_page',
        'answers_per_page',
        'Answers',
        'answers'
    ) ?>

    <div id="answer-form" class="account-card">
        <h2>Your answer</h2>
        <p class="muted">
            Answers in all six supported languages are checked for safety, spam, and relevance before they are saved.
            If checks are unavailable, the answer will not be saved. Uncertain answers may be held for moderator review.
        </p>

        <?php if (!$user): ?>
            <p><a href="login.php">Log in</a> to post an answer.</p>
        <?php else: ?>
            <form method="post" class="account-form" id="ai-answer-form">
                <?= csrf_field() ?>
                <input type="hidden" name="interface_language" value="<?= e($interfaceLanguage) ?>">
                <input type="hidden" name="action" value="generate_ai_answer">
                <label for="answer-language">AI answer language</label>
                <select id="answer-language" name="answer_language">
                    <option value="original">
                        Match question language: <?= e(cc_multilingual_languages()[$questionLanguage]) ?>
                    </option>
                    <?php foreach (cc_multilingual_languages() as $code => $name): ?>
                        <?php if ($code !== $questionLanguage): ?>
                            <option value="<?= e($code) ?>"><?= e($name) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <p class="form-note">
                    AI answers are checked by the same safety and relevance system as community answers.
                    Verify important details with official college sources.
                </p>
                <button class="btn btn-secondary" type="submit">Generate AI answer</button>
            </form>

            <form method="post" class="account-form">
                <?= csrf_field() ?>
                <input type="hidden" name="interface_language" value="<?= e($interfaceLanguage) ?>">
                <input type="hidden" name="action" value="answer">

                <label for="body">Answer details</label>
                <textarea
                    id="body"
                    name="body"
                    rows="5"
                    minlength="5"
                    maxlength="10000"
                    required
                    placeholder="Write a helpful answer..."
                ><?= e($answerBody) ?></textarea>

                <button class="btn btn-primary" type="submit">
                    Post answer ↗
                </button>
            </form>
        <?php endif; ?>
    </div>

    <a class="filter-chip" href="questions.php">← Back to questions</a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>