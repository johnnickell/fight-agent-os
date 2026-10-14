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
        // No real named product form exists yet. The complete empty catalog is intentional.
        return [];
    }
}
