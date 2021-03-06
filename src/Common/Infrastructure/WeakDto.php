<?php

namespace AlephTools\DDD\Common\Infrastructure;

abstract class WeakDto extends Dto
{
    /**
     * Constructor.
     *
     * @param array $properties
     */
    public function __construct(array $properties = [])
    {
        parent::__construct($properties, false);
    }
}
