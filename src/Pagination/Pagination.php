<?php

declare(strict_types=1);

namespace App\Pagination;

/**
 * Immutable page descriptor consumed by templates/_pagination.html.twig.
 *
 * Twig reads `pagination.page`, `pagination.totalPages`, `pagination.total`
 * and `pagination.perPage`. The requested page is clamped into the valid
 * range so the component never renders an out-of-bounds active page.
 */
final class Pagination
{
    public readonly int $page;

    public function __construct(
        int $page,
        public readonly int $perPage,
        public readonly int $total,
    ) {
        $this->page = max(1, min($page, $this->getTotalPages()));
    }

    public function getTotalPages(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }
}
