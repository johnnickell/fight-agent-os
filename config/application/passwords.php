<?php

declare(strict_types=1);

use App\Adapter\Security\Password\Argon2idPasswords;
use App\Adapter\Security\Password\LocalCompromisedPasswords;
use App\Application\Security\Password\CompromisedPasswordChecker;
use App\Application\Security\Password\PasswordSetting;
use App\Domain\Security\Password\PasswordStructure;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Service\Container;

return static function (Container $container): void {
    $container->set(Argon2idPasswords::class, static fn(): Argon2idPasswords => new Argon2idPasswords());
    $container->set(LocalCompromisedPasswords::class, static fn(): LocalCompromisedPasswords =>
        new LocalCompromisedPasswords(dirname(__DIR__, 2).'/resources/passwords/common.sha256'));
    $container->set(CompromisedPasswordChecker::class, static fn(Container $container): CompromisedPasswordChecker =>
        $container->get(LocalCompromisedPasswords::class));
    $container->set(PasswordStructure::class, static fn(): PasswordStructure => new PasswordStructure());
    $container->set(PasswordSetting::class, static fn(Container $container): PasswordSetting => new PasswordSetting(
        $container->get(PasswordStructure::class),
        $container->get(CompromisedPasswordChecker::class),
        $container->get(PasswordHasher::class)
    ));

    // Mandatory in all environments and entrypoints before any password-setting consumer can run.
    $container->get(LocalCompromisedPasswords::class);
    $container->get(Argon2idPasswords::class);
};
