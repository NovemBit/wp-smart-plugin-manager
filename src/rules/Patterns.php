<?php


namespace NovemBit\wp\plugins\spm\rules;


use diazoxide\helpers\Arrays;
use diazoxide\helpers\Environment;
use diazoxide\helpers\HTML;
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
            function ($patterns) {
                return $this->overwritePredefinedPatterns($patterns, $this->predefinedPatterns());
            }
        );


        if (($this->parent->config['common']['auto_pattern_generation_tracker'] ?? false)) {
            add_action('shutdown', [$this, 'initAutoPatternGenerationTracker']);
        }


        $this->config = Option::expandOptions($this->settings, $this->getName());

        if (is_admin()) {
            $this->adminInit();
        }
    }

    public function predefinedPatterns()
    {
        return [
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
            ]
        ];
    }

    /**
     * @param array $patterns
     * @param array $predefined_patterns
     * @return array
     */
    public function overwritePredefinedPatterns(array $patterns, array $predefined_patterns): array
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

        return $patterns;
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
     */
    public function adminMenu(): void
    {
        $this->parent->tabs['patterns'] = ['label' => 'Patterns', 'content' => [$this, 'tabContent']];
    }

    /**
     * @param $url
     */
    public function tabContent($url): void
    {
        $this->initPatternsGenerator();

        Option::printForm(
            $this->getName(),
            $this->settings,
            [
                'wrap_params' => ['style' => 'width:100%;max-width:calc( 100% - 20px );']
            ]
        );
    }

    private function initPatternsGenerator(): void
    {
        if (wp_verify_nonce(Environment::post($this->getName()), 'generate')) {
            global $wp_rewrite;
            $rewrite_rules = $wp_rewrite->wp_rewrite_rules();
            foreach ($rewrite_rules as $rule => $rewrite) {
                $generated_patterns[] = [
                    'name' => $rule,
                    'label' => $rule,
                    'rules' =>
                        [
                            [
                                'rule' =>
                                    [
                                        [
                                            'type' => $this->parent::TYPE_SERVER,
                                            'key' => 'REQUEST_URI',
                                            'compare' => Variables::COMPARE_REGEXP,
                                            'value' => '#' . $rule . '#',
                                            'logic' => $this->parent::LOGIC_AND,
                                        ],
                                    ],
                                'logic' => $this->parent::LOGIC_AND,
                            ],
                        ],
                ];
            }

            add_filter(
                Option::getOptionFilterName('patterns', $this->getName()),
                function ($patterns) use ($generated_patterns) {
                    return $this->overwritePredefinedPatterns($patterns, $generated_patterns);
                }
            );

            return;
        }


        echo HTML::tagOpen('div', ['class' => 'wrap']);

        echo HTML::tagOpen(
            'form',
            [
                'action' => '',
                'method' => 'post'
            ]
        );

        echo HTML::tag('button', 'Generate', ['type' => 'submit', 'class' => 'button button-primary']);

        wp_nonce_field('generate', $this->getName());

        echo HTML::tagClose('form');
        echo HTML::tagClose('div');
    }

    public function getTrackingVars(): array
    {
        return Option::getOption('tracking_vars', $this->getName());
    }

    public function initAutoPatternGenerationTracker(): void
    {
        $tracking_vars = $this->getTrackingVars();

        global $wp_rewrite;

        /** @var \WP_Rewrite $wp_rewrite */
        $tracking_vars['wp_rewrite']['extra_rules'] = array_merge(
            $tracking_vars['wp_rewrite']['extra_rules'] ?? [],
            $wp_rewrite->extra_rules
        );

        $tracking_vars['wp_rewrite']['extra_rules_top'] = array_merge(
            $tracking_vars['wp_rewrite']['extra_rules_top'] ?? [],
            $wp_rewrite->extra_rules_top
        );

        $tracking_vars['wp_rewrite']['extra_permastructs'] = array_merge(
            $tracking_vars['wp_rewrite']['extra_permastructs'] ?? [],
            $wp_rewrite->extra_permastructs
        );

        Option::setOption('tracking_vars', $this->getName(), $tracking_vars);
    }
}