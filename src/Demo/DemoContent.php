<?php

declare(strict_types=1);

namespace Tardis\Demo;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tardis\Bread\BreadManager;
use Tardis\Demo\Models\DemoCategory;
use Tardis\Demo\Models\DemoPage;
use Tardis\Demo\Models\DemoPost;
use Tardis\Menu\SiteMenuStore;
use Tardis\Support\JsonFile;

/**
 * Sample content for a fresh panel: categories, posts and pages with their
 * BREADs, a few records and a "main" site menu.
 *
 * Everything lives in tardis_demo_* tables with models inside the package,
 * so it never touches the host's own tables and remove() takes it all away.
 * Nothing that already exists (a BREAD slug, the main menu) is replaced.
 */
final class DemoContent
{
    /**
     * @return list<string> what was done, one line per step
     */
    public function install(): array
    {
        $done = [];

        Artisan::call('migrate', ['--path' => $this->migration(), '--realpath' => true, '--force' => true]);
        $done[] = 'Created the tardis_demo_* tables.';

        foreach ($this->definitions() as $definition) {
            if (app(BreadManager::class)->has($definition['slug'])) {
                $done[] = "Kept your existing [{$definition['slug']}] BREAD; the demo one was not added.";

                continue;
            }

            app(BreadManager::class)->save($definition);
            $done[] = "Added the [{$definition['slug']}] BREAD.";
        }

        if (DemoCategory::query()->doesntExist()) {
            $this->seed();
            $done[] = 'Added sample categories, posts and pages.';
        }

        $store = app(SiteMenuStore::class);

        if (! $store->has('main')) {
            $store->create('main');
            $store->add('main', ['title' => 'Home', 'url' => '/']);
            $blog = $store->add('main', ['title' => 'Blog', 'url' => '/blog']);
            $store->add('main', ['title' => 'News', 'url' => '/blog/news'], $blog);
            $store->add('main', ['title' => 'About', 'url' => '/about']);
            JsonFile::update($this->markerPath(), fn (array $data) => [...$data, 'menu' => 'main']);
            $done[] = 'Added the [main] site menu.';
        }

        return $done;
    }

    /**
     * @return list<string>
     */
    public function remove(): array
    {
        $done = [];

        foreach (app(BreadManager::class)->all() as $slug => $definition) {
            if (str_starts_with(ltrim($definition->model, '\\'), 'Tardis\\Demo\\Models\\')) {
                app(BreadManager::class)->delete($slug);
                $done[] = "Removed the [{$slug}] BREAD.";
            }
        }

        $marker = JsonFile::read($this->markerPath());

        if (($marker['menu'] ?? null) === 'main') {
            app(SiteMenuStore::class)->delete('main');
            $done[] = 'Removed the [main] site menu.';
        }

        @unlink($this->markerPath());

        Artisan::call('migrate:rollback', ['--path' => $this->migration(), '--realpath' => true, '--force' => true]);
        $done[] = 'Dropped the tardis_demo_* tables.';

        return $done;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        $status = ['draft' => 'Draft', 'published' => 'Published'];

        return [
            [
                'slug' => 'categories',
                'model' => DemoCategory::class,
                'name' => 'Category',
                'name_plural' => 'Categories',
                'icon' => 'tag',
                'search_key' => 'name',
                'fields' => [
                    ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'validation' => ['required', 'max:120']],
                    ['name' => 'slug', 'type' => 'slug', 'label' => 'Slug', 'from' => 'name', 'browse' => false, 'validation' => ['required', 'max:120']],
                ],
            ],
            [
                'slug' => 'posts',
                'model' => DemoPost::class,
                'name' => 'Post',
                'name_plural' => 'Posts',
                'icon' => 'document-text',
                'search_key' => 'title',
                'fields' => [
                    ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'validation' => ['required', 'max:200']],
                    ['name' => 'slug', 'type' => 'slug', 'label' => 'Slug', 'from' => 'title', 'browse' => false, 'validation' => ['required', 'max:200']],
                    ['name' => 'category_id', 'type' => 'belongs_to', 'label' => 'Category', 'relation' => 'category', 'model' => DemoCategory::class, 'label_column' => 'name'],
                    ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => $status, 'default' => 'draft'],
                    ['name' => 'featured', 'type' => 'toggle', 'label' => 'Featured', 'browse' => false],
                    ['name' => 'published_at', 'type' => 'datetime', 'label' => 'Published at'],
                    ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Excerpt', 'browse' => false, 'help' => 'One or two sentences for listings.'],
                    ['name' => 'body', 'type' => 'rich_text', 'label' => 'Body', 'browse' => false],
                    ['name' => 'image', 'type' => 'image', 'label' => 'Image', 'browse' => false, 'directory' => 'demo', 'resize' => ['width' => 1600], 'thumbnails' => [['name' => 'small', 'width' => 400, 'height' => 300, 'crop' => true]]],
                ],
            ],
            [
                'slug' => 'pages',
                'model' => DemoPage::class,
                'name' => 'Page',
                'name_plural' => 'Pages',
                'icon' => 'book-open',
                'search_key' => 'title',
                'order_column' => 'position',
                'order_display_column' => 'title',
                'fields' => [
                    ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'validation' => ['required', 'max:200']],
                    ['name' => 'slug', 'type' => 'slug', 'label' => 'Slug', 'from' => 'title', 'validation' => ['required', 'max:200']],
                    ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => $status, 'default' => 'draft'],
                    ['name' => 'body', 'type' => 'rich_text', 'label' => 'Body', 'browse' => false],
                ],
            ],
        ];
    }

    private function seed(): void
    {
        $categories = collect(['News', 'Guides', 'Releases'])
            ->mapWithKeys(fn (string $name) => [$name => DemoCategory::create(['name' => $name, 'slug' => Str::slug($name)])]);

        $posts = [
            ['Welcome to Tardis', 'News', 'Your panel is ready: this post, its category and its page come from the demo content.'],
            ['Building your first BREAD', 'Guides', 'Open BREAD in the sidebar, pick a model and choose which fields appear where.'],
            ['Relations without code', 'Guides', 'A belongs_to field turns a foreign key into a searchable picker.'],
            ['What changed in 0.2', 'Releases', 'Site menus, image thumbnails, user accounts and an import from Voyager.'],
        ];

        foreach ($posts as $index => [$title, $category, $excerpt]) {
            DemoPost::create([
                'title' => $title,
                'slug' => Str::slug($title),
                'category_id' => $categories[$category]->getKey(),
                'excerpt' => $excerpt,
                'body' => '<p>'.e($excerpt).'</p>',
                'status' => $index === 3 ? 'draft' : 'published',
                'featured' => $index === 0,
                'published_at' => $index === 3 ? null : now()->subDays(3 - $index),
            ]);
        }

        foreach (['About', 'Contact', 'Privacy'] as $position => $title) {
            DemoPage::create([
                'title' => $title,
                'slug' => Str::slug($title),
                'body' => '<p>'.$title.' page.</p>',
                'status' => 'published',
                'position' => $position + 1,
            ]);
        }
    }

    private function migration(): string
    {
        return (string) realpath(__DIR__.'/../../database/demo/2026_11_19_000001_create_tardis_demo_tables.php');
    }

    private function markerPath(): string
    {
        return storage_path('tardis/demo.json');
    }
}
