<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\common;

class OldNavigator
{
    private readonly string $currentPage;
    private readonly string $currentLink;

    public function __construct(
        array $pathVars,
        private array $navigationLevels,
        string $currentPage = '',
        private readonly string $separator = ' '
    ) {
        if ($currentPage === '') {
            $this->currentPage = $pathVars[0];
        } else {
            $this->currentPage = $currentPage;
        }
        $this->currentLink = implode(
            separator: '-',
            array: $pathVars
        );
        if (array_key_exists(
            key: 'reset',
            array: $_GET
        )) {
            $this->resetBreadcrumb();
        }
    }

    public function resetBreadcrumb(): void
    {
        if (array_key_exists(
            key: 'sess_breadcrumb',
            array: $_SESSION
        )) {
            unset($_SESSION['sess_breadcrumb']);
        }
    }

    public function addBreadcrumb($title = ''): void
    {
        if (!array_key_exists(
            key: 'sess_breadcrumb',
            array: $_SESSION
        )) {
            $_SESSION['sess_breadcrumb'] = [];
        }

        $_SESSION['sess_breadcrumb'][$this->currentPage]['title'] = $title;
        $_SESSION['sess_breadcrumb'][$this->currentPage]['link'] = $this->currentLink;
    }

    public function getBreadcrumb(): string
    {
        $breadcrumb = '';
        if (
            array_key_exists(
                key: 'sess_breadcrumb',
                array: $_SESSION
            )
        ) {
            $xArr = [];
            $found = 0;
            foreach ($_SESSION['sess_breadcrumb'] as $key => $val) {
                if ($key == $this->currentPage) {
                    $xArr[] = "<strong>{$val['title']}</strong>";
                    $found = 1;
                } else {
                    if ($found == 0) {
                        $href = "{$val['link']}.html";
                        $xArr[] = "<a href=\"$href\">{$val['title']}</a>";
                    } else {
                        unset($_SESSION['sess_breadcrumb'][$key]);
                    }
                }
            }
            $breadcrumb = "<p class=\"breadcrumb\">" . implode(
                    separator: $this->separator,
                    array: $xArr
                ) . "</p>";
            if (count(value: $_SESSION['sess_breadcrumb']) <= 1) {
                $breadcrumb = '';
            }
        }

        return $breadcrumb;
    }

    public function setNavistufe(): array
    {
        if (!isset($_SESSION['sess_navistufe'])) {
            $_SESSION['sess_navistufe'] = [];
        }
        if (isset($_GET['n'])) {
            $_SESSION['sess_navistufe'] = explode(
                separator: "|",
                string: $_GET['n']
            );
        }
        foreach ($_SESSION['sess_navistufe'] as $key => $val) {
            $this->navigationLevels[$key] = $val;
        }

        return $this->navigationLevels;
    }
}