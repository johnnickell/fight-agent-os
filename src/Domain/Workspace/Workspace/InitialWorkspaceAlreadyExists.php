<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Workspace;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class InitialWorkspaceAlreadyExists
 *
 * Reports a competing owner of the one initial Workspace slot
 */
final class InitialWorkspaceAlreadyExists extends DomainException
{
}
