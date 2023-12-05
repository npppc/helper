<?php

namespace Npc\Helper\Reflection;

use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Type;
use PhpParser\Node\Stmt\Namespace_;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\TypesFinder\PhpDocumentor\NamespaceNodeToReflectionTypeContext;
use Roave\BetterReflection\TypesFinder\ResolveTypes;

class TypeParser
{
    protected static $_instances = [];

    protected static $_types = [];

    /**
     * @var ResolveTypes
     */
    private $resolveTypes;
    /**
     * @var DocBlockFactory
     */
    private $docBlockFactory;
    /**
     * @var NamespaceNodeToReflectionTypeContext
     */
    private $makeContext;

    /**
     * @return mixed|static
     */
    public static function instance()
    {
        $key = get_called_class();
        $instance = null;
        if (isset(static::$_instances[$key])) {
            $instance = static::$_instances[$key];
        }
        else
        {
            $instance = new static();
            static::$_instances[$key] = $instance;
        }
        return $instance;
    }

    public function __construct()
    {
        $this->resolveTypes = new ResolveTypes();
        $this->docBlockFactory = DocBlockFactory::createInstance();
        $this->makeContext = new NamespaceNodeToReflectionTypeContext();
    }

    /**
     * @param ReflectionClass $reflectionClass
     * @param Namespace_|null $namespace
     * @return array
     */
    public function __invoke(ReflectionClass $reflectionClass, ?Namespace_ $namespace): array
    {
        $docComment = $reflectionClass->getDocComment();
        if ($docComment === '') {
            return [];
        }
        $key = $reflectionClass->getName();
        if (!isset(self::$_types[$key])) {
            $context = $this->makeContext->__invoke($namespace);
            /** @var Property[] $varTags */
            $propertyTags = $this->docBlockFactory->create($docComment, $context)->getTagsByName('property');
            $types = [];
            /** @var Property $property */
            foreach ($propertyTags as $property) {
                $types[$property->getVariableName()] =
                    array_merge(
                        $types[$property->getVariableName()] ?? [],
                        $this->resolveTypes->__invoke(explode('|', (string) $property->getType()), $context)
                    );
            }
            static::$_types[$key] = $types;
        }

        return self::$_types[$key];
    }

    public function hasTypes($class)
    {
        return isset(self::$_types[$class]);
    }

    public function getTypes($class)
    {
        return self::$_types[$class];
    }
}