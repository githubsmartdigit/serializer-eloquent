<?php

namespace Xooxx\Serializer\Drivers\Eloquent;

use Illuminate\Database\Eloquent\Collection;
use Xooxx\Serializer\Drivers\Eloquent\Helper\RelationshipPropertyExtractor;
use Xooxx\Serializer\Serializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use ReflectionClass;

class Driver extends Serializer
{
    /** @noinspection PhpMissingParentConstructorInspection */
    /**
     *
     */
    public function __construct()
    {
    }

    /**
     * @param mixed $value
     *
     * @return mixed|string
     * @throws \ReflectionException
     */
    public function serialize($value)
    {
        $this->reset();
        return $this->serializeObject($value);
    }

    /**
     * Extract the data from an object.
     *
     * @param mixed $value
     *
     * @return array
     * @throws \ReflectionException
     */
    protected function serializeObject($value)
    {
        if ($value instanceof Collection) {
            return $this->serializeEloquentCollection($value);
        }
        if ($value instanceof Paginator) {
            return $this->serializeEloquentPaginatedResource($value);
        }
        if (\is_subclass_of($value, Model::class, true)) {
            return $this->serializeEloquentModel($value);
        }
        return parent::serializeObject($value);
    }

    /**
     * @param Collection $value
     *
     * @return array
     * @throws \ReflectionException
     */
    protected function serializeEloquentCollection(Collection $value)
    {
        $items = [];
        foreach ($value as $v) {
            $items[] = $this->serializeObject($v);
        }
        return [self::MAP_TYPE => 'array', self::SCALAR_VALUE => $items];
    }

    /**
     * @param Paginator $value
     *
     * @return array
     * @throws \ReflectionException
     */
    protected function serializeEloquentPaginatedResource(Paginator $value)
    {
        $items = [];
        foreach ($value->getItems() as $v) {
            $items[] = $this->serializeObject($v);
        }
        return [self::MAP_TYPE => 'array', self::SCALAR_VALUE => $items];
    }

    /**
     * @param Model $value
     *
     * @return array
     * @throws \ReflectionException
     */
    protected function serializeEloquentModel($value)
    {
        $stdClass = (object) $value->attributesToArray();
        $data = $this->serializeData($stdClass);
        $data[self::CLASS_IDENTIFIER_KEY] = get_class($value);
        $methods = RelationshipPropertyExtractor::getRelationshipAsPropertyName($value, get_class($value), new ReflectionClass($value), $this);
        if (!empty($methods)) {
            $data = array_merge($data, $methods);
        }
        return $data;
    }
}