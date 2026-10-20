<?php

declare(strict_types=1);

namespace App\Adapter\Validation;

/**
 * Class PublicForms
 *
 * Owns deliberate publication, not validation or endpoint authorization
 */
final class PublicForms
{
    /**
     * Lists real operations explicitly opted into public metadata
     *
     * A future operation supplies its Action class, handle method and wire-field map.
     * Each selected rule identifies its zero-based position in the parsed PHP field rules
     * and its exact public error text. Unselected rules remain server-only.
     *
     * @return list<array{name: string, action: class-string, fields: array<string, array{
     *     client_field: string, rules: list<array{index: int, message: string}>
     * }>}>
     */
    public static function registrations(): array
    {
        return [[
            'name'   => 'invitation_activation',
            'action' => \App\Adapter\Http\Action\Api\V1\Auth\ActivateInvitationAction::class,
            'fields' => [
                'password'              => ['client_field' => 'password', 'rules' => [
                    ['index' => 0, 'message' => 'Password is required'],
                    ['index' => 1, 'message' => 'Password must be a value of type: string'],
                    ['index' => 2, 'message' => \App\Domain\Security\Password\PasswordStructure::GUIDANCE],
                    ['index' => 3, 'message' => 'Password must contain no more than 128 characters']
                ]],
                'password_confirmation' => ['client_field' => 'passwordConfirmation', 'rules' => [
                    ['index' => 0, 'message' => 'Confirmation is required'],
                    ['index' => 1, 'message' => 'Confirmation must be a value of type: string'],
                    ['index' => 2, 'message' => 'Confirmation must contain no less than 8 characters'],
                    ['index' => 3, 'message' => 'Confirmation must contain no more than 128 characters'],
                    ['index' => 4, 'message' => 'Confirmation must be the same as the value in the password field']
                ]]
            ]
        ], [
            'name'   => 'login',
            'action' => \App\Adapter\Http\Action\Api\V1\Auth\LoginAction::class,
            // Remember is a strict server boolean, not a string field or credential-policy hint.
            'fields' => [
                'email'    => ['client_field' => 'email', 'rules' => [
                    ['index' => 0, 'message' => 'Email is required'],
                    ['index' => 1, 'message' => 'Email must be a value of type: string'],
                    ['index' => 2, 'message' => 'Email must contain no less than 1 characters'],
                    ['index' => 3, 'message' => 'Email must contain no more than 254 characters']
                ]],
                'password' => ['client_field' => 'password', 'rules' => [
                    ['index' => 0, 'message' => 'Password is required'],
                    ['index' => 1, 'message' => 'Password must be a value of type: string'],
                    ['index' => 2, 'message' => 'Password must contain no less than 1 characters'],
                    ['index' => 3, 'message' => 'Password must contain no more than 128 characters']
                ]]
            ]
        ]];
    }
}
