<?php

/**
 * Code related to the cache.lib.php interface.
 *
 * PHP version 5
 *
 * @category   Library
 * @package    Sucuri
 * @subpackage SucuriScanner
 * @author     Daniel Cid <dcid@sucuri.net>
 * @copyright  2010-2017 Sucuri Inc.
 * @license    https://www.gnu.org/licenses/gpl-2.0.txt GPL2
 * @link       https://wordpress.org/plugins/sucuri-scanner
 */

if (!defined('SUCURISCAN_INIT') || SUCURISCAN_INIT !== true) {
    if (!headers_sent()) {
        /* Report invalid access if possible. */
        header('HTTP/1.1 403 Forbidden');
    }
    exit(1);
}

/**
 * File-based cache library.
 *
 * WP_Object_Cache [1] is WordPress' class for caching data which may be
 * computationally expensive to regenerate, such as the result of complex
 * database queries. However the object cache is non-persistent. This means that
 * data stored in the cache resides in memory only and only for the duration of
 * the request. Cached data will not be stored persistently across page loads
 * unless of the installation of a 3party persistent caching plugin [2].
 *
 * [1] https://codex.wordpress.org/Class_Reference/WP_Object_Cache
 * [2] https://codex.wordpress.org/Class_Reference/WP_Object_Cache#Persistent_Caching
 *
 * @category   Library
 * @package    Sucuri
 * @subpackage SucuriScanner
 * @author     Daniel Cid <dcid@sucuri.net>
 * @copyright  2010-2017 Sucuri Inc.
 * @license    https://www.gnu.org/licenses/gpl-2.0.txt GPL2
 * @link       https://wordpress.org/plugins/sucuri-scanner
 */
class SucuriScanCache extends SucuriScan
{
    /**
     * The unique name (or identifier) of the file with the data.
     *
     * The file should be located in the same folder where the dynamic data
     * generated by the plugin is stored, and using the following format [1], it
     * most be a PHP file because it is expected to have an exit point in the first
     * line of the file causing it to stop the execution if a unauthorized user
     * tries to access it directly.
     *
     * [1] /public/data/sucuri-DATASTORE.php
     *
     * @var string
     */
    private $datastore;

    /**
     * The full path of the datastore file.
     *
     * @var string
     */
    private $datastore_path;

    /**
     * Whether the datastore file is usable or not.
     *
     * This variable will only be TRUE if the datastore file specified exists, is
     * writable and readable, in any other case it will always be FALSE.
     *
     * @var boolean
     */
    private $usable_datastore;

    /**
     * Initializes the cache library.
     *
     * @param string $datastore   Name of the storage file.
     * @param bool   $auto_create Forces the creation of the storage file.
     */
    public function __construct($datastore = '', $auto_create = true)
    {
        $this->datastore = $datastore;
        $this->datastore_path = $this->datastoreFilePath($auto_create);
        $this->usable_datastore = (bool) $this->datastore_path;
    }

    /**
     * Default attributes for every datastore file.
     *
     * @return array Default attributes for every datastore file.
     */
    public function datastoreDefaultInfo()
    {
        $attrs = array(
            'datastore' => $this->datastore,
            'created_on' => time(),
            'updated_on' => time(),
        );

        return $attrs;
    }

    /**
     * Default content of every datastore file.
     *
     * @param  array $finfo Rainbow table with the key names and decoded values.
     * @return string       Default content of every datastore file.
     */
    private function datastoreInfo($finfo = array())
    {
        $attrs = $this->datastoreDefaultInfo();
        $info_is_available = (bool) isset($finfo['info']);
        $info  = "<?php\n";

        foreach ($attrs as $attr_name => $attr_value) {
            if ($info_is_available
                && $attr_name != 'updated_on'
                && isset($finfo['info'][$attr_name])
            ) {
                $attr_value = $finfo['info'][$attr_name];
            }

            $info .= sprintf("// %s=%s;\n", $attr_name, $attr_value);
        }

        $info .= "exit(0);\n";
        $info .= "?>\n";

        return $info;
    }

    /**
     * Check if the datastore file exists, if it's writable and readable by the same
     * user running the server, in case that it does not exists the method will
     * tries to create it by itself with the right permissions to use it.
     *
     * @param  bool $auto_create Create the file is it does not exists.
     * @return string|bool       Absolute path to the storage file, false otherwise.
     */
    private function datastoreFilePath($auto_create = false)
    {
        if (!$this->datastore) {
            return false;
        }

        $filename = $this->dataStorePath('sucuri-' . $this->datastore . '.php');
        $directory = dirname($filename); /* create directory if necessary */

        if (!file_exists($directory)) {
            @mkdir($directory, 0755, true);
        }

        if (!file_exists($filename) && is_writable($directory) && $auto_create) {
            @file_put_contents($filename, $this->datastoreInfo());
        }

        return $filename;
    }

    /**
     * Check whether a key has a valid name or not.
     *
     * WARNING: Instead of using a regular expression to match the format of the
     * key we will use a primitive string transformation technique to reduce the
     * execution time, regular expressions are significantly slow.
     *
     * @param  string $key Unique name for the data.
     * @return bool        True if the key is valid, false otherwise.
     */
    private function validKeyName($key = '')
    {
        $result = true;
        $length = strlen($key);
        $allowed = array(
            /* preg_match('/^([0-9a-zA-Z_]+)$/', $key) */
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j',
            'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't',
            'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D',
            'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
            'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
            'Y', 'Z', '_',
        );

        for ($i = 0; $i < $length; $i++) {
            if (!in_array($key[$i], $allowed)) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    /**
     * Update the content of the datastore file with the new entries.
     *
     * @param  array $finfo Rainbow table with the key names and decoded values.
     * @return bool         TRUE if the operation finished successfully, FALSE otherwise.
     */
    private function saveNewEntries($finfo = array())
    {
        if (!$finfo) {
            return false;
        }

        $metadata = $this->datastoreInfo($finfo);

        if (@file_put_contents($this->datastore_path, $metadata)) {
            foreach ($finfo['entries'] as $key => $data) {
                $line = sprintf("%s:%s\n", $key, json_encode($data));
                @file_put_contents($this->datastore_path, $line, FILE_APPEND);
            }
        }

        return true;
    }

    /**
     * Retrieve and parse the cache file and generate a hash table with the keys
     * and decoded data as the values of each entry. Duplicated key names will
     * be merged automatically.
     *
     * @param  bool $assoc    Force object to array conversion.
     * @param  bool $onlyInfo Returns the cache headers and no content.
     * @return array          Rainbow table with the key names and decoded values.
     */
    private function getDatastoreContent($assoc = false, $onlyInfo = false)
    {
        $object = array();
        $object['info'] = array();
        $object['entries'] = array();
        $lines = SucuriScanFileInfo::fileLines($this->datastore_path);

        if (is_array($lines) && !empty($lines)) {
            foreach ($lines as $line) {
                if (strpos($line, "//\x20") === 0
                    && strpos($line, '=') !== false
                    && $line[strlen($line) - 1] === ';'
                ) {
                    $section = substr($line, 3, strlen($line) - 4);
                    list($header, $value) = explode('=', $section, 2);
                    $object['info'][$header] = $value;
                    continue;
                }

                /* skip content */
                if ($onlyInfo) {
                    continue;
                }

                if (strpos($line, ':') !== false) {
                    list($keyname, $value) = explode(':', $line, 2);
                    $object['entries'][$keyname] = @json_decode($value, $assoc);
                }
            }
        }

        return $object;
    }

    /**
     * Retrieve the headers of the datastore file.
     *
     * Each datastore file has a list of attributes at the beginning of the it with
     * information like the creation and last update time. If you are extending the
     * functionality of these headers please refer to the method that contains the
     * default attributes and their values [1].
     *
     * [1] SucuriScanCache::datastoreDefaultInfo()
     *
     * @return array|bool Default content of every datastore file.
     */
    public function getDatastoreInfo()
    {
        $finfo = $this->getDatastoreContent(false, true);

        if (empty($finfo['info'])) {
            return false;
        }

        $finfo['info']['fpath'] = $this->datastore_path;

        return $finfo['info'];
    }

    /**
     * Get the total number of unique entries in the datastore file.
     *
     * @param  array $finfo Rainbow table with the key names and decoded values.
     * @return int          Total number of unique entries found in the datastore file.
     */
    public function getCount($finfo = null)
    {
        if (!is_array($finfo)) {
            $finfo = $this->getDatastoreContent();
        }

        return count($finfo['entries']);
    }

    /**
     * Check whether the last update time of the datastore file has surpassed the
     * lifetime specified for a key name. This method is the only one related with
     * the caching process, any others besides this are just methods used to handle
     * the data inside those files.
     *
     * @param  int   $lifetime Life time of the key in the datastore file.
     * @param  array $finfo    Rainbow table with the key names and decoded values.
     * @return bool            TRUE if the life time of the data has expired, FALSE otherwise.
     */
    public function dataHasExpired($lifetime = 0, $finfo = null)
    {
        if (!is_array($finfo)) {
            $meta = $this->getDatastoreInfo();
            $finfo = array('info' => $meta);
        }

        if ($lifetime > 0 && !empty($finfo['info'])) {
            $diff_time = time() - intval($finfo['info']['updated_on']);

            if ($diff_time >= $lifetime) {
                return true;
            }
        }

        return false;
    }

    /**
     * JSON-encode the data and store it in the datastore file identifying it with
     * the key name, the data will be added to the file even if the key is
     * duplicated, but when getting the value of the same key later again it will
     * return only the value of the first occurrence found in the file.
     *
     * @param  string $key  Unique name for the data.
     * @param  mixed  $data Data to associate to the key.
     * @return bool         True if the data was cached, false otherwise.
     */
    public function add($key = '', $data = '')
    {
        return $this->set($key, $data);
    }

    /**
     * Update the data of all the key names matching the one specified.
     *
     * @param  string $key  Unique name for the data.
     * @param  mixed  $data Data to associate to the key.
     * @return bool         True if the cache data was updated, false otherwise.
     */
    public function set($key = '', $data = '')
    {
        if (!$this->validKeyName($key)) {
            return self::throwException('Invalid cache key name');
        }

        $finfo = $this->getDatastoreInfo();
        $line = sprintf("%s:%s\n", $key, json_encode($data));

        return (bool) @file_put_contents($finfo['fpath'], $line, FILE_APPEND);
    }

    /**
     * Retrieve the first occurrence of the key found in the datastore file.
     *
     * @param  string $key      Unique name for the data.
     * @param  int    $lifetime Seconds before the data expires.
     * @param  string $assoc    Force data to be converted to an array.
     * @return mixed            Data associated to the key.
     */
    public function get($key = '', $lifetime = 0, $assoc = '')
    {
        if (!$this->validKeyName($key)) {
            return self::throwException('Invalid cache key name');
        }

        $finfo = $this->getDatastoreContent($assoc === 'array');

        if ($this->dataHasExpired($lifetime, $finfo)
            || !array_key_exists($key, $finfo['entries'])
        ) {
            return false;
        }

        return @$finfo['entries'][$key];
    }

    /**
     * Retrieve all the entries found in the datastore file.
     *
     * @param  int    $lifetime Life time of the key in the datastore file.
     * @param  string $assoc    Force data to be converted to an array.
     * @return mixed            All the entries stored in the cache file.
     */
    public function getAll($lifetime = 0, $assoc = '')
    {
        $finfo = $this->getDatastoreContent($assoc === 'array');

        if ($this->dataHasExpired($lifetime, $finfo)) {
            return false;
        }

        return $finfo['entries'];
    }

    /**
     * Check whether a specific key exists in the datastore file.
     *
     * @param  string $key Unique name for the data.
     * @return bool        True if the data exists, false otherwise.
     */
    public function exists($key = '')
    {
        if (!$this->validKeyName($key)) {
            return self::throwException('Invalid cache key name');
        }

        $finfo = $this->getDatastoreContent(true);

        return array_key_exists($key, $finfo['entries']);
    }

    /**
     * Delete any entry from the datastore file matching the key name specified.
     *
     * @param  string $key Unique name for the data.
     * @return bool        True if the data was deleted, false otherwise.
     */
    public function delete($key = '')
    {
        if (!$this->validKeyName($key)) {
            return self::throwException('Invalid cache key name');
        }

        $finfo = $this->getDatastoreContent(true);

        if (!array_key_exists($key, $finfo['entries'])) {
            return true;
        }

        unset($finfo['entries'][$key]);

        return $this->saveNewEntries($finfo);
    }

    /**
     * Replaces the entire content of the cache file.
     *
     * @param  array $entries New data for the cache.
     * @return bool           True if the cache was replaced.
     */
    public function override($entries = array())
    {
        return $this->saveNewEntries(
            array(
                'info' => $this->getDatastoreInfo(),
                'entries' => $entries,
            )
        );
    }

    /**
     * Remove all the entries from the datastore file.
     *
     * @return bool True, unless the cache file is not writable.
     */
    public function flush()
    {
        $filename = 'sucuri-' . $this->datastore . '.php';

        return @unlink($this->dataStorePath($filename));
    }
}
