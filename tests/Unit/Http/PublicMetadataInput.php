<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Fight\Common\Application\Attribute\Validation;

/**
 * Class PublicMetadataInput
 *
 * Declares runtime publication inputs without adding a product route
 */
final class PublicMetadataInput
{
    /**
     * Handles the metadata contract fixture
     */
    #[Validation(formName: 'sample', rules: [
        [
            'field'  => 'code',
            'label'  => 'Code',
            'rules'  => 'required|type[string]|min_length[2]|max_length[8]',
            'errors' => [
                'required'   => 'Enter code.',
                'type'       => 'Enter text.',
                'min_length' => 'Use two characters.',
                'max_length' => 'Use at most eight.'
            ]
        ],
        [
            'field'  => 'confirm_code',
            'label'  => 'Confirmation',
            'rules'  => 'same[code]',
            'errors' => ['same' => 'Match code.']
        ],
        [
            'field'  => 'private_code',
            'label'  => 'Private',
            'rules'  => 'type[int]|min_length[-1]|starts_with[X]',
            'errors' => [
                'type'        => 'Private type.',
                'min_length'  => 'Private length.',
                'starts_with' => 'Private prefix.'
            ]
        ]
    ])]
    public function handle(): void
    {
    }
}
