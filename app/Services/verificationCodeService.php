<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;

class verificationCodeService 
{
    private static int $min = 1000000;
    private static int $max = 9999999;

    /**
     * @throws Exception
     */
    public static function generteCode(): int
    {
        return random_int(self::$min, self::$max);
    }

    /**
     * @param $key
     * @param $value
     * @param int|string|null $time
     * @throws InvalidArgumentException
     */
    public static function set($key, $value, $time = null)
    {
        Cache::flush();
        if (!cache()->has($key))
        {
            $time = empty($time) ? now()->addMinutes((int)env('TIME_FOR_CACHE', 2)) : now()->addMinutes((int)$time);
            cache()->set($key, $value, $time);
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function get(string $key)
    {
        return cache()->has($key) ? cache()->get($key) : false;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function delete(string $key)
    {
        if (cache()->has($key))
        {
            cache()->delete($key);
        }
    }
}