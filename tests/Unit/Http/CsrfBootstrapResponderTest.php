<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Responder\Api\V1\Auth\CsrfBootstrapResponder;
use App\Domain\Security\Csrf\CsrfProof;
use App\Domain\Security\Csrf\Query\CsrfProofView;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Class CsrfBootstrapResponderTest
 *
 * Exercises the safe API projection and cookie response behavior
 */
final class CsrfBootstrapResponderTest extends TestCase
{
    /**
     * Exposes the proof and deadline but never the cookie nonce
     */
    public function test_that_response_projects_only_public_proof_fields(): void
    {
        $responder = $this->responder();

        $response = $responder->respond($this->view(false));

        self::assertSame(
            ['status' => 'success', 'data' => ['proof' => '1000.'.str_repeat('a', 64), 'expires_at' => 1000]],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Prevents browser or proxy caching of issued proofs
     */
    public function test_that_proof_response_is_not_cacheable(): void
    {
        $responder = $this->responder();

        $response = $responder->respond($this->view(true));

        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    /**
     * Sets a secure session cookie for newly generated nonces
     */
    public function test_that_new_nonce_sets_cookie(): void
    {
        $responder = $this->responder();

        $response = $responder->respond($this->view(true));

        self::assertSame(
            '__Secure-agent_os_csrf='.str_repeat('a', 64).'; Path=/api/v1/auth; Secure; HttpOnly; SameSite=Strict',
            $response->getHeaderLine('Set-Cookie')
        );
    }

    /**
     * Preserves the existing browser nonce without replacing its cookie
     */
    public function test_that_reused_nonce_does_not_set_cookie(): void
    {
        $responder = $this->responder();

        $response = $responder->respond($this->view(false));

        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
    }

    /**
     * Builds the concrete HTTP presenter without application composition
     */
    private function responder(): CsrfBootstrapResponder
    {
        return new CsrfBootstrapResponder(new JSendResponseFactory(new ResponseFactory(), new StreamFactory()));
    }

    /**
     * Supplies a real query view with stable presentation values
     */
    private function view(bool $newNonce): CsrfProofView
    {
        return new CsrfProofView(
            str_repeat('a', 64),
            new CsrfProof('1000.'.str_repeat('a', 64), 1000),
            $newNonce
        );
    }
}
