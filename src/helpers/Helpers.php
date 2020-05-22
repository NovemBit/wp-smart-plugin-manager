<?php

namespace NovemBit\wp\plugins\spm\helpers;

use diazoxide\helpers\Environment;
use NovemBit\wp\plugins\spm\Bootstrap;

class Helpers
{
    /**
     * @var Bootstrap
     * */
    public $parent;

    private $helpers = [
        'RequestPath',
        'RequestUri'
    ];

    /**
     * @return string
     */
    public static function getName(): string
    {
        return Bootstrap::getName() . '-helpers';
    }

    /**
     * @param string $helper
     * @return string
     */
    public function getHookName(string $helper): string
    {
        return self::getName() . '-' . $helper;
    }

    public function run():void
    {
        foreach ($this->helpers as $helper) {
            add_filter(
                $this->getHookName($helper),
                [$this, 'callback' . $helper]
            );
        }
    }

    /**
     * @return string
     */
    public function callbackRequestPath():string
    {
        return parse_url(Environment::server('REQUEST_URI'), PHP_URL_PATH);
    }

    /**
     * @return string
     */
    public function callbackRequestUri():string
    {
        return Environment::server('REQUEST_URI');
    }

    /**
     * Helpers constructor.
     * @param Bootstrap $parent
     */
    public function __construct(Bootstrap $parent)
    {
        $this->parent = $parent;
        // TODO: Implement init() method.
    }
}