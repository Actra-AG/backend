<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
use actra\backend\libs\auth\GeneratedApiKeyFlash;
use actra\backend\libs\db\DbAuthApiKeyRepository;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * Generates (or replaces) the API key of a user. The new key is kept in the session until it has been shown once.
 */
final class ApiKeyGenerateForm extends Form
{
    public function __construct(
        BackendViewContext $context,
        private readonly int $userID,
        string $cancelLink,
    ) {
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'ApiKeyGenerateForm',
            messages: ActraBackend::messages()->form,
        );
        $this->addCssClass(className: 'form');
        $this->addComponent(
            formComponent: new FormControl(
                name: 'generate',
                submitLabel: HtmlText::fromText(text: ActraBackend::messages()->common->generateApiKeyTitle),
                cancelLink: $cancelLink,
            ),
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        GeneratedApiKeyFlash::store(
            session: ActraBackend::get()->getSession(),
            userID: $this->userID,
            apiKey: DbAuthApiKeyRepository::createForUserID(userID: $this->userID),
        );

        return true;
    }
}
