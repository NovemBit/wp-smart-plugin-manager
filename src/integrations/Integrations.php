<?php

namespace NovemBit\wp\plugins\spm\integrations;

use diazoxide\wp\lib\option\v2\Option;
use Exception;
use NovemBit\wp\plugins\spm\Bootstrap;
use NovemBit\wp\plugins\spm\integrations\brandlight\Brandlight;
use NovemBit\wp\plugins\spm\integrations\novembit\I18n;

/**
 * @property I18n $i18n
 * @property Brandlight $brandlight
 * */
class Integrations
{

    /**
     * @var Bootstrap
     */
    public $parent;

    /**
     * @var I18n
     * */
    public $i18n;

    /**
     * @var string[]
     */
    private static $integrations = [
        'brandlight' => Brandlight::class,
        'i18n' => I18n::class,
    ];


    /**
     * @var array
     * */
    private static $config;

    /**
     * @var
     */
    private static $settings;

    /**
     * @return array
     */
    private static function getConfig():array{
        if(!isset(self::$config)){
            self::$config = Option::expandOptions(
                self::getSettings(),
                self::getName(),
                ['serialize' => true, 'single_option' => true]
            );
        }
        return self::$config;
    }

    /**
     * @return array
     */
    private static function getSettings():array{
        if(!isset(self::$settings)){
            self::$settings = [];
            foreach (self::$integrations as $key => $class) {
                self::$settings['integrations'][$key] =
                    new Option(
                        [
                            'type' => Option::TYPE_BOOL,
                            'label' => $class::NAME,
                            'description' => 'Enable/Disable integration'
                        ]
                    );
            }
        }
        return self::$settings;
    }

    public function __construct(Bootstrap $parent)
    {
        $this->parent = $parent;

        foreach (self::getConfig()['integrations'] as $integration => $status) {
            if ($status === true) {
                $class = self::$integrations[$integration] ?? null;
                if ($class !== null) {
                    $this->{$integration} = new $class($this);
                }
            }
        }

        if (is_admin()) {
            $this->adminInit();
        }
    }

    /**
     * @return void
     * @uses adminMenu
     */
    public function adminInit(): void
    {
        add_action('admin_menu', [$this, 'adminMenu'],11);
    }

    /**
     * @return void
     * @uses adminContent
     */
    public function adminMenu(): void
    {
        add_submenu_page(
            $this->parent::getName(),
            __('Integrations', 'novembit-spm'),
            __('Integrations', 'novembit-spm'),
            'manage_options',
            self::getName(),
            [$this, 'adminContent']
        );
    }

    /**
     * Admin Content
     * @return void
     * @throws Exception
     */
    public function adminContent(): void
    {
        Option::printForm(
            self::getName(),
            self::getSettings(),
            [
                'title' => 'Smart Plugin Manager - Integrations',
                'ajax_submit' => true,
                'auto_submit' => true,
                'serialize' => true,
                'single_option' => true
            ]
        );
    }

    /**
     * @return string
     */
    public static function getName(): string
    {
        return Bootstrap::getName() . '-integrations';
    }

}