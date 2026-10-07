<?php

declare(strict_types=1);

/**
 * The panel is translated: a visible word in a Blade view that is not a
 * translation key stays English for everyone. This scans every view for text
 * outside tags, echoes and directives and fails with the offending lines.
 */
function tardisStaticTextIn(string $source): array
{
    // The class of a single/multi-file component sits before the closing tag.
    if (preg_match('/^(<\?php.*?\}; \?>)/s', $source, $head)) {
        $source = substr($source, strlen($head[1]));
    }

    $source = preg_replace('/<script\b.*?<\/script>|<style\b.*?<\/style>|\{\{--.*?--\}\}|@php.*?@endphp/s', '', $source);
    $source = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', '', $source);
    $source = preg_replace('/<[a-zA-Z\/!](?:[^<>"\']|"[^"]*"|\'[^\']*\')*>/', '', $source);
    $source = preg_replace('/@\w+\s*(\((?:[^()\n]|\([^()\n]*\))*\))?/', '', $source);

    $lines = [];

    foreach (explode("\n", $source) as $line) {
        $line = trim($line);

        if (! preg_match('/[A-Za-z]{3,}/', $line)) {
            continue;
        }

        // PHP that survives masking in multi-line expressions, and code samples.
        if (preg_match('/[$]|=>|->|::|composer require/', $line)) {
            continue;
        }

        $lines[] = $line;
    }

    return $lines;
}

test('no Blade view carries untranslated visible text', function () {
    $found = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/views', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $lines = tardisStaticTextIn((string) file_get_contents($file->getPathname()));

        if ($lines !== []) {
            $found[str_replace(realpath(__DIR__.'/../../resources/views').'/', '', $file->getRealPath())] = $lines;
        }
    }

    expect($found)->toBe([]);
});

test('the scanner flags visible words and ignores echoes, directives and tags', function () {
    expect(tardisStaticTextIn('<p class="mb-2">Hello there</p>'))->toBe(['Hello there'])
        ->and(tardisStaticTextIn("<p>{{ __('tardis::x.y') }}</p>"))->toBe([])
        ->and(tardisStaticTextIn("@if (\$a)\n<span>{{ \$b }}</span>\n@endif"))->toBe([])
        ->and(tardisStaticTextIn("<button title=\"Save now\">{{ __('a') }}</button>"))->toBe([]);
});
