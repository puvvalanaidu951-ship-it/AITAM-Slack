<?php
require_once __DIR__ . '/../includes/ai_helpers.php';
require_once __DIR__ . '/../includes/openai_moderation.php';

$passed = 0;

function expect_true(bool $condition, string $message): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $passed++;
}

function expect_exception(callable $callback, string $messagePart, string $testName): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        expect_true(strpos($error->getMessage(), $messagePart) !== false, $testName);
        return;
    }
    throw new RuntimeException('FAIL: ' . $testName . ' did not throw.');
}

$moderationSafe = [
    'flagged' => false,
    'flags' => [],
    'categories' => ['harassment' => false, 'violence' => false],
];
$moderationUnsafe = [
    'flagged' => true,
    'flags' => ['violence'],
    'categories' => ['harassment' => false, 'violence' => true],
];
$answerRelevant = [
    'score' => 0.9,
    'relevant' => true,
    'spam' => false,
    'abusive' => false,
    'confidence' => 0.95,
    'reason' => 'Directly answers the question.',
];

expect_true(
    cc_openai_parse_moderation_response([
        'results' => [['flagged' => false, 'categories' => ['harassment' => false, 'violence' => false]]],
    ])['flagged'] === false,
    'safe academic content passes moderation'
);

$unsafe = cc_openai_parse_moderation_response([
    'results' => [['flagged' => true, 'categories' => ['harassment' => false, 'violence' => true]]],
]);
expect_true($unsafe['flagged'] && in_array('violence', $unsafe['flags'], true), 'unsafe category flags are retained');

expect_exception(static fn() => cc_openai_require_api_key(''), 'API key is not configured', 'missing API key fails closed');
expect_exception(static fn() => cc_openai_require_curl(false), 'cURL extension is unavailable', 'missing cURL fails closed');
expect_exception(static fn() => cc_openai_validate_transport_result(false, 'Could not connect', 0), 'Could not connect', 'network failure is reported to server-side handling');
expect_exception(
    static fn() => cc_openai_validate_transport_result(false, 'Operation timed out', 0, 28),
    'category=timeout errno=28',
    'network timeout includes a sanitized transport category'
);

foreach ([401, 429, 500] as $status) {
    expect_exception(
        static fn() => cc_openai_validate_transport_result(
            json_encode(['error' => ['message' => 'Mock API error']]),
            '',
            $status
        ),
        'HTTP ' . $status,
        'HTTP ' . $status . ' is handled as a failed check'
    );
}

expect_exception(
    static fn() => cc_openai_validate_transport_result('{invalid json', '', 200),
    'invalid JSON',
    'invalid successful API JSON is rejected'
);
expect_true(
    strpos(cc_openai_sanitize_error('invalid key sk-ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890'), 'sk-ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890') === false,
    'API-key-shaped error content is redacted'
);

$relevant = cc_openai_parse_relevance_response([
    'choices' => [['message' => ['content' => '{"relevant":true,"score":0.91,"confidence":0.94,"spam":false,"abusive":false,"reason":"Direct answer."}']]],
]);
expect_true($relevant['relevant'] && $relevant['score'] === 0.91, 'contextual relevance response is parsed');

$unrelated = cc_openai_parse_relevance_response([
    'choices' => [['message' => ['content' => '{"relevant":false,"score":0.04,"confidence":0.98,"spam":false,"abusive":false,"reason":"Off topic."}']]],
]);
expect_true(!$unrelated['relevant'] && $unrelated['classification'] === 'irrelevant' && $unrelated['score'] === 0.04, 'legacy safe but unrelated answer response is parsed');

$binarySearchAnswers = [
    'Binary search repeatedly divides a sorted array into halves until it finds the target.',
    'It compares the target with the middle element and continues searching in the appropriate half.',
    'Binary search has O(log n) time complexity on a sorted array.',
    'In Java, compare the middle value; if it is too small, move the left bound right, otherwise move the right bound left.',
    'Use lo and hi indices, calculate mid, then keep the half that can contain the key.',
];
foreach ($binarySearchAnswers as $answerText) {
    $parsed = cc_phase16_answer_relevance(
        'How does binary search work in Java?',
        'Explain the algorithm and how it finds a target.',
        $answerText,
        static function (string $title, string $body, string $answer) use ($answerText): array {
            if ($title !== 'How does binary search work in Java?' || $answer !== $answerText) {
                throw new RuntimeException('Relevance classifier did not receive the actual parent question and answer.');
            }
            return [
                'classification' => 'relevant',
                'score' => 0.9,
                'relevant' => true,
                'confidence' => 0.54,
                'spam' => false,
                'abusive' => false,
                'reason' => 'Directly explains binary search.',
            ];
        }
    );
    expect_true(
        $parsed['classification'] === 'relevant'
        && cc_phase16_answer_can_persist([
            'api_ok' => true,
            'flagged' => false,
        ], $parsed, cc_phase16_answer_decision(
            ['api_ok' => true, 'flagged' => false],
            $parsed
        )),
        'concise, paraphrased, or code-oriented binary-search answer may pass without an arbitrary confidence threshold'
    );
}
$pizza = cc_phase16_answer_relevance(
    'How does binary search work in Java?',
    'Explain the algorithm and how it finds a target.',
    'My favorite food is pizza.',
    static fn(string $title, string $body, string $answer): array => [
        'classification' => 'irrelevant',
        'score' => 0.01,
        'relevant' => false,
        'confidence' => 0.98,
        'spam' => false,
        'abusive' => false,
        'reason' => 'Does not address the question.',
    ]
);
expect_true(
    $pizza['classification'] === 'irrelevant' && !$pizza['relevant'],
    'unrelated answer is explicitly classified as irrelevant'
);
$uncertainRelevance = cc_phase16_answer_relevance(
    'How does binary search work in Java?',
    'Explain the algorithm.',
    'It depends.',
    static fn(string $title, string $body, string $answer): array => [
        'classification' => 'uncertain',
        'score' => 0.5,
        'relevant' => false,
        'confidence' => 0.42,
        'spam' => false,
        'abusive' => false,
        'reason' => 'Insufficient context.',
    ]
);
expect_true(
    $uncertainRelevance['classification'] === 'uncertain'
    && !cc_phase16_answer_can_persist(
        ['api_ok' => true, 'flagged' => false],
        $uncertainRelevance,
        cc_phase16_answer_decision(
            ['api_ok' => true, 'flagged' => false],
            $uncertainRelevance
        )
    ),
    'uncertain relevance is not published'
);

expect_exception(
    static fn() => cc_openai_parse_relevance_response([
        'choices' => [['message' => ['content' => '{"relevant":"yes","score":"unknown"}']]],
    ]),
    'invalid or missing fields',
    'malformed relevance output fails closed'
);
$malformedClassification = cc_phase16_answer_relevance(
    'How does binary search work in Java?',
    'Explain the algorithm.',
    'It halves the sorted search range.',
    static fn(string $title, string $body, string $answer): array => [
        'classification' => 'maybe',
        'score' => 0.8,
        'relevant' => true,
        'confidence' => 0.9,
        'spam' => false,
        'abusive' => false,
        'reason' => 'Malformed classification value.',
    ]
);
expect_true(
    empty($malformedClassification['api_ok'])
    && $malformedClassification['classification'] === 'uncertain'
    && strpos($malformedClassification['diagnostic'], 'invalid result') !== false,
    'malformed relevance classification becomes a logged classification failure, not irrelevant content'
);
expect_exception(
    static fn() => cc_openai_parse_moderation_response(['results' => []]),
    'unexpected structure',
    'missing moderation result is rejected'
);

$risk = cc_phase16_risk_scan(
    'Academic suicide prevention resources',
    'Where can students find the campus support service?',
    static fn(string $text): array => $GLOBALS['moderationSafe']
);
$academicQuestions = [
    ['How does binary search work in Java?', 'Please explain the process and provide a simple example.'],
    ['Explain machine learning algorithms with examples.', 'I would like to understand common algorithms and where each one is useful.'],
    ['What is the difference between HTTP and Express?', 'Please explain how they differ and how they are used in web development.'],
    ['How can I prepare for technical placement interviews?', 'I am looking for a practical study plan and topics to focus on.'],
    ['Explain cybersecurity risks and how to prevent them.', 'Please describe common risks and basic prevention practices for students.'],
];
foreach ($academicQuestions as [$academicTitle, $academicBody]) {
    expect_true(
        !cc_phase16_question_needs_quality_check($academicTitle, $academicBody),
        'clear academic question does not need an additional quality-model call: ' . $academicTitle
    );
    $academicQuality = cc_phase16_question_quality_for_submission($academicTitle, $academicBody);
    $academicDecision = cc_phase16_question_decision($risk, $academicQuality);
    expect_true(
        cc_phase16_question_can_persist($risk, $academicQuality, $academicDecision),
        'safe academic question is publishable after successful safety moderation: ' . $academicTitle
    );
}
$quality = cc_phase16_question_quality(
    'Academic suicide prevention resources',
    'Where can students find the campus support service?',
    static fn(string $title, string $body): array => ['decision' => 'allow', 'confidence' => 0.94, 'reason' => 'Clear, answerable campus question.']
);
$questionDecision = cc_phase16_question_decision($risk, $quality);
expect_true($questionDecision['status'] === 'published' && $questionDecision['moderation_status'] === 'allowed', 'safe academic content mentioning a sensitive term is not blocked by local keyword matches');
expect_true(
    cc_phase16_question_can_persist($risk, $quality, $questionDecision),
    'only a successfully checked safe question can be persisted'
);

$unsafeRisk = cc_phase16_risk_scan(
    'Threat',
    'Unsafe content',
    static fn(string $text): array => $GLOBALS['moderationUnsafe']
);
$unsafeDecision = cc_phase16_question_decision($unsafeRisk, [
    'api_ok' => false,
    'decision' => 'review',
    'confidence' => 0.0,
    'reason' => 'Skipped after safety flag.',
]);
expect_true($unsafeDecision['status'] === 'rejected' && $unsafeDecision['moderation_status'] === 'blocked', 'unsafe question is blocked even when quality classification is skipped');
expect_true(
    !cc_phase16_question_can_persist($unsafeRisk, ['api_ok' => true, 'decision' => 'allow', 'confidence' => 1.0], $unsafeDecision),
    'unsafe question cannot be persisted'
);

$spam = cc_phase16_question_quality(
    'Promotional offer',
    'Buy now, limited offer, visit this unrelated link.',
    static fn(string $title, string $body): array => ['decision' => 'block', 'confidence' => 0.98, 'reason' => 'Obvious promotional spam.']
);
expect_true(
    cc_phase16_question_decision($risk, $spam)['status'] === 'rejected',
    'confident spam question is blocked'
);

$ambiguous = cc_phase16_question_quality(
    'Question',
    'Unclear context',
    static fn(string $title, string $body): array => ['decision' => 'review', 'confidence' => 0.51, 'reason' => 'Intent is unclear.']
);
expect_true(
    cc_phase16_question_decision($risk, $ambiguous)['status'] === 'pending_review',
    'ambiguous question is not accepted'
);
expect_true(
    !cc_phase16_question_can_persist($risk, $ambiguous, cc_phase16_question_decision($risk, $ambiguous)),
    'uncertain question is not persisted'
);
$ambiguousSubmission = cc_phase16_question_quality_for_submission(
    'Need advice',
    'The details are unclear and do not explain what help is needed.',
    static fn(string $title, string $body): array => [
        'decision' => 'review',
        'confidence' => 0.48,
        'reason' => 'The request needs clarification.',
    ]
);
expect_true(
    !cc_phase16_question_can_persist($risk, $ambiguousSubmission, cc_phase16_question_decision($risk, $ambiguousSubmission)),
    'contextually ambiguous question is sent through the classifier and not accepted'
);
$invalidQuality = cc_phase16_question_quality(
    'Question',
    'Context with enough detail.',
    static fn(string $title, string $body): array => ['decision' => 'allow', 'confidence' => 'invalid', 'reason' => 'Bad mock response.']
);
expect_true(
    !cc_phase16_question_can_persist($risk, $invalidQuality, cc_phase16_question_decision($risk, $invalidQuality)),
    'invalid quality response prevents question persistence'
);

$unavailableRisk = cc_phase16_risk_scan(
    'Normal question',
    'Context with enough details.',
    static function (string $text): array {
        throw new RuntimeException('OpenAI API key is not configured.');
    }
);
expect_true(
    cc_phase16_question_decision($unavailableRisk, $quality)['status'] === 'pending_review',
    'missing configuration does not publish unchecked questions'
);
expect_true(
    !cc_phase16_question_can_persist($unavailableRisk, $quality, cc_phase16_question_decision($unavailableRisk, $quality)),
    'OpenAI failure prevents question persistence'
);
expect_true(
    strpos($unavailableRisk['reason'], 'API key') === false,
    'configuration details are not returned as public-facing moderation reason'
);

$safeRelevant = cc_phase16_answer_relevance(
        'How do I reset my campus password?',
        'Where can I find the self-service portal?',
        'Use the password reset option on the campus self-service portal.',
        static fn(string $title, string $body, string $answer): array => $GLOBALS['answerRelevant']
    );
expect_true(
    cc_phase16_answer_can_persist($risk, $safeRelevant, cc_phase16_answer_decision($risk, $safeRelevant)),
    'safe concise, contextually relevant answer can be persisted'
);
expect_true(
    cc_phase16_answer_decision($risk, $unrelated) === 'pending_review',
    'safe unrelated answer is not accepted'
);
expect_true(
    !cc_phase16_answer_can_persist($risk, $unrelated, cc_phase16_answer_decision($risk, $unrelated)),
    'irrelevant answer is not persisted'
);
$unrelatedClassification = cc_phase16_answer_relevance(
    'Where is the campus library?',
    'Which building and floor contains the library?',
    'My favorite sport is basketball.',
    static fn(string $title, string $body, string $answer): array => [
        'score' => 0.01,
        'relevant' => false,
        'spam' => false,
        'abusive' => false,
        'confidence' => 0.98,
        'reason' => 'Unrelated to the question.',
    ]
);
expect_true(
    !cc_phase16_answer_can_persist($risk, $unrelatedClassification, cc_phase16_answer_decision($risk, $unrelatedClassification)),
    'successfully classified irrelevant answer is not persisted'
);
expect_true(
    cc_phase16_answer_decision($unsafeRisk, $answerRelevant) === 'blocked',
    'unsafe answer is blocked from publication'
);
expect_true(
    !cc_phase16_answer_can_persist($unsafeRisk, $answerRelevant, cc_phase16_answer_decision($unsafeRisk, $answerRelevant)),
    'unsafe answer cannot be persisted'
);
expect_true(
    cc_phase16_answer_decision($risk, [
        'api_ok' => true,
        'score' => 0.02,
        'relevant' => false,
        'spam' => true,
        'abusive' => false,
        'confidence' => 0.98,
    ]) === 'blocked',
    'confident answer spam is blocked'
);
expect_true(
    cc_phase16_answer_decision($risk, [
        'api_ok' => false,
        'score' => 0.0,
        'relevant' => false,
        'spam' => false,
        'abusive' => false,
        'confidence' => 0.0,
    ]) === 'pending_review',
    'relevance API failure holds answer for review'
);
expect_true(
    !cc_phase16_answer_can_persist($risk, [
        'api_ok' => false,
        'score' => 0.0,
        'relevant' => false,
        'spam' => false,
        'abusive' => false,
        'confidence' => 0.0,
    ], 'pending_review'),
    'relevance API failure prevents answer persistence'
);

$moderationFailure = cc_phase16_risk_scan(
    'Question',
    'Details',
    static function (string $text): array {
        throw new RuntimeException('OpenAI API returned HTTP 429: mocked rate limit.');
    }
);
expect_true(
    !empty($moderationFailure['diagnostic']) && strpos($moderationFailure['diagnostic'], '429') !== false,
    'sanitized API failure diagnostic is retained for server/admin review'
);

$mockCategories = [
    ['id' => 1, 'name' => 'Admissions', 'slug' => 'admissions', 'description' => 'Applications and admissions.'],
    ['id' => 2, 'name' => 'Academics', 'slug' => 'academics', 'description' => 'Courses and exams.'],
    ['id' => 3, 'name' => 'Placements', 'slug' => 'placements', 'description' => 'Jobs and interviews.'],
    ['id' => 4, 'name' => 'Campus Life', 'slug' => 'campus-life', 'description' => 'Hostel, bus, and clubs.'],
];
$invalidCat = cc_openai_check_category('Title', 'Body', 999, $mockCategories);
expect_true(!$invalidCat['matches_selected'], 'unrecognized category id fails category match');

echo 'Passed ' . $passed . " OpenAI moderation tests.\n";
