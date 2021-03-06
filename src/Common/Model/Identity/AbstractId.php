<?php

namespace AlephTools\DDD\Common\Model\Identity;

use AlephTools\DDD\Common\Infrastructure\Hash;
use AlephTools\DDD\Common\Infrastructure\Scalarable;
use AlephTools\DDD\Common\Model\Exceptions\InvalidArgumentException;
use ReflectionException;
use AlephTools\DDD\Common\Infrastructure\ValueObject;

/**
 * The base class for all identifiers.
 *
 * @property-read mixed $identity
 */
abstract class AbstractId extends ValueObject implements Scalarable
{
    /**
     * The identity (unique identifier).
     *
     * @var mixed
     */
    protected $identity;

    /**
     * Converts this identifier to a string.
     *
     * @return string
     */
    public function toString(): string
    {
        return (string)$this->identity;
    }

    public function toScalar()
    {
        return $this->identity;
    }

    /**
     * Parsing of raw value
     *
     * @param $identity
     * @return mixed
     */
    abstract protected function parse($identity);

    /**
     * Validates the identity.
     *
     * @return void
     * @throws ReflectionException
     */
    protected function validateIdentity(): void
    {
        if ($this->identity === null) {
            $domainName = $this->domainName();
            throw new InvalidArgumentException("Identity of $domainName must not be null.");
        }
    }
}
