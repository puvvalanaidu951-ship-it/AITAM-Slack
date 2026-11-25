<?php

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Privacy Policy';
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <span class="eyebrow">LEGAL & PRIVACY</span>
    <h1>Privacy Policy</h1>
    <p>How we collect, protect, and handle data across the AITAM SLACK community platform.</p>
</section>

<section class="section-block">
    <div class="content-card" style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 32px; line-height: 1.7; color: var(--text-primary); max-width: 860px; margin: 0 auto;">
        <h2 style="color: var(--color-navy); margin-top: 0; font-size: 20px;">1. Information We Collect</h2>
        <p>AITAM SLACK collects basic profile information needed to foster collaborative campus discussions:</p>
        <ul style="padding-left: 20px; margin-bottom: 24px;">
            <li>Account details: Name, institutional email address, department, roll number, and role (student, faculty, or alumni).</li>
            <li>Content: Questions, answers, comments, bookmarks, and reported issues submitted by you.</li>
            <li>System logs: Standard session timestamps and access logs required to maintain platform security and performance.</li>
        </ul>

        <h2 style="color: var(--color-navy); font-size: 20px;">2. How We Use Your Information</h2>
        <p>Your information is used strictly to provide educational collaboration services on campus:</p>
        <ul style="padding-left: 20px; margin-bottom: 24px;">
            <li>Displaying verified author details alongside posted questions and answers.</li>
            <li>Enforcing community guidelines, safety rules, and content quality.</li>
            <li>Notifying you about replies, mentions, and updates relevant to your campus activity.</li>
            <li>We do not sell, rent, or share personal user data with third-party advertisers.</li>
        </ul>

        <h2 style="color: var(--color-navy); font-size: 20px;">3. Automated Moderation & Data Security</h2>
        <p>To keep college discussions safe and respectful, content may pass through automated moderation services to screen for prohibited content, personal contact leaks, or academic integrity violations. All data in transit is encrypted using industry-standard protocols, and database access is restricted to authorized platform administrators.</p>

        <h2 style="color: var(--color-navy); font-size: 20px;">4. User Rights & Account Management</h2>
        <p>You may review and update your profile details at any time from your account settings. If you wish to delete your account or request the removal of personal data, contact the Department of Computer Science and Machine Learning (CSM) or platform administration through the support portal.</p>

        <h2 style="color: var(--color-navy); font-size: 20px;">5. Policy Updates</h2>
        <p>Any revisions to this policy will be posted on this page with an updated modification date. Continued use of AITAM SLACK confirms acceptance of these terms.</p>
        <p style="font-size: 13px; color: var(--text-secondary); margin-top: 28px; border-top: 1px solid var(--border-color); padding-top: 14px;">Last updated: October 2026</p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
