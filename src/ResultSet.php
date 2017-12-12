<?php

namespace AndrewDalpino\DataLoader;

class ResultSet extends InMemoryCollection
{
    /**
     * Collect the results of a query.
     *
     * @param  mixed  $results
     * @return self
     */
    public static function collect($results = [])
    {
        return new self($results);
    }
}
