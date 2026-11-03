<?php
/**
 * Injellik Recommendations for PrestaShop.
 *
 * @author    Web-Nest <contact@web-nest.fr>
 * @copyright Web-Nest
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Tables, hooks, back-office tabs and default configuration; shared by install and upgrade scripts.
 */
class InjellikInstaller
{
    /** @var Module */
    private $module;

    public function __construct(Module $module)
    {
        $this->module = $module;
    }

    public static function hooks()
    {
        $hooks = [
            'actionFrontControllerSetMedia',
            'actionCartUpdateQuantityBefore',
            'actionValidateOrder',
            'actionObjectProductAddAfter',
            'actionObjectProductUpdateAfter',
            'actionObjectProductDeleteAfter',
        ];
        foreach (InjellikConfig::defaultPlacements() as $placement) {
            if ($placement['hook'] !== null) {
                $hooks[] = $placement['hook'];
            }
        }

        return $hooks;
    }

    public function install()
    {
        return $this->installTables()
            && $this->module->registerHook(self::hooks())
            && $this->installConfiguration()
            && $this->installTabs();
    }

    public function uninstall()
    {
        foreach (InjellikConfig::keys() as $key) {
            Configuration::deleteByName($key);
        }
        InjellikCache::clear();

        return $this->uninstallTabs() && $this->uninstallTables();
    }

    public function installTables()
    {
        $engine = _MYSQL_ENGINE_;
        $prefix = _DB_PREFIX_;

        return Db::getInstance()->execute(
            "CREATE TABLE IF NOT EXISTS `{$prefix}injellik_event` (
                `id_injellik_event` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL,
                `action_type` VARCHAR(50) NOT NULL,
                `item_sku` VARCHAR(50) NOT NULL,
                `item_name` VARCHAR(255) NULL,
                `user_uuid` VARCHAR(64) NOT NULL,
                `session_uuid` VARCHAR(64) NULL,
                `rating_value` TINYINT UNSIGNED NULL,
                `status` VARCHAR(10) NOT NULL DEFAULT 'pending',
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `last_error` VARCHAR(255) NULL,
                `date_add` DATETIME NOT NULL,
                `date_sent` DATETIME NULL,
                PRIMARY KEY (`id_injellik_event`),
                KEY `status_shop` (`status`, `id_shop`),
                KEY `shop_date` (`id_shop`, `date_add`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4"
        ) && Db::getInstance()->execute(
            "CREATE TABLE IF NOT EXISTS `{$prefix}injellik_stat` (
                `id_shop` INT UNSIGNED NOT NULL,
                `date` DATE NOT NULL,
                `placement` VARCHAR(32) NOT NULL,
                `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
                `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_shop`, `date`, `placement`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4"
        );
    }

    public function uninstallTables()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'injellik_event`, `' . _DB_PREFIX_ . 'injellik_stat`');
    }

    public function installConfiguration()
    {
        foreach (InjellikConfig::defaults() as $key => $value) {
            if (Configuration::get($key) === false && !Configuration::updateValue($key, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * "Injellik" menu (Improve section) with the dashboard and the configuration pages.
     */
    public function installTabs()
    {
        $parentId = $this->createTab('AdminInjellik', (int) Tab::getIdFromClassName('IMPROVE'), 'Injellik', 'trending_up');
        if (!$parentId) {
            return false;
        }

        return $this->createTab('AdminInjellikDashboard', $parentId, 'Dashboard')
            && $this->createTab('AdminInjellikConfiguration', $parentId, 'Configuration');
    }

    public function uninstallTabs()
    {
        foreach (['AdminInjellikDashboard', 'AdminInjellikConfiguration', 'AdminInjellik'] as $className) {
            $id = (int) Tab::getIdFromClassName($className);
            if ($id) {
                $tab = new Tab($id);
                $tab->delete();
            }
        }

        return true;
    }

    private function createTab($className, $idParent, $name, $icon = null)
    {
        $id = (int) Tab::getIdFromClassName($className);
        if ($id) {
            return $id;
        }

        $tab = new Tab();
        $tab->class_name = $className;
        $tab->module = $this->module->name;
        $tab->id_parent = (int) $idParent;
        $tab->active = 1;
        if ($icon) {
            $tab->icon = $icon;
        }
        // Explicit names: the module catalog is not always loaded yet when the module is installed.
        $names = [
            'Dashboard' => ['fr' => 'Suivi', 'es' => 'Seguimiento', 'it' => 'Monitoraggio', 'de' => 'Übersicht'],
            'Configuration' => ['de' => 'Konfiguration', 'es' => 'Configuración', 'it' => 'Configurazione'],
        ];
        foreach (Language::getLanguages(false) as $language) {
            $iso = $language['iso_code'];
            $tab->name[(int) $language['id_lang']] = isset($names[$name][$iso]) ? $names[$name][$iso] : $name;
        }

        return $tab->add() ? (int) $tab->id : 0;
    }
}
