# Repository Package

- [Install Guide](#repository-install)
- [How to use?](#repository-how-to)
    -  [ServiceProvider](#repository-service-provider)
    -  [TermRepository](#repository-term)
    -  [TermResourceRepository](#repository-term-resource)
    -  [PostRepository](#repository-post)

<a name="repository-install"></a>
## Install Guide
```php
composer require rumur/wputils-repository
```
<a name="how-to"></a>
## How to use?

<a name="repository-service-provider"></a>
####  ServiceProvider
If you're using inside your project a DI such as [Pimple](https://pimple.symfony.com), in this case you can use a ServiceProvider.  
```php
<?php
// Simple example.
$container = new \Pimple\Container();

$services = [
    // ...
    \Rumur\WPUtils\Repository\RepositoryServiceProvider::class,
    // ...
];

foreach ($services as $service) {
    $container->register(new $service);
}

// To get an instance from a DI.
add_action('init', static function() use ($container) {
    $post_repository = $container[\Rumur\WPUtils\Contracts\Repository\IPostRepository::class];
    $term_repository = $container[\Rumur\WPUtils\Contracts\Repository\ITermRepository::class];
});
```

_If you're instantiated a repository via `ServiceProvider`, in this case, you can also use some extra functionality such as `create`, `update`, and` delete`_

```php
<?php
$post_repository = $container[\Rumur\WPUtils\Contracts\Repository\IPostRepository::class];

$created = $post_repository->create([
    'post_title' => 'New Post Created via Repository',    
    'post_content' => 'Lorem ipsum ...',    
]);

if ($created) {
    $updated = $post_repository->update($created->ID, [
        'post_title' => 'New Post Created via Repository',
        'post_status' => 'publish', 
    ]);
    
    $post_repository->delete($updated->ID);
}

$term_repository = $container[\Rumur\WPUtils\Contracts\Repository\ITermRepository::class];

// Create a term for a category
$created = $term_repository->create('New category created via repository', 'category');

// Advanced
$child = $term_repository->create([
    'name' => 'New advanced category created via repository',
    'description' => 'Lorem Ipsum ...',
    'slug' => 'new-advanced-category',
    'parent' => $created->term_id,
    'term_group' => 12,
], 'category');

// Delete term
$term_repository->delete($child->term_id, $child->taxonomy);
```

#### Switch a context of `$post` repository
```php
<?php 
// In order to switch a context for a repository `post_type` e.g. to `page`
$post_repository->usePostType('page');

$post_repository->all(); // Will return all pages.
```

<a name="repository-term"></a>
#### TermRepository
```php
<?php
// Via container.
$term = $container[\Rumur\WPUtils\Contracts\Repository\ITermRepository::class];

// Or instantiate directly
// $term = new \Rumur\WPUtils\Repository\TermRepository();

// To get all terms from a DB.
$term->all();

// To get specific terms
$term->only('category', 'ctx_genre', 'ctx_skill');

// Or you can pass variables as an array
$term->only(['category', 'ctx_genre', 'ctx_skill']);

// Get all terms except specific ones.
$term->except('category');

// Get a specific term
$term->find($term_id = 12, 'category');

// To create a specific query you can use the following
$term->where('taxonomy', 'category')
     ->where('id', 12) // It's the same as 'term_id'
     ->get();

// To scope a query to a specific post
$term->where('taxonomy', 'category')
     ->where('post', 155) // It's the same as 'object_ids'
     ->get();
```

<a name="repository-term-resource"></a>
#### TermResourceRepository
```php
<?php
// Via container.
$term = $container[\Rumur\WPUtils\Contracts\Repository\ITermResourceRepository::class];

// Or instantiate directly
// $term = new \Rumur\WPUtils\Repository\TermResourceRepository();

$term->useResource(get_post(12));
```

This will behave the same as a regular `TermRepository` except it will bind term queries to a specific resource. 

<a name="repository-post"></a>
#### PostRepository
```php
<?php
// Via container.
$post = $container[\Rumur\WPUtils\Contracts\Repository\IPostRepository::class];

// Or instantiate directly
// $post = new \Rumur\WPUtils\Repository\PostRepository();

// To get all posts from a DB.
$post->all();

// To get specific posts
$post->only(13, 12, 155);

// Or you can pass variables as an array
$post->only([13, 12, 155]);

// Get all terms except specific ones.
$post->except(13);

// Get a specific post
$post->find($post_id = 12);

// To create a specific query you can use the following
$post->where('post__in', [12, 155])->get();

// Get the \WP_Query instance
$post->where('post__in', [12, 155])->query();
```

