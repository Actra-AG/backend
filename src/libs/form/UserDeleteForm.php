<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\db\DbAuthUser;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class UserDeleteForm extends Form
{
    private readonly BackendViewContext $backendContext;
    public function __construct(
        BackendViewContext $context,
        private readonly DbAuthUser $dbAuthUser,
    ) {
        $this->backendContext = $context;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'UserDeleteForm',
            messages: $this->backendContext->messages->form,
        );
        $this->addCssClass(className: 'form');
        $this->addComponent(
            formComponent: new FormControl(
                name: 'delete',
                submitLabel: HtmlText::fromText(text: $this->backendContext->messages->user->deleteButton),
                cancelLink: $this->backendContext->paths->user(id: $dbAuthUser->id),
            ),
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        $this->backendContext->userController->deleteUser(userId: $this->dbAuthUser->id);

        return true;
    }
}
