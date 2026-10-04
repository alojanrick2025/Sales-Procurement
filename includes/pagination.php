<?php
/*
 * Pagination for list pages that filter on the server (search form submits a GET).
 * Usage: count the matching rows, call paginate() for the LIMIT/OFFSET, then print
 * paginationLinks() under the table. Links keep the page's other filters (?search=...).
 */
define('ROWS_PER_PAGE', 25);

// Current page (from ?page=), kept within 1..last page
function paginate($totalRows, $perPage = ROWS_PER_PAGE) {
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min(max(1, (int) ($_GET['page'] ?? 1)), $totalPages);
    return [
        'page' => $page,
        'total_pages' => $totalPages,
        'total_rows' => (int) $totalRows,
        'limit' => $perPage,
        'offset' => ($page - 1) * $perPage,
    ];
}

function paginationLinks($pagination) {
    $page = $pagination['page'];
    $totalPages = $pagination['total_pages'];
    $first = $pagination['total_rows'] === 0 ? 0 : $pagination['offset'] + 1;
    $last = min($pagination['offset'] + $pagination['limit'], $pagination['total_rows']);

    $html = '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">';
    $html .= '<div class="small text-muted">Showing ' . $first . '&ndash;' . $last . ' of ' . $pagination['total_rows'] . '</div>';
    if ($totalPages > 1) {
        $link = function ($target, $label, $disabled = false, $active = false) {
            $query = http_build_query(array_merge($_GET, ['page' => $target]));
            return '<li class="page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '') . '">'
                . '<a class="page-link" href="?' . htmlspecialchars($query) . '">' . $label . '</a></li>';
        };
        $html .= '<nav aria-label="Pages"><ul class="pagination pagination-sm mb-0">';
        $html .= $link($page - 1, 'Previous', $page <= 1);
        // First and last page, plus two pages either side of the current one
        $shown = 0;
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2) {
                if ($shown && $i - $shown > 1) {
                    $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                }
                $html .= $link($i, $i, false, $i === $page);
                $shown = $i;
            }
        }
        $html .= $link($page + 1, 'Next', $page >= $totalPages);
        $html .= '</ul></nav>';
    }
    return $html . '</div>';
}
