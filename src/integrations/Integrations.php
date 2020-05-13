<?php

namespace NovemBit\wp\plugins\spm\integrations;

use diazoxide\wp\lib\option\v2\Option;
use Exception;
use NovemBit\wp\plugins\spm\Bootstrap;
use NovemBit\wp\plugins\spm\integrations\brandlight\Brandlight;
use NovemBit\wp\plugins\spm\integrations\novembit\I18n;
use NovemBit\wp\plugins\spm\system\Component;

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

    private $integrations = [
        'brandlight' => Brandlight::class,
        'i18n' => I18n::class,
    ];

    /**
     * @var array
     * */
    private $settings;

    /**
     * @var array
     * */
    private $config;

    public function __construct(Bootstrap $parent)
    {
        $this->parent = $parent;

        foreach ($this->integrations as $key => $class) {
            $this->settings['integrations'][$key] =
                new Option(
                    [
                        'type' => Option::TYPE_BOOL,
                        'label' => $class::NAME,
                        'description' => 'Enable/Disable integration'
                    ]
                );
        }

        $this->config = Option::expandOptions(
            $this->settings,
            $this->getName(),
            ['serialize' => true, 'single_option' => true]
        );

        foreach ($this->config['integrations'] as $integration => $status) {
            if ($status === true) {
                $class = $this->integrations[$integration] ?? null;
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
            $this->getName(),
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
            $this->getName(),
            $this->settings,
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
    public function getName(): string
    {
        return $this->parent::getName() . '-integrations';
    }

}