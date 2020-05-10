<?php

namespace NovemBit\wp\plugins\spm\helpers;

use diazoxide\helpers\Environment;
use NovemBit\wp\plugins\spm\Bootstrap;
use NovemBit\wp\plugins\spm\system\Component;

class Helpers extends Component
{
    /**
     * @var Bootstrap
     * */
    public $parent;

    private $helpers = [
        'RequestPath',
        'RequestUri'
    ];

    public static function getName(): string
    {
        return Bootstrap::getName() . '-helpers';
    }

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

    public function callbackRequestPath():string
    {
        return parse_url(Environment::server('REQUEST_URI'), PHP_URL_PATH);
    }

    public function callbackRequestUri():string
    {
        return Environment::server('REQUEST_URI');
    }

    protected function init(): void
    {
        // TODO: Implement init() method.
    }
}