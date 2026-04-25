<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\page;

use actra\backend\page\php\login;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlSnippet;

abstract class AbstractPage
{
    abstract protected static function getRequiredAccessRights(): AccessRightCollection;

    public static function getByName(string $name): AbstractPage
    {
        return match ($name) {
            'login' => new login(name: $name),
        };
    }

    private function __construct(public readonly string $name)
    {
    }

    public function getContent(): HtmlSnippet
    {
        return new HtmlSnippet(htmlSnippetFilePath: __DIR__ . '/html/' . $this->name . '.html');
    }
}