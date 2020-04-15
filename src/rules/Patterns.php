<?php


namespace NovemBit\wp\plugins\spm\rules;


use diazoxide\helpers\Variables;
use diazoxide\wp\lib\option\v2\Option;

class Patterns
{

    /**
     * @var Rules
     * */
    public $parent;

    /**
     * @var array
     * */
    public $settings;

    /**
     * @var array
     * */
    public $config;

    /**
     * Patterns constructor.
     * @param Rules $parent
     */
    public function __construct(Rules $parent)
    {
        $this->parent = $parent;

        $this->settings = [
            'patterns' => new Option(
                [
                    'default' => [],
                    'method' => Option::METHOD_MULTIPLE,
                    'type' => Option::TYPE_GROUP,
                    'values' => [],
                    'main_params' => ['style' => 'grid-template-columns: repeat(1, 1fr);'],
                    'template' => $this->parent::getRulesSettings(true),
                    'label' => 'Rules'
                ]
            ),
        ];

        $this->config = Option::expandOptions($this->settings, $this->getName());

        if (is_admin()) {
            $this->adminInit();
        }
    }

    public function getPatterns(): array
    {
        return $this->config['patterns'] ?? [];
    }

    public function getPatternsList()
    {
        $list = [];
        foreach ($this->getPatterns() as $pattern){
            $list[] = $pattern['name'];
        }
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->parent->getName() . '-patterns';
    }

    /**
     * @return void
     * @uses adminMenu
     */
    public function adminInit(): void
    {
        $this->parent->tabs['patterns'] = ['label' => 'Patterns', 'content' => [$this, 'tabContent']];
    }

    public function tabContent()
    {
        Option::printForm($this->getName(), $this->settings);
    }
}