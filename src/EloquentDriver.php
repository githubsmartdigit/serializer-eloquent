<?php

/**
 * Author: Xooxx <Xooxx@xooxx.dev@gmail.com>
 * Date: 11/21/15
 * Time: 4:46 PM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Xooxx\Serializer\Drivers\Eloquent;

/**
 * Class EloquentDriver.
 */
class EloquentDriver
{
    /**
     * @var Driver
     */
    private static $driver;

    /**
     * @param $value
     *
     * @return mixed|string
     * @throws \ReflectionException
     */
    public static function serialize($value)
    {
        if (empty(self::$driver)) {
            self::$driver = new Driver();
        }
        return self::$driver->serialize($value);
    }
}