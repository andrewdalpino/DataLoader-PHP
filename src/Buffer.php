<?php

namespace AndrewDalpino\DataLoader;

class Buffer extends InMemoryCollection
{
    /**
     * Initialize the buffer.
     *
     * @return self
     */
    public static function init()
    {
        return new self();
    }
}
