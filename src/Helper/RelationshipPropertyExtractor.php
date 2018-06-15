<?php

namespace Xooxx\Serializer\Drivers\Eloquent\Helper;

use Xooxx\Laravel\JsonApi\JsonApiSerializer;
use ErrorException;
use Xooxx\Serializer\Drivers\Eloquent\Driver;
use Xooxx\Serializer\Serializer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use Traversable;
class RelationshipPropertyExtractor
{
    /**
     * @var array
     */
    private static $forbiddenFunction = ['forceDelete', 'forceFill', 'delete', 'newQueryWithoutScopes', 'newQuery', 'bootIfNotBooted', 'boot', 'bootTraits', 'clearBootedModels', 'query', 'onWriteConnection', 'delete', 'forceDelete', 'performDeleteOnModel', 'flushEventListeners', 'push', 'touchOwners', 'touch', 'updateTimestamps', 'freshTimestamp', 'freshTimestampString', 'newQuery', 'newQueryWithoutScopes', 'newBaseQueryBuilder', 'usesTimestamps', 'reguard', 'isUnguarded', 'totallyGuarded', 'syncOriginal', 'getConnectionResolver', 'unsetConnectionResolver', 'getEventDispatcher', 'unsetEventDispatcher', '__toString', '__wakeup'];

    /**
     * @param Model $value
     * @param $className
     * @param ReflectionClass $reflection
     * @param Driver $serializer
     *
     * @return array
     */
    public static function getRelationshipAsPropertyName($value, $className, ReflectionClass $reflection, Driver $serializer)
    {
        $methods = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (ltrim($method->class, '\\') !== ltrim($className, '\\')) {
                continue;
            }
            $name = $method->name;
            $reflectionMethod = $reflection->getMethod($name);
            if (!self::isAllowedEloquentModelFunction($name) || $reflectionMethod->getNumberOfParameters() > 0) {
                continue;
            }
            if (in_array($name, $value->getHidden(), true)) {
                continue;
            }
            try {
                $returned = $reflectionMethod->invoke($value);
                if (!(\is_object($returned) && self::isAnEloquentRelation($returned))) {
                    continue;
                }

                /** @var Relation | Model $related */
                $related = $returned->getRelated();
                $relatedClass = get_class($related);

                $relationData = function ($keysOnly) use ($returned, $related) {
                    if($keysOnly) {
                        /** @var Collection $collection */
                        $collection = $returned->get([$related->getTable() . '.'. $related->getKeyName()]);
                        return $collection->each(function($model){
                            /**  @var Model $model */
                            $model->setAppends([]);
                        });
                    }else{
                        return $returned->getResults();
                    }
                };

                $methods[$name] = [
                    '_relation' => $relatedClass,
                    '_func' => function ($keysOnly) use ($relationData, $serializer, $relatedClass, $name) {
                        $relationData = $relationData($keysOnly);
                        if ($relationData instanceof Traversable) {
                            //Something traversable with Models
                            $items = [];
                            foreach ($relationData as $model) {
                                if ($model instanceof Model) {
                                    $items[] = self::getModelData($serializer, $model, $relatedClass);
                                }
                            }
                            return [Serializer::MAP_TYPE => 'array', Serializer::SCALAR_VALUE => $items];
                        } elseif ($relationData instanceof Model) {
                            //Single element returned.
                            return self::getModelData($serializer, $relationData, $relatedClass);
                        } else {
                            return null;
                        }
                    }
                ];

            } catch (ErrorException $e) {
            }
        }

        return $methods;
    }
    /**
     * @param $name
     *
     * @return bool
     */
    protected static function isAllowedEloquentModelFunction($name)
    {
        return false === in_array($name, self::$forbiddenFunction, true);
    }
    /**
     * @param $returned
     *
     * @return bool
     */
    protected static function isAnEloquentRelation($returned)
    {
        return false !== strpos(get_class($returned), 'Illuminate\\Database\\Eloquent\\Relations');
    }

    /**
     * @param Driver $serializer
     * @param Model $model
     *
     * @param string $relatedClass
     * @return array
     * @throws \ReflectionException
     */
    protected static function getModelData(Driver $serializer, Model $model, $relatedClass)
    {
        $model->setHidden(app(JsonApiSerializer::class)->getTransformer()->getMappingByClassName($relatedClass)->getHiddenProperties());
        $stdClass = (object) $model->attributesToArray();
        $data = $serializer->serialize($stdClass);
        $data[Serializer::CLASS_IDENTIFIER_KEY] = get_class($model);
        return $data;
    }
}