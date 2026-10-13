<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Adapter\Console\ManagedPolicyConsole;
use App\Domain\Authorization\InstallationManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Command\ReconcileManagedPolicy;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\ManagedPolicyPlan;
use Fight\AccessControl\Domain\AccessControl\ManagedPolicy\Query\PreviewManagedPolicy;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Exercises the operational transport contract without repository mutation shortcuts
 */
final class ManagedPolicyConsoleTest extends TestCase
{
    /**
     * Dispatches only the selected package use case and presents a deterministic result
     */
    #[DataProvider('operations')]
    public function testOperation(string $operation): void
    {
        $policy = InstallationManagedPolicy::create();
        $queries = $this->createMock(QueryBus::class);
        $commands = $this->createMock(SynchronousCommandBus::class);
        $queries->expects($operation === 'preview' ? self::once() : self::never())
            ->method('fetch')->with(self::callback(
                static fn(PreviewManagedPolicy $query): bool => $query->getPolicy() === $policy
            ))->willReturn(new ManagedPolicyPlan([], []));
        $commands->expects($operation === 'apply' ? self::once() : self::never())
            ->method('execute')->with(self::callback(
                static fn(ReconcileManagedPolicy $command): bool => $command->getPolicy() === $policy
            ));
        $output = '';
        $error = '';
        $exit = (new ManagedPolicyConsole($policy, $queries, $commands))->run(
            [$operation, '--internal'],
            static function (string $text) use (&$output): void {
                $output .= $text;
            },
            static function (string $text) use (&$error): void {
                $error .= $text;
            }
        );
        self::assertSame(0, $exit);
        self::assertSame('', $error);
        $expected = [
            'operation'     => $operation,
            'policy_sha256' => hash('sha256', json_encode($policy->toArray(), JSON_THROW_ON_ERROR))
        ];
        if ($operation === 'preview') {
            $expected['plan'] = ['permissions' => [], 'roles' => []];
        } else {
            $expected['status'] = 'applied';
        }
        self::assertSame(json_encode($expected, JSON_THROW_ON_ERROR)."\n", $output);
    }

    /**
     * Redacts command and query failures rather than echoing exception material
     */
    #[DataProvider('operations')]
    public function testFailureIsSanitized(string $operation): void
    {
        $queries = $this->createMock(QueryBus::class);
        $commands = $this->createMock(SynchronousCommandBus::class);
        $failure = new RuntimeException('SQL password=private-token postgresql://private-host stack trace');
        $queries->method('fetch')->willThrowException($failure);
        $commands->method('execute')->willThrowException($failure);
        $exit = (new ManagedPolicyConsole(InstallationManagedPolicy::create(), $queries, $commands))->run(
            [$operation, '--internal'],
            static function (): void {
                self::fail('Failure must not emit a success or partial plan.');
            },
            static function (string $text): void {
                self::assertSame(ManagedPolicyConsole::FAILURE, $text);
            }
        );
        self::assertSame(1, $exit);
    }

    /**
     * Rejects malformed or unacknowledged input before dispatch
     *
     * @phpstan-param list<string> $arguments
     */
    #[DataProvider('invalidArguments')]
    public function testInvalidArguments(array $arguments): void
    {
        $queries = $this->createMock(QueryBus::class);
        $commands = $this->createMock(SynchronousCommandBus::class);
        $queries->expects(self::never())->method('fetch');
        $commands->expects(self::never())->method('execute');
        self::assertSame(2, (new ManagedPolicyConsole(InstallationManagedPolicy::create(), $queries, $commands))->run(
            $arguments,
            static function (): void {
                self::fail('Invalid input must not emit a result.');
            },
            static function (string $text): void {
                self::assertSame(ManagedPolicyConsole::USAGE, $text);
            }
        ));
    }

    /**
     * Supplies both supported use cases
     *
     * @return iterable<array{string}>
     */
    public static function operations(): iterable
    {
        yield ['preview'];
        yield ['apply'];
    }

    /**
     * Supplies unsafe and unsupported invocations
     *
     * @return iterable<array{list<string>}>
     */
    public static function invalidArguments(): iterable
    {
        yield [[]];
        yield [['apply']];
        yield [['preview']];
        yield [['unknown', '--internal']];
        yield [['apply', '--internal', '--internal']];
        yield [['apply', '--internal', 'private-token']];
    }
}
