<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend;

use actra\backend\libs\form\ConfirmationForm;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;
use LogicException;

/**
 * Extension point: a confirmation page for a destructive action of a project, with the template of the backend (title,
 * question and a POST form with CSRF token, confirm button and cancel link). A GET request shows the page; only a
 * valid POST (with the CSRF token of the session) runs `confirm()` once and redirects to the URI it returns. Link to
 * it with `data-action="confirm-deletion" data-form="main form"`, so the dialog of the backend submits the form.
 *
 * The page title (`getPageTitle()`) and the access rights (`getRequiredAccessRights()`) come from `BackendView`.
 * The route needs a session with CSRF token (otherwise a `LogicException`).
 */
abstract class ConfirmationView extends BackendView
{
    /**
     * Loads what the page needs (e.g. the record of a path variable) before anything else is called; throws
     * `NotFoundException` if it does not exist.
     */
    protected function prepareConfirmation(): void {}

    /**
     * The question of the page as plain text (escaped when rendered).
     */
    abstract protected function getQuestion(): string;

    /**
     * The label of the confirm button as plain text, e.g. "Delete".
     */
    abstract protected function getConfirmLabel(): string;

    /**
     * The link of the cancel button, e.g. back to the record.
     */
    abstract protected function getCancelLink(): string;

    /**
     * Runs the action; called only on a valid POST of the form, exactly once per request.
     *
     * @return string The relative or absolute URI the user is redirected to afterwards
     */
    abstract protected function confirm(): string;

    #[\Override]
    final protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        if ($this->context->formContext->csrfTokenSource === null) {
            throw new LogicException(message: 'A confirmation page needs the CSRF token of the session.');
        }
        $this->prepareConfirmation();
        $confirmationForm = new ConfirmationForm(
            context: $this->backendContext,
            confirmLabel: $this->getConfirmLabel(),
            cancelLink: $this->getCancelLink(),
        );
        if (
            $this->context->httpRequest->getMethod() === RequestMethodEnum::POST
            && $confirmationForm->validate()
        ) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $this->confirm(),
                httpRequest: $this->context->httpRequest,
                responseSender: $this->context->responseSender,
            );
        }
        // The template of the backend, also for views on project routes with file groups
        $htmlDocument->useContentFile(filePath: __DIR__ . '/view/backend/confirmation/confirmation.html');
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'confirmMessage',
            htmlText: HtmlText::fromText(text: $this->getQuestion()),
        );
        $replacements->addHtml(
            identifier: 'form',
            html: $confirmationForm->render(),
        );
    }
}
