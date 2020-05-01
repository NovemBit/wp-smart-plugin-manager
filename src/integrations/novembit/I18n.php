<?php


namespace NovemBit\wp\plugins\spm\integrations\novembit;


use diazoxide\helpers\Environment;
use diazoxide\wp\lib\option\v2\Option;
use NovemBit\wp\plugins\spm\integrations\Integrations;

class I18n
{
    /**
     * @var Integrations
     */
    public $parent;

    public const NAME = 'NovemBit i18n plugin';

    private function migrations(): array
    {
        return [
            '28.04.2020' => 'CREATE TABLE IF NOT EXISTS `' . $this->getMapTableName() . '` (
                `origin` varchar(255) NOT NULL,
			    `path` varchar(255) NOT NULL,
                PRIMARY KEY (`origin`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        ];
    }

    public function getMapTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . str_replace('-', '_', $this->getName()) . '_map';
    }

    public function __construct(Integrations $parent)
    {
        $this->parent = $parent;

        $this->migrateUp();


        add_filter(
            $this->parent->parent->helpers->getHookName('RequestPath'),
            [$this, 'restoreOriginPath'],
            11
        );

        $origin_request_path = trim(parse_url(Environment::server('REQUEST_URI'), PHP_URL_PATH), '/');

        add_filter(
            'shutdown',
            function () use ($origin_request_path) {
                $request_path = trim(parse_url(Environment::server('REQUEST_URI'), PHP_URL_PATH), '/');

                if ($origin_request_path === $request_path) {
                    return;
                }

                global $wpdb;

                $path = $wpdb->get_var(
                    $wpdb->prepare(
                        'SELECT path FROM ' . $this->getMapTableName() . ' WHERE origin="%s"',
                        $origin_request_path
                    )
                );

                if ($path === null) {
                    $data = array('path' => $request_path, 'origin' => $origin_request_path);
                    $format = array('%s', '%s');
                    $wpdb->insert($this->getMapTableName(), $data, $format);
                }
            }
        );
    }

    public function migrateUp(): void
    {
        $history = Option::getOption('migrations', $this->getName(), []);

        global $wpdb;

        $migrations = $this->migrations();

        $updated = false;

        foreach ($migrations as $date => $sql) {
            if (!in_array($date, $history, true)) {
                $wpdb->query($sql);
                $history[] = $date;
                $updated = true;
            }
        }
        if ($updated) {
            Option::setOption('migrations', $this->getName(), $history);
        }
    }

    /**
     * @param string $path
     * @return string
     */
    public function restoreOriginPath(string $path): string
    {
        global $wpdb;
        $origin_request_path = trim(parse_url(Environment::server('REQUEST_URI'), PHP_URL_PATH), '/');

        $restored_path = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT path FROM ' . $this->getMapTableName() . ' WHERE origin="%s"',
                $origin_request_path
            )
        );

        if ($restored_path !== null) {
            $restored_path = '/' . $restored_path . '/';
        }

        return $restored_path ?? $path;
    }

    public function getName(): string
    {
        return $this->parent->getName() . '-i18n';
    }

}