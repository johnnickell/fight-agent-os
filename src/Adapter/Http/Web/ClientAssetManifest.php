<?php

declare(strict_types=1);

namespace App\Adapter\Http\Web;

use JsonException;

/**
 * Class ClientAssetManifest
 *
 * Resolves the built client entry assets without exposing deployment paths
 */
final class ClientAssetManifest
{
    /**
     * Constructs ClientAssetManifest
     */
    public function __construct(private readonly string $buildDirectory)
    {
    }

    /**
     * Returns available same-origin assets or an unavailable deployment
     *
     * @return array{prepaint: string, script: string, stylesheet: string}|null
     */
    public function read(): ?array
    {
        $path = $this->buildDirectory.'/manifest.json';
        if (!is_readable($path)) {
            return null;
        }

        $source = file_get_contents($path);
        if ($source === false) {
            return null;
        }

        try {
            $manifest = json_decode($source, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($manifest) || count($manifest) !== 3) {
            return null;
        }

        foreach (['prepaint' => 'js', 'script' => 'js', 'stylesheet' => 'css'] as $key => $extension) {
            $asset = $manifest[$key] ?? null;
            $prefix = $key === 'prepaint' ? 'prepaint' : 'main';
            if (
                !is_string($asset)
                || preg_match('~\A/build/'.$prefix.'-[A-Z0-9]{8}\.'.$extension.'\z~', $asset) !== 1
                || !is_file($this->buildDirectory.'/'.basename($asset))
            ) {
                return null;
            }
        }

        return [
            'prepaint'   => $manifest['prepaint'],
            'script'     => $manifest['script'],
            'stylesheet' => $manifest['stylesheet']
        ];
    }
}
