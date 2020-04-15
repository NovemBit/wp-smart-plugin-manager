<?php


namespace NovemBit\wp\plugins\spm\rules;


use diazoxide\helpers\Arrays;
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
                    'main_params' => ['style' => 'grid-template-columns: repeat(1, 1fr);display:grid'],
                    'template' => [
                        'name' => [
                            'label' => 'Name',
                            'type' => Option::TYPE_TEXT,
                            'required' => true,
                        ],
                        'label' => [
                            'label' => 'Label',
                            'type' => Option::TYPE_TEXT,
                            'required' => true,
                        ],
                        'rules' => [
                            'main_params' => ['style' => 'grid-template-columns: repeat(2, 1fr);display:grid'],
                            'type' => Option::TYPE_GROUP,
                            'method' => Option::METHOD_MULTIPLE,
                            'template' => $this->parent::getRulesSettings()
                        ]
                    ],
                    'label' => 'Rules'
                ]
            ),
        ];

        add_filter(
            Option::getOptionFilterName('patterns', $this->getName()),
            [$this, 'predefinedPatterns']
        );

        $this->config = Option::expandOptions($this->settings, $this->getName());

        if (is_admin()) {
            $this->adminInit();
        }
    }

    public function predefinedPatterns(array $patterns): array
    {
        $predefined_patterns = [
            [
                'name' => 'is_admin',
                'label' => 'Is Administrator',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'function',
                                        'key' => 'is_admin',
                                        'compare' => 'equal',
                                        'value' => '1',
                                        'logic' => 'and',
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ],
            [
                'name' => 'is_front',
                'label' => 'Is Frontend',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => 'function',
                                        'key' => 'is_admin',
                                        'compare' => 'equal',
                                        'value' => '0',
                                        'logic' => 'and',
                                    ],
                                ],
                            'logic' => 'and',
                        ],
                    ],
            ]
        ];

        foreach ($predefined_patterns as $predefined_pattern) {
            $name = $predefined_pattern['name'] ?? null;
            if ($name !== null) {
                $existing_rule = Arrays::ufind(
                    $patterns,
                    $name,
                    'name',
                    null,
                    static function ($a, $b) {
                        return $a === $b;
                    }
                );
                if ($existing_rule === null) {
                    $patterns[] = $predefined_pattern;
                }
            }
        }
        return $patterns;
    }

    public function checkPatterns(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = $this->getPattern($pattern);


            if (isset($pattern['rules']) && $this->parent->checkRules($pattern['rules'])) {
                return true;
            }
        }

        return false;
    }

    public function getPattern($name)
    {
        return Arrays::ufind(
            $this->getPatterns(),
            $name,
            'name',
            null,
            static function ($a, $b) {
                return $a === $b;
            }
        );
    }

    public function getPatterns(): array
    {
        return $this->config['patterns'] ?? [];
    }

    public function getPatternsMap(): array
    {
        $list = [];
        foreach ($this->getPatterns() as $pattern) {
            if (isset($pattern['name'])) {
                $list[$pattern['name']] = $pattern['label'] ?? $pattern['name'];
            }
        }

        return $list;
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