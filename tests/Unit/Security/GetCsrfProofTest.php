<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Domain\Security\Csrf\Query\GetCsrfProof;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class GetCsrfProofTest
 *
 * Exercises the transport-free query input contract
 */
final class GetCsrfProofTest extends TestCase
{
    /**
     * Preserves an optional nonce across query serialization
     */
    public function test_that_valid_nonce_round_trips(): void
    {
        $nonce = bin2hex(random_bytes(32));

        $query = GetCsrfProof::fromArray(['nonce' => $nonce]);

        self::assertSame(['nonce' => $nonce], $query->toArray());
    }

    /**
     * Accepts an absent nonce so the handler can generate one
     */
    public function test_that_absent_nonce_round_trips(): void
    {
        $query = GetCsrfProof::fromArray(['nonce' => null]);

        self::assertSame(['nonce' => null], $query->toArray());
    }

    /**
     * Rejects unexpected fields and invalid nonce types
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalid_queries(): iterable
    {
        yield 'missing nonce' => [[]];
        yield 'extra field' => [['nonce' => null, 'secret' => 'unapproved']];
        yield 'wrong type' => [['nonce' => 42]];
        yield 'malformed nonce' => [['nonce' => 'not-a-nonce']];
    }

    /**
     * Rejects noncanonical query input shape
     *
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalid_queries')]
    public function test_that_invalid_input_is_rejected(array $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        GetCsrfProof::fromArray($input);
    }
}
