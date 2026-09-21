<?php

declare(strict_types=1);

use Fight\Common\Adapter\Auth\Hmac\HmacRequestService;
use Fight\Common\Adapter\Auth\Security\PhpPasswordHasher;
use Fight\Common\Adapter\Auth\Security\PhpPasswordValidator;
use Fight\Common\Adapter\Auth\Security\JwtDecoder;
use Fight\Common\Adapter\Auth\Security\JwtEncoder;
use Fight\Common\Application\Auth\RequestService;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Auth\Security\TokenDecoder;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use Fight\Common\Application\Service\Container;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Validation;

return static function (Container $container): void {
    $requiredEnvironmentValue = static function (string $name): string {
        $value = getenv($name);

        if ($value === false || $value === '') {
            throw new RuntimeException(sprintf('%s must be configured.', $name));
        }

        return $value;
    };

    $container->set(ValidatorInterface::class, static function (): ValidatorInterface {
        return Validation::createValidator();
    });
    $container->set(PasswordHasher::class, static function (): PasswordHasher {
        return new PhpPasswordHasher(PASSWORD_ARGON2ID);
    });
    $container->set(PasswordValidator::class, static function (): PasswordValidator {
        return new PhpPasswordValidator(PASSWORD_ARGON2ID);
    });
    $container->set(
        TokenEncoder::class,
        static function () use ($requiredEnvironmentValue): TokenEncoder {
            return new JwtEncoder($requiredEnvironmentValue('APP_HMAC_SECRET'));
        }
    );
    $container->set(
        TokenDecoder::class,
        static function () use ($requiredEnvironmentValue): TokenDecoder {
            return new JwtDecoder($requiredEnvironmentValue('APP_HMAC_SECRET'));
        }
    );
    $container->set(
        RequestService::class,
        static function () use ($requiredEnvironmentValue): RequestService {
            return new HmacRequestService(
                $requiredEnvironmentValue('APP_HMAC_KEY'),
                $requiredEnvironmentValue('APP_HMAC_SECRET')
            );
        }
    );
};
