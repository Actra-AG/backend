<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double\view\factory\php;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\BaseView;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\ViewContext;

/**
 * A view of a project that is not based on `BackendView` (`BackendViewFactoryTest`).
 */
final class PlainView extends BaseView
{
    public function __construct(ViewContext $context)
    {
        parent::__construct(
            context: $context,
            requiredViewGroupName: 'factory',
            ipWhitelist: [],
            authUser: null,
            requiredAccessRights: AccessRightCollection::createEmpty(),
            inputParameterCollection: new InputParameterCollection(),
        );
    }

    public function getViewContext(): ViewContext
    {
        return $this->context;
    }

    #[\Override]
    public function execute(): void {}
}
