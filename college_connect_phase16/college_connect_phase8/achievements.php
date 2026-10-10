
<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Community Achievements';
$loadError = false;

$sql = "SELECT
    u.id,
    u.full_name,
    u.role,
    (SELECT COUNT(*) FROM answers a
        WHERE a.user_id = u.id AND a.status = 'published') AS answers_count,
    (SELECT COUNT(*) FROM answers a
        WHERE a.user_id = u.id
        AND a.status = 'published'
        AND a.is_accepted = 1) AS accepted_count,
    (SELECT COUNT(*) FROM answer_votes v
        INNER JOIN answers a ON a.id = v.answer_id
        WHERE a.user_id = u.id
        AND a.status = 'published'
        AND v.vote = 1) AS helpful_count,
    (SELECT COUNT(*) FROM question_bookmarks b
        WHERE b.user_id = u.id) AS bookmarks_count
    FROM users u
    WHERE u.status = 'active'
    AND u.role <> 'admin'
    ORDER BY u.full_name ASC";

try {
    $rows = db()->query($sql)->fetchAll();
} catch (Throwable $ex) {
    $rows = [];
    $loadError = true;
    error_log('Achievements query failed: ' . $ex->getMessage());
}

function cc_badges(array $r): array
{
    $badges = [];
    $answers = (int) $r['answers_count'];
    $accepted = (int) $r['accepted_count'];
    $helpful = (int) $r['helpful_count'];
    $bookmarks = (int) $r['bookmarks_count'];

    if ($answers >= 1) {
        $badges[] = [
            'First Answer',
            'Posted a published answer',
            '💬',
            'blue'
        ];
    }

    if ($answers >= 10) {
        $badges[] = [
            'Knowledge Sharer',
            'Published 10 answers',
            '📚',
            'purple'
        ];
    }

    if ($accepted >= 1) {
        $badges[] = [
            'Solution Finder',
            'Had an answer accepted',
            '✅',
            'green'
        ];
    }

    if ($accepted >= 5) {
        $badges[] = [
            'Problem Solver',
            'Had 5 answers accepted',
            '🧠',
            'orange'
        ];
    }

    if ($helpful >= 10) {
        $badges[] = [
            'Community Helper',
            'Received 10 helpful votes',
            '🤝',
            'teal'
        ];
    }

    if ($helpful >= 50) {
        $badges[] = [
            'Trusted Mentor',
            'Received 50 helpful votes',
            '🏆',
            'gold'
        ];
    }

    if ($bookmarks >= 5) {
        $badges[] = [
            'Curator',
            'Saved 5 or more questions',
            '🔖',
            'pink'
        ];
    }

    return $badges;
}

require __DIR__ . '/includes/header.php';
?>

<style>
/* ========================================
   COMMUNITY ACHIEVEMENTS PAGE
   Scoped styles to avoid changing other pages
======================================== */

.achievements-page {
    --ach-navy: #0f2854;
    --ach-blue: #1c4d8d;
    --ach-accent: #4988c4;
    --ach-light: #bde8f5;
    --ach-text: #172e55;
    --ach-muted: #657b99;
    --ach-border: #dce7f3;
    --ach-surface: #ffffff;

    width: 100%;
    max-width: 1240px;
    margin: 0 auto;
    padding: 28px 24px 44px;
    box-sizing: border-box;
    color: var(--ach-text);
}

/* Hero section */

.achievements-page .ach-hero {
    position: relative;
    padding: 32px;
    margin-bottom: 30px;
    overflow: hidden;
    border: 1px solid rgba(73, 136, 196, 0.2);
    border-radius: 20px;
    background:
        radial-gradient(
            circle at 90% 10%,
            rgba(189, 232, 245, 0.55),
            transparent 34%
        ),
        linear-gradient(135deg, #ffffff 0%, #edf6ff 100%);
    box-shadow: 0 8px 30px rgba(15, 40, 84, 0.06);
}

.achievements-page .ach-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    color: var(--ach-blue);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.7px;
    text-transform: uppercase;
}

.achievements-page .ach-eyebrow::before {
    content: "";
    width: 24px;
    height: 3px;
    border-radius: 4px;
    background: var(--ach-accent);
}

.achievements-page .ach-hero h1 {
    margin: 0 0 12px;
    color: var(--ach-navy);
    font-size: clamp(28px, 4vw, 40px);
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -1px;
}

.achievements-page .ach-hero p {
    max-width: 620px;
    margin: 0;
    color: var(--ach-muted);
    font-size: 15px;
    line-height: 1.8;
}

/* Section heading */

.achievements-page .ach-section {
    margin-top: 28px;
}

.achievements-page .ach-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 22px;
}

.achievements-page .ach-section-heading h2 {
    margin: 0 0 7px;
    color: var(--ach-navy);
    font-size: 26px;
    font-weight: 800;
    line-height: 1.3;
    letter-spacing: -0.5px;
}

.achievements-page .ach-section-heading p {
    margin: 0;
    color: var(--ach-muted);
    font-size: 14px;
    line-height: 1.7;
}

.achievements-page .ach-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    min-width: 38px;
    height: 38px;
    padding: 0 12px;
    border: 1px solid #d6e6f6;
    border-radius: 12px;
    background: #edf6ff;
    color: var(--ach-blue);
    font-size: 13px;
    font-weight: 800;
    box-sizing: border-box;
}

/* Responsive member-card grid */

.achievements-page .ach-member-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
    width: 100%;
    align-items: stretch;
}

/* Individual member card */

.achievements-page .ach-member-card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 24px;
    border: 1px solid var(--ach-border);
    border-radius: 18px;
    background: var(--ach-surface);
    box-shadow: 0 5px 20px rgba(15, 40, 84, 0.045);
    box-sizing: border-box;
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        border-color 0.25s ease;
    animation: achFadeUp 0.45s ease both;
}

.achievements-page .ach-member-card:hover {
    transform: translateY(-4px);
    border-color: #a9cbea;
    box-shadow: 0 14px 34px rgba(15, 40, 84, 0.10);
}

.achievements-page .ach-member-top {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    margin-bottom: 22px;
}

.achievements-page .ach-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 52px;
    width: 52px;
    height: 52px;
    border: 1px solid #cfe3f6;
    border-radius: 15px;
    background: linear-gradient(135deg, #e8f4ff, #cfeaff);
    color: var(--ach-blue);
    font-size: 21px;
    font-weight: 800;
    text-transform: uppercase;
}

.achievements-page .ach-member-info {
    flex: 1;
    min-width: 0;
}

.achievements-page .ach-member-info h3 {
    margin: 0 0 6px;
    color: var(--ach-navy);
    font-size: 17px;
    font-weight: 800;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.achievements-page .ach-member-role {
    display: inline-flex;
    align-items: center;
    padding: 4px 9px;
    border-radius: 6px;
    background: #f0f5fb;
    color: var(--ach-muted);
    font-size: 11px;
    font-weight: 700;
    line-height: 1.4;
}

/* Statistics: equal-width aligned boxes */

.achievements-page .ach-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 24px;
}

.achievements-page .ach-stat {
    min-width: 0;
    padding: 13px 8px;
    border: 1px solid #e5edf6;
    border-radius: 11px;
    background: #f7faff;
    text-align: center;
    box-sizing: border-box;
}

.achievements-page .ach-stat strong {
    display: block;
    margin-bottom: 5px;
    color: var(--ach-navy);
    font-size: 21px;
    font-weight: 800;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}

.achievements-page .ach-stat span {
    display: block;
    color: var(--ach-muted);
    font-size: 11px;
    font-weight: 600;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

/* Badge section */

.achievements-page .ach-badge-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 13px;
}

.achievements-page .ach-badge-heading h4 {
    margin: 0;
    color: var(--ach-navy);
    font-size: 13px;
    font-weight: 800;
}

.achievements-page .ach-badge-total {
    color: var(--ach-muted);
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

/* Badge tiles use a grid instead of narrow inline pills */

.achievements-page .ach-badge-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    align-content: start;
}

.achievements-page .ach-badge {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    min-width: 0;
    min-height: 74px;
    padding: 12px;
    border: 1px solid #e2ebf5;
    border-radius: 12px;
    background: #fbfdff;
    box-sizing: border-box;
    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease;
}

.achievements-page .ach-badge:hover {
    transform: translateY(-2px);
    background: #f1f7ff;
    border-color: #bfd7f0;
}

.achievements-page .ach-badge-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 34px;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #eaf3ff;
    font-size: 17px;
    line-height: 1;
}

.achievements-page .ach-badge-copy {
    flex: 1;
    min-width: 0;
    padding-top: 1px;
}

.achievements-page .ach-badge-copy strong {
    display: block;
    margin: 0 0 5px;
    color: var(--ach-navy);
    font-size: 12px;
    font-weight: 800;
    line-height: 1.45;
    overflow-wrap: anywhere;
}

.achievements-page .ach-badge-copy span {
    display: block;
    color: var(--ach-muted);
    font-size: 11px;
    line-height: 1.55;
    overflow-wrap: anywhere;
}

/* Badge variations */

.achievements-page .ach-badge.blue .ach-badge-icon {
    background: #e6f1ff;
}

.achievements-page .ach-badge.purple .ach-badge-icon {
    background: #f0eaff;
}

.achievements-page .ach-badge.green .ach-badge-icon {
    background: #e2f8ec;
}

.achievements-page .ach-badge.orange .ach-badge-icon {
    background: #fff0df;
}

.achievements-page .ach-badge.teal .ach-badge-icon {
    background: #dff8f5;
}

.achievements-page .ach-badge.gold .ach-badge-icon {
    background: #fff5d8;
}

.achievements-page .ach-badge.pink .ach-badge-icon {
    background: #ffeaf2;
}

/* Empty state and error */

.achievements-page .ach-empty {
    padding: 42px 24px;
    border: 1px dashed #bfd3e8;
    border-radius: 16px;
    background: #f8fbff;
    text-align: center;
}

.achievements-page .ach-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 54px;
    height: 54px;
    margin: 0 auto 16px;
    border-radius: 16px;
    background: #e8f3ff;
    font-size: 25px;
}

.achievements-page .ach-empty h3 {
    margin: 0 0 10px;
    color: var(--ach-navy);
    font-size: 19px;
    font-weight: 800;
}

.achievements-page .ach-empty p {
    max-width: 480px;
    margin: 0 auto 20px;
    color: var(--ach-muted);
    font-size: 13px;
    line-height: 1.8;
}

.achievements-page .ach-empty .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 18px;
    border-radius: 9px;
    text-decoration: none;
    font-weight: 700;
}

/* Footer note */

.achievements-page .ach-note {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 24px;
    padding: 15px 17px;
    border: 1px solid #dce8f5;
    border-radius: 12px;
    background: #f5f9ff;
    color: var(--ach-muted);
    font-size: 12px;
    line-height: 1.8;
}

.achievements-page .ach-note-icon {
    flex: 0 0 auto;
}

/* Animations */

@keyframes achFadeUp {
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
    .achievements-page {
        padding: 24px 20px 36px;
    }

    .achievements-page .ach-member-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 18px;
    }

    .achievements-page .ach-hero {
        padding: 26px;
    }
}

/* Mobile */

@media (max-width: 480px) {
    .achievements-page {
        padding: 18px 14px 28px;
    }

    .achievements-page .ach-hero {
        padding: 22px 18px;
        margin-bottom: 24px;
        border-radius: 15px;
    }

    .achievements-page .ach-hero h1 {
        font-size: 27px;
    }

    .achievements-page .ach-hero p {
        font-size: 13px;
    }

    .achievements-page .ach-section-heading {
        gap: 10px;
        align-items: flex-start;
    }

    .achievements-page .ach-section-heading h2 {
        font-size: 22px;
    }

    .achievements-page .ach-section-heading p {
        font-size: 12px;
    }

    .achievements-page .ach-member-card {
        padding: 17px;
        border-radius: 15px;
    }

    .achievements-page .ach-stats {
        gap: 7px;
    }

    .achievements-page .ach-stat {
        padding: 11px 4px;
    }

    .achievements-page .ach-stat strong {
        font-size: 18px;
    }

    .achievements-page .ach-stat span {
        font-size: 10px;
    }

    .achievements-page .ach-badge-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .achievements-page .ach-badge {
        min-height: 64px;
    }
}

/* Accessibility */

@media (prefers-reduced-motion: reduce) {
    .achievements-page *,
    .achievements-page *::before,
    .achievements-page *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
</style>

<div class="achievements-page">

    <section class="ach-hero">
        <span class="ach-eyebrow">Recognizing Contributions</span>

        <h1>Community Achievements</h1>

        <p>
            Celebrate learning, useful answers, and positive contributions
            to AITAM SLACK. Every helpful answer brings our community closer.
        </p>
    </section>

    <section class="ach-section">

        <div class="ach-section-heading">
            <div>
                <h2>Member badges</h2>
                <p>
                    Badges are calculated from published answers,
                    accepted solutions, and community feedback.
                </p>
            </div>

            <?php if (!$loadError): ?>
                <span class="ach-count">
                    <?php
                    $eligibleCount = 0;

                    foreach ($rows as $member) {
                        if (count(cc_badges($member)) > 0) {
                            $eligibleCount++;
                        }
                    }

                    echo $eligibleCount;
                    ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($loadError): ?>

            <div class="ach-empty">
                <div class="ach-empty-icon">⚠️</div>
                <h3>Achievements are temporarily unavailable</h3>
                <p>
                    Check that the required database migrations and
                    answer-voting tables are installed correctly.
                </p>
            </div>

        <?php else: ?>

            <?php
            $shown = 0;
            ?>

            <div class="ach-member-grid">

                <?php foreach ($rows as $r): ?>

                    <?php
                    $badges = cc_badges($r);

                    if (!$badges) {
                        continue;
                    }

                    $shown++;

                    $name = trim((string) $r['full_name']);
                    $initial = function_exists('mb_substr')
                        ? mb_substr($name, 0, 1, 'UTF-8')
                        : substr($name, 0, 1);
                    ?>

                    <article class="ach-member-card">

                        <div class="ach-member-top">

                            <div class="ach-avatar" aria-hidden="true">
                                <?= e($initial) ?>
                            </div>

                            <div class="ach-member-info">
                                <h3><?= e($name) ?></h3>

                                <span class="ach-member-role">
                                    <?= e(ucfirst((string) $r['role'])) ?>
                                </span>
                            </div>

                        </div>

                        <div class="ach-stats">

                            <div class="ach-stat">
                                <strong>
                                    <?= (int) $r['answers_count'] ?>
                                </strong>
                                <span>Published answers</span>
                            </div>

                            <div class="ach-stat">
                                <strong>
                                    <?= (int) $r['accepted_count'] ?>
                                </strong>
                                <span>Accepted solutions</span>
                            </div>

                            <div class="ach-stat">
                                <strong>
                                    <?= (int) $r['helpful_count'] ?>
                                </strong>
                                <span>Helpful votes</span>
                            </div>

                        </div>

                        <div class="ach-badge-heading">
                            <h4>Unlocked badges</h4>

                            <span class="ach-badge-total">
                                <?= count($badges) ?>
                                <?= count($badges) === 1 ? 'badge' : 'badges' ?>
                            </span>
                        </div>

                        <div class="ach-badge-grid">

                            <?php foreach ($badges as $b): ?>

                                <div
                                    class="ach-badge <?= e($b[3]) ?>"
                                    title="<?= e($b[1]) ?>"
                                >
                                    <span class="ach-badge-icon"
                                          aria-hidden="true">
                                        <?= e($b[2]) ?>
                                    </span>

                                    <div class="ach-badge-copy">
                                        <strong><?= e($b[0]) ?></strong>
                                        <span><?= e($b[1]) ?></span>
                                    </div>
                                </div>

                            <?php endforeach; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

            <?php if ($shown === 0): ?>

                <div class="ach-empty">
                    <div class="ach-empty-icon">🏅</div>
                    <h3>Badges are waiting to be earned</h3>
                    <p>
                        Publish helpful answers, solve questions,
                        and participate in the community to unlock
                        your first achievement.
                    </p>

                    <a class="btn btn-primary" href="questions.php">
                        Explore questions
                    </a>
                </div>

            <?php endif; ?>

        <?php endif; ?>

        <div class="ach-note">
            <span class="ach-note-icon" aria-hidden="true">ℹ️</span>
            <span>
                Achievements are lightweight recognition, not an official
                academic credential. Badge counts are calculated from
                current database activity and update when this page
                is refreshed.
            </span>
        </div>

    </section>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
