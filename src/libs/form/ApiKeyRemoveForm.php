<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
use actra\backend\libs\db\DbAuthApiKeyRepository;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

final class ApiKeyRemoveForm extends Form
{
    public function __construct(
        BackendViewContext $context,
        private readonly int $userID,
        string $cancelLink,
    ) {
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'ApiKeyRemoveForm',
            messages: ActraBackend::messages()->form,
        );
        $this->addCssClass(className: 'form');
        $this->addComponent(
            formComponent: new FormControl(
                name: 'remove',
                submitLabel: HtmlText::fromText(text: ActraBackend::messages()->common->removeApiKeyTitle),
                cancelLink: $cancelLink,
            ),
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        DbAuthApiKeyRepository::deleteByUserID(userID: $this->userID);

        return true;
    }
}
