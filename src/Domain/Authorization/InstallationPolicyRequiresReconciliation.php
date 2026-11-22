<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use LogicException;

/**
 * Class InstallationPolicyRequiresReconciliation
 *
 * Identifies a valid installation policy preview with changes still to apply
 */
final class InstallationPolicyRequiresReconciliation extends LogicException
{
}
