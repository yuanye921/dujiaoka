<?php

namespace App\Admin\Tools;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\Tools\Paginator;

class GameLicensePaginator extends Paginator
{
    public function render()
    {
        $paginator = $this->paginator;
        $current = max(1, (int) $paginator->currentPage());
        $last = max(1, (int) $paginator->lastPage());
        $perPage = (int) $paginator->perPage();
        $color = Admin::color()->dark80();
        $range = trans('admin.pagination.range', [
            'first' => '<b>' . (int) $paginator->firstItem() . '</b>',
            'last' => '<b>' . (int) $paginator->lastItem() . '</b>',
            'total' => '<b>' . (int) $paginator->total() . '</b>',
        ]);

        $html = "<span class=\"d-none d-sm-inline\" style=\"line-height:33px;color:{$color}\">{$range}</span>";
        $html .= '<ul class="pagination pagination-sm no-margin pull-right shadow-100" style="border-radius:1.5rem">';
        $html .= $this->pageButton('&lsaquo;', max(1, $current - 1), $perPage, $current === 1);

        $previous = null;
        foreach ($this->visiblePages($current, $last) as $page) {
            if ($previous !== null && $page > $previous + 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= $this->pageButton((string) $page, $page, $perPage, false, $page === $current);
            $previous = $page;
        }

        $html .= $this->pageButton('&rsaquo;', min($last, $current + 1), $perPage, $current === $last);
        $html .= '</ul>';
        $html .= $this->renderPerPageSelector($perPage);

        return $html;
    }

    private function visiblePages(int $current, int $last): array
    {
        $pages = [1, $last];
        for ($page = max(1, $current - 2); $page <= min($last, $current + 2); $page++) {
            $pages[] = $page;
        }
        $pages = array_values(array_unique($pages));
        sort($pages);
        return $pages;
    }

    private function pageButton(string $label, int $page, int $perPage, bool $disabled = false, bool $active = false): string
    {
        if ($disabled || $active) {
            $class = $active ? 'page-item active' : 'page-item disabled';
            return "<li class=\"{$class}\"><span class=\"page-link\">{$label}</span></li>";
        }

        $url = htmlspecialchars($this->pageUrl($page, $perPage), ENT_QUOTES, 'UTF-8');
        return "<li class=\"page-item\"><button type=\"button\" class=\"page-link\" data-url=\"{$url}\" onclick=\"window.location.assign(this.dataset.url)\">{$label}</button></li>";
    }

    private function renderPerPageSelector(int $currentPerPage): string
    {
        $options = '';
        foreach ([20, 50, 100, 200] as $perPage) {
            $url = htmlspecialchars($this->pageUrl(1, $perPage), ENT_QUOTES, 'UTF-8');
            $selected = $perPage === $currentPerPage ? ' selected' : '';
            $options .= "<option value=\"{$url}\"{$selected}>{$perPage}</option>";
        }

        return '<label class="pull-right d-none d-sm-inline" style="margin-right:10px">'
            . '<select class="form-control form-control-sm" onchange="window.location.assign(this.value)">'
            . $options
            . '</select></label>';
    }

    private function pageUrl(int $page, int $perPage): string
    {
        return admin_url("game-license-page/{$page}/{$perPage}");
    }
}
