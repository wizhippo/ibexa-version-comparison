<?php

declare(strict_types=1);

namespace Wizhippo\IbexaVersionComparison\Service;

final class PreviewDocument
{
    public function create(string $html, string $origin): string
    {
        $base = TokenDiff::escape(rtrim($origin, '/').'/');

        return '<!doctype html><html><head><meta charset="utf-8"><base href="'.$base.'"><style>
body{font:15px/1.5 Arial,sans-serif;margin:0;padding:16px;color:#171b20;background:#fff;overflow-wrap:anywhere}
img,video{max-width:100%;height:auto}table{border-collapse:collapse;width:100%}th,td{border:1px solid #bbb;padding:8px;vertical-align:top}
h1,h2,h3,h4,h5,h6{line-height:1.25}a,button,input,select,textarea{pointer-events:none}
.vc-added{background:#d8edff;color:#063960;text-decoration:none}.vc-removed{background:#ffe0e3;color:#7a1121;text-decoration:line-through}
</style></head><body>'.$html.'</body></html>';
    }
}
