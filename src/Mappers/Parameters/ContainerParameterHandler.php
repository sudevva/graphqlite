<?php

declare(strict_types=1);

namespace TheCodingMachine\GraphQLite\Mappers\Parameters;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\Type;
use Psr\Container\ContainerInterface;
use ReflectionParameter;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\ParameterAnnotations;
use TheCodingMachine\GraphQLite\Parameters\ContainerParameter;
use TheCodingMachine\GraphQLite\Parameters\ParameterInterface;

/**
 * Maps parameters with the \@Autowire annotation to container entry based on the FQCN or the passed identifier.
 */
class ContainerParameterHandler implements ParameterMiddlewareInterface
{
    /** @var ContainerInterface */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function mapParameter(ReflectionParameter $parameter, DocBlock $docBlock, ?Type $paramTagType, ParameterAnnotations $parameterAnnotations, ParameterHandlerInterface $next): ParameterInterface
    {
        /**
         * @var Autowire|null $autowire
         */
        $autowire = $parameterAnnotations->getAnnotationByType(Autowire::class);

        if ($autowire === null) {
            return $next->mapParameter($parameter, $docBlock, $paramTagType, $parameterAnnotations);
        }

        $id = $autowire->getIdentifier();
        if ($id === null) {
            $type = $parameter->getType();
            if ($type === null) {
                throw MissingAutowireTypeException::create($parameter);
            }
            $id = (string) $type;
        }

        return new ContainerParameter($this->container, $id);
    }
}
