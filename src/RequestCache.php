<?php

namespace AndrewDalpino\DataLoader;

class RequestCache extends InMemoryCollection
{
    /**
     * Initialize the cache.
     *
     * @return self
     */
    public static function init()
    {
        return new self();
    }
}
