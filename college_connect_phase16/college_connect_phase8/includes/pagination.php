<?php
declare(strict_types=1);

function getPaginationData(
    int $total,
    int $defaultPerPage = 10,
    array $allowedPerPage = [10, 20, 50, 100],
    string $pageParam = 'page',
    string $perPageParam = 'per_page'
): array {
    $perPageOptions = array_values(array_unique(array_filter(
        array_map('intval', $allowedPerPage),
        static fn (int $size): bool => $size > 0 && $size <= 100
    )));

    if (!$perPageOptions) {
        $perPageOptions = [10, 20, 50, 100];
    }

    if (!in_array($defaultPerPage, $perPageOptions, true)) {
        $perPageOptions[] = $defaultPerPage;
    }

    sort($perPageOptions);

    $requestedPerPage = filter_var(
        $_GET[$perPageParam] ?? null,
        FILTER_VALIDATE_INT
    );
    $perPage = in_array($requestedPerPage, $perPageOptions, true)
        ? $requestedPerPage
        : $defaultPerPage;

    $requestedPage = filter_var(
        $_GET[$pageParam] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $pageCount = max(1, (int) ceil(max(0, $total) / $perPage));
    $page = min($requestedPage === false ? 1 : $requestedPage, $pageCount);
    $offset = ($page - 1) * $perPage;
    $total = max(0, $total);

    return [
        'page' => $page,
        'page_count' => $pageCount,
        'per_page' => $perPage,
        'per_page_options' => $perPageOptions,
        'total' => $total,
        'offset' => $offset,
        'from' => $total === 0 ? 0 : $offset + 1,
        'to' => min($total, $offset + $perPage),
    ];
}

function buildPaginationUrl(
    int $page,
    array $params = [],
    string $pageParam = 'page'
): string {
    $query = $_GET;

    foreach ($params as $name => $value) {
        if ($value === null) {
            unset($query[$name]);
        } else {
            $query[$name] = $value;
        }
    }

    $query[$pageParam] = max(1, $page);

    return '?' . http_build_query($query);
}

function getPaginatedRecords(
    string $countSql,
    string $recordsSql,
    array $params = [],
    int $defaultPerPage = 10,
    array $allowedPerPage = [10, 20, 50, 100],
    string $pageParam = 'page',
    string $perPageParam = 'per_page',
    ?array $countParams = null
): array {
    $countStatement = db()->prepare($countSql);
    bindPaginationQueryParams($countStatement, $countParams ?? $params);
    $countStatement->execute();

    $pagination = getPaginationData(
        (int) $countStatement->fetchColumn(),
        $defaultPerPage,
        $allowedPerPage,
        $pageParam,
        $perPageParam
    );

    $recordsStatement = db()->prepare($recordsSql);
    bindPaginationQueryParams($recordsStatement, $params);
    $recordsStatement->bindValue(
        ':pagination_limit',
        $pagination['per_page'],
        PDO::PARAM_INT
    );
    $recordsStatement->bindValue(
        ':pagination_offset',
        $pagination['offset'],
        PDO::PARAM_INT
    );
    $recordsStatement->execute();

    return [
        'records' => $recordsStatement->fetchAll(),
        'pagination' => $pagination,
    ];
}

function renderPagination(
    array $pagination,
    string $pageParam = 'page',
    string $perPageParam = 'per_page',
    string $label = 'Results',
    string $fragment = ''
): string {
    $paginationUrl = static function (int $page) use ($pageParam, $fragment): string {
        $url = buildPaginationUrl($page, [], $pageParam);

        return $fragment === ''
            ? $url
            : $url . '#' . rawurlencode($fragment);
    };

    ob_start();
    ?>
    <div class="pagination-bar">
        <p class="pagination-summary" aria-live="polite">
            <?= e($label) ?>:
            Showing <?= number_format((int) $pagination['from']) ?>
            to <?= number_format((int) $pagination['to']) ?>
            of <?= number_format((int) $pagination['total']) ?>
        </p>

        <?php if (count($pagination['per_page_options']) > 1): ?>
            <form
                class="pagination-size-form"
                method="get"
                action="<?= e(
                    basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''))
                    . ($fragment === '' ? '' : '#' . rawurlencode($fragment))
                ) ?>"
            >
                <?php foreach ($_GET as $name => $value): ?>
                    <?php if (
                        $name !== $pageParam
                        && $name !== $perPageParam
                        && is_scalar($value)
                    ): ?>
                        <input
                            type="hidden"
                            name="<?= e((string) $name) ?>"
                            value="<?= e((string) $value) ?>"
                        >
                    <?php endif; ?>
                <?php endforeach; ?>

                <label>
                    <span>Per page</span>
                    <select name="<?= e($perPageParam) ?>">
                        <?php foreach ($pagination['per_page_options'] as $size): ?>
                            <option
                                value="<?= (int) $size ?>"
                                <?= (int) $size === (int) $pagination['per_page']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= (int) $size ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="pagination-size-submit" type="submit">
                    Apply
                </button>
            </form>
        <?php endif; ?>

        <?php if ((int) $pagination['page_count'] > 1): ?>
            <nav class="pagination-nav" aria-label="<?= e($label) ?> pages">
                <?php if ((int) $pagination['page'] > 1): ?>
                    <a
                        class="pagination-link"
                        href="<?= e($paginationUrl(1)) ?>"
                        aria-label="First page"
                    >First</a>
                    <a
                        class="pagination-link"
                        href="<?= e($paginationUrl(
                            (int) $pagination['page'] - 1
                        )) ?>"
                        rel="prev"
                    >Previous</a>
                <?php else: ?>
                    <span class="pagination-link is-disabled" aria-disabled="true">First</span>
                    <span class="pagination-link is-disabled" aria-disabled="true">Previous</span>
                <?php endif; ?>

                <?php
                $pageCount = (int) $pagination['page_count'];
                $currentPage = (int) $pagination['page'];
                $visiblePages = array_unique(array_merge(
                    [1, $pageCount],
                    range(
                        max(1, $currentPage - 1),
                        min($pageCount, $currentPage + 1)
                    )
                ));
                sort($visiblePages);
                $previousPage = 0;
                foreach ($visiblePages as $visiblePage):
                    if ($previousPage > 0 && $visiblePage - $previousPage > 1):
                        ?>
                        <span class="pagination-ellipsis" aria-hidden="true">...</span>
                    <?php endif; ?>
                    <?php if ($visiblePage === $currentPage): ?>
                        <span
                            class="pagination-link is-current"
                            aria-current="page"
                        ><?= (int) $visiblePage ?></span>
                    <?php else: ?>
                        <a
                            class="pagination-link"
                            href="<?= e($paginationUrl(
                                (int) $visiblePage
                            )) ?>"
                            aria-label="Page <?= (int) $visiblePage ?>"
                        ><?= (int) $visiblePage ?></a>
                    <?php endif; ?>
                    <?php
                    $previousPage = $visiblePage;
                endforeach;
                ?>

                <?php if ($currentPage < $pageCount): ?>
                    <a
                        class="pagination-link"
                        href="<?= e($paginationUrl(
                            $currentPage + 1
                        )) ?>"
                        rel="next"
                    >Next</a>
                    <a
                        class="pagination-link"
                        href="<?= e($paginationUrl($pageCount)) ?>"
                        aria-label="Last page"
                    >Last</a>
                <?php else: ?>
                    <span class="pagination-link is-disabled" aria-disabled="true">Next</span>
                    <span class="pagination-link is-disabled" aria-disabled="true">Last</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function bindPaginationQueryParams(PDOStatement $statement, array $params): void
{
    foreach ($params as $key => $value) {
        $placeholder = is_int($key) ? $key + 1 : (string) $key;
        $type = match (true) {
            $value === null => PDO::PARAM_NULL,
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            default => PDO::PARAM_STR,
        };

        $statement->bindValue($placeholder, $value, $type);
    }
}
