<?php

declare(strict_types=1);

namespace App\Adapter\Validation;

use RuntimeException;
use stdClass;

/**
 * Class Catalog
 *
 * Reads one complete private generation without runtime reflection or path construction
 */
final readonly class Catalog
{
    /**
     * Constructs Catalog
     *
     * @param string       $file
     * @param array<int, string> $allowedNames
     */
    public function __construct(private string $file, private array $allowedNames)
    {
    }

    /**
     * Encodes a complete sorted generation with a content revision
     *
     * @param array<string, mixed>|stdClass $forms
     */
    public static function generation(array|stdClass $forms): string
    {
        $forms = (object) $forms;

        return self::encode($forms, hash('sha256', self::payload($forms)));
    }

    /**
     * Checks a canonical bounded public identifier
     */
    public static function identifier(string $name): bool
    {
        return (bool) preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $name);
    }

    /**
     * Checks a bounded camelCase client field identifier
     */
    public static function clientIdentifier(string $name): bool
    {
        return (bool) preg_match('/\A[a-z][a-zA-Z0-9]{0,63}\z/D', $name);
    }

    /**
     * Checks bounded single-line public text
     */
    public static function message(string $text): bool
    {
        return $text !== '' && strlen($text) <= 200 && mb_check_encoding($text, 'UTF-8')
            && !preg_match('/[\x00-\x1f\x7f]/', $text);
    }

    /**
     * Finds an approved form or null; throws when the complete artifact is unavailable
     *
     * @return array{schema_version: int, revision: string, form_name: string, fields: array<string, mixed>}|null
     */
    public function find(string $name): ?array
    {
        if (!self::identifier($name) || !in_array($name, $this->allowedNames, true)) {
            return null;
        }
        $catalog = $this->load();
        /** @var stdClass $forms */
        $forms = $catalog->forms;
        if (!property_exists($forms, $name)) {
            throw new RuntimeException('Incomplete public form catalog.');
        }
        /** @var stdClass $form */
        $form = $forms->{$name};

        return [
            'schema_version' => 1,
            'revision'       => $catalog->revision,
            'form_name'      => $name,
            'fields'         => json_decode(
                json_encode($form->fields, JSON_THROW_ON_ERROR),
                true,
                32,
                JSON_THROW_ON_ERROR
            )
        ];
    }

    /**
     * Encodes a catalog in its versioned canonical wire representation
     */
    private static function encode(stdClass $forms, string $revision): string
    {
        return json_encode(
            ['schema_version' => 1, 'forms' => $forms, 'revision' => $revision],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        )."\n";
    }

    /**
     * Computes the content identity independently of the revision field
     */
    private static function payload(stdClass $forms): string
    {
        return json_encode(
            ['schema_version' => 1, 'forms' => $forms],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Requires exactly the documented keys
     *
     * @param stdClass     $object
     * @param array<int, string> $expected
     */
    private static function keys(stdClass $object, array $expected): bool
    {
        $keys = array_keys(get_object_vars($object));
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);

        return $keys === $expected;
    }

    /**
     * Validates the entire generation before resolving any name
     */
    private function load(): stdClass
    {
        if (
            is_link($this->file) || realpath(dirname($this->file)) !== dirname($this->file)
            || !is_file($this->file) || filesize($this->file) > 262144
        ) {
            throw new RuntimeException('Public form catalog unavailable.');
        }
        $bytes = file_get_contents($this->file);
        if ($bytes === false || strlen($bytes) > 262144) {
            throw new RuntimeException('Public form catalog unavailable.');
        }
        try {
            $data = json_decode($bytes, false, 32, JSON_THROW_ON_ERROR);
            if (
                !$data instanceof stdClass || !self::keys($data, ['schema_version', 'forms', 'revision'])
                || $data->schema_version !== 1 || !$data->forms instanceof stdClass
                || !is_string($data->revision) || !preg_match('/\A[a-f0-9]{64}\z/D', $data->revision)
            ) {
                throw new RuntimeException('Unsupported public form catalog.');
            }
            $names = array_keys(get_object_vars($data->forms));
            $allowed = $this->allowedNames;
            sort($names, SORT_STRING);
            sort($allowed, SORT_STRING);
            if ($names !== $allowed) {
                throw new RuntimeException('Incomplete public form catalog.');
            }
            foreach (get_object_vars($data->forms) as $name => $form) {
                if (
                    !self::identifier($name) || !$form instanceof stdClass || !self::keys($form, ['fields'])
                    || !$form->fields instanceof stdClass || get_object_vars($form->fields) === []
                ) {
                    throw new RuntimeException('Invalid public form.');
                }
                $clientNames = [];
                foreach (get_object_vars($form->fields) as $field => $definition) {
                    if (
                        !self::identifier($field) || !$definition instanceof stdClass
                        || !self::keys($definition, ['client_field', 'rules'])
                        || !is_string($definition->client_field) || !self::clientIdentifier($definition->client_field)
                        || isset($clientNames[$definition->client_field])
                        || !is_array($definition->rules) || $definition->rules === []
                    ) {
                        throw new RuntimeException('Invalid public field.');
                    }
                    $clientNames[$definition->client_field] = true;
                    foreach ($definition->rules as $rule) {
                        if (
                            !$rule instanceof stdClass || !self::keys($rule, ['type', 'args', 'message', 'depends_on'])
                            || !is_string($rule->type) || !is_array($rule->args)
                            || !is_string($rule->message) || !self::message($rule->message)
                            || !is_array($rule->depends_on)
                        ) {
                            throw new RuntimeException('Invalid public rule.');
                        }
                        $args = $rule->args;
                        $dependencies = $rule->depends_on;
                        if ($rule->type === 'Same') {
                            if (
                                count($args) !== 1 || count($dependencies) !== 1
                                || !is_string($args[0]) || !isset($form->fields->{$args[0]})
                                || $dependencies[0] !== $form->fields->{$args[0]}->client_field
                            ) {
                                throw new RuntimeException('Invalid public comparison.');
                            }
                        } elseif ($rule->type === 'Type') {
                            if ($args !== ['string'] || $dependencies !== []) {
                                throw new RuntimeException('Invalid public type.');
                            }
                        } elseif (in_array($rule->type, ['MinLength', 'MaxLength'], true)) {
                            if (
                                count($args) !== 1 || !is_string($args[0])
                                || !preg_match('/\A(?:0|[1-9][0-9]{0,3})\z/D', $args[0]) || $dependencies !== []
                            ) {
                                throw new RuntimeException('Invalid public length.');
                            }
                        } elseif ($rule->type !== 'Required' || $args !== [] || $dependencies !== []) {
                            throw new RuntimeException('Unsupported public rule.');
                        }
                    }
                }
            }
            if ($bytes !== self::encode($data->forms, $data->revision)) {
                throw new RuntimeException('Noncanonical public form catalog.');
            }
            $payload = self::payload($data->forms);
            if (!hash_equals(hash('sha256', $payload), $data->revision)) {
                throw new RuntimeException('Public form catalog integrity failure.');
            }

            return $data;
        } catch (\Throwable $exception) {
            throw new RuntimeException('Public form catalog unavailable.', 0, $exception);
        }
    }
}
