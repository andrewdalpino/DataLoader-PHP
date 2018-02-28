# DataLoader PHP
A thin caching and data access layer that goes over your existing storage layer and infrastructure. DataLoader PHP uses  buffering and cache memoization to optimize requests to the storage layer, prevent overfetching, and prevent N+1 access pattern issues. Based on the [Facebook Javascript reference implementation](https://github.com/facebook/dataloader).

### Features
- Fast in-memory buffering, deduplication, and memoization out of the box
- Simple, lightweight, and no dependencies
- Storage layer agnostic (use with Redis, MySQL, MongoDB, a REST endpoint, anything you want)

## Installation
Install DataLoader PHP using composer:
```sh
composer require andrewdalpino/dataloader-php
```

## Getting Started
First, you need to create a batch function that will perform the duty of fetching the buffered entities from storage (Redis, REST endpoint, etc.) when necessary. The batch function takes an array of buffered keys as its only argument and **must** return an array or iterable object.

```php
$batchFunction = function ($keys) {
    return Redis::mget(...$keys);
};
```

```php
$loader = new BatchingDataLoader($batchFunction);
```

Optionally, you can specify a cache key function that tells DataLoader how to key the loaded entities. The cache key function takes the `$entity` to be keyed as an argument as well as its index in the returned array. If you do not specify a cache key function then DataLoader will attempt to use `$entity->id`, `$entity['id']`, or fallback to the index of the returned array.

```php
$cacheKeyFunction = function ($entity, $index) {
    return $entity['id'];
};

$loader = new BatchingDataLoader($batchFunction, $cacheKeyFunction);
```

### Example
The following is an example of how you could build a User loader in [Laravel](https://laravel.com/) using an  [Eloquent](https://laravel.com/docs/5.5/eloquent) model as the fetching API.

```php
use AndrewDalpino\DataLoader\BatchingDataLoader;
use App\User;

// Required batch function to load users with supplied array of buffered $keys.
$batchFunction = function ($keys) {
    return User::findMany($keys);
};

// Optional cache key function returns the primary key of the user entity.
$cacheKeyFunction = function ($user, $index) {
    return $user->id;
};

$userLoader = new BatchingDataLoader($batchFunction, $cacheKeyFunction);
```

## Usage
First you must buffer the keys of the entities you wish to load in the future by calling the batch method on the DataLoader object. The `batch()` function takes either a single integer or string `$key` or an array of `$keys` and will tell DataLoader to hold them in the buffer until the next `load()` operation is called.

```php
$userLoader->batch(1);

$userLoader->batch([1, 2, 3, 4, 5]);
```

It is important to call `batch()` on every entity you plan to load during the request cycle. DataLoader will **not** make additional requests to the storage backend if the keys are not in the buffer.

Once you have finished the batching stage, you may call `load()` to load the entities by key. The `load()` and `loadMany()` methods take a single `$key` or an array of `$keys` and return a single entity or an array of entities respectively.

```php
$userLoader->batch(['a', 'b', 'c', 'd', 'e']);

$user = $userLoader->load('a'); // Returns the user with primary key 'a'.

$users = $userLoader->loadMany(['b', 'c', 'd', 'e']); // Returns an array of users.

$users = $userLoader->loadMany(['b', 'c']); // Additional loads don't hit the database.

$user = $userLoader->load('z'); // Returns null.

$users = $userLoader->loadMany(['y', 'z']); // Return an empty array.
```

### Example
The following example demonstrates how DataLoader could be used in a [Webonyx GraphQL](https://github.com/webonyx/graphql-php) resolve function paired with the Webonyx *Deferred* mechanism to fulfill GraphQL queries. In this example, imagine that the user loader has been built and injected via an application container and accessed by its UserLoader facade.

```php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Deferred;
use UserLoader;

$postType = new ObjectType([
    'fields' => [
        'author' => [
            'type' => 'UserNode',
            'resolve' => function($post) {
                UserLoader::batch($post->author_id);

                return new Deferred(function () use ($post) {
                    return UserLoader::load($post->author_id);
                });
            }
        ],
    ],
]);
```

In this example, whenever the *author* field on a Post node is requested in a GraphQL query, the `UserLoader` will batch the user entity supporting that data, and then wait until the resolvers have all been called to fetch the data via the Deferred callback. It is clearer to see how we avoid any N+1 access pattern issues by employing this mechanism.

### Loading entities from outside of the batch function
Sometimes, the batch function is not the most efficient route to accessing a particular entity. Under other circumstances, such as non-primary key lookups, it's just not possible.

When you need to load an entity into the cache from another source, other than the batch function, you may do so by calling the `prime()` method on the data loader instance. The `prime()` method takes an `$entity` to be primed as an argument. Each primed entity will be keyed by the same cache key function as the ones loaded with the batch function.

```php
$friend = User::find(1);

$userLoader->prime($friend);
```

### Flushing the cache
You may flush the entire in-memory cache by calling the `flush()` method on the cache instance.

```php
$userLoader->flush();
```

## Runtime Configuration
You can tweak the runtime performance of DataLoader by supplying an array of options as the third parameter to the constructor. The options available are listed below with their default values.

```php
$options = [
    'batch_size' => 1000 // The max number of entities to batch load in a single round trip from storage.
];

$loader = new BatchingDataLoader($batchFunction, $cacheKeyFunction, $options);
```

## Requirements
- PHP 7.1.3 or above

## License
MIT
