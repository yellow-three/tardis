<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tardis\Menu\SiteMenuStore;

function siteMenus(): SiteMenuStore
{
    return app(SiteMenuStore::class);
}

/** @return list<string> titles with their depth, e.g. ['Blog', '-News'] */
function siteMenuOutline(string $name): array
{
    $walk = function (array $items, int $depth) use (&$walk): array {
        $out = [];

        foreach ($items as $item) {
            $out[] = str_repeat('-', $depth).$item['title'];
            array_push($out, ...$walk($item['children'], $depth + 1));
        }

        return $out;
    };

    return $walk(siteMenus()->items($name), 0);
}

test('menus are created, listed and deleted by name', function () {
    siteMenus()->create('main');
    siteMenus()->create('footer');

    expect(siteMenus()->names())->toBe(['footer', 'main']);

    siteMenus()->delete('footer');

    expect(siteMenus()->names())->toBe(['main'])
        ->and(fn () => siteMenus()->create('Bad Name'))->toThrow(InvalidArgumentException::class);
});

test('items nest, move, indent and outdent', function () {
    siteMenus()->create('main');
    $blog = siteMenus()->add('main', ['title' => 'Blog', 'url' => '/blog']);
    $news = siteMenus()->add('main', ['title' => 'News', 'url' => '/news']);
    $about = siteMenus()->add('main', ['title' => 'About', 'url' => '/about']);
    siteMenus()->add('main', ['title' => 'Team', 'url' => '/team'], $about);

    expect(siteMenuOutline('main'))->toBe(['Blog', 'News', 'About', '-Team']);

    siteMenus()->indent('main', $news);
    expect(siteMenuOutline('main'))->toBe(['Blog', '-News', 'About', '-Team']);

    siteMenus()->move('main', $about, -1);
    expect(siteMenuOutline('main'))->toBe(['About', '-Team', 'Blog', '-News']);

    siteMenus()->outdent('main', $news);
    expect(siteMenuOutline('main'))->toBe(['About', '-Team', 'Blog', 'News']);

    siteMenus()->update('main', $blog, ['title' => 'Journal', 'new_tab' => true]);
    siteMenus()->remove('main', $about);
    expect(siteMenuOutline('main'))->toBe(['Journal', 'News'])
        ->and(siteMenus()->items('main')[0]['new_tab'])->toBeTrue();
});

test('nesting stops at the maximum depth', function () {
    siteMenus()->create('main');
    $a = siteMenus()->add('main', ['title' => 'A', 'url' => '/a']);
    $b = siteMenus()->add('main', ['title' => 'B', 'url' => '/b'], $a);
    $c = siteMenus()->add('main', ['title' => 'C', 'url' => '/c'], $b);

    expect(fn () => siteMenus()->add('main', ['title' => 'D', 'url' => '/d'], $c))->toThrow(InvalidArgumentException::class);

    $d = siteMenus()->add('main', ['title' => 'D', 'url' => '/d'], $b);
    siteMenus()->indent('main', $d);

    expect(siteMenuOutline('main'))->toBe(['A', '-B', '--C', '--D']);
});

test('only safe urls are accepted', function (string $url, bool $ok) {
    siteMenus()->create('main');
    $add = fn () => siteMenus()->add('main', ['title' => 'X', 'url' => $url]);

    $ok ? expect($add())->toBeString() : expect($add)->toThrow(InvalidArgumentException::class);
})->with([
    ['/blog', true], ['https://example.test', true], ['#top', true],
    ['javascript:alert(1)', false], ['//evil.test', false], ['data:text/html,x', false], ['', false],
]);

test('the helpers read settings and menus', function () {
    siteMenus()->create('main');
    siteMenus()->add('main', ['title' => 'Blog', 'url' => '/blog']);

    expect(menu('main')[0]['title'])->toBe('Blog')
        ->and(tardis_menu('missing'))->toBe([])
        ->and(setting('admin.title'))->toBe(tardis_setting('admin.title'))
        ->and(setting('nope.nothing', 'fallback'))->toBe('fallback');
});

test('the site-menu component renders nested lists with escaped titles', function () {
    siteMenus()->create('main');
    $about = siteMenus()->add('main', ['title' => '<b>About</b>', 'url' => '/about', 'new_tab' => true]);
    siteMenus()->add('main', ['title' => 'Team', 'url' => '/team'], $about);

    $html = Blade::render('<x-tardis::site-menu name="main" class="menu" />');

    expect($html)->toContain('<ul class="menu">')
        ->toContain('&lt;b&gt;About&lt;/b&gt;')
        ->toContain('target="_blank" rel="noopener noreferrer"')
        ->toContain('href="'.url('/team').'"')
        ->and(substr_count($html, '<ul'))->toBe(2)
        ->and(Blade::render('<x-tardis::site-menu name="missing" />'))->not->toContain('<ul');
});

test('the site menus page manages a menu', function () {
    Livewire::test('tardis::pages.site-menus')
        ->set('newMenu', 'main')
        ->call('createMenu')
        ->assertSet('menu', 'main')
        ->set('itemTitle', 'Blog')
        ->set('itemUrl', '/blog')
        ->call('addItem')
        ->assertHasNoErrors()
        ->assertSee('Blog')
        ->set('itemTitle', 'Bad')
        ->set('itemUrl', 'javascript:alert(1)')
        ->call('addItem')
        ->assertHasErrors('itemUrl')
        ->set('newMenu', 'Not Valid')
        ->call('createMenu')
        ->assertHasErrors('newMenu');

    expect(siteMenuOutline('main'))->toBe(['Blog']);
});
