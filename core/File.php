<?php
namespace Core;
/**
 * Class File
 * 
 * This class handles file objects and provides methods to access file data.
 * 
 * @property string $url The URL of the file.
 * 
 */
class File
{
    public $url;

    public function __construct($url)
    {
        $this->url = $url;
    }
    public function exist(){
        return file_exists($this->url);
    }
    /**
     * Get the URL of the file
     * @return string The URL of the file
     */
    public function get_url()
    {
        return $this->url;
    }

    /**
     * downaload file from form and save it to the folder
     * @param string $file_folder The folder where the file will be saved
     * @param string $file_name The name of the file
     * @return File The new file object
     * 
     */
    public static function new_file($file_folder, $file_name = null)
    {
        $file = $_FILES['file'];
        $new_file_name = $file['name'];
        if ($file_name != null) {
            $new_file_name = $file_name;
        }

        if ($file_folder[strlen($file_folder) - 1] !== '/') {
            $file_folder .= '/';
        }
        if (file_exists($file_folder . $new_file_name)) {
            $new_file_name = str_replace('.', time() . '.', $new_file_name);
        }
        $target_file = $file_folder . $new_file_name;
        move_uploaded_file($file['tmp_name'], $target_file);
        return new File($target_file);
    }

    /**
     * Get the file name
     * @return string The file name
     */
    public function get_name()
    {
        return basename($this->url);
    }

    /**
     * Move the file to another folder
     * @param string $folder The folder where the file will be moved
     * @param string $name The name of the file (optional)
     * @return string The new URL of the file
     */
    public function move_file($folder, $name = null, $overwrite = false)
    {
        $path = $this->copy($folder, $name, $overwrite);
        $this->delete();
        $this->url = $path;
        return $path;
    }

    /**
     * Get the file extension
     * @return string The file extension
     */
    public function get_extension()
    {
        return pathinfo($this->url, PATHINFO_EXTENSION);
    }

    /**
     * Get the file size
     * @return int The file size
     */
    public function get_size()
    {
        return filesize($this->url);
    }

    /**
     * Copy the file to another folder
     * @param string $folder The folder where the file will be copied
     * @param string $name The name of the file (optional)
     * @return string The URL of the new file
     */
    public function copy($folder, $name = null, $overwrite = false)
    {
        $file_name = $this->get_name();
        if ($name != null) {
            $file_name = $name;
        }
        if ($folder[strlen($folder) - 1] !== '/') {
            $folder .= '/';
        }
        if (file_exists($folder . $file_name) && !$overwrite) {
            $pathInfo = pathinfo($file_name);
            $baseName = $pathInfo['filename'];
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

            // Přidat counter
            $counter = 1;
            while (file_exists($folder . $file_name)) {
                $file_name = $baseName . '_' . $counter . $extension;
                $counter++;
            }
        }

        // ✅ OPRAVA: Odstranit duplikaci přípony
        $target_file = $folder . $file_name;  // <-- Přípona už je v $file_name!

        copy($this->url, $target_file);
        return $target_file;
    }

    /**
     * Delete the file
     * @return bool True if the file was deleted, false otherwise
     */
    public function delete()
    {
        return unlink($this->url);
    }

    /**
     * Get the folder of the file
     * @return string The folder of the file
     */
    public function get_folder()
    {
        return dirname($this->url);
    }
}