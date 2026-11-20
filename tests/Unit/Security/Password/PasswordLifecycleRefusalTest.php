<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Password;

use App\Application\Security\Activation\ActivateInvitation;
use App\Application\Security\Password\CompromisedPasswordChecker;
use App\Application\Security\Password\PasswordSetting;
use App\Application\Security\PasswordChange\ChangePassword;
use App\Application\Security\PasswordReset\CompletePasswordReset;
use App\Domain\Security\Password\PasswordStructure;
use DateInterval;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\RefreshCredentialGenerator;
use Fight\AccessControl\Application\AccessControl\RefreshSession\Service\SessionRevocationService;
use Fight\AccessControl\Application\AccessControl\User\Authentication\TokenIssuer;
use Fight\AccessControl\Application\AccessControl\User\Authentication\UserAuthentication;
use Fight\AccessControl\Application\AccessControl\User\PasswordManagement;
use Fight\AccessControl\Application\AccessControl\User\Security\AuthenticationTokenPolicy;
use Fight\AccessControl\Application\AccessControl\User\Service\LoginThrottle;
use Fight\AccessControl\Domain\AccessControl\ActivationGrant\ActivationGrantRepository;
use Fight\AccessControl\Domain\AccessControl\PasswordResetGrant\PasswordResetGrantRepository;
use Fight\AccessControl\Domain\AccessControl\RefreshSession\RefreshSessionRepository;
use Fight\AccessControl\Domain\AccessControl\User\PasswordHash;
use Fight\AccessControl\Domain\AccessControl\User\User;
use Fight\AccessControl\Domain\AccessControl\User\UserId;
use Fight\AccessControl\Domain\AccessControl\User\UserRepository;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessClock;

/**
 * Ensures credential refusals leave Agent OS as generic conflicts without package diagnostics
 */
final class PasswordLifecycleRefusalTest extends TestCase
{
    private const string CANDIDATE = 'Distinct-Fixture-Ω42!';

    /**
     * Converts a missing activation grant without hashing or issuing a session
     */
    public function testActivationRefusalIsGeneric(): void
    {
        $clock = new AccessClock();
        $user = User::invite(UserId::generate(), EmailAddress::fromString('pending@example.test'), $clock->time);
        $users = $this->createMock(UserRepository::class);
        $users->method('getById')->willReturn($user);
        $users->expects(self::never())->method('replaceAuthenticationAuthorityAndAddRefreshSession');
        $grants = $this->createStub(ActivationGrantRepository::class);
        $grants->method('getLatestByUserId')->willReturn(null);
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects(self::never())->method('hash');
        $authentication = new UserAuthentication(
            $users,
            $grants,
            $this->transaction(),
            $clock,
            $this->createStub(LoginThrottle::class),
            $this->createStub(RefreshCredentialGenerator::class),
            $hasher,
            $this->createStub(PasswordValidator::class),
            new TokenIssuer(
                $this->createStub(TokenEncoder::class),
                AuthenticationTokenPolicy::starterDefaults(new DateInterval('PT5S'))
            ),
            $this->fixtureHash(),
            $this->createStub(EventDispatcher::class)
        );
        $activation = new ActivateInvitation($this->passwordSetting($hasher), $authentication);

        $this->assertGenericConflict(static function () use ($activation, $user): void {
            $activation->activate($user->getId(), str_repeat('a', 64), self::CANDIDATE);
        });
    }

    /**
     * Converts failed current proof without writing replacement authority
     */
    public function testPasswordChangeRefusalIsGeneric(): void
    {
        $clock = new AccessClock();
        $user = $this->activeUser($clock);
        $users = $this->createMock(UserRepository::class);
        $users->method('getById')->willReturn($user);
        $users->expects(self::never())->method('replaceAuthenticationAuthority');
        $validator = $this->createStub(PasswordValidator::class);
        $validator->method('validate')->willReturn(false);
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects(self::never())->method('hash');
        $passwords = $this->passwordManagement(
            $users,
            $this->createStub(PasswordResetGrantRepository::class),
            $clock,
            $hasher,
            $validator
        );
        $change = new ChangePassword($this->passwordSetting($hasher), $passwords);

        $this->assertGenericConflict(static function () use ($change, $user): void {
            $change->change($user->getId(), 'fixture-nonmatching-proof', self::CANDIDATE);
        });
    }

    /**
     * Converts a missing reset grant without writing replacement authority
     */
    public function testPasswordResetRefusalIsGeneric(): void
    {
        $clock = new AccessClock();
        $user = $this->activeUser($clock);
        $users = $this->createMock(UserRepository::class);
        $users->method('getById')->willReturn($user);
        $users->method('validateRecoveryAuthority')->willReturn(true);
        $users->expects(self::never())->method('replaceAuthenticationAuthority');
        $grants = $this->createStub(PasswordResetGrantRepository::class);
        $grants->method('getLatestByUserId')->willReturn(null);
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects(self::never())->method('hash');
        $validator = $this->createStub(PasswordValidator::class);
        $passwords = $this->passwordManagement($users, $grants, $clock, $hasher, $validator);
        $reset = new CompletePasswordReset($this->passwordSetting($hasher), $passwords);

        $this->assertGenericConflict(static function () use ($reset, $user): void {
            $reset->complete($user->getId(), str_repeat('a', 64), self::CANDIDATE);
        });
    }

    /**
     * Checks the safe shared conflict rather than the package-specific diagnostic or exception chain
     *
     * @param callable(): void $operation
     */
    private function assertGenericConflict(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected the application to refuse the credential operation.');
        } catch (StateConflict $failure) {
            self::assertSame('', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }
    }

    /**
     * Composes the released password owner with isolated ports
     */
    private function passwordManagement(
        UserRepository $users,
        PasswordResetGrantRepository $grants,
        AccessClock $clock,
        PasswordHasher $hasher,
        PasswordValidator $validator
    ): PasswordManagement {
        return new PasswordManagement(
            $users,
            $grants,
            new SessionRevocationService($this->createStub(RefreshSessionRepository::class)),
            $this->transaction(),
            $clock,
            $hasher,
            $validator,
            $this->fixtureHash(),
            $this->createStub(EventDispatcher::class)
        );
    }

    /**
     * Runs the callback in memory without making claims about database transactions
     */
    private function transaction(): TransactionalUnitOfWork
    {
        $transaction = $this->createStub(TransactionalUnitOfWork::class);
        $transaction->method('commitTransactional')->willReturnCallback(static fn(callable $work): mixed => $work());

        return $transaction;
    }

    /**
     * Admits a structurally valid candidate without an external corpus
     */
    private function passwordSetting(PasswordHasher $hasher): PasswordSetting
    {
        $checker = $this->createStub(CompromisedPasswordChecker::class);
        $checker->method('isCompromised')->willReturn(false);

        return new PasswordSetting(new PasswordStructure(), $checker, $hasher);
    }

    /**
     * Supplies established package authority without persistence
     */
    private function activeUser(AccessClock $clock): User
    {
        $user = User::invite(UserId::generate(), EmailAddress::fromString('active@example.test'), $clock->time);
        $user->activate($this->fixtureHash(), $clock->time);

        return $user;
    }

    /**
     * Supplies valid synthetic password material for the fixture
     */
    private function fixtureHash(): PasswordHash
    {
        return PasswordHash::fromString(password_hash('fixture-password', PASSWORD_BCRYPT, ['cost' => 4]));
    }
}
