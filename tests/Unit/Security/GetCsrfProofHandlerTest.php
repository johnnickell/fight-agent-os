<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Application\Security\Csrf\Clock\CsrfClock;
use App\Application\Security\Csrf\QueryHandler\GetCsrfProofHandler;
use App\Application\Security\Csrf\Service\CsrfNonceGenerator;
use App\Application\Security\Csrf\Service\CsrfProofs;
use App\Domain\Security\Csrf\Query\GetCsrfProof;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Class GetCsrfProofHandlerTest
 *
 * Exercises the transport-free proof query through injected capabilities
 */
final class GetCsrfProofHandlerTest extends TestCase
{
    /**
     * Reuses an approved nonce instead of replacing the session cookie
     */
    public function test_that_existing_nonce_is_reused(): void
    {
        $nonce = bin2hex(random_bytes(32));
        $handler = $this->handler(100);

        $view = $handler->handle(QueryMessage::create(new GetCsrfProof($nonce)));

        self::assertSame($nonce, $view->nonce);
    }

    /**
     * Does not replace the browser cookie when reusing its nonce
     */
    public function test_that_existing_nonce_does_not_request_new_cookie(): void
    {
        $handler = $this->handler(100);

        $view = $handler->handle(QueryMessage::create(new GetCsrfProof(bin2hex(random_bytes(32)))));

        self::assertFalse($view->newNonce);
    }

    /**
     * Marks generated nonce for issuance as a new cookie
     */
    public function test_that_absent_nonce_is_generated(): void
    {
        $handler = $this->handler(100);

        $view = $handler->handle(QueryMessage::create(new GetCsrfProof(null)));

        self::assertTrue($view->newNonce);
    }

    /**
     * Issues a 15-minute proof using the injected clock and signer
     */
    public function test_that_proof_deadline_matches_injected_clock(): void
    {
        $handler = $this->handler(100);

        $view = $handler->handle(QueryMessage::create(new GetCsrfProof(null)));

        self::assertSame(1000, $view->proof->expiresAt);
    }

    /**
     * Rejects another query rather than treating it as a CSRF request
     */
    public function test_that_wrong_query_is_rejected(): void
    {
        $handler = $this->handler(100);
        $this->expectException(InvalidArgumentException::class);

        $handler->handle(QueryMessage::create(new class implements \Fight\Common\Domain\Messaging\Query\Query {
            /**
             * @inheritDoc
             */
            public static function fromArray(array $data): static
            {
                return new self();
            }

            /**
             * @inheritDoc
             */
            public function toArray(): array
            {
                return [];
            }
        }));
    }

    /**
     * Provides deterministic collaborators to isolate query coordination
     */
    private function handler(int $now): GetCsrfProofHandler
    {
        $nonce = bin2hex(random_bytes(32));
        $generator = $this->createStub(CsrfNonceGenerator::class);
        $generator->method('generate')->willReturn($nonce);
        $clock = $this->createStub(CsrfClock::class);
        $clock->method('now')->willReturn($now);
        $proofs = $this->createStub(CsrfProofs::class);
        $proofs->method('sign')->willReturn('1000.'.str_repeat('a', 64));

        return new GetCsrfProofHandler($generator, $clock, $proofs);
    }
}
