# Example BREAD Definition

A BREAD definition is a plain PHP config file under `config/bread/{slug}.php`. This example registers a dynamic BREAD resource for a `Post` model.

## Generate from a model

```bash
php artisan tardis:make-bread "App\Models\Post"
```

This writes `config/bread/posts.php` with fields detected from the model.

## Or write it by hand

`config/bread/posts.php`:

```php
<?php

return [
    'slug' => 'posts',
    'model' => App\Models\Post::class,
    'name' => 'Post',
    'name_plural' => 'Posts',
    'description' => 'Blog posts managed from the admin panel.',
    'icon' => 'heroicon-o-document-text',
    'search_key' => 'title',
    'order_column' => 'created_at',
    'order_direction' => 'desc',
    'fields' => [
        'id' => [
            'type' => 'number',
            'browse' => false,
            'edit' => false,
            'read' => true,
        ],
        'title' => [
            'type' => 'text',
            'label' => 'Title',
            'browse' => true,
            'edit' => true,
            'read' => true,
            'required' => true,
            'search' => true,
            'rules' => ['required', 'string', 'max:255'],
        ],
        'slug' => [
            'type' => 'text',
            'label' => 'Slug',
            'browse' => true,
            'edit' => true,
            'read' => true,
            'required' => true,
            'rules' => ['required', 'string', 'max:255', 'unique:posts,slug'],
        ],
        'content' => [
            'type' => 'textarea',
            'label' => 'Content',
            'browse' => false,
            'edit' => true,
            'read' => true,
            'required' => true,
            'rules' => ['required', 'string'],
        ],
        'published_at' => [
            'type' => 'datetime',
            'label' => 'Published At',
            'browse' => true,
            'edit' => true,
            'read' => true,
        ],
    ],
    'layout' => [
        'browse' => ['title', 'slug', 'published_at'],
        'edit' => ['title', 'slug', 'content', 'published_at'],
        'read' => ['title', 'slug', 'content', 'published_at'],
    ],
    'validation' => [
        'title' => 'required|string|max:255',
        'slug' => 'required|string|max:255|unique:posts,slug',
        'content' => 'required|string',
    ],
];
```

## Reading definitions

```php
use Tardis\Bread\BreadManager;

app(BreadManager::class)->find('posts'); // ?BreadDefinition
app(BreadManager::class)->all();         // Collection of BreadDefinition
```

## Result

The `posts` config file registers the BREAD resource and allows the Tardis admin to render:

- list view
- create form
- read view
- edit form
- delete action

The URL pattern follows the admin route family:

- `/admin/posts`
- `/admin/posts/create`
- `/admin/posts/{id}`
- `/admin/posts/{id}/edit`