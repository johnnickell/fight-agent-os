<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Attribute\JsonBody;
use Fight\Common\Application\Attribute\Validation;

/**
 * Declares a test-owned input contract for the real middleware and publication boundary
 */
final class PublishedInput
{
    /**
     * Accepts checked input in the middleware contract test
     */
    #[JsonBody]
    #[Validation(formName: 'sample_form', rules: [
        [
            'field' => 'display_name',
            'label' => 'Name',
            'rules' => 'min_length[2]|starts_with[Z]',
            'errors' => [
                'min_length' => 'Use two characters.',
                'starts_with' => 'Private rule secret.'
            ]
        ]
    ])]
    public function handle(): void
    {
    }
}
