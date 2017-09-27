<?php

/**
 * @package GPL Cart core
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2015, Iurii Makukh
 * @license https://www.gnu.org/licenses/gpl.html GNU/GPLv3
 */

/**
 * Scans a directory and deletes its files that match a specific condition
 * @param string $directory
 * @param mixed $pattern Either an array of extensions or a pattern for glob()
 * @param integer $lifespan
 * @return integer
 */
function gplcart_file_delete($directory, $pattern, $lifespan = 0)
{
    $deleted = 0;
    foreach (gplcart_file_scan($directory, $pattern) as $file) {
        if ((filemtime($file) < GC_TIME - $lifespan) && unlink($file)) {
            $deleted++;
        }
    }

    return $deleted;
}

/**
 * Finds all files matching a given pattern in a given directory
 * @param string $path
 * @param string|array $pattern
 * @return array
 */
function gplcart_file_scan($path, $pattern)
{
    if (!is_array($pattern)) {
        return glob("$path/$pattern");
    }

    $extensions = implode(',', $pattern);
    return glob("$path/*.{{$extensions}}", GLOB_BRACE);
}

/**
 * Recursive deletes files and directories
 * @param string $directory
 * @return boolean
 */
function gplcart_file_delete_recursive($directory)
{
    if (!is_dir($directory)) {
        return false;
    }

    $files = gplcart_file_scan_recursive($directory);

    foreach ($files as $file) {
        if (is_dir($file)) {
            gplcart_file_delete_recursive($file);
        } else {
            unlink($file);
        }
    }

    return rmdir($directory);
}

/**
 * Recursive scans files in a directory
 * @param string $directory
 * @param array $results
 * @return array
 */
function gplcart_file_scan_recursive($directory, &$results = array())
{
    foreach (scandir($directory) as $file) {
        $path = $directory . DIRECTORY_SEPARATOR . $file;
        if (!is_dir($path)) {
            $results[] = $path;
        } else if ($file != "." && $file != "..") {
            gplcart_file_scan_recursive($path, $results);
            $results[] = $path;
        }
    }

    return $results;
}

/**
 * Returns a unique file path using a base path
 * @param string $file
 * @return string
 */
function gplcart_file_unique($file)
{
    if (!file_exists($file)) {
        return $file;
    }

    $info = pathinfo($file);
    $extension = isset($info['extension']) ? '.' . $info['extension'] : '';

    $counter = 0;

    do {
        $counter++;
        $modified_filename = $info['filename'] . '-' . $counter . $extension;
        $modified_file = "{$info['dirname']}/$modified_filename";
    } while (file_exists($modified_file));

    return $modified_file;
}

/**
 * Writes a CSV file
 * @param string $file
 * @param array $data
 * @param string $del
 * @param string $en
 * @param integer $limit
 * @return boolean
 */
function gplcart_file_csv($file, $data, $del = ",", $en = '"', $limit = 0)
{
    $handle = fopen($file, 'a+');

    if ($handle === false) {
        return false;
    }

    if (!empty($limit) && filesize($file) > $limit) {
        ftruncate($handle, 0);
        rewind($handle);
    }

    $result = fputcsv($handle, $data, $del, $en);
    fclose($handle);
    return $result !== false;
}

/**
 * Converts an absolute file path to relative
 * @param string $absolute
 * @return string
 */
function gplcart_file_relative_path($absolute)
{
    if (strpos($absolute, GC_FILE_DIR) === 0) {
        return trim(substr($absolute, strlen(GC_FILE_DIR)), '/\\');
    }

    return $absolute;
}

/**
 * Converts a relative file path to absolute
 * @param string $path
 * @return string
 */
function gplcart_file_absolute_path($path)
{
    return strpos($path, GC_FILE_DIR) === 0 ? $path : GC_FILE_DIR . "/$path";
}

/**
 * Converts bytes to a human readable file size
 * @param integer $size
 * @param integer $precision
 * @return string
 */
function gplcart_file_size($size, $precision = 2)
{
    $units = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');

    $i = 0;
    $step = 1024;
    while (($size / $step) > 0.9) {
        $size = $size / $step;
        $i++;
    }

    return round($size, $precision) . $units[$i];
}

/**
 * Sanitize file name
 * @param string $name
 * @param string $replace
 * @return string
 */
function gplcart_file_name_clean($name, $replace = '')
{
    // Remove unsafe characters
    $safe = preg_replace('/[^0-9a-zA-Z-.,_]/', $replace, $name);
    // Remove multiple consecutive non-alphabetical characters
    return preg_replace('/(_)_+|(\.)\.+|(-)-+/', '\\1\\2\\3', $safe);
}

/**
 * Saves PHP configuration in a file
 * @param string $file
 * @param mixed $data
 */
function gplcart_file_config($file, $data)
{
    if (file_exists($file)) {
        chmod($file, 0644);
    }

    $result = file_put_contents($file, '<?php return ' . var_export($data, true) . ';');

    if ($result !== false) {
        chmod($file, 0444);
        return true;
    }

    return false;
}
