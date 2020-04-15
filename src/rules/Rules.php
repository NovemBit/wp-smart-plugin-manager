<?php


namespace NovemBit\wp\plugins\spm\rules;


use diazoxide\helpers\Environment;
use diazoxide\helpers\HTML;
use diazoxide\helpers\URL;
use diazoxide\helpers\Variables;
use diazoxide\wp\lib\option\v2\Option;
use NovemBit\wp\plugins\spm\Bootstrap;

class Rules
{

    /**
     * @var Bootstrap
     * */
    public $parent;

    /**
     * @var $patterns
     * */
    public $patterns;

    /**
     * @var array
     * */
    public $tabs = [];

    /**
     * Patterns constructor.
     * @param Bootstrap $parent
     */
    public function __construct(Bootstrap $parent)
    {
        $this->parent = $parent;
        $this->patterns = new Patterns($this);

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
        add_action('admin_menu', [$this, 'adminMenu']);

        $this->tabs['default'] = ['label' => 'Rules', 'content' => [$this, 'defaultTabContent']];
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->parent->getName() . '-rules';
    }

    /**
     * @return void
     * @uses adminContent
     */
    public function adminMenu(): void
    {
        add_submenu_page(
            $this->parent->getName(),
            __('Rules', 'novembit-spm'),
            __('Rules', 'novembit-spm'),
            'manage_options',
            $this->getName(),
            [$this, 'adminContent']
        );
    }

    /**
     * Admin Content
     * @return void
     */
    public function adminContent(): void
    {
        $tabs = [];
        $current_url = admin_url('admin.php?page=' . $this->getName());
        $active = Environment::get('action') ?? 'default';
        $active_tab = $this->tabs[$active];

        foreach ($this->tabs as $tab => $params) {
            $url = URL::addQueryVars($current_url, 'action', $tab);
            $active_class = $active === $tab ? ' nav-tab-active' : '';
            $tabs[] = [
                'a',
                $params['label'] ?? $tab,
                ['class' => 'nav-tab' . $active_class, 'href' => $url]
            ];
        }
        echo HTML::tag('nav', $tabs, ['class' => 'nav-tab-wrapper']);

        $content = $active_tab['content'] ?? null;

        if (is_callable($content)) {
            $content();
            return;
        }

        if (is_string($content)) {
            echo $content;
        }
    }

    public function defaultTabContent()
    {
        echo 111;
    }

    public static function getRulesSettings($with_name = false): array
    {
        $result = [
            'rule' => self::getRuleSetting(),
            'logic' => self::getLogicSetting()
        ];

        if ($with_name) {
            $result = [
                    'name' => [
                        'label' => 'Name',
                        'type' => Option::TYPE_TEXT,
                        'required'=>true,
                    ]
                ] + $result;
        }

        return $result;
    }

    public static function getLogicSetting(): array
    {
        return [
            'type' => Option::TYPE_TEXT,
            'label' => 'Logic',
            'values' => [
                'and' => 'And',
                'or' => 'Or',
                'not' => 'Not',
            ]
        ];
    }

    /**
     * @return array
     */
    public static function getRuleSetting(): array
    {
        return [
            'type' => Option::TYPE_GROUP,
            'method' => Option::METHOD_MULTIPLE,
            'label' => 'Single Rule',
            'main_params' => ['style' => 'grid-template-columns: repeat(3, 1fr);display:grid'],
            'template' => [
                'type' => [
                    'type' => Option::TYPE_TEXT,
                    'values' => [
                        'request' => 'Request',
                        'get' => 'Get',
                        'post' => 'Post',
                        'cookie' => 'Cookie',
                        'server' => 'Server',
                        'hook' => 'Hook',
                        'function' => 'Function'
                    ]
                ],
                'key' => [
                    'type' => Option::TYPE_TEXT,
                    'label' => 'Key',
                ],
                'compare' => [
                    'type' => Option::TYPE_TEXT,
                    'label' => 'Compare operator',
                    'default' => 'equal',
                    'values' => [
                        Variables::COMPARE_EQUAL => 'Equal ( == )',
                        Variables::COMPARE_NOT_EQUAL => 'Not equal ( <> )',
                        Variables::COMPARE_IDENTICAL => 'Identical ( === )',
                        Variables::COMPARE_NOT_IDENTICAL => 'Not Identical ( !== )',
                        Variables::COMPARE_GREATER_THAN => 'Greater than ( > )',
                        Variables::COMPARE_GREATER_THAN_OR_EQUAL => 'Greater than or equal ( >= )',
                        Variables::COMPARE_LESS_THAN => 'Less than ( < )',
                        Variables::COMPARE_LESS_THAN_OR_EQUAL => 'Less than or equal ( <= )',
                        Variables::COMPARE_CONTAINS => 'Contains ( %word% )',
                        Variables::COMPARE_REGEXP => 'Regular expression ( /^(man|woman)$/ )',
                        Variables::COMPARE_STARTS_WITH => 'Starts with',
                        Variables::COMPARE_ENDS_WITH => 'Ends with',
                    ]
                ],
                'value' => [
                    'type' => Option::TYPE_TEXT,
                    'label' => 'Value',
                ],
                'logic' => self::getLogicSetting()
            ],
        ];
    }
}