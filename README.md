# DataLoader PHP
A thin caching and data access layer that goes over your existing storage layer and infrastructure. DataLoader PHP provides a single source of truth for data fetching that abstracts away the implementation details of storage and caching. Inspired by the [Facebook Javascript reference implementation](https://github.com/facebook/dataloader), this tool uses an in-memory buffer and request cache to optimize requests to the data layer, prevent overfetching, and solve N+1 access pattern issues. Unlike the reference implementation however, this implementation focuses on leveraging the synchronous environment of the PHP runtime, instead of promises.

### Features
- Fast in-memory buffer and request cache out of the box
- Simple, lightweight, and zero dependencies
- Storage layer agnostic (use with Redis, MySQL, MongoDB, a REST endpoint, anything you want)
- Framework agnostic

## Installation
Install DataLoader PHP using composer:
```sh
composer require andrewdalpino/dataloader-php
```

## Getting Started
First, you need to create a batch function that will perform the duty of loading the buffered entities from storage when the time comes. You are free to implement the batch function how you wish, however, it **must** return an array.

```php
$batchFunction = function ($keys) {
    return Redis::mget(...$keys);
};
```

To build your data loader, feed the batch function into the static build method of the DataLoader class, and you're done.

```php
$loader = BatchingDataLoader::build($batchFunction);
```

Optionally, you can specify a cache key function that gets looped over with the data returned from the batch function so that you can tell DataLoader how to identify the entity. If you do not specify a cache key function then DataLoader will try to use `$entity->id`, `$entity['id']`, or fallback to the index of the returned array. It is a best practice to specify a cache key function.

```php
$cacheKeyFunction = function ($entity, $index) {
    return $entity['id'];
};

$loader = BatchingDataLoader::build($batchFunction, $cacheKeyFunction);
```

### Example
The following is an example of how you can build a User loader in [Laravel](https://laravel.com/) using an  [Eloquent](https://laravel.com/docs/5.5/eloquent) model as the fetching API.

```php
use AndrewDalpino\DataLoader\BatchingDataLoader;
use App\User;

// Required batch function to load users with supplied array of buffered $keys.
$batchFunction = function ($keys) {
    return User::findMany($keys);
};

// Optional cache key function returns the primary key of the user.
$cacheKeyFunction = function ($user, $index) {
    return $user->id;
};

$userLoader = BatchingDataLoader::build($batchFunction, $cacheKeyFunction);
```

## Usage
Using DataLoader to fetch your entities involves first batching the keys of the entities you wish to load, and then loading the data. In order to achieve this programatically, DataLoader provides two functions `batch()` and `load()`.

The `batch()` function takes in either a single `$key` or an array of `$keys` and will tell DataLoader to hold them in a buffer until the next `load()` operation is called. Duplicate keys will automatically be removed.

```php
$userLoader->batch(1);

$userLoader->batch([1, 2, 3, 4, 5]);
```

It is important to call `batch()` for every entity you plan to load during the current request cycle. DataLoader will **not** make any additional requests to the storage backend for you if you miss something.

Once you are finished batching, you may call `load()` to load the entities by key. The `load()` method takes either a single key or an array of keys and returns a single entity or an array of entities respectively. Entities not found will return null.

```php
$userLoader->batch(['a', 'b', 'c', 'd', 'e']);

$user = $userLoader->load('a'); // Returns a user.

$user = $userLoader->load('z'); // Returns null.

$users = $userLoader->load(['b', 'c', 'd', 'e']); // Returns an array of users.

$users = $userLoader->load(['b', 'c']); // Additional loads don't hit the database.
```

### Example
The following example demonstrates how DataLoader can be used in a [Webonyx GraphQL](https://github.com/webonyx/graphql-php) resolve function paired with the Webonyx *Deferred* mechanism to fulfill GraphQL queries. In this example, imagine that the user loader has been built and injected via an application container and accessed by its UserLoader facade.

```php
use GraphQL\Deferred;
use UserLoader;

$postType = new ObjectType([
    'fields' => [
        'author' => [
            'type' => 'UserNode',
            'resolve' => function($root) {
                UserLoader::batch($root->author_id);

                return new Deferred(function () use ($root) {
                    return UserLoader::load($root->author_id);
                });
            }
        ],
    ],
]);
```

In this example, whenever the *author* field on a Post object is requested in a GraphQL query, the data loader will batch the user supporting that data, and wait until the Deffered callback is executed to actually fetch the data. It is clearer to see how we avoid any N+1 problems by employing this mechanism.

### Loading entities from outside of the batch function
Sometimes, the batch function is not always the most efficient route to accessing a particular set of data. Other times, such as non-primary key lookups, it's just not possible.

If you need to load an entity into the request cache from another source, besides the batch function, you may do so by calling the `prime()` method on the data loader object. The `prime()` method takes either a single entity or an array of entities as an argument, and will be keyed by the same cache key function as entities loaded with `batch()`.

```php
$user = User::find(1);

$friends = $user->friends()->get();

$userLoader->prime($friends);
```

Once the cache has been primed, you may call `load()` to load the entities as usual. If you try to prime a key that has already been primed or loaded, the cached entry will **not** be overwritten. To force an overwrite you may call `forget()` first and then `prime()` per normal.

```php
$user = User::find('qDkX7');

$userLoader->forget('qDkX7')->prime($user);
```

### Flushing the cache
You may clear the entire request cache by calling the `flush()` method on the data loader instance.

```php
$userLoader->flush();
```

## Additional caching
Although the DataLoader request cache is very fast, because of it's short-lived nature, it is not a replacement for application level caching. DataLoader batch functions make it easy to add application level caching right below the request cache to make data fetching even faster. The following example demonstrates how you can implement an application cache using the [Laravel Cache](https://laravel.com/docs/5.5/cache) facade in a batch function.

```php
use AndrewDalpino\DataLoader\BatchingDataLoader;
use App\User;
use Cache;

$batchFunction = function ($keys) {
    // Try loading entities from the application cache first.
    $cached = Cache::tags('users')->many($keys);

    // Filter the keys that could not be loaded by the cache.
    $keys = array_filter($keys, function ($key, $index) use ($cached) {
        return $cached[$key] === null;
    }, ARRAY_FILTER_USE_BOTH);

    // Return the cached users if nothing else needs to be loaded.
    if (empty($keys)) {
        return $cached;
    }

    // Load the remaining users from the database and index by
    // primary key so we can merge the data with the cache.
    $loaded = User::findMany($keys)->keyBy('id')->all();

    // Put the loaded users in the application cache for 60 minutes.
    Cache::tags('users')->putMany($loaded, 60);

    // Return the merged results.
    return array_merge($cached, $loaded);
};

$cacheKeyFunction = function ($user, $index) {
    return $user->id;
};

$userLoader = BatchingDataLoader::build($batchFunction. $cacheKeyFunction);

$userLoader->batch([1, 2, 3]);

$users = $userLoader->load([1, 2, 3]);
```

Now when we call `load()`, the database will only get hit if the key is not in the request cache *or* the application cache.

## Requirements
- PHP 7.1 or above

## License
MIT
