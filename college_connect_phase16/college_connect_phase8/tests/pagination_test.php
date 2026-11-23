<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$passed = 0;

function expect_pagination(bool $condition, string $message): void
{
    global $passed;

    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }

    $passed++;
}

$_GET = [];
$firstPage = getPaginationData(205, 10);
expect_pagination(
    $firstPage['page'] === 1
        && $firstPage['per_page'] === 10
        && $firstPage['page_count'] === 21
        && $firstPage['from'] === 1
        && $firstPage['to'] === 10,
    'default pagination range and page count'
);

$_GET = ['page' => '3', 'per_page' => '20'];
$middlePage = getPaginationData(205, 10);
expect_pagination(
    $middlePage['page'] === 3
        && $middlePage['per_page'] === 20
        && $middlePage['offset'] === 40
        && $middlePage['from'] === 41
        && $middlePage['to'] === 60,
    'valid page and approved page size'
);

$_GET = ['page' => '-4', 'per_page' => '999'];
$invalidPage = getPaginationData(25, 25, [10, 20, 50, 100]);
expect_pagination(
    $invalidPage['page'] === 1 && $invalidPage['per_page'] === 25,
    'invalid page and unapproved size use safe defaults'
);

$_GET = ['page' => '999', 'per_page' => '10'];
$lastPage = getPaginationData(25, 10);
expect_pagination(
    $lastPage['page'] === 3
        && $lastPage['from'] === 21
        && $lastPage['to'] === 25,
    'page beyond the result range clamps to the last page'
);

$_GET = ['per_page' => '15'];
$notificationPage = getPaginationData(31, 15, [10, 15, 20, 50, 100]);
expect_pagination(
    $notificationPage['per_page'] === 15
        && $notificationPage['page_count'] === 3
        && in_array(15, $notificationPage['per_page_options'], true),
    'page-specific default size is supported'
);

$_GET = ['page' => '7'];
$emptyPage = getPaginationData(0, 10);
expect_pagination(
    $emptyPage['page'] === 1
        && $emptyPage['page_count'] === 1
        && $emptyPage['from'] === 0
        && $emptyPage['to'] === 0,
    'empty results have a zero range and valid page'
);

$_GET = ['q' => 'binary search', 'category' => '2', 'sort' => 'oldest'];
$url = buildPaginationUrl(4);
parse_str(ltrim($url, '?'), $urlParams);
expect_pagination(
    $urlParams === [
        'q' => 'binary search',
        'category' => '2',
        'sort' => 'oldest',
        'page' => '4',
    ],
    'pagination URLs retain active filters and sort order'
);

$_GET = ['q' => 'binary search', 'page' => '8'];
$html = renderPagination(
    getPaginationData(500, 10),
    'page',
    'per_page',
    'Questions'
);
expect_pagination(
    strpos($html, 'aria-current="page"') !== false
        && strpos($html, 'aria-label="First page"') !== false
        && strpos($html, 'aria-label="Last page"') !== false
        && strpos($html, '...') !== false
        && strpos($html, 'q=binary+search') !== false,
    'rendered navigation is accessible, abbreviated, and preserves filters'
);

$_GET = ['page' => '1'];
$firstNavigation = renderPagination(
    getPaginationData(25, 10),
    'page',
    'per_page',
    'Members'
);
expect_pagination(
    substr_count($firstNavigation, 'aria-disabled="true"') === 2,
    'first page has disabled backward navigation'
);

$_GET = ['id' => '25', 'answers_page' => '1'];
$answerNavigation = renderPagination(
    getPaginationData(25, 10, [10, 20, 50, 100], 'answers_page', 'answers_per_page'),
    'answers_page',
    'answers_per_page',
    'Answers',
    'answers'
);
expect_pagination(
    strpos($answerNavigation, 'answers_page=2#answers') !== false,
    'answer pagination links return to the answers section'
);

echo 'Passed ' . $passed . " pagination tests.\n";
