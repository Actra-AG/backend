<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\auth\MyAuthenticator;
use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\form\component\ReturnPathField;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\HiddenField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class LoginTokenForm extends Form
{
    private readonly BackendViewContext $backendContext;
    private readonly HiddenField $returnPathField;
    private readonly TextField $tokenField;

    public function __construct(BackendViewContext $context)
    {
        $this->backendContext = $context;
        $messages = $this->backendContext->messages;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'LoginTokenForm',
            messages: $messages->form,
        );
        $this->addCssClass(className: 'form');
        $this->addCssClass(className: 'form-login');
        $this->addField(
            formField: $this->tokenField = new TextField(
                name: 'token',
                label: HtmlText::fromText(text: $messages->auth->tokenLabel),
                value: null,
                requiredError: HtmlText::fromText(text: $messages->auth->tokenRequired),
            ),
        );
        $this->tokenField->autoFocus = true;
        $this->tokenField->renderRequiredAbbr = false;
        $this->addField(
            formField: $this->returnPathField = ReturnPathField::create(httpRequest: $context->viewContext->httpRequest),
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::fromText(text: $messages->auth->tokenSubmitLabel),
            ),
        );
    }

    /**
     * The logged-in user, `null` if the form was not sent or the token is wrong.
     */
    public function process(): ?MyAuthUser
    {
        if (!$this->validate()) {
            return null;
        }
        $myAuthUser = new MyAuthenticator(context: $this->backendContext)->tokenLogin(
            inputToken: $this->tokenField->getValueAsString(),
        );
        if ($myAuthUser === null) {
            $this->tokenField->addError(
                errorMessage: HtmlText::fromText(text: $this->backendContext->messages->auth->tokenInvalid),
            );
        }

        return $myAuthUser;
    }

    /**
     * The page requested before the login (validated local path), `null` without one.
     */
    public function getReturnPath(): ?string
    {
        return ReturnPathField::getReturnPath(hiddenField: $this->returnPathField);
    }
}
