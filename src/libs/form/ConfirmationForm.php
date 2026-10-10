<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * The POST form of a `ConfirmationView`: the CSRF token (first, before the visible content) and the confirm button
 * with the cancel link.
 *
 * @internal
 */
final class ConfirmationForm extends Form
{
    public const string NAME = 'ConfirmationForm';
    public const string CONTROL_NAME = 'confirm';

    public function __construct(BackendViewContext $context, string $confirmLabel, string $cancelLink)
    {
        parent::__construct(
            context: $context->viewContext->formContext,
            name: ConfirmationForm::NAME,
            messages: $context->messages->form,
        );
        $this->addCssClass(className: 'form');
        $this->addComponent(
            formComponent: new FormControl(
                name: ConfirmationForm::CONTROL_NAME,
                submitLabel: HtmlText::fromText(text: $confirmLabel),
                cancelLink: $cancelLink,
            ),
        );
    }
}
