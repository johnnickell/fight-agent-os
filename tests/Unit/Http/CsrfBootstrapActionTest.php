<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Adapter\Http\Action\Api\V1\Auth\CsrfBootstrapAction;
use App\Adapter\Http\Responder\Api\V1\Auth\CsrfBootstrapResponder;
use App\Domain\Security\Csrf\CsrfProof;
use App\Domain\Security\Csrf\Query\CsrfProofView;
use App\Domain\Security\Csrf\Query\GetCsrfProof;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Query\Query;
use PHPUnit\Framework\TestCase;
use Slim\Exception\HttpBadRequestException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Class CsrfBootstrapActionTest
 *
 * Exercises cookie-to-query mapping without starting the application
 */
final class CsrfBootstrapActionTest extends TestCase
{
    /**
     * Dispatches the approved cookie nonce without passing HTTP to the query
     */
    public function test_that_approved_cookie_maps_to_query(): void
    {
        $nonce = bin2hex(random_bytes(32));
        $dispatched = null;
        $bus = $this->createStub(QueryBus::class);
        $bus->method('fetch')->willReturnCallback(static function (Query $query) use (&$dispatched): CsrfProofView {
            $dispatched = $query;

            return self::view();
        });
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withHeader('Cookie', '__Secure-agent_os_csrf='.$nonce);

        ($this->action($bus))($request);

        self::assertSame($nonce, $dispatched instanceof GetCsrfProof ? $dispatched->nonce : null);
    }

    /**
     * Discards an invalid nonce so the handler issues a new one
     */
    public function test_that_invalid_cookie_maps_to_absent_nonce(): void
    {
        $dispatched = null;
        $bus = $this->createStub(QueryBus::class);
        $bus->method('fetch')->willReturnCallback(static function (Query $query) use (&$dispatched): CsrfProofView {
            $dispatched = $query;

            return self::view();
        });
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withHeader('Cookie', '__Secure-agent_os_csrf=unsafe');

        ($this->action($bus))($request);

        self::assertNull($dispatched instanceof GetCsrfProof ? $dispatched->nonce : 'unexpected query');
    }

    /**
     * Rejects ambiguous cookies before any query call
     */
    public function test_that_ambiguous_cookie_is_rejected(): void
    {
        $bus = $this->createStub(QueryBus::class);
        $bus->method('fetch')->willThrowException(new \LogicException('Ambiguous cookie reached the query bus.'));
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf')
            ->withHeader('Cookie', '__Secure-agent_os_csrf=x; __Secure-agent_os_csrf=y');
        $this->expectException(HttpBadRequestException::class);

        ($this->action($bus))($request);
    }

    /**
     * Rejects an unexpected query bus result without presenting it
     */
    public function test_that_unexpected_query_result_is_rejected(): void
    {
        $bus = $this->createStub(QueryBus::class);
        $bus->method('fetch')->willReturn('unsafe');
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://agent-os.test/api/v1/auth/csrf');
        $this->expectException(\UnexpectedValueException::class);

        ($this->action($bus))($request);
    }

    /**
     * Creates the Action and real responder from injected capabilities
     */
    private function action(QueryBus $bus): CsrfBootstrapAction
    {
        return new CsrfBootstrapAction(
            $bus,
            new CsrfBootstrapResponder(new JSendResponseFactory(new ResponseFactory(), new StreamFactory()))
        );
    }

    /**
     * Supplies a valid transport-free query result
     */
    private static function view(): CsrfProofView
    {
        return new CsrfProofView(
            str_repeat('a', 64),
            new CsrfProof('1000.'.str_repeat('a', 64), 1000),
            false
        );
    }
}
