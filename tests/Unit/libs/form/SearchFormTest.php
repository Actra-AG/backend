<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\form\AbstractSearchForm;
use actra\backend\libs\form\TokenSearchForm;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\core\Route;
use actra\yuf\form\component\field\TextField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

/**
 * The search forms keep the markup of v2.3.1 (`standards/versioning.md`, section 4): label and control in a plain
 * `<div>`, the label points to the id of the control (B-4: it pointed to the field name).
 */
final class SearchFormTest extends TestCase
{
    public function testBackendSearchFormKeepsItsMarkup(): void
    {
        $context = $this->createContext(fileTitle: 'tokens');

        $html = new TokenSearchForm(context: $context, name: 'TokenSearch')->render();

        $this->assertSame(
            '<form method="post" action="?TokenSearch" class="form-filter form-autosubmit">'
            . '<div><label for="typeFilterField">Typ</label><select name="typeFilterField" id="typeFilterField">'
            . '<option value="" selected>alle</option><option value="password">Passwort-Reset</option>'
            . '<option value="activation">Aktivierung</option><option value="login">Anmeldung</option></select></div>'
            . '<div><label for="searchQuery">Suchbegriff</label>'
            . '<input type="text" name="searchQuery" id="searchQuery" value=""></div>'
            . '<div class="form-control"><button type="submit" name="find">anzeigen</button></div>'
            . '</form>',
            $html,
        );
    }

    /**
     * Project search forms choose their renderer themselves (`useCompactFieldRenderer()`), as in v2.3.1.
     */
    public function testProjectSearchFormKeepsTheDefaultRenderer(): void
    {
        $context = $this->createContext(fileTitle: 'products');
        $projectSearchForm = new class (context: $context, name: 'ProductSearch') extends AbstractSearchForm {
            public function __construct(BackendViewContext $context, string $name)
            {
                parent::__construct(context: $context, name: $name);
                $this->addField(
                    formField: new TextField(name: 'productQuery', label: HtmlText::fromText(text: 'Product')),
                );
            }
        };

        $html = $projectSearchForm->render();

        $this->assertSame(
            '<form method="post" action="?ProductSearch"><dl><dt><label for="productQuery">Product</label></dt>'
            . '<dd><input type="text" name="productQuery" id="productQuery" value=""></dd></dl></form>',
            $html,
        );
    }

    private function createContext(string $fileTitle): BackendViewContext
    {
        return ActraBackendTestInstance::create()->createContext(
            viewContext: ViewContextFactory::create(
                route: new Route(path: '/backend/', viewDirectory: __DIR__),
                fileTitle: $fileTitle,
            ),
        );
    }
}
