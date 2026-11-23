<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\QueryString;
use Fight\Common\Application\Attribute\Validation;

/**
 * Class DuplicateFieldInput
 *
 * Supplies conflicting declarations for one query field
 */
final class DuplicateFieldInput
{
    /**
     * Handles input
     */
    #[QueryString]
    #[Validation(rules: [
        ['field' => 'page', 'label' => 'Page', 'rules' => 'required'],
        ['field' => 'page', 'label' => 'Page number', 'rules' => 'type[int]']
    ])]
    public function handle(): void
    {
    }
}
