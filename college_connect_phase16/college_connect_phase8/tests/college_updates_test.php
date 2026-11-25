<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/college_updates.php';

$passed = 0;

function expect_college_update(bool $condition, string $message): void
{
    global $passed;

    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }

    $passed++;
}

$categories = college_update_categories();
expect_college_update(
    $categories === [
        'Community',
        'Academics',
        'Events',
        'Examinations',
        'Placements',
        'Announcements',
    ],
    'the requested campus update categories are available'
);

expect_college_update(
    college_update_statuses() === ['draft', 'published', 'archived'],
    'draft, published, and archived states are supported'
);

expect_college_update(
    college_update_valid_registration_url('')
        && college_update_valid_registration_url('https://events.example.edu/register')
        && college_update_valid_registration_url('http://example.edu/form?id=4'),
    'empty and HTTP(S) registration URLs are accepted'
);

expect_college_update(
    !college_update_valid_registration_url('javascript:alert(1)')
        && !college_update_valid_registration_url('//example.edu/register')
        && !college_update_valid_registration_url('https://user:pass@example.edu/register')
        && !college_update_valid_registration_url(str_repeat('a', 2050)),
    'unsafe, credential-bearing, and overlong registration URLs are rejected'
);

expect_college_update(
    college_update_ini_size_bytes('40M') === 41943040
        && college_update_ini_size_bytes('2K') === 2048
        && college_update_ini_size_bytes('1024') === 1024
        && college_update_ini_size_bytes('-1') === 0,
    'PHP upload-size settings are parsed safely'
);

expect_college_update(
    college_update_input_string(['field' => 'College Fest'], 'field') === 'College Fest'
        && college_update_input_string(['field' => ['invalid']], 'field') === '',
    'request values reject non-string input without unsafe coercion'
);

echo 'Passed ' . $passed . " college updates tests.\n";
