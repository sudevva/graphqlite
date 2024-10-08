<?php

declare(strict_types=1);

namespace TheCodingMachine\GraphQLite\Mappers;

use ReflectionClass;

/**
 * The cached results of a GlobTypeMapper
 */
class GlobExtendTypeMapperCache
{
    /** @var array<string,array<string,string>> Maps a domain class to one or many type extenders (with the @ExtendType annotation) The array of type extenders has a key and value equals to FQCN */
    private array $mapClassToExtendTypeArray = [];
    /** @var array<string,array<string,string>> Maps a GraphQL type name to one or many type extenders (with the @ExtendType annotation) The array of type extenders has a key and value equals to FQCN */
    private array $mapNameToExtendType = [];

    /**
     * Merges annotations of a given class in the global cache.
     *
     * @param ReflectionClass<object>|class-string $sourceClass
     */
    public function registerAnnotations(ReflectionClass|string $sourceClass, GlobExtendAnnotationsCache $globExtendAnnotationsCache): void
    {
        $className = $sourceClass instanceof ReflectionClass ? $sourceClass->getName() : $sourceClass;

        $typeClassName = $globExtendAnnotationsCache->getExtendTypeClassName();
        if ($typeClassName !== null) {
            $this->mapClassToExtendTypeArray[$typeClassName][$className] = $className;
        }

        $typeName = $globExtendAnnotationsCache->getExtendTypeName();
        $this->mapNameToExtendType[$typeName][$className] = $className;
    }

    /** @return array<string,string>|null An array of classes with the @ExtendType annotation (key and value = FQCN) */
    public function getExtendTypesByObjectClass(string $className): array|null
    {
        return $this->mapClassToExtendTypeArray[$className] ?? null;
    }

    /** @return array<string,string>|null An array of classes with the @ExtendType annotation (key and value = FQCN) */
    public function getExtendTypesByGraphQLTypeName(string $graphqlTypeName): array|null
    {
        return $this->mapNameToExtendType[$graphqlTypeName] ?? null;
    }
}
