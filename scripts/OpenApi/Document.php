<?php

declare(strict_types=1);

namespace Tooling\OpenApi;

use cebe\openapi\json\JsonPointer;
use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use JsonSchema\Validator;
use OpenApi\Analysers\AttributeAnnotationFactory;
use OpenApi\Analysers\ReflectionAnalyser;
use OpenApi\Generator;
use OpenApi\Processors\AugmentProperties;
use OpenApi\Processors\DocBlockDescriptions;
use Psr\Log\AbstractLogger;

/**
 * Class Document
 *
 * Generates the bounded owned contract for explicit tooling and response checks
 */
final class Document
{
    /**
     * Generates and validates a fresh deterministic projection without filesystem writes
     */
    public static function generate(): string
    {
        $root = dirname(__DIR__, 2);
        $files = self::sources($root);
        $identity = self::identity($root, $files);
        $logger = new class extends AbstractLogger {
            /**
             * @inheritDoc
             */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if (!in_array($level, ['debug', 'info'], true)) {
                    throw new \RuntimeException('Attribute generation reported a warning; inspect owned attributes.');
                }
            }
        };
        $generator = (new Generator($logger))
            ->setVersion('3.0.3')
            ->setConfig(['augmentParameters' => ['augmentOperationParameters' => false]])
            ->setAnalyser(new ReflectionAnalyser([new AttributeAnnotationFactory(true)]));
        $generator->getProcessorPipeline()
            ->remove(DocBlockDescriptions::class)
            ->remove(AugmentProperties::class);
        $spec = $generator->generate(array_map(static fn (string $file): string => $root.'/'.$file, $files));
        if ($spec === null) {
            throw new \RuntimeException('Attribute generation returned no document.');
        }

        $document = json_decode($spec->toJson(), false, 512, JSON_THROW_ON_ERROR);
        if (!$document instanceof \stdClass) {
            throw new \RuntimeException('Attribute generation returned an invalid document.');
        }

        $document->{'x-source-sha256'} = $identity;
        $json = json_encode(
            self::canonicalize($document),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        )."\n";
        $schema = Reader::readFromJson($json);
        $schema->setDocumentContext($schema, new JsonPointer(''));
        $schema->resolveReferences(new ReferenceContext($schema, '/openapi.json'));
        $validator = new Validator();
        $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        $validator->validate($data, (object) [
            '$ref' => 'file://'.$root.'/vendor/devizzent/cebe-php-openapi/schemas/openapi-v3.0.json'
        ]);
        if (!$schema->validate() || !$validator->isValid()) {
            throw new \RuntimeException('The generated document failed the locked OpenAPI 3.0 validator.');
        }

        if ($files !== self::sources($root) || $identity !== self::identity($root, $files)) {
            throw new \RuntimeException('Contract sources changed during generation; rerun from a stable checkout.');
        }

        return $json;
    }

    /**
     * Lists only owned operation, responder and shared-schema PHP files
     *
     * @return list<string>
     */
    private static function sources(string $root): array
    {
        $files = [];
        $scopes = ['src/Adapter/Http/Action/Api', 'src/Adapter/Http/Responder/Api', 'src/Adapter/Http/Api/OpenApi'];
        foreach ($scopes as $scope) {
            if (realpath($root.'/'.$scope) !== $root.'/'.$scope) {
                throw new \RuntimeException('Contract source locations must be local directories without symlinks.');
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$scope));
            foreach ($iterator as $file) {
                if ($file->isLink()) {
                    throw new \RuntimeException('Symlinks are not allowed in contract source locations.');
                }

                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Computes the identity of relative source names, contents and the locked generation recipe
     *
     * @param string             $root
     * @param array<int, string> $files
     */
    private static function identity(string $root, array $files): string
    {
        $files = [...$files, 'composer.json', 'composer.lock', 'scripts/OpenApi/Document.php', 'scripts/openapi.php'];
        sort($files, SORT_STRING);
        $hash = hash_init('sha256');
        foreach ($files as $file) {
            $contents = file_get_contents($root.'/'.$file);
            if ($contents === false) {
                throw new \RuntimeException('Cannot read a contract source.');
            }

            hash_update($hash, $file."\0".$contents."\0");
        }

        return hash_final($hash);
    }

    /**
     * Sorts object keys and rejects external references before any validator can resolve them
     */
    private static function canonicalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        if (!$value instanceof \stdClass) {
            return $value;
        }

        $properties = get_object_vars($value);
        ksort($properties, SORT_STRING);
        $result = new \stdClass();
        foreach ($properties as $key => $child) {
            if ($key === '$ref' && (!is_string($child) || !str_starts_with($child, '#/'))) {
                throw new \RuntimeException('Only document-local OpenAPI references are allowed.');
            }

            $result->{$key} = self::canonicalize($child);
        }

        return $result;
    }
}
