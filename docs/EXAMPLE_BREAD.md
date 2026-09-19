# Example BREAD Definition

This example shows how to register a dynamic BREAD resource for a `Post` model.

```php
<?php

use App\Models\Post;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\Repositories\JsonBreadRepository;

$bread = BreadDefinition::fromArray([
    'slug' => 'posts',
    'model' => Post::class,
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
]);

app(JsonBreadRepository::class)->save($bread);
```

## Result

This registers the `posts` resource and allows the Tardis admin to render:

- list view
- create form
- read view
- edit form
- delete action

The URL pattern will follow the admin route family:

- `/admin/posts`
- `/admin/posts/create`
- `/admin/posts/{id}`
- `/admin/posts/{id}/edit`
