<?php

declare(strict_types=1);

namespace App\Adapter\Validation;

use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Validation\RulesParser;
use ReflectionMethod;
use RuntimeException;

/**
 * Class SchemaProjection
 *
 * Projects explicitly selected PHP rules without publishing server-only declarations
 */
final class SchemaProjection
{
    /**
     * Builds the complete public form map from named real Actions
     *
     * @param list<array{name: string, action: class-string, fields: array<string, array{
     *     client_field: string, rules: list<array{index: int, message: string}>
     * }>}> $registrations
     *
     * @return array<string, array{fields: array<string, array{client_field: string,
     *     rules: list<array{type: string, args: list<string>, message: string, depends_on: list<string>}>
     * }>}>
     */
    public static function project(array $registrations): array
    {
        $forms = [];
        foreach ($registrations as $registration) {
            $name = $registration['name'];
            if (!Catalog::identifier($name) || isset($forms[$name])) {
                throw new RuntimeException('Invalid or duplicate public form name.');
            }
            $method = new ReflectionMethod($registration['action'], '__invoke');
            $attributes = $method->getAttributes(Validation::class);
            if (count($attributes) !== 1) {
                throw new RuntimeException('A public form needs one Validation declaration.');
            }
            /** @var Validation $validation */
            $validation = $attributes[0]->newInstance();
            if ($validation->formName() !== $name) {
                throw new RuntimeException('Public form name does not match its declaration.');
            }
            $definitions = $validation->rules();
            $parsed = RulesParser::parse($definitions);
            if (count($definitions) !== count($parsed) || $registration['fields'] === []) {
                throw new RuntimeException('Ambiguous or empty public field declaration.');
            }
            $fields = [];
            $clientNames = [];
            foreach ($registration['fields'] as $wire => $selection) {
                $client = $selection['client_field'];
                if (
                    !Catalog::identifier($wire) || !Catalog::clientIdentifier($client) || isset($clientNames[$client])
                    || !isset($parsed[$wire]) || $selection['rules'] === []
                ) {
                    throw new RuntimeException('Invalid or ambiguous public field mapping.');
                }
                $clientNames[$client] = true;
                $rules = [];
                $used = [];
                foreach ($selection['rules'] as $choice) {
                    $index = $choice['index'];
                    if ($index < 0 || isset($used[$index]) || !isset($parsed[$wire][$index])) {
                        throw new RuntimeException('Invalid public rule selection.');
                    }
                    $used[$index] = true;
                    $source = $parsed[$wire][$index];
                    if ($choice['message'] !== $source['error'] || !Catalog::message($choice['message'])) {
                        throw new RuntimeException('Public error text must match a bounded declared message.');
                    }
                    $type = $source['type'];
                    $args = array_values($source['args']);
                    $dependencies = [];
                    if ($type === 'Same') {
                        if (count($args) !== 1 || !isset($registration['fields'][$args[0]])) {
                            throw new RuntimeException('Comparison requires a published peer field.');
                        }
                        $dependencies[] = $registration['fields'][$args[0]]['client_field'];
                    } elseif ($type === 'Type') {
                        if ($args !== ['string']) {
                            throw new RuntimeException('Only non-null string type is publicly qualified.');
                        }
                    } elseif (in_array($type, ['MinLength', 'MaxLength'], true)) {
                        if (count($args) !== 1 || !preg_match('/\A(?:0|[1-9][0-9]{0,3})\z/D', $args[0])) {
                            throw new RuntimeException('Length must be a bounded decimal integer.');
                        }
                    } elseif ($type !== 'Required' || $args !== []) {
                        throw new RuntimeException('Unsupported public rule semantics.');
                    }
                    $rules[] = [
                        'type'       => $type,
                        'args'       => $args,
                        'message'    => $choice['message'],
                        'depends_on' => $dependencies
                    ];
                }
                $fields[$wire] = ['client_field' => $client, 'rules' => $rules];
            }
            ksort($fields, SORT_STRING);
            $forms[$name] = ['fields' => $fields];
        }
        ksort($forms, SORT_STRING);

        return $forms;
    }
}
