<?php

namespace AndrewDalpino\DataLoader;

class ResultSet extends InMemoryCollection
{
    /**
     * Collect the results of a query.
     *
     * @param  interable  $results
     * @return self
     */
    public static function collect(iterable $results = [])
    {
        return new self($results);
    }
}
