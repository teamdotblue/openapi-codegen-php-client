<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Exception;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

use function array_combine;
use function array_diff_key;
use function array_flip;
use function array_map;

/**
 * Used by generated exception classes for RFC7807-style "problem details" error
 * responses (properties: type, title, detail, context, status, metadata).
 *
 * Mirrors team-blue/php-exception's DefaultJsonSchemaException trait, minus its
 * symfony/http-kernel dependency, which is a server-side concern this client
 * runtime has no business pulling in.
 */
trait ProblemDetailsExceptionLogic
{
    use JsonSchemaAwareRecordLogic {
        init as parentInit;
    }

    private const PROPERTY_ORDER = [
        'type',
        'title',
        'detail',
        'context',
        'status',
        'metadata',
    ];

    /**
     * \Exception's own properties are untyped or otherwise incompatible with the
     * reflection-based schema builder below, and must never be treated as part of
     * the record's data.
     */
    private const PROPERTIES_TO_IGNORE = [
        'message',
        'code',
        'file',
        'line',
        '__useMaxValues',
        '__encryptedSensitiveData',
        '__propTypeMap',
        '__schema',
        '__arrayPropItemTypeMap',
    ];

    private function init(): void
    {
        $this->parentInit();

        $this->message = $this->detail;
    }

    /** @return array<string, array{0: string, 1: bool, 2: bool, 3: bool}> */
    private static function buildPropTypeMap(): array
    {
        $refObj = new ReflectionClass(self::class);
        $properties = $refObj->getProperties();
        $propertiesByName = array_combine(
            array_map(static fn (ReflectionProperty $property): string => $property->getName(), $properties),
            $properties,
        );
        $propertiesByName = array_diff_key($propertiesByName, array_flip(self::PROPERTIES_TO_IGNORE));

        $propertyTypeMap = [];
        foreach ($propertiesByName as $propertyName => $property) {
            /** @var ReflectionNamedType $type */
            $type = self::typeFromProperty($property, $refObj);

            $propertyTypeMap[$propertyName] = [
                $type->getName(),
                self::isScalarType($type->getName()),
                $type->allowsNull(),
                self::isPropertySensitive($property),
            ];
        }

        $orderedPropertyTypeMap = [];
        foreach (self::PROPERTY_ORDER as $propertyName) {
            if (! isset($propertyTypeMap[$propertyName])) {
                continue;
            }

            $orderedPropertyTypeMap[$propertyName] = $propertyTypeMap[$propertyName];
        }

        return $orderedPropertyTypeMap + $propertyTypeMap;
    }
}
