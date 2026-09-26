<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Domain\Security\Csrf\CsrfProof;
use App\Domain\Security\Csrf\Query\CsrfProofView;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class CsrfProofTest
 *
 * Exercises proof and query-view invariants without HTTP
 */
final class CsrfProofTest extends TestCase
{
    /**
     * Rejects a proof whose signed deadline disagrees with its expiry
     */
    public function test_that_proof_rejects_mismatched_deadline(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CsrfProof('100.'.str_repeat('a', 64), 101);
    }

    /**
     * Rejects malformed proof encodings and impossible deadlines
     *
     * @return iterable<string, array{string, int}>
     */
    public static function invalid_proofs(): iterable
    {
        yield 'empty proof' => ['', 100];
        yield 'short signature' => ['100.abc', 100];
        yield 'zero deadline' => ['0.'.str_repeat('a', 64), 0];
        yield 'oversized deadline' => ['10000000000.'.str_repeat('a', 64), 10000000000];
    }

    /**
     * Rejects invalid proof values
     */
    #[DataProvider('invalid_proofs')]
    public function test_that_proof_rejects_invalid_values(string $proof, int $expiresAt): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CsrfProof($proof, $expiresAt);
    }

    /**
     * Rejects non-canonical nonce values in the query result
     */
    public function test_that_view_rejects_invalid_nonce(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CsrfProofView('unsafe', new CsrfProof('100.'.str_repeat('a', 64), 100), false);
    }
}
