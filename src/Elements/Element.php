<?php

namespace Kompo\Elements;

use BadMethodCallException;

abstract class Element
{
    use Traits\HasId, 
        Traits\HasClasses, 
        Traits\HasInternalConfig, 
        Traits\HasConfig, 
        Traits\HasStyles, 
        Traits\HasAnimation, 
        Traits\HasDuskSelector,
        Traits\IsMountable, 
        Traits\ElementHelperMethods;
    
    /**
     * The related Vue component name.
     *
     * @var string
     */
    public $vueComponent;

    /**
     * Prepares an element and passes important information for display.
     */
    abstract public function prepareForDisplay($komposer);

    /**
     * Prepares an element and passes important information for submit.
     */
    abstract public function prepareForAction($komposer);

    /**
     * A helpful way to construct a Kompo object and chain it additional methods.
     * ! Works for Komposers too - because ... handles variable # of arguments.
     *
     * @param  mixed $arguments
     * @return void
     */
    public static function form(...$arguments)
    {
        return new static(...$arguments);
    }
    

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        if(in_array($method, static::duplicateStaticMethods())){
            $method .= 'Static';
            return static::$method(...$parameters);
        }
        throw new BadMethodCallException('Method '.static::class.'::'.$method.' does not exist.');
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if(in_array($method, static::duplicateStaticMethods())){
            $method .= 'NonStatic';
            return $this->$method(...$parameters);
        }
        throw new BadMethodCallException('Method '.static::class.'::'.$method.' does not exist.');
    }

    /**
     * Methods that can be called both statically or non-statically
     *
     * @return array
     */
    public static function duplicateStaticMethods()
    {
        return [];
    }

    /**
     * Displays the rendered Vue component.
     * Mostly, useful when echoing in blade for example.
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this);
    }
}