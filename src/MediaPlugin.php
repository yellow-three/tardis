<?php

namespace Tardis;

use Tardis\Contracts\Plugins\GenericPlugin;

class MediaPlugin implements GenericPlugin
{
    public function name(): string
    {
        return 'tardis-media';
    }

    public function description(): string
    {
        return 'Media management with file upload, grid/list views, multi-select, and ZIP download';
    }
}
