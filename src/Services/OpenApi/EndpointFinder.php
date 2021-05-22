<?php

namespace RestApiBundle\Services\OpenApi;

use Composer\Autoload\ClassLoader;
use Doctrine\Common\Annotations\AnnotationReader;
use Mapper\Helper\AnnotationReaderFactory;
use RestApiBundle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Routing\Annotation\Route;

use function array_merge;
use function array_slice;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_string;
use function preg_match_all;
use function reset;
use function spl_autoload_functions;
use function sprintf;
use function substr_count;
use function token_get_all;

class EndpointFinder
{
    private AnnotationReader $annotationReader;
    private RestApiBundle\Services\OpenApi\Types\DocBlockTypeReader $docBlockReader;
    private RestApiBundle\Services\OpenApi\Types\TypeHintTypeReader $typeHintReader;
    private RestApiBundle\Services\OpenApi\DoctrineResolver $doctrineHelper;

    public function __construct(
        RestApiBundle\Services\OpenApi\Types\DocBlockTypeReader $docBlockSchemaReader,
        RestApiBundle\Services\OpenApi\Types\TypeHintTypeReader $typeHintSchemaReader,
        RestApiBundle\Services\OpenApi\DoctrineResolver $doctrineHelper
    ) {
        $this->annotationReader = AnnotationReaderFactory::create(true);
        $this->docBlockReader = $docBlockSchemaReader;
        $this->typeHintReader = $typeHintSchemaReader;
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * @return RestApiBundle\Model\OpenApi\EndpointData[]
     */
    public function findInDirectory(string $directory, ?string $excludePath = null): array
    {
        $result = [];

        $finder = new Finder();
        $finder
            ->files()
            ->in($directory)
            ->name('*.php')
            ->notPath($excludePath);

        $autoloadFixed = false;

        foreach ($finder as $fileInfo) {
            $class = $this->getClassByFileInfo($fileInfo);

            if (!$autoloadFixed) {
                $filePathParts = explode('/', $fileInfo->getPathname());
                $namespaceDirectory = implode('/', array_slice($filePathParts, 0, count($filePathParts) - substr_count($class, '\\') - 1));
                $this->getClassLoader()->add("", $namespaceDirectory);

                $autoloadFixed = true;
            }

            $result[] = $this->extractFromController($class);

        }

        return array_merge(...$result);
    }

    private function getClassByFileInfo(SplFileInfo $fileInfo): string
    {
        $tokens = token_get_all($fileInfo->getContents());

        $namespaceTokenOpened = false;
        $namespace = '';

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === \T_NAMESPACE) {
                $namespaceTokenOpened = true;
            } elseif ($namespaceTokenOpened && is_array($token) && $token[0] !== \T_WHITESPACE) {
                $namespace .= $token[1];
            } elseif ($namespaceTokenOpened && is_string($token) && $token === ';') {
                break;
            }
        }

        if (!$namespace) {
            throw new \LogicException();
        }

        $fileNameWithoutExtension = $fileInfo->getBasename('.' . $fileInfo->getExtension());

        return sprintf('%s\%s', $namespace, $fileNameWithoutExtension);
    }

    /**
     * @return RestApiBundle\Model\OpenApi\EndpointData[]
     */
    private function extractFromController(string $class): array
    {
        $result = [];

        $reflectionController = RestApiBundle\Helper\ReflectionClassStore::get($class);
        $controllerRouteAnnotation = $this->annotationReader->getClassAnnotation($reflectionController, Route::class);

        foreach ($reflectionController->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            $actionRouteAnnotation = $this->annotationReader->getMethodAnnotation($reflectionMethod, Route::class);
            if (!$actionRouteAnnotation instanceof Route) {
                continue;
            }

            $endpointAnnotation = $this->annotationReader->getMethodAnnotation($reflectionMethod, RestApiBundle\Mapping\OpenApi\Endpoint::class);
            if (!$endpointAnnotation instanceof RestApiBundle\Mapping\OpenApi\Endpoint) {
                continue;
            }

            try {
                if ($controllerRouteAnnotation instanceof Route && $controllerRouteAnnotation->getPath()) {
                    $path = $controllerRouteAnnotation->getPath();

                    if ($actionRouteAnnotation->getPath()) {
                        $path .= $actionRouteAnnotation->getPath();
                    }
                } elseif ($actionRouteAnnotation->getPath()) {
                    $path = $actionRouteAnnotation->getPath();
                } else {
                    throw new RestApiBundle\Exception\OpenApi\InvalidDefinition\EmptyRoutePathException();
                }

                $endpointData = new RestApiBundle\Model\OpenApi\EndpointData();
                $endpointData
                    ->setTitle($endpointAnnotation->title)
                    ->setDescription($endpointAnnotation->description)
                    ->setTags($endpointAnnotation->tags)
                    ->setPath($path)
                    ->setMethods($actionRouteAnnotation->getMethods())
                    ->setResponse($this->extractResponse($reflectionMethod))
                    ->setPathParameters($this->extractPathParameters($path, $reflectionMethod))
                    ->setRequest($this->extractRequest($reflectionMethod));

                $result[] = $endpointData;
            } catch (RestApiBundle\Exception\OpenApi\InvalidDefinition\BaseInvalidDefinitionException $exception) {
                $context = sprintf('%s::%s', $class, $reflectionMethod->getName());
                throw new RestApiBundle\Exception\OpenApi\InvalidDefinitionException($exception, $context);
            }
        }

        return $result;
    }

    private function extractResponse(\ReflectionMethod $reflectionMethod): RestApiBundle\Model\OpenApi\Response\ResponseInterface
    {
        $returnType = $this->getReturnType($reflectionMethod);

        switch (true) {
            case $returnType instanceof RestApiBundle\Model\OpenApi\Types\NullType:
                $result = new RestApiBundle\Model\OpenApi\Response\EmptyResponse();

                break;

            case $returnType instanceof RestApiBundle\Model\OpenApi\Types\ClassType && RestApiBundle\Helper\ClassInstanceHelper::isResponseModel($returnType->getClass()):
                $result = new RestApiBundle\Model\OpenApi\Response\ResponseModel($returnType->getClass(), $returnType->getNullable());

                break;

            case $returnType instanceof RestApiBundle\Model\OpenApi\Types\ArrayType:
                $innerType = $returnType->getInnerType();
                if (!$innerType instanceof RestApiBundle\Model\OpenApi\Types\ClassType || !RestApiBundle\Helper\ClassInstanceHelper::isResponseModel($innerType->getClass())) {
                    throw new \InvalidArgumentException('Invalid response type');
                }

                $result = new RestApiBundle\Model\OpenApi\Response\ArrayOfResponseModels($innerType->getClass(), $returnType->getNullable());

                break;

            default:
                throw new \InvalidArgumentException('Invalid response type');
        }

        return $result;
    }

    private function getReturnType(\ReflectionMethod $reflectionMethod): RestApiBundle\Model\OpenApi\Types\TypeInterface
    {
        $result = $this->docBlockReader->resolveReturnType($reflectionMethod) ?: $this->typeHintReader->resolveReturnType($reflectionMethod);
        if (!$result) {
            $context = sprintf('%s::%s', $reflectionMethod->class, $reflectionMethod->name);
            throw new RestApiBundle\Exception\OpenApi\InvalidDefinitionException(new RestApiBundle\Exception\OpenApi\InvalidDefinition\EmptyReturnTypeException(), $context);
        }

        return $result;
    }

    /**
     * @return RestApiBundle\Model\OpenApi\PathParameter\PathParameterInterface[]
     */
    private function extractPathParameters(string $path, \ReflectionMethod $reflectionMethod): array
    {
        $scalarTypes = [];
        $entityTypes = [];

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $parameterType = $this->typeHintReader->resolveParameterType($parameter);
            if ($parameterType instanceof RestApiBundle\Model\OpenApi\Types\ScalarInterface) {
                $scalarTypes[$parameter->getName()] = $parameterType;
            } elseif ($parameterType instanceof RestApiBundle\Model\OpenApi\Types\ClassType && $this->doctrineHelper->isEntity($parameterType->getClass())) {
                $entityTypes[$parameter->getName()] = $parameterType;
            }
        }

        $result = [];
        $placeholders = $this->getPathPlaceholders($path);

        foreach ($placeholders as $placeholder) {
            $pathParameter = null;

            if (isset($scalarTypes[$placeholder])) {
                $result[] = new RestApiBundle\Model\OpenApi\PathParameter\ScalarParameter($placeholder, $scalarTypes[$placeholder]);
            } elseif (isset($entityTypes[$placeholder])) {
                $result[] = new RestApiBundle\Model\OpenApi\PathParameter\EntityTypeParameter($placeholder, $entityTypes[$placeholder], 'id');
                unset($entityTypes[$placeholder]);
            } else {
                $entityType = reset($entityTypes);
                if (!$entityType instanceof RestApiBundle\Model\OpenApi\Types\ClassType) {
                    throw new RestApiBundle\Exception\OpenApi\InvalidDefinition\NotMatchedRoutePlaceholderParameterException($placeholder);
                }
                $result[] = new RestApiBundle\Model\OpenApi\PathParameter\EntityTypeParameter($placeholder, $entityType, $placeholder);
            }
        }

        return $result;
    }

    private function getPathPlaceholders(string $path): array
    {
        $matches = null;
        $parameters = [];

        if (preg_match_all('/{([^}]+)}/', $path, $matches)) {
            $parameters = $matches[1];
        }

        return $parameters;
    }

    private function extractRequest(\ReflectionMethod $reflectionMethod): ?RestApiBundle\Model\OpenApi\Request\RequestInterface
    {
        $result = null;

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $parameterType = $this->typeHintReader->resolveParameterType($parameter);
            if ($parameterType instanceof RestApiBundle\Model\OpenApi\Types\ClassType && RestApiBundle\Helper\ClassInstanceHelper::isRequestModel($parameterType->getClass())) {
                $result = new RestApiBundle\Model\OpenApi\Request\RequestModel($parameterType->getClass(), $parameterType->getNullable());

                break;
            }
        }

        return $result;
    }

    private function getClassLoader(): ClassLoader
    {
        $result = null;
        foreach (spl_autoload_functions() as $classWithFunction) {
            if (!is_array($classWithFunction)) {
                continue;
            }

            if ($classWithFunction[0] instanceof ClassLoader) {
                $result = $classWithFunction[0];

                break;
            }
        }

        if (!$result) {
            throw new \InvalidArgumentException();
        }

        return $result;
    }
}