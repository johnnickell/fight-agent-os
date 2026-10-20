<?php

declare(strict_types=1);

namespace App\Adapter\Http\Responder\Api\V1\Repositories;

use App\Application\Workspace\RegistrationView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ListRepositoryRegistrationsResponder
 */
final readonly class ListRepositoryRegistrationsResponder
{
    /**
     * Constructs ListRepositoryRegistrationsResponder
     */
    public function __construct(private JSendResponseFactory $responses)
    {
    }

    /**
     * Returns bounded explicit pagination
     *
     * @param array{items: list<RegistrationView>, has_more: bool} $page
     */
    public function respond(array $page, int $offset, int $limit): ResponseInterface
    {
        return $this->responses->fromEnvelope(
            JSendEnvelope::success(new RegistrationPageData($page, $offset, $limit)),
            200,
            ['Cache-Control' => 'no-store']
        );
    }
}
