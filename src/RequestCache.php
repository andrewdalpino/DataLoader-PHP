<?php

namespace AndrewDalpino\DataLoader;

class RequestCache extends InMemoryCollection
{
    /**
     * Initialize the request cache.
     *
     * @return self
     */
    public static function init()
    {
        return new self();
    }
}
