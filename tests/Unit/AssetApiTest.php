<?php

declare(strict_types=1);

use Tardis\Assets\Asset;
use Tardis\Facades\Tardis;
use Tardis\Manager\AssetManager;

test('an Asset describes a stylesheet or script by url or inline text', function () {
    $css = Asset::css('/vendor/host/extra.css');
    $js = Asset::js('https://cdn.example.test/x.js', integrity: 'sha384-abc', defer: false);
    $inline = Asset::inlineCss('.a{color:red}');

    expect($css->type)->toBe('css')->and($css->url)->toBe('/vendor/host/extra.css')->and($css->inline)->toBeNull()
        ->and($js->type)->toBe('js')->and($js->integrity)->toBe('sha384-abc')->and($js->defer)->toBeFalse()
        ->and($inline->inline)->toBe('.a{color:red}')->and($inline->url)->toBeNull();
});

test('Tardis::addCss and addJs register assets that the next page emits', function () {
    Tardis::addCss('/vendor/host/extra.css');
    Tardis::addJs(Asset::js('/vendor/host/extra.js'));
    Tardis::addCss(Asset::inlineCss('.host{margin:0}'));

    $manager = app(AssetManager::class);
    $styles = $manager->styles();
    $scripts = $manager->scripts();

    expect($styles)->toContain('<link rel="stylesheet" href="/vendor/host/extra.css">')
        ->toContain('<style>.host{margin:0}</style>')
        ->and($scripts)->toContain('<script src="/vendor/host/extra.js" defer></script>');
});

test('assets load package first, then registered, then plugins, config last', function () {
    config()->set('tardis.assets.css', ['/from-config.css']);
    Tardis::addCss('/from-api.css');

    $styles = app(AssetManager::class)->styles();

    expect(strpos($styles, 'app.css'))->toBeLessThan(strpos($styles, '/from-api.css'))
        ->and(strpos($styles, '/from-api.css'))->toBeLessThan(strpos($styles, '/from-config.css'));
});

test('unsafe urls and integrity values are refused', function () {
    Tardis::addCss('javascript:alert(1)');
    Tardis::addJs(Asset::js('//evil.test/x.js'));
    Tardis::addJs(Asset::js('https://cdn.example.test/ok.js', integrity: 'sha384-ok"><script>x</script>'));

    $manager = app(AssetManager::class);
    $styles = $manager->styles();
    $scripts = $manager->scripts();

    expect($styles)->not->toContain('javascript:')
        ->and($scripts)->not->toContain('evil.test')
        ->and($scripts)->toContain('https://cdn.example.test/ok.js')
        ->and($scripts)->not->toContain('<script>x');
});

test('an inline asset cannot close its own element', function () {
    Tardis::addCss(Asset::inlineCss('.a{}</style><script>x</script>'));

    $styles = app(AssetManager::class)->styles();

    // Only the element's own closing tag may appear.
    expect(substr_count($styles, '</style>'))->toBe(1)
        ->and($styles)->not->toContain('</script>');
});
