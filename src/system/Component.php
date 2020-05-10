<?php

namespace NovemBit\wp\plugins\spm\system;


abstract class Component
{

    /**
     * @var self
     * */
    public $parent;

    /**
     * @var array
     * @return array
     */
    protected static function components(): array
    {
        return [];
    }

    public function __construct(self $parent = null)
    {
        $this->parent = $parent;

        // foreach(static::components() as $component=>$class){
        //     $this->{$component} = new $class($this);
        // }

        $this->init();
    }

    public function __get($name)
    {
        $component = static::components()[$name] ?? null;
        if (
            $component !== null
            && is_subclass_of($component, self::class)
        ) {
            $this->{$name} = new $component($this);
        }

        return $this->{$name};
    }


    abstract protected function init(): void;
}