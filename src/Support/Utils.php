<?php

namespace Fleetbase\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Fleetbase\Models\Model;
use Fleetbase\Models\File;
use Fleetbase\Models\Company;

class Utils
{
    /**
     * Generates a URL to this API
     * 
     * @param string $path
     * @param null|array $queryParams
     * @param string $subdomain
     * @return string
     */
    public static function apiUrl(string $path, ?array $queryParams = null, $subdomain = 'api'): string
    {
        if (app()->environment(['local', 'development'])) {
            $subdomain = 'v2api';
        }

        return static::consoleUrl($path, $queryParams, $subdomain);
    }

    /**
     * Generate a url to the console
     *
     * @param string $path
     * @param null|array $queryParams
     * @param string $subdomain
     * @return string
     */
    public static function consoleUrl(string $path, ?array $queryParams = null, $subdomain = 'console'): string
    {
        $url = 'https://' . $subdomain;

        if (app()->environment(['qa', 'staging'])) {
            $url .= '.' . app()->environment();
        }

        if (app()->environment(['local', 'development'])) {
            $url .= '.fleetbase.engineering';
        } else {
            $url .= '.fleetbase.io';
        }

        if (!empty($path)) {
            $url = Str::startsWith($path, '/') ? $url . $path : $url . '/' . $path;
        }

        if ($queryParams) {
            $url = $url . '?' . http_build_query($queryParams);
        }

        return $url;
    }

    /**
     * Return asset URL from s3.
     *
     * @param string $string
     * @param string $pattern
     * @return boolean
     */
    public static function fromS3(string $path, $bucket = null, $region = null): string
    {
        $bucket = $bucket ?? config('filesystems.disks.s3.bucket', $bucket);
        $region = $region ?? config('filesystems.disks.s3.region', $region);

        if ($region) {
            $region = '.s3-' . $region;
        }

        return 'https://' . $bucket . $region . '.amazonaws.com/' . $path;
    }

    /**
     * Return asset URL from s3.
     *
     * @param string $string
     * @param string $pattern
     * @return boolean
     */
    public static function assetFromS3(string $path, $region = null): string
    {
        return static::fromS3($path, 'flb-assets', $region);
    }

    /**
     * Return asset URL from Fleetbase S3 asset bucket.
     *
     * @param string $string
     * @param string $pattern
     * @return boolean
     */
    public static function assetFromFleetbase(string $path): string
    {
        return static::assetFromS3($path, 'ap-southeast-1');
    }

    /**
     * Checks if string contains a match for given regex pattern.
     *
     * @param string $string
     * @param string $pattern
     * @return boolean
     */
    public static function stringMatches(string $string, $pattern): bool
    {
        $matches = [];
        preg_match($pattern, $string, $matches);

        return (bool) count($matches);
    }

    /**
     * Extracts the matched pattern from the string.
     *
     * @param string $string
     * @param string $pattern
     * @return string|null
     */
    public static function stringExtract(string $string, $pattern): ?string
    {
        $matches = [];
        preg_match($pattern, $string, $matches);

        return Arr::first($matches);
    }

    /**
     * Converts headers array to key value using the colon : delimieter.
     *
     * ```
     * $headers = ['Content-Type: application/json]
     *
     * keyHeaders($headers) // ['Content-Type' => 'application/json']
     * ```
     *
     * @param array $headers
     * @return array
     */
    public static function keyHeaders(array $headers): array
    {
        $keyHeaders = [];

        foreach ($headers as $header) {
            [$key, $value] = explode(':', $header);

            $keyHeaders[$key] = $value;
        }

        return $keyHeaders;
    }

    /**
     * Converts headers array to key value using the colon : delimieter.
     *
     * ```
     * $headers = ['Content-Type' => 'application/json']
     *
     * unkeyHeaders($headers) // ['Content-Type: application/json']
     * ```
     *
     * @param array $headers
     * @return array
     */
    public static function unkeyHeaders(array $headers): array
    {
        $unkeyedHeaders = [];

        foreach ($headers as $key => $header) {
            if (is_numeric($key)) {
                $unkeyedHeaders[] = $header;
                continue;
            }

            $unkeyedHeaders[] = $key . ': ' . $header;
        }

        return $unkeyedHeaders;
    }



    /**
     * Creates an object from an array.
     *
     * @param array $attributes
     * @return stdObject
     */
    public static function createObject($attributes = [])
    {
        return (object) $attributes;
    }

    /**
     * Converts a time/date string to a mysql datetime.
     *
     * @param string $string
     * @return string
     */
    public static function toMySqlDatetime($string)
    {
        $string = preg_replace('/\([a-z0-9 ]+\)/i', '', $string);
        return date('Y-m-d H:i:s', strtotime($string));
    }

    /**
     * Converts a time/date string to a mysql datetime.
     *
     * @param string $string
     * @return string
     */
    public static function toDatetime($string)
    {
        return Carbon::parse($string)->toDateTime();
    }

    /**
     * Check if the value is a valid date
     *
     * @param mixed $value
     * @return boolean
     */
    public static function isDate($value)
    {
        if (!$value) {
            return false;
        }

        try {
            new \DateTime($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Converts a QueryBuilder to a string
     *
     * @param QueryBuilder $query
     * @return string
     */
    public static function queryBuilderToString($query)
    {
        return vsprintf(str_replace('?', '"%s"', $query->toSql()), $query->getBindings());
    }

    /**
     * Dump and die's a formatted SQL string
     *
     * @param string $string
     * @return string
     */
    public static function sqlDump($sql, $die = true, $withoutBinding = false)
    {
        if (is_object($sql) && $withoutBinding === false) {
            $sql = static::queryBuilderToString($sql);
        } elseif (is_object($sql)) {
            $sql = $sql->toSql();
        }

        $sql = \SqlFormatter::format($sql);
        if ($die) {
            exit($sql);
        } else {
            print($sql);
        }
    }

    /**
     * Replaces any parameter placeholders in a query with the value of that
     * parameter. Useful for debugging. Assumes anonymous parameters from 
     * $params are are in the same order as specified in $query
     *
     * @param string $query The sql query with parameter placeholders
     * @param array $params The array of substitution parameters
     * @return string The interpolated query
     */
    public static function interpolateQuery($query, $params)
    {
        $keys = array();

        # build a regular expression for each parameter
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $keys[] = '/:' . $key . '/';
            } else {
                $keys[] = '/[?]/';
            }
        }

        $query = preg_replace($keys, $params, $query, 1, $count);

        #trigger_error('replaced '.$count.' keys');

        return $query;
    }

    /**
     * Determines if variable is not empty
     *
     * @param mixed $var
     * @return boolean
     */
    public static function isset($var, $key = null)
    {
        if ($key !== null && is_string($key)) {
            return null !== Utils::get($var, $key);
        }

        return isset($var);
    }

    /**
     * Determines if variable is not empty
     *
     * @param mixed $var
     * @return boolean
     */
    public static function notEmpty($var)
    {
        return !empty($var);
    }

    /**
     * Determines if variable is empty
     *
     * @param mixed $var
     * @return boolean
     */
    public static function isEmpty($var)
    {
        return empty($var);
    }

    /**
     * Casts value to boolean.
     *
     * @param mixed $val
     * @param boolean $return_null
     * @return boolean
     */
    public static function castBoolean($val): bool
    {
        if (is_null($val)) {
            return false;
        }

        if (is_string($val) && in_array($val, ['true', '1', 'truthy', 'on'])) {
            return true;
        }

        if (is_string($val) && in_array($val, ['false', '0', '-1', 'falsey', 'off'])) {
            return false;
        }

        if (is_string($val)) {
            return filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return (bool) $val;
    }

    public static function isBooleanValue($val)
    {
        if (is_bool($val)) {
            return true;
        }

        if (is_string($val)) {
            return in_array($val, ['true', 'false', '1', '0']);
        }

        return false;
    }

    /**
     * Checks if a value is true.
     *
     * @param mixed $val
     * @param boolean $return_null
     * @return boolean
     */
    public static function isTrue($val, $return_null = false)
    {
        $boolval = static::castBoolean($val);

        return $boolval === null && !$return_null ? false : $boolval;
    }

    /**
     * Checks if a value is false.
     *
     * @param mixed $val
     * @param boolean $return_null
     * @return boolean
     */
    public static function isFalse($val, $return_null = false)
    {
        return !static::isTrue($val, $return_null);
    }

    /**
     * Checks if a value is valid json.
     *
     * @param string $string
     * @return boolean
     */
    public static function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() == JSON_ERROR_NONE;
    }

    /**
     * Parse a SQL error exception to a string.
     *
     * @param  string $error
     * @return string
     */
    public static function sqlExceptionString($error)
    {
        if (is_object($error)) {
            $error = $error->getMessage();
        }
        if (Str::contains($error, ']:') && Str::contains($error, '(')) {
            $error = explode(']:', $error);
            $error = explode('(', $error[1]);

            return trim($error[0]);
        }

        return $error;
    }

    /**
     * Returns the short version class name for an object
     * without its namespace.
     *
     * @param object|string $class
     * @return string
     */
    public static function classBasename($class): ?string
    {
        if (function_exists('class_basename')) {
            return class_basename($class);
        }

        $className = null;

        try {
            $className = (new \ReflectionClass($class))->getShortName();
        } catch (\ReflectionException $e) {
            //
        }

        return $className;
    }

    /**
     * Pluralizes a string
     *
     * @param string $text
     * @return string
     */
    public static function pluralize(?string $text): string
    {
        if (!is_string($text)) {
            return '';
        }

        $inflector = \Doctrine\Inflector\InflectorFactory::create()->build();

        return $inflector->pluralize($text);
    }

    /**
     * Singularizes a string
     *
     * @param string $text
     * @return string
     */
    public static function singularize(?string $text): string
    {
        if (!is_string($text)) {
            return '';
        }

        $inflector = \Doctrine\Inflector\InflectorFactory::create()->build();

        return $inflector->singularize($text);
    }

    /**
     * Tableize a string
     *
     * @param string $text
     * @return string
     */
    public static function tableize($text): string
    {
        $inflector = \Doctrine\Inflector\InflectorFactory::create()->build();

        return $inflector->tableize($text);
    }

    /**
     * Alias for strtolower
     *
     * @param string $str
     * @return string
     */
    public static function lowercase($str)
    {
        return Str::lower($str);
    }

    /**
     * Humanize a string
     *
     * @param string $str
     * @return string
     */
    public static function humanize(string $string): string
    {
        $uppercase = ['api', 'vat', 'id', 'uuid', 'sku', 'ean', 'upc', 'erp', 'tms', 'wms', 'ltl', 'ftl', 'lcl', 'fcl', 'rfid', 'jot', 'roi', 'eta', 'pod', 'asn', 'oem', 'ddp', 'fob'];
        $string = str_replace('_', ' ', $string);
        $string = str_replace('-', ' ', $string);
        $string = ucwords($string);

        $string = implode(
            ' ',
            array_map(
                function ($word) use ($uppercase) {
                    if (in_array(strtolower($word), $uppercase)) {
                        return strtoupper($word);
                    }

                    return $word;
                },
                explode(' ', $string)
            )
        );

        return $string;
    }

    /**
     * "Smart" humanize a string by retaining common abbreviation cases
     *
     * @param string $str
     * @return string
     */
    public static function smartHumanize(?string $string): string
    {
        $search = ['api', 'vat', 'id', 'sku'];
        $replace = array_map(function ($word) {
            return strtoupper($word);
        }, $search);
        $subject = static::humanize($string);

        return Str::replace($search, $replace, $subject);
    }

    /**
     * Returns the uuid for a table with where hook.
     *
     * @param string|array $table
     * @param array|callable $where
     *
     * @return string
     */
    public static function getUuid($table, $where = [])
    {
        if (is_array($table)) {
            foreach ($table as $t) {
                $uuid = static::getUuid($t, $where);

                if ($uuid) {
                    return ['uuid' => $uuid, 'table' => static::pluralize($t)];
                }
            }
            return;
        }

        $result =  DB::table(static::pluralize($table))
            ->select(['uuid'])
            ->where($where)->first();

        return data_get($result, 'uuid');
    }

    /**
     * Returns the model for the specific where clause, and can check accross multiple tables
     *
     * @param string|array $table
     * @param array $where
     *
     * @return \Fleetbase\Models\Model
     */
    public static function findModel($table, $where = [])
    {
        if (is_array($table)) {
            foreach ($table as $t) {
                $model =
                    DB::table($t)
                    ->select(['*'])
                    ->where($where)
                    ->first() ?? null;
                if ($model) {
                    return $model;
                }
            }
        }
        return DB::table($table)
            ->select(['*'])
            ->where($where)
            ->first() ?? null;
    }

    /**
     * Generate a random number with specified length
     *
     * @param int length
     * @return int
     */
    public static function randomNumber($length = 4)
    {
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= mt_rand(0, 9);
        }

        return $result;
    }

    /**
     * Converts the param to an integer with numbers only
     *
     * @param string|mixed $string
     * @return int
     */
    public static function numbersOnly($string)
    {
        return intval(preg_replace('/[^0-9]/', '', $string));
    }

    /**
     * Removes all special charavters from a string, unless excepted characters are supplied
     *
     * @param string|mixed $string
     * @param array $except
     * @return string
     */
    public static function removeSpecialCharacters($string, $except = [])
    {
        $regex = '/[^a-zA-Z0-9';

        if (is_array($except)) {
            foreach ($except as $char) {
                $regex .= $char;
            }
        }

        $regex .= ']/';

        return preg_replace($regex, '', $string);
    }

    /**
     * Format number to a particular currency.
     *
     * @param float $amount amount to format
     * @param string $currency the currency to format into
     * @param boolean $cents whether if amount is in cents, this will auto divide by 100
     * @return string
     */
    public static function moneyFormat($amount, $currency = 'USD', $cents = true)
    {
        $amount = $cents === true ? static::numbersOnly($amount) / 100 : $amount;
        $money = new \Cknow\Money\Money($amount, $currency);

        return $money->format();
    }

    /**
     * Calculates the percentage of a integer
     *
     * @param integer|float $percentage
     * @param integer $number
     *
     * @return integer
     */
    public static function calculatePercentage($percentage, $number)
    {
        return ($percentage / 100) * $number;
    }

    /**
     * Get the fully qualified class name for the given table, including the namespace.
     *
     * @param string|object $table The table name or an object instance to derive the class name from.
     * @param string|array $namespaceSegments A string representing the namespace or an array of segments to be appended to the model class name.
     * @return string The fully qualified class name, including the namespace.
     * @throws InvalidArgumentException If the provided $namespaceSegments is not a string or an array.
     */
    public static function getModelClassName($table, $namespaceSegments = '\\Fleetbase\\'): string
    {
        if (is_object($table)) {
            $table = static::classBasename($table);
        }

        if (Str::startsWith($table, $namespaceSegments)) {
            return $table;
        }

        $modelName = Str::studly(static::singularize($table));

        // Check if the input is a string (namespace) or an array (segments)
        if (is_string($namespaceSegments)) {
            $namespace = rtrim($namespaceSegments, '\\');
            $segments = [$namespace, 'Models'];
        } elseif (is_array($namespaceSegments)) {
            $segments = $namespaceSegments;
        } else {
            throw new \InvalidArgumentException('The input must be a string or an array.');
        }

        // Add the model name to the segments array
        $segments[] = $modelName;

        // Implode the segments with a backslash
        return implode('\\', $segments);
    }

    /**
     * Converts a model name or table name into a mutation type for eloquent relationships.
     * 
     * storefront:store -> Fleetbase\Storefront\Models\Store
     * fleet-ops:order -> Fleetbase\FleetOps\Models\Order
     * user -> Fleetbase\Models\User
     * Fleetbase\Models\Order -> Fleetbase\Models\Order
     * 
     * @param string|object type
     * @return string
     */
    public static function getMutationType($type): string
    {
        if (is_object($type)) {
            return get_class($type);
        }

        if (Str::contains($type, '\\')) {
            return $type;
        }

        if (Str::contains($type, ':')) {
            $namespace = explode(':', $type);
            $package = $namespace[0];
            $type = $namespace[1];
            $namespace = 'Fleetbase\\' . Str::studly($package);

            return Utils::getModelClassName($type, $namespace);
        }

        return Utils::getModelClassName($type);
    }

    /**
     * Retrieves a model class name ans turns it to a type
     * 
     * ex: UserDevice -> user-device
     *
     * @param int length
     * @return int
     */
    public static function getTypeFromClassName($className)
    {
        $basename = static::classBasename($className);
        $basename = static::classBasename($basename);

        return Str::slug($basename);
    }

    /**
     * Retrieves a model class name ans turns it to a type
     * 
     * ex: UserDevice -> user device
     *
     * @param int length
     * @return int
     */
    public static function humanizeClassName($className)
    {
        $basename = static::classBasename($className);

        return (string) static::humanize(Str::snake($basename));
    }

    /**
     * Retrieve the first value available from the targets
     *
     * @param mixed target
     * @param string key
     * @param mixed default
     *
     * @return mixed
     */
    public static function firstValue($target, $keys = [], $default = null)
    {
        if (!is_object($target) && !is_array($target)) {
            return $default;
        }

        foreach ($keys as $key) {
            $value = static::get($target, $key);

            if ($value) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Alias for data_get
     *
     * @param mixed target
     * @param string key
     * @param mixed default
     *
     * @return mixed
     */
    public static function get($target, $key, $default = null)
    {
        return data_get($target, $key, $default);
    }

    /**
     * Returns first available property value from a target array or object.
     *
     * @param mixed target
     * @param string key
     * @param mixed default
     *
     * @return mixed
     */
    public static function or($target, $keys = [], $defaultValue = null)
    {
        foreach ($keys as $key) {
            if (static::isset($target, $key)) {
                return static::get($target, $key);
            }
        }

        return $defaultValue;
    }

    /**
     * Alias for data_set
     *
     * @param mixed target
     * @param string key
     * @param boolean overwrite
     *
     * @return mixed
     */
    public static function set($target, $key, $value, $overwrite = true)
    {
        return data_set($target, $key, $value, $overwrite);
    }

    /**
     * Alias for data_set
     *
     * @param mixed target
     * @param string key
     * @param boolean overwrite
     *
     * @return mixed
     */
    public static function setProperties($target, $properties, $overwrite = true)
    {
        foreach ($properties as $key => $value) {
            $target = static::set($target, $key, $value, $overwrite);
        }

        return $target;
    }

    /**
     * Check if key exists with value.
     *
     * @param mixed target
     * @param string key
     * @param mixed default
     *
     * @return mixed
     */
    public static function exists($target, $key)
    {
        return static::notEmpty(static::get($target, $key));
    }

    /**
     * Check if key has no value.
     *
     * @param mixed target
     * @param string key
     * @param mixed default
     *
     * @return mixed
     */
    public static function notSet($target, $key)
    {
        return static::isEmpty(static::get($target, $key));
    }

    /**
     * Validate string if is valid fleetbase public_id
     *
     * @param string $string
     *
     * @return boolean
     */
    public static function isPublicId($string)
    {
        return is_string($string) && Str::contains($string, ['_']) && strlen(explode('_', $string)[1]) === 7;
    }

    /**
     * Checks if target is iterable and gets the count
     *
     * @param mixed target
     * @param string key
     * @param mixed default
     *
     * @return mixed
     */
    public static function count($target, $key)
    {
        $subject = static::get($target, $key);

        if (!is_iterable($subject)) {
            return 0;
        }

        return count($subject);
    }

    /**
     * Check if target is not scalar
     *
     * @param mixed target
     * @param string key
     * @param mixed default
     *
     * @return mixed
     */
    public static function isNotScalar($target)
    {
        return !is_scalar($target);
    }

    /**
     * Returns the ISO2 country name by providing a countries full name
     *
     * @param string countryName
     *
     * @return string
     */
    public static function getCountryCodeByName($countryName)
    {
        $countries = new \PragmaRX\Countries\Package\Countries();
        $countries = $countries
            ->all()
            ->map(function ($country) {
                return [
                    'name' => static::get($country, 'name.common'),
                    'iso2' => static::get($country, 'cca2'),
                ];
            })
            ->values()
            ->toArray();
        $countries = collect($countries);

        $data = $countries->first(function ($country) use ($countryName) {
            // @todo switch to string contains or like search
            return strtolower($country['name']) === strtolower($countryName);
        });

        // if faield try to find by the first word of the countryName
        if (!$data) {
            $cnSplit = explode(' ', $countryName);
            if (count($cnSplit) > 1 && strlen($cnSplit[0])) {
                return static::getCountryCodeByName($cnSplit[0]);
            }
        }

        return static::get($data, 'iso2') ?? null;
    }

    /**
     * Returns the ISO2 country name by providing a countries full name
     *
     * @param string $timezone
     * @return \PragmaRX\Countries\Package\Support\Collection
     */
    public static function findCountryFromTimezone(string $timezone): \PragmaRX\Countries\Package\Support\Collection
    {
        $countries = new \PragmaRX\Countries\Package\Countries(new \PragmaRX\Countries\Package\Services\Config([
            'hydrate' => [
                'elements' => [
                    'timezones' => true,
                ],
            ],
        ]));

        return $countries->filter(function ($country) use ($timezone) {
            return $country->timezones->filter(function ($tzData) use ($timezone) {
                return $tzData->zone_name === $timezone;
            })->count();
        });
    }

    /**
     * Returns additional country data for a given country in ISO2 format.
     *
     * @param string $country The ISO2 country code.
     *
     * @return array|null The additional country data.
     */
    public static function getCountryData(string $country): ?array
    {
        if (static::isEmpty($country)) {
            return null;
        }

        $storageKey = 'countryData:' . $country;

        if (Redis::exists($storageKey)) {
            return json_decode(Redis::get($storageKey), true);
        }

        $data = (new \PragmaRX\Countries\Package\Countries())
            ->where('cca2', $country)
            ->map(function ($country) {
                $longitude = (float) static::get($country, 'geo.longitude_desc') ?? 0;
                $latitude = (float) static::get($country, 'geo.latitude_desc') ?? 0;

                return [
                    'iso3' => static::get($country, 'cca3'),
                    'iso2' => static::get($country, 'cca2'),
                    'emoji' => static::get($country, 'flag.emoji'),
                    'name' => static::get($country, 'name'),
                    'aliases' => static::get($country, 'alt_spellings', []),
                    'capital' => static::get($country, 'capital_rinvex'),
                    'geo' => static::get($country, 'geo'),
                    'currency' => Arr::first(static::get($country, 'currencies', [])),
                    'dial_code' => Arr::first(static::get($country, 'calling_codes', [])),
                    'coordinates' => [
                        'longitude' => $longitude,
                        'latitude' => $latitude,
                    ]
                ];
            })
            ->first()
            ->toArray();

        if ($data) {
            Redis::set($storageKey, json_encode($data));
        }

        return $data ?? null;
    }

    /**
     * Retrieve currency from given ISO country code.
     *
     * @param string $countryCode The ISO country code to fetch currency information.
     *
     * @return string|null The currency code related to the given country code, or null if not found.
     */
    public static function getCurrenyFromCountryCode(string $countryCode): ?string
    {
        $data = static::getCountryData($countryCode);

        return static::get($data, 'currency');
    }

    /**
     * Retrieve area/dial code from given ISO country code.
     *
     * @param string $countryCode The ISO country code to fetch currency information.
     *
     * @return string|null The dial code related to the given country code, or null if not found.
     */
    public static function getDialCodeFromCountryCode(string $countryCode): ?string
    {
        $data = static::getCountryData($countryCode);

        return static::get($data, 'dial_code');
    }

    /**
     * Retrieve country capital from given ISO country code.
     *
     * @param string $countryCode The ISO country code to fetch currency information.
     *
     * @return string|null The capital city related to the given country code, or null if not found.
     */
    public static function getCapitalCityFromCountryCode(string $countryCode): ?string
    {
        $data = static::getCountryData($countryCode);

        return static::get($data, 'capital');
    }

    /**
     * Looks up a user client info w/ api
     *
     * @param string $ip
     * @return stdClass
     */
    public static function lookupIp($ip = null)
    {
        if ($ip === null) {
            $ip = request()->ip();
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->get('https://api.ipdata.co/' . $ip . '?api-key=' . env('IPINFO_API_KEY'));

        return $response->json();
    }

    /**
     * Filter an array, removing all null values
     *
     * @param array $arr
     * @return array
     */
    public static function filterArray(array $arr = []): array
    {
        $filteredArray = [];

        foreach ($arr as $key => $el) {
            if ($el !== null) {
                $filteredArray[$key] = $el;
            }
        }

        return $filteredArray;
    }

    /**
     * Delete all of a models relations.
     */
    public static function deleteModels(Collection $models)
    {
        if ($models->count() === 0) {
            return true;
        }

        $ids = $models->map(function ($model) {
            return $model->uuid;
        });

        $instance = app(static::getModelClassName($models->first()));
        $deleted = $instance->whereIn('uuid', $ids)->delete();

        return $deleted;
    }

    /**
     * Get an ordinal formatted number.
     * 
     * @return string
     */
    public static function ordinalNumber($number, $locale = 'en_US')
    {
        $ordinal = new \NumberFormatter($locale, \NumberFormatter::ORDINAL);
        return $ordinal->format($number);
    }

    public static function serializeJsonResource(JsonResource $resource)
    {
        $request = request();
        $data = $resource->toArray($request);

        foreach ($data as $key => $value) {
            if ($value instanceof JsonResource) {
                $data[$key] = static::serializeJsonResource($value);
            }

            if ($value instanceof Model) {
                $data[$key] = $value->toArray();
            }

            if ($value instanceof Carbon) {
                $data[$key] = $value->toDateTimeString();
            }
        }

        return $data;
    }

    public static function getBase64ImageSize(string $base64ImageString)
    {
        return (int)(strlen(rtrim($base64ImageString, '=')) * 0.75);
    }

    public static function getImageSizeFromString(string $data)
    {
        $data = static::isBase64($data) ? base64_decode($data) : $data;
        $uri = 'data://application/octet-stream;base64,' . $data;

        return getimagesize($uri);
    }

    public static function isBase64(string $data)
    {
        return (bool) preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $data);
    }

    /**
     * Generates a public id given a type.
     *
     * @return string
     */
    public static function generatePublicId(string $type): string
    {
        $hashid = lcfirst(\Vinkla\Hashids\Facades\Hashids::encode(time(), rand(), rand()));
        $hashid = substr($hashid, 0, 7);

        return $type . '_' . $hashid;
    }



    public static function formatSeconds($seconds)
    {
        return Carbon::now()->addSeconds($seconds)->longAbsoluteDiffForHumans();
    }

    public static function isEmail($email)
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function convertDb($connection, $charset, $collate, $dryRun)
    {
        $dbName = config("database.connections.{$connection}.database");

        $varchars = DB::connection($connection)
            ->select(DB::raw("select * from INFORMATION_SCHEMA.COLUMNS where DATA_TYPE = 'varchar' and (CHARACTER_SET_NAME != '{$charset}' or COLLATION_NAME != '{$collate}') AND TABLE_SCHEMA = '{$dbName}'"));
        // Check if shrinking field size will truncate!
        $skip = [];  // List of table.column that will be handled manually
        $indexed = [];
        if ($charset == 'utf8mb4') {
            $error = false;
            foreach ($varchars as $t) {
                if ($t->CHARACTER_MAXIMUM_LENGTH > 191) {
                    $key = "{$t->TABLE_NAME}.{$t->COLUMN_NAME}";

                    // Check if column is indexed
                    $index = DB::connection($connection)
                        ->select(DB::raw("SHOW INDEX FROM `{$t->TABLE_NAME}` where column_name = '{$t->COLUMN_NAME}'"));
                    $indexed[$key] = count($index) ? true : false;

                    if (count($index)) {
                        $result = DB::connection($connection)
                            ->select(DB::raw("select count(*) as `count` from `{$t->TABLE_NAME}` where length(`{$t->COLUMN_NAME}`) > 191"));
                        if ($result[0]->count > 0) {
                            echo "-- DATA TRUNCATION: {$t->TABLE_NAME}.{$t->COLUMN_NAME}({$t->CHARACTER_MAXIMUM_LENGTH}) => {$result[0]->count}" . PHP_EOL;
                            if (!in_array($key, $skip)) {
                                $error = true;
                            }
                        }
                    }
                }
            }
            if ($error) {
                throw new \Exception('Aborting due to data truncation');
            }
        }

        $query = "SET FOREIGN_KEY_CHECKS = 0";
        static::dbExec($query, $dryRun, $connection);

        $query = "ALTER SCHEMA {$dbName} DEFAULT CHARACTER SET {$charset} DEFAULT COLLATE {$collate}";
        static::dbExec($query, $dryRun, $connection);

        $tableChanges = [];
        foreach ($varchars as $t) {
            $key = "{$t->TABLE_NAME}.{$t->COLUMN_NAME}";
            if (!in_array($key, $skip)) {
                if ($charset == 'utf8mb4' && $t->CHARACTER_MAXIMUM_LENGTH > 191 && $indexed["{$t->TABLE_NAME}.{$t->COLUMN_NAME}"]) {
                    $tableChanges["{$t->TABLE_NAME}"][] = "CHANGE `{$t->COLUMN_NAME}` `{$t->COLUMN_NAME}` VARCHAR(191) CHARACTER SET {$charset} COLLATE {$collate}";
                    echo "-- Shrinking: {$t->TABLE_NAME}.{$t->COLUMN_NAME}({$t->CHARACTER_MAXIMUM_LENGTH})" . PHP_EOL;
                } else if ($charset == 'utf8' && $t->CHARACTER_MAXIMUM_LENGTH == 191) {
                    $tableChanges["{$t->TABLE_NAME}"][] = "CHANGE `{$t->COLUMN_NAME}` `{$t->COLUMN_NAME}` VARCHAR(255) CHARACTER SET {$charset} COLLATE {$collate}";
                    echo "-- Expanding: {$t->TABLE_NAME}.{$t->COLUMN_NAME}({$t->CHARACTER_MAXIMUM_LENGTH})";
                } else {
                    $tableChanges["{$t->TABLE_NAME}"][] = "CHANGE `{$t->COLUMN_NAME}` `{$t->COLUMN_NAME}` VARCHAR({$t->CHARACTER_MAXIMUM_LENGTH}) CHARACTER SET {$charset} COLLATE {$collate}";
                }
            }
        }

        $texts = DB::connection($connection)
            ->select(DB::raw("select * from INFORMATION_SCHEMA.COLUMNS where DATA_TYPE like '%text%' and (CHARACTER_SET_NAME != '{$charset}' or COLLATION_NAME != '{$collate}') AND TABLE_SCHEMA = '{$dbName}'"));
        foreach ($texts as $t) {
            $tableChanges["{$t->TABLE_NAME}"][] = "CHANGE `{$t->COLUMN_NAME}` `{$t->COLUMN_NAME}` {$t->DATA_TYPE} CHARACTER SET {$charset} COLLATE {$collate}";
        }

        $tables = DB::connection($connection)
            ->select(DB::raw("select * from INFORMATION_SCHEMA.TABLES where TABLE_COLLATION != '{$collate}' and TABLE_SCHEMA = '{$dbName}';"));
        foreach ($tables as $t) {
            $tableChanges["{$t->TABLE_NAME}"][] = "CONVERT TO CHARACTER SET {$charset} COLLATE {$collate}";
            $tableChanges["{$t->TABLE_NAME}"][] = "DEFAULT CHARACTER SET={$charset} COLLATE={$collate}";
        }

        foreach ($tableChanges as $table => $changes) {
            $query = "ALTER TABLE `{$table}` " . implode(",\n", $changes);
            static::dbExec($query, $dryRun, $connection);
        }

        $query = "SET FOREIGN_KEY_CHECKS = 1";
        static::dbExec($query, $dryRun, $connection);

        echo "-- {$dbName} CONVERTED TO {$charset}-{$collate}" . PHP_EOL;
    }

    public static function dbExec($query, $dryRun, $connection)
    {
        if ($dryRun) {
            echo $query . ';' . PHP_EOL;
        } else {
            DB::connection($connection)->getPdo()->exec($query);
        }
    }

    public static function numberAsWord(int $number): string
    {
        $formatter = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);

        return $formatter->format($number);
    }

    public static function numericStringToDigits(string $number): string
    {
        // Replace all number words with an equivalent numeric value
        $data = strtr(
            $number,
            array(
                'zero'      => '0',
                'a'         => '1',
                'one'       => '1',
                'two'       => '2',
                'three'     => '3',
                'four'      => '4',
                'five'      => '5',
                'six'       => '6',
                'seven'     => '7',
                'eight'     => '8',
                'nine'      => '9',
                'ten'       => '10',
                'eleven'    => '11',
                'twelve'    => '12',
                'thirteen'  => '13',
                'fourteen'  => '14',
                'fifteen'   => '15',
                'sixteen'   => '16',
                'seventeen' => '17',
                'eighteen'  => '18',
                'nineteen'  => '19',
                'twenty'    => '20',
                'thirty'    => '30',
                'forty'     => '40',
                'fourty'    => '40', // common misspelling
                'fifty'     => '50',
                'sixty'     => '60',
                'seventy'   => '70',
                'eighty'    => '80',
                'ninety'    => '90',
                'hundred'   => '100',
                'thousand'  => '1000',
                'million'   => '1000000',
                'billion'   => '1000000000',
                'and'       => '',
            )
        );

        // Coerce all tokens to numbers
        $parts = array_map(
            function ($val) {
                return floatval($val);
            },
            preg_split('/[\s-]+/', $data)
        );

        $stack = new \SplStack; // Current work stack
        $sum   = 0; // Running total
        $last  = null;

        foreach ($parts as $part) {
            if (!$stack->isEmpty()) {
                // We're part way through a phrase
                if ($stack->top() > $part) {
                    // Decreasing step, e.g. from hundreds to ones
                    if ($last >= 1000) {
                        // If we drop from more than 1000 then we've finished the phrase
                        $sum += $stack->pop();
                        // This is the first element of a new phrase
                        $stack->push($part);
                    } else {
                        // Drop down from less than 1000, just addition
                        // e.g. "seventy one" -> "70 1" -> "70 + 1"
                        $stack->push($stack->pop() + $part);
                    }
                } else {
                    // Increasing step, e.g ones to hundreds
                    $stack->push($stack->pop() * $part);
                }
            } else {
                // This is the first element of a new phrase
                $stack->push($part);
            }

            // Store the last processed part
            $last = $part;
        }

        return $sum + $stack->pop();
    }

    public static function bindVariablesToString(string $template, array $vars = [])
    {
        return preg_replace_callback('/{(.+?)}/', function ($matches) use ($vars) {
            return Utils::get($vars, $matches[1]) ?? '#null';
        }, $template);
    }

    public static function resolveSubject(string $publicId)
    {
        $resourceMap = [
            'store' => 'store:storefront',
            'product' => 'store:storefront',
            'order' => 'order',
            'customer' => 'contact',
            'contact' => 'contact'
        ];

        list($type) = explode('_', $publicId);

        $modelNamespace = static::getMutationType($resourceMap[$type]);

        if ($modelNamespace) {
            return app($modelNamespace)->where('public_id', $publicId)->first();
        }

        return null;
    }

    public static function unicodeDecode($str)
    {
        $str = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
            return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
        }, $str);

        return $str;
    }

    public static function isUnicodeString($string)
    {
        return is_string($string) && strlen($string) != strlen(utf8_decode($string));
    }

    public static function findDelimiterFromString(?string $string, $fallback = ',')
    {
        if (!is_string($string)) {
            return $fallback;
        }

        $delimiters = ['|', ','];
        $score = [];

        foreach ($delimiters as $delimiter) {
            if (Str::contains($string, $delimiter)) {
                $score[$delimiter] = Str::substrCount($string, $delimiter);
            }
        }

        $result = collect($score)->sortDesc()->keys()->first();

        return $result ?? $fallback;
    }

    /**
     * @param string $path
     * @param string $type
     * @param \Fleetbase\Models\Model $owner
     * @return null|\Fleetbase\Models\File
     */
    public static function urlToStorefrontFile($url, $type = 'source', ?Model $owner = null)
    {
        if (!is_string($url)) {
            return null;
        }

        if (empty($url)) {
            return null;
        }

        if (!Str::startsWith($url, 'http')) {
            return null;
        }

        try {
            $contents = file_get_contents($url);
        } catch (\ErrorException $e) {
            return null;
        }

        $defaultExtensionGuess = '.jpg';

        if (!$contents) {
            return null;
        }

        // parsed path
        $path = urldecode(parse_url($url, PHP_URL_PATH));
        $fileName = basename($path);
        $fileNameInfo = pathinfo($fileName);

        // if no file extension use guess extension
        if (!isset($fileNameInfo['extension'])) {
            $fileName .= $defaultExtensionGuess;
        }

        $bucketPath = 'uploads/storefront/' . $owner->uuid . '/' . Str::slug($type) . '/' . $fileName;
        $pathInfo = pathinfo($bucketPath);

        // upload to bucket
        Storage::disk('s3')->put($bucketPath, $contents, 'public');

        $fileInfo = [
            'company_uuid' => $owner->company_uuid ?? null,
            'uploader_uuid' => $owner->uuid,
            // 'name' => $pathInfo['filename'],
            'original_filename' => $fileName,
            // 'extension' => $pathInfo['extension'],
            'content_type' => File::getFileMimeType($pathInfo['extension']),
            'path' => $bucketPath,
            'bucket' => config('filesystems.disks.s3.bucket'),
            'type' => Str::slug($type, '_'),
            'file_size' => Utils::getBase64ImageSize($contents)
        ];

        if ($owner) {
            $fileInfo['subject_uuid'] = $owner->uuid;
            $fileInfo['subject_type'] = Utils::getMutationType($owner);
        }

        // create file 
        $file = File::create($fileInfo);

        return $file;
    }

    public static function isSubscriptionValidForAction(Request $request): bool
    {
        $company = Company::where('uuid', session('company'))->first();

        if (!$company) {
            return false;
        }

        $guarded = config('api.subscription_required_endpoints');
        $method = strtolower($request->method());
        $endpoint = strtolower(last($request->segments()));

        $current = $method . ':' . $endpoint;

        // if attempting to hit a guarded api check and validate company is subscribed
        if (in_array($current, $guarded)) {
            return $company->subscribed('standard') || $company->onTrial();
        }

        return true;
    }

    /**
     * Returns the name of the queue for events.
     *
     * The queue name is retrieved from the 'SQS_EVENTS_QUEUE' environment variable
     * or falls back to the default 'events' if the variable is not set.
     * Additionally, if the 'QUEUE_URL_EVENTS' environment variable is set,
     * the queue name is extracted from the URL.
     *
     * @return string The name of the queue for events.
     */
    public static function getEventsQueue(): string
    {
        if (!empty(env('AWS_ACCESS_KEY_ID')) && !empty(env('AWS_SECRET_ACCESS_KEY'))) {
            $sqs_events_queue = env('SQS_EVENTS_QUEUE', 'events');

            if ($queueUrl = getenv('QUEUE_URL_EVENTS')) {
                $url = parse_url($queueUrl);
                $sqs_events_queue = basename($url['path']);
            }

            return $sqs_events_queue;
        }

        // Fallback to Redis queuq
        return env('REDIS_QUEUE', 'default');
    }

    /**
     * Chooses the queue connection for the event.
     *
     * If the AWS SQS credentials (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY) and the SQS_EVENTS_QUEUE 
     * environment variable are all set, it will return the value of SQS_EVENTS_QUEUE as the chosen queue connection.
     * If not, it defaults to using the 'redis' connection.
     *
     * @return string The name of the queue connection.
     */
    public static function chooseQueueConnection()
    {
        // AWS SQS
        if (!empty(env('AWS_ACCESS_KEY_ID')) && !empty(env('AWS_SECRET_ACCESS_KEY')) && !empty(env('SQS_EVENTS_QUEUE'))) {
            return env('SQS_EVENTS_QUEUE', 'events');
        }

        // Fallback to Redis Connection
        return 'redis';
    }

    /**
     * Converts a string or class name to an ember resource type \Fleetbase\FleetOps\Models\IntegratedVendor -> integrated-vendor
     * @param string $className
     * @return null|string
     */
    public static function toEmberResourceType($className)
    {
        if (!is_string($className)) {
            return null;
        }

        $baseClassName = static::classBasename($className);
        $emberResourceType = Str::snake($baseClassName, '-');

        return $emberResourceType;
    }

    public static function dateRange($date)
    {
        if (is_string($date) && Str::contains($date, ',')) {
            return static::dateRange(explode(',', $date));
        }

        if (is_array($date)) {
            return array_map(
                function ($dateString) {
                    return Carbon::parse($dateString);
                },
                $date
            );
        }

        // if not valid range just parse as date
        return Carbon::parse($date);
    }

    /**
     * Retrieves the values of a specified key from the "extra" property of all packages
     * with the "fleetbase" key.
     *
     * @param string $key The key to search for in the "extra" property of packages with the "fleetbase" key.
     *
     * @return array An array of values for the specified key from the "extra" property of packages with the "fleetbase" key.
     *
     * @throws \RuntimeException If the installed.json file cannot be found.
     */
    public static function fromFleetbaseExtensions(string $key): array
    {
        $installedJsonPath = realpath(base_path('vendor/composer/installed.json'));

        if (!$installedJsonPath) {
            throw new \RuntimeException('Unable to find the installed.json file.');
        }

        $installedPackages = json_decode(file_get_contents($installedJsonPath), true);
        $fleetbaseExtensions = [];

        if (isset($installedPackages['packages'])) {
            foreach ($installedPackages['packages'] as $package) {
                if (isset($package['extra']['fleetbase']) && isset($package['extra']['fleetbase'][$key])) {
                    $fleetbaseExtensions[] = $package['extra']['fleetbase'][$key];
                }
            }
        }

        return array_values($fleetbaseExtensions);
    }

    /**
     * Retrieves the values of a specified key from the "extra" property for a specific package
     * with the "fleetbase" key.
     *
     * @param string $extension The fleetbase extension to lookup the property for.
     * @param string $key The key to search for in the "extra" property of packages with the "fleetbase" key.
     * @return mixed The value of the key
     *
     * @throws \RuntimeException If the installed.json file cannot be found.
     */
    public static function getFleetbaseExtensionProperty(string $extension, string $key)
    {
        $installedJsonPath = realpath(base_path('vendor/composer/installed.json'));

        if (!$installedJsonPath) {
            throw new \RuntimeException('Unable to find the installed.json file.');
        }

        $installedPackages = json_decode(file_get_contents($installedJsonPath), true);
        $value = null;

        if (isset($installedPackages['packages'])) {
            foreach ($installedPackages['packages'] as $package) {
                if ($package['name'] !== $extension) {
                    continue;
                }

                if (isset($package['extra']['fleetbase']) && isset($package['extra']['fleetbase'][$key])) {
                    $value = $package['extra']['fleetbase'][$key];
                    break;
                }
            }
        }

        return $value;
    }

    /**
     * Retrieves the database name for the Fleetbase connection from the configuration.
     *
     * @return string|null The database name for the Fleetbase connection, or null if not found.
     */
    public static function getFleetbaseDatabaseName(): ?string
    {
        $connection = config('fleetbase.connection.db');
        $databaseName = config('database.connections.' . $connection . '.database');

        return $databaseName;
    }

    /**
     * Find the package namespace for a given path.
     *
     * @param string|null $path The path to search for the package namespace. If null, no namespace is returned.
     * @return string|null The package namespace, or null if the path is not valid.
     */
    public static function findPackageNamespace($path = null): ?string
    {
        if (!$path) {
            return null;
        }

        $packagePath = strstr($path, '/src', true);
        $composerJsonPath = $packagePath . '/composer.json';

        // Load the composer.json file into an array
        $composerJson = json_decode(file_get_contents($composerJsonPath), true);

        // Get the package's namespace from its psr-4 autoloading configuration
        $namespace = null;
        if (isset($composerJson['autoload']['psr-4'])) {
            foreach ($composerJson['autoload']['psr-4'] as $ns => $dir) {
                if (strpos($dir, 'src') !== false) {
                    $namespace = rtrim($ns, '\\');
                    break;
                }
            }
        }

        return $namespace;
    }

    /**
     * Search installed composer packages for a specified keyword within their keywords array.
     * The function reads the composer.lock file, which includes the exact versions of installed packages.
     * If the keyword is found, the package's information is added to the result array.
     *
     * @param string $keyword The keyword to search for within the packages' keywords array.
     * 
     * @throws Exception If the composer.lock file does not exist or if packages are not defined in it.
     *
     * @return array An associative array of packages that contain the keyword in their keywords array.
     *               The keys are the package names, and the values are the corresponding composer.json information.
     */
    public static function findComposerPackagesWithKeyword($keyword = 'fleetbase-extension')
    {
        // Path to composer.lock file.
        $filePath = base_path('composer.lock');

        // Check if file exists.
        if (!file_exists($filePath)) {
            throw new \Exception('composer.lock file does not exist');
        }

        // Read composer.lock content.
        $fileContent = file_get_contents($filePath);
        $composerData = json_decode($fileContent, true);

        // Check if packages are defined.
        if (!isset($composerData['packages'])) {
            throw new \Exception('Packages are not defined in the composer.lock file');
        }

        $foundPackages = [];
        $packages = array_values($composerData['packages']);

        // Loop through packages.
        foreach ($packages as $package) {
            // Check if keywords array exists and contains the keyword.
            if (isset($package['keywords']) && in_array($keyword, $package['keywords'])) {
                // If package contains the keyword in its keywords array, add it to the result array.
                $foundPackages[$package['name']] = $package;
            }
        }

        return $foundPackages;
    }

    /**
     * Get installed Fleetbase extensions.
     *
     * @return array
     */
    public static function getInstalledFleetbaseExtensions()
    {
        return static::findComposerPackagesWithKeyword('fleetbase-extension');
    }

    /**
     * Retrieves directories containing seeders from installed Fleetbase extensions.
     *
     * This function first gets all installed Fleetbase extensions. Then it iterates over them
     * and checks for a 'seeds' directory within each one. If it exists, the directory path
     * is added to an array. The function finally returns this array of migration directories.
     *
     * @return array The array containing the paths to seeder directories of all installed Fleetbase extensions.
     *
     * @throws \RuntimeException if an error occurs during directory retrieval.
     */
    public static function getSeederClassesFromExtensions(): array
    {
        $packages = static::getInstalledFleetbaseExtensions();
        $seederClasses = [];

        foreach ($packages as $packageName => $package) {
            // Derive the seeds directory path
            $seedsDirectory = base_path('vendor/' . $packageName . '/seeds');

            // Check if the seeds directory exists
            if (!is_dir($seedsDirectory)) {
                continue;
            }

            // Get all PHP files in the seeds directory
            $files = glob($seedsDirectory . '/*.php');

            // Find the namespace that corresponds to the seeds directory
            $namespace = static::getNamespaceFromAutoload($package['autoload']['psr-4'], 'seeds');

            foreach ($files as $file) {
                // Get the base name of the file, and remove the .php extension to get the class name
                $className = basename($file, '.php');

                // Combine the namespace and class name to get the fully qualified class name
                $seederClasses[] = $namespace . '\\' . $className;
            }
        }

        return $seederClasses;
    }

    /**
     * Get directories containing seed files from Fleetbase extensions installed in the project.
     *
     * This method iterates over all packages installed in the project and identified as Fleetbase extensions.
     * For each extension, it identifies the "seeds" directory, fetches all PHP files in it, and maps these files
     * to their fully qualified class names based on the PSR-4 autoload configuration in the extension's composer.json.
     * The resulting array contains the fully qualified class names and the full paths to the corresponding PHP files.
     *
     * @return array Each item is an associative array with two keys:
     *               'class' => the fully qualified class name of a seeder,
     *               'path' => the full path to the PHP file of the seeder.
     *
     * @throws \Exception if the composer.lock file does not exist or does not contain packages data.
     */
    public static function getSeedersFromExtensions(): array
    {
        $packages = static::getInstalledFleetbaseExtensions();
        $seederClasses = [];

        foreach ($packages as $packageName => $package) {
            // Derive the seeds directory path
            $seedsDirectory = base_path('vendor/' . $packageName . '/seeds');

            // Check if the seeds directory exists
            if (!is_dir($seedsDirectory)) {
                continue;
            }

            // Get all PHP files in the seeds directory
            $files = glob($seedsDirectory . '/*.php');

            // Find the namespace that corresponds to the seeds directory
            $namespace = static::getNamespaceFromAutoload($package['autoload']['psr-4'], 'seeds');

            foreach ($files as $file) {
                // Get the base name of the file, and remove the .php extension to get the class name
                $className = basename($file, '.php');

                // Combine the namespace and class name to get the fully qualified class name
                $seederClasses[] = [
                    'class' => $namespace . '\\' . $className,
                    'path' => $file,
                ];
            }
        }

        return $seederClasses;
    }

    /**
     * Determines the namespace of a given directory from a given PSR-4 autoload configuration.
     *
     * @param array $psr4 The PSR-4 autoload configuration, mapping namespace prefixes to directories.
     * @param string $directory The directory whose corresponding namespace should be returned.
     *
     * @return string|null The namespace corresponding to the given directory in the autoload configuration,
     * or null if no such namespace exists.
     */
    private static function getNamespaceFromAutoload(array $psr4, string $directory): ?string
    {
        foreach ($psr4 as $namespace => $path) {
            if (strpos($path, $directory) !== false) {
                // Remove trailing backslashes from the namespace
                $namespace = rtrim($namespace, '\\');
                return $namespace;
            }
        }

        return null;
    }

    /**
     * Retrieves directories containing migrations from installed Fleetbase extensions.
     *
     * This function first gets all installed Fleetbase extensions. Then it iterates over them
     * and checks for a 'migrations' directory within each one. If it exists, the directory path
     * is added to an array. The function finally returns this array of migration directories.
     *
     * @return array The array containing the paths to migration directories of all installed Fleetbase extensions.
     *
     * @throws \RuntimeException if an error occurs during directory retrieval.
     */
    public static function getMigrationDirectories(): array
    {
        $packages = static::getInstalledFleetbaseExtensions();
        $directories = [];

        foreach ($packages as $packageName => $package) {
            $migrationDirectory = base_path('vendor/' . $packageName . '/migrations/');

            if (file_exists($migrationDirectory)) {
                $directories[] = $migrationDirectory;
            }
        }

        return $directories;
    }

    /**
     * Retrieves the migration directory for a specific Fleetbase extension.
     *
     * This function first gets all installed Fleetbase extensions. Then it iterates over them
     * until it finds the specified extension. If the extension is found, the function constructs
     * the path to its 'migrations' directory and returns this path.
     *
     * @param string $extension The name of the Fleetbase extension for which the migration directory is to be retrieved.
     *
     * @return string|null The path to the migration directory of the specified Fleetbase extension, or null if the extension is not found.
     *
     * @throws \RuntimeException if an error occurs during directory retrieval.
     */
    public static function getMigrationDirectoryForExtension(string $extension): ?string
    {
        $packages = static::getInstalledFleetbaseExtensions();
        $migrationDirectory = null;

        foreach ($packages as $packageName => $package) {
            if ($packageName !== $extension) {
                continue;
            }

            $migrationDirectory = base_path('vendor/' . $packageName . '/migrations/');
            break;
        }

        return $migrationDirectory;
    }

    /**
     * Get the namespaced names of the authentication schemas found in the installed Fleetbase extensions.
     *
     * @return array
     */
    public static function getAuthSchemaNamespaces()
    {
        $packages = static::getInstalledFleetbaseExtensions();
        $authSchemaClasses = [];

        // Local package directory
        $localNamespace = 'Fleetbase\\';
        $localPackageSrcDirectory = base_path('vendor/fleetbase/core-api/src/');
        $localPackageDirectoryPath = $localPackageSrcDirectory . 'Auth/Schemas';

        if (file_exists($localPackageDirectoryPath)) {
            $localDirectoryIterator = new \DirectoryIterator($localPackageDirectoryPath);

            foreach ($localDirectoryIterator as $file) {
                if ($file->isFile() && $file->getExtension() == 'php') {
                    $className = 'Auth\\Schemas\\' . $file->getBasename('.php');
                    $authSchemaClasses[] = $localNamespace . $className;
                }
            }
        }

        foreach ($packages as $packageName => $package) {
            $srcDirectory = base_path('vendor/' . $packageName . '/src/');

            if (!isset($package['autoload']['psr-4'])) {
                continue;
            }

            foreach ($package['autoload']['psr-4'] as $namespace => $directory) {
                $directoryPath = $srcDirectory . 'Auth/Schemas';

                // try path with namespace
                if (!file_exists($directoryPath)) {
                    $directoryPath = $srcDirectory . str_replace('\\', '/', $namespace) . 'Auth/Schemas';
                }

                if (!file_exists($directoryPath)) {
                    continue;
                }

                $directoryIterator = new \DirectoryIterator($directoryPath);

                foreach ($directoryIterator as $file) {
                    if ($file->isFile() && $file->getExtension() == 'php') {
                        $className = $namespace . 'Auth\\Schemas\\' . $file->getBasename('.php');
                        $authSchemaClasses[] = $className;
                    }
                }
            }
        }

        return array_values($authSchemaClasses);
    }

    /**
     * Get the authentication schemas instances from the installed Fleetbase extensions.
     *
     * @return array
     */
    public static function getAuthSchemas()
    {
        $namespaces = static::getAuthSchemaNamespaces();

        return array_map(
            function ($schema) {
                return app($schema);
            },
            $namespaces
        );
    }

    /**
     * UTF-8 aware parse_url() replacement.
     * 
     * @return array
     */
    public static function parseUrl($url)
    {
        $parts = parse_url($url);

        if ($parts === false) {
            throw new \InvalidArgumentException('Malformed URL: ' . $url);
        }

        foreach ($parts as $name => $value) {
            $parts[$name] = urldecode($value);
        }

        return $parts;
    }

    /**
     * Get the default "from" email address.
     *
     * This method retrieves the default "from" email address from the environment variable 'MAIL_FROM_ADDRESS'.
     * If that's not set, it constructs the email address using the 'CONSOLE_HOST' environment variable.
     * If neither is available, it uses the server's IP address.
     *
     * @return string The default "from" email address.
     */
    public static function getDefaultMailFromAddress(?string $default = 'hello@fleetbase.io'): string
    {
        $from = env('MAIL_FROM_ADDRESS', $default);

        if (!$from && env('CONSOLE_HOST')) {
            $from = 'hello@' . Str::domain(env('CONSOLE_HOST'));
        }

        if (!$from && is_string($default)) {
            return $default;
        }

        if (!$from) {
            $from = 'hello@' . \Illuminate\Support\Facades\Request::server('SERVER_ADDR');
        }

        return $from;
    }
}
