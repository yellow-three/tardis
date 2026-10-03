<?php

declare(strict_types=1);

namespace Tardis\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tardis\Manager\AssetManager;

/**
 * Serves a file asset that a plugin or the host registered. The URL carries
 * only a content hash: it is looked up in the registered assets, so no part of
 * the request ever becomes a file path.
 */
class AssetController
{
    public function __invoke(Request $request, AssetManager $assets, string $hash, string $extension): Response
    {
        $asset = $assets->findByHash($hash, $extension);

        abort_if($asset === null || $asset->file === null, 404);

        $response = response((string) file_get_contents($asset->file), 200, [
            'Content-Type' => $extension === 'css' ? 'text/css; charset=UTF-8' : 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setEtag($hash);
        $response->isNotModified($request);

        return $response;
    }
}
