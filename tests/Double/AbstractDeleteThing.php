<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\backend\BackendViewContext;
use actra\backend\ConfirmationView;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlText;

/**
 * A confirmation page of a project (`ConfirmationViewTest`), on a route with and without file group.
 */
abstract class AbstractDeleteThing extends ConfirmationView
{
    public const string QUESTION = 'Really delete <Thing & Co>?';
    public const string CANCEL_LINK = '/project/thing.html';
    public const string REDIRECT_TARGET = '/project/things.html?removed';

    public function __construct(BackendViewContext $context, private readonly ConfirmationCalls $confirmationCalls)
    {
        parent::__construct(context: $context, requiredViewGroupName: 'confirmation');
    }

    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: 'Delete thing');
    }

    #[\Override]
    protected function getQuestion(): string
    {
        return AbstractDeleteThing::QUESTION;
    }

    #[\Override]
    protected function getConfirmLabel(): string
    {
        return 'Delete';
    }

    #[\Override]
    protected function getCancelLink(): string
    {
        return AbstractDeleteThing::CANCEL_LINK;
    }

    #[\Override]
    protected function confirm(): string
    {
        $this->confirmationCalls->count++;

        return AbstractDeleteThing::REDIRECT_TARGET;
    }
}
