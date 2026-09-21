<?php

return [
    'slug' => 'bread-page-posts-create',
    'model' => BreadPageCreateTestModel::class,
    'name' => 'Post',
    'name_plural' => 'Posts',
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'browse' => true, 'read' => true, 'edit' => true, 'add' => true, 'validation' => ['required']],
        ['name' => 'content', 'type' => 'textarea', 'label' => 'Content', 'browse' => false, 'read' => true, 'edit' => true, 'add' => true, 'validation' => ['nullable']],
    ],
];
