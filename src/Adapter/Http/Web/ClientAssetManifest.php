<?php

declare(strict_types=1);

namespace App\Adapter\Http\Web;

use JsonException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemReader;

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
    public function __construct(private readonly FilesystemReader $buildAssets)
    {
    }

    /**
     * Returns available same-origin assets or an unavailable deployment
     *
     * @return array{prepaint: string, script: string, stylesheet: string}|null
     */
    public function read(): ?array
    {
        try {
            $manifest = json_decode($this->buildAssets->read('manifest.json'), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || count($manifest) !== 3) {
                return null;
            }

            foreach (['prepaint' => 'js', 'script' => 'js', 'stylesheet' => 'css'] as $key => $extension) {
                $asset = $manifest[$key] ?? null;
                $prefix = $key === 'prepaint' ? 'prepaint' : 'main';
                if (
                    !is_string($asset)
                    || preg_match('~\A/build/'.$prefix.'-[A-Z0-9]{8}\.'.$extension.'\z~', $asset) !== 1
                    || !$this->buildAssets->fileExists(basename($asset))
                ) {
                    return null;
                }
            }
        } catch (FilesystemException | JsonException) {
            return null;
        }

        return [
            'prepaint'   => $manifest['prepaint'],
            'script'     => $manifest['script'],
            'stylesheet' => $manifest['stylesheet']
        ];
    }
}
