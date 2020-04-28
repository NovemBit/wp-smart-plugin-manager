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
                $this->overwritePatterns($config['patterns'], $this->predefinedPatterns());
                //$this->overwritePatterns($config['patterns'], $this->getRegistered());
                $this->overwritePatterns($config['patterns'], [
                    [
                        'label' => 'BL: pages/homepage',
                        'name'  => 'bl_pages/homepage',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'equal',
                                                'value'   => '/',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: post/docs',
                        'name'  => 'bl_post/docs',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#^\/?(docs|dc|d)($|(\/.*))#',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ],
                                                'logic'   => 'and',
                                            ]
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: ajax/adsl_get_fragments',
                        'name'  => 'bl_ajax/adsl_get_fragments',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'defined',
                                                'compare' => 'equal',
                                                'value'   => '1',
                                                'params'  => [ 'DOING_AJAX' ],
                                                'logic'   => 'and',
                                            ],
                                            [
                                                'type'    => 'request',
                                                'key'     => 'action',
                                                'compare' => 'equal',
                                                'value'   => 'adsl_get_fragments',
                                                'params'  => [],
                                                'logic'   => 'and',
                                            ]
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: ajax/woocommerce_get_refreshed_fragments',
                        'name'  => 'bl_ajax/woocommerce_get_refreshed_fragments',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'defined',
                                                'compare' => 'equal',
                                                'value'   => '1',
                                                'params'  => [ 'DOING_AJAX' ],
                                                'logic'   => 'and',
                                            ],
                                            [
                                                'type'    => 'request',
                                                'key'     => 'action',
                                                'compare' => 'equal',
                                                'value'   => 'woocommerce_get_refreshed_fragments',
                                                'params'  => [],
                                                'logic'   => 'and',
                                            ]
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: ajax/get_refreshed_fragments',
                        'name'  => 'bl_ajax/get_refreshed_fragments',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'request',
                                                'key'     => 'wc-ajax',
                                                'compare' => 'equal',
                                                'value'   => 'get_refreshed_fragments',
                                                'params'  => [],
                                                'logic'   => 'and',
                                            ]
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: post/wiki',
                        'name'  => 'bl_post/wiki',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#^\/?(wiki|wc|w)($|(\/.*))#',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: url/shop/*',
                        'name'  => 'bl_url/shop/*',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'starts_with',
                                                'value'   => '/shop/',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: post/info',
                        'name'  => 'bl_post/info',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#^\/?(info|i)($|(\/.*))#',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: post/articles/archive',
                        'name'  => 'bl_post/articles/archive',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#^\/?(article|ac)($|(\/.*))#',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: post/help',
                        'name'  => 'bl_post/help',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#^\/?(help|hc|h)($|(\/.*))#',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: wp-admin/product-list',
                        'name'  => 'bl_wp-admin/product-list',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'starts_with',
                                                'value'   => '/wp-admin/edit.php?post_type=product',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: pages/product',
                        'name'  => 'bl_pages/product',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#^\/?(p)($|(\/.*))#',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: wp-admin/uxbuilder',
                        'name'  => 'bl_wp-admin/uxbuilder',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#^\/?((wp-admin/edit\.php\?page=uxbuilder)|(post\.php\?post=asd.*?app=uxbuilder))#',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: pages/frontend',
                        'name'  => 'bl_pages/frontend',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#^\/?(wp-admin|wp-cron\.php|wp-json)($|(\/.*))#',
                                                'logic'   => 'not',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],


                    [
                        'label' => 'BL: pages/archive',
                        'name'  => 'bl_pages/archive',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#/shop/$#',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: pages/my-account',
                        'name'  => 'bl_pages/my-account',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#/my-account/?$#',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: pages/shopping-lists',
                        'name'  => 'bl_pages/shopping-lists',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#/shopping-lists/?$#',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: pages/contact-us',
                        'name'  => 'bl_pages/contact-us',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#/contact-us/?$#',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: pages/cart',
                        'name'  => 'bl_pages/cart',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#/shop/basket/?$#',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                    [
                        'label' => 'BL: pages/checkout',
                        'name'  => 'bl_pages/checkout',
                        'rules' =>
                            [
                                [
                                    'rule'  =>
                                        [
                                            [
                                                'type'    => 'function',
                                                'key'     => 'parse_url',
                                                'compare' => 'regexp',
                                                'value'   => '#/shop/checkout/?$#',
                                                'logic'   => 'and',
                                                'params'  => [
                                                    '{{$_SERVER->REQUEST_URI}}',
                                                    '{{@PHP_URL_PATH}}'
                                                ]
                                            ],
                                        ],
                                    'logic' => 'and',
                                ],
                            ],
                    ],
                ]);

                return $config;
            }
        );


        $this->config = Option::expandOptions($this->settings, $this->getName());

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
                                            'type' => $this->parent::TYPE_FUNCTION,
                                            'key' => 'parse_url',
                                            'compare' => Variables::COMPARE_REGEXP,
                                            'value' => '#' . $rule . '#',
                                            'logic' => $this->parent::LOGIC_AND,
                                            'params' => ['{{$_SERVER->REQUEST_URI}}', '{{@PHP_URL_PATH}}']
                                        ],
                                    ],
                                'logic' => $this->parent::LOGIC_AND,
                            ],
                        ],
                ];
            }
        }
        return $generated_patterns;
    }

    /**
     * @param array $patterns
     */
    public function register(array $patterns): void
    {
        $registered_patterns = Option::getOption('registered_patterns', $this->getName(), []);
        $this->overwritePatterns($registered_patterns, $patterns);
        Option::setOption('registered_patterns', $this->getName(), $registered_patterns);
    }

    public function getRegistered(): array
    {
        return Option::getOption('registered_patterns', $this->getName(), []);
    }

    public function removeRegistered($name)
    {
    }

    /**
     * @param array $patterns
     * @param array $predefined_patterns
     * @return void
     */
    public function overwritePatterns(array &$patterns, array $predefined_patterns): void
    {
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
     */
    public function tabContent(): void
    {
        Option::printForm(
            $this->getName(),
            $this->settings,
            [
                'wrap_params' => ['style' => 'width:100%;max-width:calc( 100% - 20px );']
            ]
        );
    }

}