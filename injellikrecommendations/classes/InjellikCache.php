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
 * Small file cache of the API responses (var/cache/<env>/injellik), independent of the
 * PrestaShop cache settings. Rankings and item recommendations change once a night (training).
 */
class InjellikCache
{
    private static function dir()
    {
        return rtrim(_PS_CACHE_DIR_, '/\\') . DIRECTORY_SEPARATOR . 'injellik' . DIRECTORY_SEPARATOR;
    }

    private static function path($key)
    {
        return self::dir() . sha1($key) . '.json';
    }

    public static function get($key)
    {
        $path = self::path($key);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($path), true);
        if (!is_array($data) || !isset($data['expires'], $data['value']) || $data['expires'] < time()) {
            return null;
        }

        return $data['value'];
    }

    public static function set($key, $value, $ttl)
    {
        if ($ttl <= 0) {
            return;
        }
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $path = self::path($key);
        $tmp = $path . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode(['expires' => time() + (int) $ttl, 'value' => $value])) !== false) {
            @rename($tmp, $path);
        }
    }

    /**
     * @param int $olderThan seconds; 0 deletes every entry
     */
    public static function clear($olderThan = 0)
    {
        $limit = time() - (int) $olderThan;
        foreach (glob(self::dir() . '*.json') ?: [] as $file) {
            if ($olderThan === 0 || @filemtime($file) < $limit) {
                @unlink($file);
            }
        }
    }
}
