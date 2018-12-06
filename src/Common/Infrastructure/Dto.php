<?php

namespace AlephTools\DDD\Common\Infrastructure;

use ReflectionClass;
use RuntimeException;
use AlephTools\DDD\Common\Infrastructure\Exceptions\PropertyMissingException;

/**
 * The base class for data transfer objects.
 */
abstract class Dto implements Serializable
{
    use AssertionConcern;

    /**
     * Offsets in $properties.
     */
    private const PROP_TYPE = 0;
    private const PROP_VALIDATOR = 1;
    private const PROP_SETTER = 2;
    private const PROP_GETTER = 3;

    /**
     * Property types.
     */
    private const PROP_TYPE_READ = 0;
    private const PROP_TYPE_WRITE = 1;
    private const PROP_TYPE_READ_WRITE = 2;

    /**
     * The property cache.
     *
     * @var array
     */
    private static $properties;

    /**
     * The class reflector.
     *
     * @var ReflectionClass
     */
    private $reflector;

    /**
     * Constructor.
     *
     * @param array $properties
     */
    public function __construct(array $properties = [])
    {
        $this->init();
        $this->assignPropertiesAndValidate($properties);
    }

    /**
     * We need restore the reflection object after serialization
     * to eliminate this bug https://bugs.php.net/bug.php?id=30324
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->init();
    }

    private function init(): void
    {
        $this->reflector = new ReflectionClass($this);
        $this->extractProperties();
    }

    /**
     * Converts this object to an associative array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->properties() as $property => $info) {
            $result[$property] = $this->extractPropertyValue($property, $info);
        }
        return $result;
    }

    /**
     * Converts this object to a nested associative array.
     *
     * @return array
     */
    public function toNestedArray(): array
    {
        $result = [];
        foreach ($this->properties() as $attribute => $info) {
            $value = $this->extractPropertyValue($attribute, $info);
            if ($value instanceof self) {
                $result[$attribute] = $value->toNestedArray();
            } else {
                $result[$attribute] = $value;
            }
        }
        return $result;
    }

    private function extractPropertyValue(string $property, array $info)
    {
        $getter = $info[self::PROP_GETTER];
        if ($getter === null) {
            return $this->propertyValue($property);
        }
        return $this->invokeGetter($getter);
    }

    /**
     * Converts this object to JSON.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Returns data which should be serialized to JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Converts this object to a string.
     *
     * @return string
     */
    public function toString(): string
    {
        return print_r($this, true);
    }

    /**
     * Converts this object to a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Returns the property value.
     *
     * @param string $property
     * @return mixed
     */
    public function __get(string $property)
    {
        $this->checkPropertyExistence($property);

        $info = $this->properties()[$property];
        if ($info[self::PROP_TYPE] === self::PROP_TYPE_WRITE) {
            throw new RuntimeException("Property $property is write only.");
        }

        $getter = $info[self::PROP_GETTER];
        if ($getter === null) {
            return $this->propertyValue($property);
        }
        $method = $this->reflector->getMethod($getter);
        if ($method->isPublic() || $method->isProtected() && $this->isCalledFromSameClass()) {
            return $this->{$getter}();
        }
        throw new RuntimeException("Property $property does not have accessible getter.");
    }

    /**
     * Sets the property value.
     *
     * @param string $property
     * @param mixed $value
     * @return void
     */
    public function __set(string $property, $value): void
    {
        $this->checkPropertyExistence($property);

        $info = $this->properties()[$property];
        if ($info[self::PROP_TYPE] === self::PROP_TYPE_READ) {
            throw new RuntimeException("Property $property is read only.");
        }

        $setter = $info[self::PROP_SETTER];
        if ($setter === null) {
            $this->assignValueToProperty($property, $value);
        } else {
            $method = $this->reflector->getMethod($setter);
            if ($method->isPublic() || $method->isProtected() && $this->isCalledFromSameClass()) {
                $this->{$setter}($value);
            } else {
                throw new RuntimeException("Property $property does not have accessible setter.");
            }
        }
    }

    /**
     * Returns TRUE if the given property is not NULL.
     *
     * @param string $property
     * @return bool
     */
    public function __isset(string $property): bool
    {
        return $this->__get($property) !== null;
    }

    /**
     * Sets the given property value to NULL.
     *
     * @param string $property
     * @return void
     */
    public function __unset(string $property): void
    {
        $this->__set($property, null);
    }

    /**
     * Returns the properties information.
     *
     * @return array
     */
    private function properties(): array
    {
        return self::$properties[static::class];
    }

    /**
     * Assigns values to properties.
     *
     * @param array $properties
     * @return void
     */
    protected function assignProperties(array $properties): void
    {
        foreach ($properties as $property => $value) {
            $this->assignProperty($property, $value);
        }
    }

    /**
     * Assigns value to a property.
     *
     * @param string $property
     * @param mixed $value
     * @return void
     */
    protected function assignProperty(string $property, $value): void
    {
        $this->checkPropertyExistence($property);

        $setter = $this->properties()[$property][self::PROP_SETTER];
        if ($setter === null) {
            $this->assignValueToProperty($property, $value);
        } else {
            $this->invokeSetter($setter, $value);
        }
    }

    /**
     * Sets properties and validates their values.
     *
     * @param array $properties
     * @return void
     */
    protected function assignPropertiesAndValidate(array $properties): void
    {
        $this->assignProperties($properties);
        $this->validate();
    }

    /**
     * Validates attribute values.
     *
     * @return void
     */
    protected function validate(): void
    {
        foreach ($this->properties() as $attribute => $info) {
            if (null !== $validator = $info[self::PROP_VALIDATOR]) {
                $this->invokeValidator($validator);
            }
        }
    }

    private function checkPropertyExistence(string $property): void
    {
        if (!isset($this->properties()[$property])) {
            throw new PropertyMissingException("Property $property not found.");
        }
    }

    /**
     * Returns true if the caller of this method is called from this class.
     *
     * @return bool
     */
    private function isCalledFromSameClass(): bool
    {
        $class = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['class'] ?? '';
        return $class === static::class;
    }

    private function propertyValue(string $property)
    {
        $property = $this->reflector->getProperty($property);
        $property->setAccessible(true);
        return $property->getValue($this);
    }

    private function assignValueToProperty(string $property, $value)
    {
        $property = $this->reflector->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($this, $value);
    }

    private function invokeGetter(string $getter)
    {
        $method = $this->reflector->getMethod($getter);
        $method->setAccessible(true);
        return $method->invoke($this);
    }

    private function invokeSetter(string $setter, $value): void
    {
        $method = $this->reflector->getMethod($setter);
        $method->setAccessible(true);
        $method->invoke($this, $value);
    }

    private function invokeValidator(string $validator): void
    {
        $method = $this->reflector->getMethod($validator);
        $method->setAccessible(true);
        $method->invoke($this);
    }

    /**
     * Determines properties of a DTO object.
     *
     * @return void
     */
    private function extractProperties(): void
    {
        if (isset(self::$properties[static::class])) {
            return;
        }

        $properties = [];

        if (preg_match_all('/@property(-read|-write|)[^$]+\$([^\s]+)/i', $this->getComment(), $matches)) {
            foreach ($matches[1] as $i => $type) {
                if ($type === '-read') {
                    $type = self::PROP_TYPE_READ;
                } else if ($type === '-write') {
                    $type = self::PROP_TYPE_WRITE;
                } else {
                    $type = self::PROP_TYPE_READ_WRITE;
                }

                $propertyName = $matches[2][$i];
                if (!$this->reflector->hasProperty($propertyName)) {
                    throw new PropertyMissingException(
                        "Property $propertyName is not connected with the appropriate class field."
                    );
                }

                if (!$this->reflector->hasMethod($setter = $this->getSetterName($propertyName))) {
                    $setter = null;
                }
                if (!$this->reflector->hasMethod($getter = $this->getGetterName($propertyName))) {
                    $getter = null;
                }
                if (!$this->reflector->hasMethod($validator = $this->getValidatorName($propertyName))) {
                    $validator = null;
                }

                $properties[$propertyName] = [$type, $validator, $setter, $getter];
            }
        }

        self::$properties[static::class] = $properties;
    }

    private function getComment(): string
    {
        $comment = '';
        $class = $this->reflector;
        while ($class) {
            $comment = $class->getDocComment() . $comment;
            $class = $class->getParentClass();
        }
        return $comment;
    }

    private function getValidatorName(string $attribute): string
    {
        return 'validate' . ucfirst($attribute);
    }

    private function getGetterName(string $attribute): string
    {
        return 'get' . ucfirst($attribute);
    }

    private function getSetterName(string $attribute): string
    {
        return 'set' . ucfirst($attribute);
    }
}