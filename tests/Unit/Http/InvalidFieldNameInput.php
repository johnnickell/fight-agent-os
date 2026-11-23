<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\QueryString;
use Fight\Common\Application\Attribute\Validation;

/**
 * Class InvalidFieldNameInput
 *
 * Supplies a query field that the API naming policy does not support
 */
final class InvalidFieldNameInput
{
    /**
     * Handles input
     */
    #[QueryString]
    #[Validation(rules: [['field' => 'page-number', 'label' => 'Page number', 'rules' => 'required']])]
    public function handle(): void
    {
    }
}
