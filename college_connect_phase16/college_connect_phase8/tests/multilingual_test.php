<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/multilingual.php';
require_once __DIR__ . '/../includes/openai_moderation.php';

$passed = 0;

function expect_multilingual(bool $condition, string $message): void
{
    global $passed;

    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }

    $passed++;
}

$questions = [
    'en' => [
        'title' => 'How can students prepare for semester exams',
        'body' => 'Which study plan helps students revise the syllabus effectively',
    ],
    'te' => [
        'title' => 'విద్యార్థులు పరీక్షలకు ఎలా సిద్ధం కావాలి',
        'body' => 'పాఠ్యాంశాన్ని సమర్థవంతంగా చదవడానికి ఏ విధానం ఉపయోగపడుతుంది',
    ],
    'hi' => [
        'title' => 'विद्यार्थी परीक्षा की तैयारी कैसे करें',
        'body' => 'पाठ्यक्रम को अच्छी तरह दोहराने के लिए कौन सी योजना उपयोगी है',
    ],
    'ta' => [
        'title' => 'மாணவர்கள் தேர்வுக்கு எவ்வாறு தயாராகலாம்',
        'body' => 'பாடத்திட்டத்தைச் சிறப்பாகப் படிக்க எந்தத் திட்டம் உதவும்',
    ],
    'kn' => [
        'title' => 'ವಿದ್ಯಾರ್ಥಿಗಳು ಪರೀಕ್ಷೆಗೆ ಹೇಗೆ ಸಿದ್ಧರಾಗಬೇಕು',
        'body' => 'ಪಠ್ಯಕ್ರಮವನ್ನು ಚೆನ್ನಾಗಿ ಓದಲು ಯಾವ ಯೋಜನೆ ಸಹಾಯ ಮಾಡುತ್ತದೆ',
    ],
    'or' => [
        'title' => 'ଛାତ୍ରଛାତ୍ରୀମାନେ ପରୀକ୍ଷା ପାଇଁ କିପରି ପ୍ରସ୍ତୁତ ହେବେ',
        'body' => 'ପାଠ୍ୟକ୍ରମକୁ ଭଲଭାବେ ପଢ଼ିବା ପାଇଁ କେଉଁ ଯୋଜନା ସାହାଯ୍ୟ କରିବ',
    ],
];
$answers = [
    'en' => 'A steady study plan and regular practice can help students prepare.',
    'te' => 'నియమిత అధ్యయనం మరియు సాధన విద్యార్థులకు సిద్ధం కావడంలో సహాయపడతాయి.',
    'hi' => 'नियमित अध्ययन और अभ्यास विद्यार्थियों को तैयारी में मदद करते हैं।',
    'ta' => 'தொடர்ந்து படிப்பதும் பயிற்சி செய்வதும் மாணவர்கள் தயாராக உதவும்.',
    'kn' => 'ನಿಯಮಿತ ಅಧ್ಯಯನ ಮತ್ತು ಅಭ್ಯಾಸವು ವಿದ್ಯಾರ್ಥಿಗಳು ಸಿದ್ಧರಾಗಲು ಸಹಾಯ ಮಾಡುತ್ತದೆ.',
    'or' => 'ନିୟମିତ ଅଧ୍ୟୟନ ଓ ଅଭ୍ୟାସ ଛାତ୍ରଛାତ୍ରୀଙ୍କୁ ପ୍ରସ୍ତୁତିରେ ସାହାଯ୍ୟ କରେ।',
];
$languages = cc_multilingual_languages();
expect_multilingual(
    array_keys($languages) === ['en', 'te', 'hi', 'ta', 'kn', 'or'],
    'all supported languages are registered'
);

foreach ($questions as $language => $question) {
    expect_multilingual(
        cc_multilingual_detect_language($question['title'] . ' ' . $question['body']) === $language,
        'script-based language detection recognizes ' . $language
    );

    $risk = cc_phase16_risk_scan(
        $question['title'],
        $question['body'],
        static fn(string $text): array => [
            'flagged' => false,
            'flags' => [],
            'categories' => [],
        ]
    );
    $quality = cc_phase16_question_quality_for_submission(
        $question['title'],
        $question['body'],
        static fn(string $title, string $body): array => [
            'decision' => 'allow',
            'confidence' => 0.96,
            'reason' => 'Clear educational question in the submitted language.',
        ]
    );
    $decision = cc_phase16_question_decision($risk, $quality);
    expect_multilingual(
        cc_phase16_question_can_persist($risk, $quality, $decision),
        'safe educational question passes the existing moderation gate in ' . $language
    );

    $answerText = $answers[$language];
    $answerRisk = cc_phase16_risk_scan(
        '',
        $answerText,
        static fn(string $text): array => [
            'flagged' => false,
            'flags' => [],
            'categories' => [],
        ]
    );
    $relevance = cc_phase16_answer_relevance(
        $question['title'],
        $question['body'],
        $answerText,
        static fn(string $title, string $body, string $answer): array => [
            'score' => 0.9,
            'classification' => 'relevant',
            'relevant' => true,
            'confidence' => 0.9,
            'spam' => false,
            'abusive' => false,
            'reason' => 'Mock multilingual educational answer is relevant.',
        ]
    );
    expect_multilingual(
        cc_phase16_answer_can_persist(
            $answerRisk,
            $relevance,
            cc_phase16_answer_decision($answerRisk, $relevance)
        ),
        'safe relevant answer passes moderation in ' . $language
    );

    $generatedAnswer = cc_openai_generate_answer(
        $question['title'],
        $question['body'],
        $language,
        $language,
        static function (array $payload, string $check) use ($answers, $languages, $language): array {
            if (
                $check !== 'answer_generation'
                || strpos($payload['messages'][0]['content'], $languages[$language]) === false
            ) {
                throw new RuntimeException('Answer prompt did not preserve the requested language.');
            }

            return ['choices' => [['message' => ['content' => $answers[$language]]]]];
        }
    );
    expect_multilingual(
        $generatedAnswer === $answers[$language],
        'AI answer generation targets ' . $language
    );

    $languageCodes = array_keys($languages);
    $targetLanguage = $languageCodes[(array_search($language, $languageCodes, true) + 1) % count($languageCodes)];
    $translatedQuestion = cc_openai_translate_question(
        $question['title'],
        $question['body'],
        $language,
        $targetLanguage,
        static function (array $payload, string $check) use ($languages, $language, $targetLanguage): array {
            if (
                $check !== 'question_translation'
                || strpos($payload['messages'][0]['content'], $languages[$language]) === false
                || strpos($payload['messages'][0]['content'], $languages[$targetLanguage]) === false
            ) {
                throw new RuntimeException('Question translation prompt did not preserve source and target languages.');
            }

            return ['choices' => [[
                'message' => [
                    'content' => json_encode(
                        ['title' => 'Translated title', 'details' => 'Translated details'],
                        JSON_UNESCAPED_UNICODE
                    ),
                ],
            ]]];
        }
    );
    expect_multilingual(
        $translatedQuestion === ['title' => 'Translated title', 'body' => 'Translated details'],
        'question translation preserves title and details as separate values for ' . $language
    );

    $translatedAnswer = cc_openai_translate_text(
        $answerText,
        $language,
        $targetLanguage,
        static function (array $payload, string $check) use ($languages, $targetLanguage): array {
            if (
                $check !== 'content_translation'
                || strpos($payload['messages'][0]['content'], $languages[$targetLanguage]) === false
            ) {
                throw new RuntimeException('Answer translation prompt did not preserve the target language.');
            }

            return ['choices' => [['message' => ['content' => 'Translated answer']]]];
        }
    );
    expect_multilingual(
        $translatedAnswer === 'Translated answer',
        'answer translation targets ' . $language
    );
}

expect_multilingual(
    cc_multilingual_detect_language('A question written in English') === 'en',
    'Latin-script questions default to English'
);
expect_multilingual(
    cc_multilingual_message('translation_failed', 'te') !== cc_multilingual_message('translation_failed', 'en'),
    'failure messages use the selected interface language'
);

echo 'Passed ' . $passed . " multilingual tests.\n";
