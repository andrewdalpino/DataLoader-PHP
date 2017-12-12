# DataLoader PHP
A thin caching and data access layer that goes over your existing storage layer and infrastructure based on the [Facebook Javascript reference implementation](https://github.com/facebook/dataloader). DataLoader PHP uses an in-memory buffer and request cache to optimize requests to the data layer, prevent overfetching, and solve N+1 access pattern issues. Unlike the reference implementation however, this implementation focuses on leveraging the shared nothing architecture of the PHP runtime in a synchonous environment, instead of promises.

### Features
- Fast in-memory buffer and request cache out of the box
- Simple, lightweight, and no dependencies
- Storage layer agnostic (use with Redis, MySQL, MongoDB, a REST endpoint, anything you want)
- Framework agnostic

## Installation
Install DataLoader PHP using composer:
```sh
composer require andrewdalpino/dataloader-php
```

## Getting Started
First, you need to create a batch function that will perform the duty of loading the buffered entities from storage when the time comes. You are free to implement the batch function how you wish, however, it **must** return an array of entities loaded by their key.

```php
$batchFunction = function ($keys) {
    return Redis::mget(...$keys);
};
```

To build a data loader, feed the batch function into the static `make()` factory method on the DataLoader class, and you're done.

```php
$loader = BatchingDataLoader::make($batchFunction);
```

Optionally, you can specify a cache key function that gets looped over with the data returned from the batch function so that you can tell DataLoader how to key the entity. If you do not specify a cache key function then DataLoader will try to use `$entity->id`, `$entity['id']`, or fallback to the index of the returned array.

```php
$cacheKeyFunction = function ($entity, $index) {
    return $entity['id'];
};

$loader = BatchingDataLoader::make($batchFunction, $cacheKeyFunction);
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

$userLoader = BatchingDataLoader::make($batchFunction, $cacheKeyFunction);
```

## Usage
Using DataLoader to fetch entities first involves putting the keys of the entities you wish to load in a buffer, and then loading the request cache with data from the batch function. In order to achieve this programatically, DataLoader provides two functions `batch()` and `load()`.

The `batch()` function takes in either a single `$key` or an array of `$keys` and will tell DataLoader to hold them in the buffer until the next `load()` operation is called. Duplicate keys will automatically be removed.

```php
$userLoader->batch(1);

$userLoader->batch([1, 2, 3, 4, 5]);
```

It is important to call `batch()` on every entity you plan to load during the current request cycle. DataLoader will **not** make additional requests to the storage backend for entities whose keys are not in the buffer.

Once you have finished batching, you may call `load()` to fetch an entity by its key. The `load()` method takes either a single `$key` or an array of `$keys` and returns a single entity or an array of entities respectively. Subsequent loads of the same entity will come directly from the in-memory request cache. Entities not found will return null.

```php
$userLoader->batch(['a', 'b', 'c', 'd', 'e']);

$user = $userLoader->load('a'); // Returns the user with primary key 'a'.

$users = $userLoader->load(['b', 'c', 'd', 'e']); // Returns an array of users.

$users = $userLoader->load(['b', 'c']); // Additional loads don't hit the database.

$users = $userLoader->loadMany(['f', 'g', 'h']); // Does the same as load(), but only accepts an array.

$user = $userLoader->load('z'); // Returns null.
```

If for any reason you just need DataLoader to load an entity immediately, then you may use the `loadNow()` method to bypass the buffer and load the data directly from the batch function. Entities loaded with `loadNow()` will automatically be cached for the remainder of the request cycle. To force a refetch you may use the `forget()` method first and then call `loadNow()`. `forget()` takes a single `$key` as its only argument and returns the data loader instance for chaining.

```php
$user = $userLoader->loadNow(1234); // Bypass the buffer and fetch the user entity immediately.

$user = $userLoader->forget(1234)->loadNow(1234); // Force a refetch.
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

In this example, whenever the *author* field on a Post object is requested in a GraphQL query, the `UserLoader` will batch the user entity supporting that data, and then wait until the query has been fully parsed to fetch the data via the Deferred callback. It is clearer to see how we avoid any N+1 problems by employing this mechanism.

### Loading entities from outside of the batch function
Sometimes, the batch function is not the most efficient route to accessing a particular dataset. Under other circumstances, such as non-primary key lookups, it's just not possible.

When you need to load an entity into the request cache from another source, other than the batch function, you may do so by calling the `prime()` method on the data loader instance. The `prime()` method takes an array of `$entities` that are to be primed as an argument. Each primed entity will be keyed by the same cache key function as the ones loaded with the batch function.

```php
$user = User::find(1);

$friends = $user->friends()->get();

$userLoader->prime($friends);
```

Once the cache has been primed, you may call `load()` on the entity's key to load it as normal. If you try to prime a key that has already been loaded or primed, the existing entry will **not** be overwritten. To force an overwrite you can call `forget()` first and then `prime()` as normal.

```php
$user = User::find('qDkX7');

$userLoader->forget('qDkX7')->prime([$user]);
```

### Flushing the cache
You may clear the entire in-memory request cache by calling the `flush()` method on the data loader instance.

```php
$userLoader->flush();
```

## Additional Caching
Although the DataLoader request cache is fast, because of its short-lived nature, it is not a replacement for application level caching. However, DataLoader batch functions make it easy to add application level caching right below the in-memory request cache to make data fetching even faster. The following example demonstrates how you can implement an application cache using the [Laravel Cache](https://laravel.com/docs/5.5/cache) in a batch function.

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

$userLoader = BatchingDataLoader::make($batchFunction, $cacheKeyFunction);

$userLoader->batch([1, 2, 3]);

// Do something ...

$users = $userLoader->load([1, 2, 3]);
```

Now when we call `load()`, the database will only get hit if the key is not in the request cache *or* the application cache.

## Runtime Configuration
You can tweak the runtime characteristics of DataLoader by supplying an array of options as the third parameter to the `make()` method when building the data loader. The options available are listed below with their default values.

```php
$options = [
    'batch_size' => 1000 // The max number of entities to batch load in a single round trip from storage.
];

$loader = BatchingDataLoader::make($batchFunction, $cacheKeyFunction, $options);
```

## Requirements
- PHP 7.1 or above

## License
MIT
