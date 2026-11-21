<?php

declare(strict_types=1);

namespace Tests\Unit\Invitation;

use App\Adapter\Http\Api\Invitation\InvitationOperation;
use App\Adapter\Http\Api\Invitation\InvitationRatePolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects separate invitation operation budgets and their configuration bounds
 */
final class InvitationRatePolicyTest extends TestCase
{
    /**
     * Returns the distinct configured budget for each operation
     */
    public function testOperationBudgets(): void
    {
        $policy = new InvitationRatePolicy(
            issueLimit: 2,
            statusLimit: 5,
            retryLimit: 7,
            resendLimit: 11,
            correctLimit: 13
        );

        self::assertSame(2, $policy->limit(InvitationOperation::ISSUE));
        self::assertSame(5, $policy->limit(InvitationOperation::STATUS));
        self::assertSame(7, $policy->limit(InvitationOperation::RETRY));
        self::assertSame(11, $policy->limit(InvitationOperation::RESEND));
        self::assertSame(13, $policy->limit(InvitationOperation::CORRECT));
    }

    /**
     * Rejects invalid configuration before the budgets can be used
     *
     * @param array<string, int> $configuration
     */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidConfiguration(array $configuration): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InvitationRatePolicy(
            windowSeconds: $configuration['windowSeconds'] ?? 60,
            issueLimit: $configuration['issueLimit'] ?? 10,
            statusLimit: $configuration['statusLimit'] ?? 60,
            capacity: $configuration['capacity'] ?? 10000,
            retryLimit: $configuration['retryLimit'] ?? 5,
            resendLimit: $configuration['resendLimit'] ?? 3,
            correctLimit: $configuration['correctLimit'] ?? 3
        );
    }

    /**
     * Supplies values just outside the allowed bounds for each setting
     *
     * @return iterable<string, array{array<string, int>}>
     */
    public static function invalidConfigurations(): iterable
    {
        $bounds = [
            'windowSeconds' => 86400,
            'issueLimit'    => 1000,
            'statusLimit'   => 10000,
            'capacity'      => 100000,
            'retryLimit'    => 1000,
            'resendLimit'   => 1000,
            'correctLimit'  => 1000
        ];
        foreach ($bounds as $setting => $maximum) {
            yield $setting.' too small' => [[$setting => 0]];
            yield $setting.' too large' => [[$setting => $maximum + 1]];
        }
    }
}
