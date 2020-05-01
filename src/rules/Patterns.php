<?php


namespace NovemBit\wp\plugins\spm\rules;


use diazoxide\helpers\Arrays;
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
                    'main_params' => ['col' => '2'],
                    'template' => [
                        'name' => [
                            'label' => 'Name',
                            'type' => Option::TYPE_TEXT,
                            'required' => true,
                            'main_params' => ['col' => 2]
                        ],
                        'label' => [
                            'label' => 'Label',
                            'type' => Option::TYPE_TEXT,
                            'required' => true,
                            'main_params' => ['col' => 2]
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
            'wp-lib-option/' . $this->getName() . '/expanded-option',
            function ($config) {
                self::overwritePatterns($config['patterns'], $this->predefinedPatterns());
                self::overwritePatterns($config['patterns'], $this->getRegistered());
                return $config;
            }
        );


        $this->config = Option::expandOptions($this->settings, $this->getName(), ['serialize' => true]);

        if (is_admin()) {
            $this->adminInit();
        }
    }

    public function predefinedPatterns(): array
    {
        $generated_patterns = [
            // Is Backend
            [
                'name' => 'backend',
                'label' => 'Backend',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => $this->parent::TYPE_FUNCTION,
                                        'key' => 'is_admin',
                                        'compare' => Variables::COMPARE_EQUAL,
                                        'value' => '1',
                                        'logic' => $this->parent::LOGIC_AND,
                                    ],
                                ],
                            'logic' => $this->parent::LOGIC_AND,
                        ],
                    ],
            ],
            // Is frontend
            [
                'name' => 'frontend',
                'label' => 'Frontend',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => $this->parent::TYPE_FUNCTION,
                                        'key' => 'defined',
                                        'compare' => Variables::COMPARE_EQUAL,
                                        'value' => '0',
                                        'params' => [
                                            'WP_ADMIN'
                                        ],
                                        'logic' => $this->parent::LOGIC_AND,
                                    ],
                                ],
                            'logic' => $this->parent::LOGIC_AND,
                        ],
                    ],
            ],
            [
                'name' => 'wp_doing_ajax',
                'label' => 'WP Ajax request',
                'rules' =>
                    [
                        [
                            'rule' =>
                                [
                                    [
                                        'type' => $this->parent::TYPE_FUNCTION,
                                        'key' => 'wp_doing_ajax',
                                        'compare' => Variables::COMPARE_EQUAL,
                                        'value' => '1',
                                        'logic' => $this->parent::LOGIC_AND,
                                    ],
                                ],
                            'logic' => $this->parent::LOGIC_AND,
                        ],
                    ],
            ]
        ];

        if (($this->parent->config['common']['include_wp_rewrite_rules_as_patterns'] ?? false)) {
            $rewrite_rules = get_option('rewrite_rules', []);
            foreach ($rewrite_rules as $rule => $rewrite) {
                $generated_patterns[] = [
                    'name' => $rule,
                    'label' => 'RR: ' . $rule,
                    'rules' =>
                        [
                            [
                                'rule' =>
                                    [
                                        [
                                            'type' => $this->parent::TYPE_HOOK,
                                            'key' => $this->parent->parent->helpers->getHookName('RequestPath'),
                                            'compare' => Variables::COMPARE_REGEXP,
                                            'value' => '#' . $rule . '#',
                                            'logic' => $this->parent::LOGIC_AND
                                        ],
                                    ],
                                'logic' => $this->parent::LOGIC_AND,
                            ],
                        ],
                ];
            }
        }

        return apply_filters($this->getName() . '-predefined', $generated_patterns);
    }

    /**
     * @param array $patterns
     */
    public function register(array $patterns): void
    {
        $registered_patterns = Option::getOption('registered_patterns', $this->getName(), []);
        self::overwritePatterns($registered_patterns, $patterns);
        Option::setOption('registered_patterns', $this->getName(), $registered_patterns, true);
    }

    /**
     * @return array
     */
    public function getRegistered(): array
    {
        return Option::getOption('registered_patterns', $this->getName(), [], true);
    }

    /**
     * @param string $name
     */
    public function removeRegistered(string $name): void
    {
        $registered_patterns = Option::getOption('registered_patterns', $this->getName(), []);

        $index = Arrays::ufind($registered_patterns, 'name', $name);

        unset($registered_patterns[$index]);

        Option::setOption('registered_patterns', $this->getName(), $registered_patterns, true);
    }

    /**
     * @param array $patterns
     * @param array $predefined_patterns
     * @return void
     */
    public static function overwritePatterns(array &$patterns, array $predefined_patterns): void
    {
        foreach ($predefined_patterns as $predefined_pattern) {
            $name = $predefined_pattern['name'] ?? null;
            if ($name !== null) {
                $existing_rule = Arrays::ufind(
                    $patterns,
                    $name,
                    'name'
                );
                if ($existing_rule === null) {
                    unset($patterns[$existing_rule]);
                    $patterns[] = $predefined_pattern;
                }
            }
        }
    }

    /**
     * @param array $patterns
     * @return bool
     */
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

    /**
     * @param $name
     * @return mixed|null
     */
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

    /**
     * @return array
     */
    public function getPatterns(): array
    {
        return $this->config['patterns'] ?? [];
    }

    /**
     * @return array
     */
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
        add_action('admin_menu', [$this, 'adminMenu'], 11);
    }

    /**
     * @var void
     * @uses tabContent
     */
    public function adminMenu(): void
    {
        $this->parent->tabs['patterns'] = ['label' => 'Patterns', 'content' => [$this, 'tabContent']];
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function tabContent(): void
    {
        Option::printForm(
            $this->getName(),
            $this->settings,
            [
                'wrap_params' => ['style' => 'width:100%;max-width:calc( 100% - 20px );'],
                'serialize' => true
            ]
        );
    }

}