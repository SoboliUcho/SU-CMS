<?php
namespace Core;
use DateTime;
use Exception;

/**
 * Class Image
 * 
 * This class handles image objects and provides methods to access image data.
 * 
 * @property string $url The URL of the image.
 * 
 */
class Image extends File
{
    public $creation_date;
    public $width;
    public $height;
    public $size;
    public $exif;
    public $iptc;
    public $type;

    public function __construct($url)
    {
        parent::__construct($url);
        $this->exif = @exif_read_data($this->url);
        $this->iptc = @iptcparse(@getimagesize($this->url, $info) ? ($info["APP13"] ?? '') : '');
        $this->creation_date = $this->created();
        list($this->width, $this->height, $this->type, $attr) = getimagesize($this->url);
        $this->size = filesize($this->url);
    }

    /**
     * Get the creation date of the image from EXIF or file creation time
     * @return string The creation date as a Unix timestamp
     */
    private function created()
    {
        if (!empty($this->exif['DateTimeOriginal'])) {
            return strtotime($this->exif['DateTimeOriginal']);
        } elseif (!empty($this->exif['DateTime'])) {
            return strtotime($this->exif['DateTime']);
        }
        if (!empty($this->iptc['2#055'][0])) {
            return strtotime($this->iptc['2#055'][0]);
        }
        return filectime($this->url);
    }

    /**
     * Get the creation date of the image
     * @return string The creation date in 'Y-m-d H:i:s' format
     */
    public function get_creation_date()
    {   
        if (is_int($this->creation_date)) {
            return date('Y-m-d H:i:s', $this->creation_date);
        }
        $dt = new DateTime($this->creation_date);
        // print_r ($dt);
        $creation_date = $dt->format('Y-m-d H:i:s');
        return $creation_date;
    }

    /**
     * downaload image from form and save it to the folder
     * @param string $image_folder The folder where the image will be saved
     * @param string $image_name The name of the image file
     * @return Image The new image object
     * 
     */
    public static function new_image($image_folder, $image_name = null)
    {
        $new_file = self::new_file($image_folder, $image_name);
        return new Image($new_file->url);
    }


    /**
     * change the size of the image
     * @param mixed $width width of the new image 0 for auto
     * @param mixed $height height of the new image 0 for auto
     * @param string $folder The folder where the image will be saved (default null = same as original)
     * @param string $new_file_name The name of the new image file (default null = original name with _resized_{$width}_x_{$height})
     * @return Image The new image object
     */
    public function change_size($width, $height = 0, $folder = null, $new_file_name = null)
    {
        if ($folder == null) {
            $folder = $this->get_folder();
        }
        if ($width == 0 && $height == 0) {
            return $this;
        }

        list($originalWidth, $originalHeight, $imageType) = getimagesize($this->url);
        if ($width == 0) {
            $width = $originalWidth * $height / $originalHeight;
        }
        if ($height == 0) {
            $height = $originalHeight * $width / $originalWidth;
        }

        switch ($imageType) {
            case IMAGETYPE_AVIF:
                $sourceImage = @imagecreatefromavif($this->url);
                if ($sourceImage === false) {
                    throw new Exception("Failed to load AVIF image. Runtime library missing.");
                }
                break;
            case IMAGETYPE_BMP:
                $sourceImage = imagecreatefrombmp($this->url);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($this->url);
                break;
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($this->url);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($this->url);
                break;
            case IMAGETYPE_WEBP:
                $sourceImage = imagecreatefromwebp($this->url);
                break;
            default:
                throw new Exception("Unsupported image type.");
        }

        $exifData = @exif_read_data($this->url);

        if (!empty($exifData['Orientation'])) {
            switch ($exifData['Orientation']) {
                case 3:
                    $sourceImage = imagerotate($sourceImage, 180, 0);
                    break;
                case 6:
                    $sourceImage = imagerotate($sourceImage, -90, 0);
                    $temp = $width;
                    $width = $height;
                    $height = $temp;
                    break;
                case 8:
                    $sourceImage = imagerotate($sourceImage, 90, 0);
                    $temp = $width;
                    $width = $height;
                    $height = $temp;
                    break;
            }
        }
        $resizedImage = imagecreatetruecolor($width, $height);

        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        $transparentColor = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
        imagefill($resizedImage, 0, 0, $transparentColor);

        imagecopyresampled(
            $resizedImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $originalWidth,
            $originalHeight
        );

        if ($folder == null) {
            $folder = $this->get_folder();
        }
        if ($folder[strlen($folder) - 1] !== '/') {
            $folder .= '/';
        }

        if ($new_file_name == null) {
            $new_file_name = $this->get_name() . "_resized_" . $width . '_x_' . $height;
        }

        $new_file = $folder . $new_file_name;

        switch ($imageType) {
            case IMAGETYPE_AVIF:
                // Pokusit se použít imageavif
                $result = @imageavif($resizedImage, $new_file, 100);
                if ($result === false) {
                    // Fallback na avifenc
                    $tempJpeg = $new_file . '.tmp.jpg';
                    imagejpeg($resizedImage, $tempJpeg, 100);
                    $this->convertUsingAvifenc($tempJpeg, $new_file, 100);
                    @unlink($tempJpeg);
                }
                break;
            case IMAGETYPE_BMP:
                imagebmp($resizedImage, $new_file);
                break;
            case IMAGETYPE_GIF:
                imagegif($resizedImage, $new_file);
                break;
            case IMAGETYPE_JPEG:
                imagejpeg($resizedImage, $new_file, 100);
                break;
            case IMAGETYPE_PNG:
                imagepng($resizedImage, $new_file);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($resizedImage, $new_file, 100);
                break;
            default:
                throw new Exception("Unsupported image type.");
        }

        imagedestroy($sourceImage);
        imagedestroy($resizedImage);

        return new Image($new_file);
    }

    /**
     * change the size of the image by percent
     * @param int $percent The percent by which the image will be resized
     * @param string $folder The folder where the image will be saved (default null = same as original)
     * @param string $new_file_name The name of the new image file (default null = original name with _resized_{$width}_x_{$height})
     * @return Image The new image object
     */
    public function change_size_percent($percent, $folder = null, $new_file_name = null)
    {
        if ($percent == 100) {
            return $this;
        }
        list($originalWidth, $originalHeight, $imageType) = getimagesize($this->url);
        $width = $originalWidth * $percent / 100;
        $height = $originalHeight * $percent / 100;
        return $this->change_size($width, $height, $folder, $new_file_name);
    }


    /**
     * Convert image to AVIF format
     * @param string $folder The folder where the image will be saved (default null = same as original)
     * @param string $new_file_name The name of the new image file (default null = original name with .avif)
     * @param int $quality Quality of the AVIF image (0-100, default 80)
     * @return Image The new image object in AVIF format
     */
    public function convert_to_avif($folder = null, $new_file_name = null, $quality = 80)
    {
        if ($folder == null) {
            $folder = $this->get_folder();
        }
        if ($folder[strlen($folder) - 1] !== '/') {
            $folder .= '/';
        }

        if ($new_file_name == null) {
            $new_file_name = $this->get_name() . '.avif';
        }

        $new_file_path = $folder . $new_file_name;

        // Zkusit PHP GD nejdříve
        $result = @$this->convertToAvifUsingGD($new_file_path, $quality);

        // Pokud GD selhalo nebo vytvořilo prázdný soubor
        if ($result === false || !file_exists($new_file_path) || filesize($new_file_path) === 0) {
            // Smazat případný prázdný soubor
            if (file_exists($new_file_path)) {
                @unlink($new_file_path);
            }

            // Použít avifenc
            $this->convertUsingAvifenc($this->url, $new_file_path, $quality);
        }

        return new Image($new_file_path);
    }

    /**
     * Convert image to WebP format - OPTIMIZED VERSION
     * @param string $folder The folder where the image will be saved (default null = same as original)
     * @param string $new_file_name The name of the new image file (default null = original name with .webp)
     * @param int $quality Quality of the WebP image (0-100, default 80)
     * @param bool $lossless Use lossless compression (default false)
     * @return Image The new image object in WebP format
     */
    public function convert_to_webp($folder = null, $new_file_name = null, $quality = 80, $lossless = false)
    {
        if ($folder == null) {
            $folder = $this->get_folder();
        }
        if ($folder[strlen($folder) - 1] !== '/') {
            $folder .= '/';
        }

        if ($new_file_name == null) {
            $new_file_name = $this->get_name() . '.webp';
        }

        $new_file_path = $folder . $new_file_name;

        list($originalWidth, $originalHeight, $imageType) = getimagesize($this->url);

        // Optimalizace: Načíst obrázek podle typu (rychlejší než switch)
        $sourceImage = null;

        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($this->url);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($this->url);
                break;
            case IMAGETYPE_WEBP:
                // Pokud je už WebP, jen zkopírovat s novou kvalitou
                $sourceImage = imagecreatefromwebp($this->url);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($this->url);
                break;
            case IMAGETYPE_BMP:
                $sourceImage = imagecreatefrombmp($this->url);
                break;
            case IMAGETYPE_AVIF:
                $sourceImage = @imagecreatefromavif($this->url);
                if ($sourceImage === false) {
                    throw new Exception("Failed to load AVIF image");
                }
                break;
            default:
                throw new Exception("Unsupported image type.");
        }

        if ($sourceImage === false) {
            throw new Exception("Failed to load source image");
        }

        // Zpracovat EXIF rotaci (pouze pro JPEG)
        if ($imageType === IMAGETYPE_JPEG) {
            $exifData = @exif_read_data($this->url);
            if (!empty($exifData['Orientation'])) {
                switch ($exifData['Orientation']) {
                    case 3:
                        $sourceImage = imagerotate($sourceImage, 180, 0);
                        break;
                    case 6:
                        $sourceImage = imagerotate($sourceImage, -90, 0);
                        $temp = $originalWidth;
                        $originalWidth = $originalHeight;
                        $originalHeight = $temp;
                        break;
                    case 8:
                        $sourceImage = imagerotate($sourceImage, 90, 0);
                        $temp = $originalWidth;
                        $originalWidth = $originalHeight;
                        $originalHeight = $temp;
                        break;
                }
            }
        }

        // Optimalizace: Pokud není potřeba alpha kanál (JPEG), použít jednodušší cestu
        $needsAlpha = in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_AVIF]);

        if ($needsAlpha) {
            // Zachovat průhlednost
            $newImage = imagecreatetruecolor($originalWidth, $originalHeight);
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparentColor = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefill($newImage, 0, 0, $transparentColor);
            imagecopy($newImage, $sourceImage, 0, 0, 0, 0, $originalWidth, $originalHeight);
        } else {
            // Pro JPEG - rychlejší bez alpha
            $newImage = $sourceImage;
        }

        // WebP konverze s optimalizací
        if ($lossless) {
            // Lossless mode - větší soubor, ale bez ztráty kvality
            imagewebp($newImage, $new_file_path, IMG_WEBP_LOSSLESS);
        } else {
            // Lossy mode - optimalizovaná kvalita
            imagewebp($newImage, $new_file_path, $quality);
        }

        imagedestroy($sourceImage);
        if ($needsAlpha) {
            imagedestroy($newImage);
        }

        return new Image($new_file_path);
    }

    /**
     * Convert image to WebP using external cwebp tool (fastest, best quality)
     * @param string $folder The folder where the image will be saved (default null = same as original)
     * @param string $new_file_name The name of the new image file (default null = original name with .webp)
     * @param int $quality Quality (0-100, default 80)
     * @param int $method Compression method 0-6 (0=fastest, 6=slowest/best, default 4)
     * @return Image The new image object in WebP format
     */
    public function convert_to_webp_fast($folder = null, $new_file_name = null, $quality = 80, $method = 4)
    {
        if ($folder == null) {
            $folder = $this->get_folder();
        }
        if ($folder[strlen($folder) - 1] !== '/') {
            $folder .= '/';
        }

        if ($new_file_name == null) {
            
            $new_file_name = $this->get_name();
            $new_file_name = explode('.', $new_file_name)[0] . '.webp';
        }

        $new_file_path = $folder . $new_file_name;

        // Zkontrolovat dostupnost cwebp
        exec('which cwebp 2>&1', $checkOutput, $checkReturnCode);

        if ($checkReturnCode !== 0) {
            // Fallback na PHP GD
            return $this->convert_to_webp($folder, $new_file_name, $quality);
        }

        // Získat počet CPU jader
        $cpuCores = (int) shell_exec('nproc');
        if ($cpuCores < 1) {
            $cpuCores = 4;
        }

        // cwebp příkaz s multi-threading
        // -m metoda (0-6): 0=nejrychlejší, 6=nejlepší kvalita
        // -q kvalita (0-100)
        // -mt použít multi-threading
        $command = sprintf(
            'cwebp -q %d -m %d -mt %s -o %s 2>&1',
            $quality,
            $method,
            escapeshellarg($this->url),
            escapeshellarg($new_file_path)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($new_file_path)) {
            throw new Exception("cwebp failed with code {$returnCode}.\nCommand: {$command}\nOutput: " . implode("\n", $output));
        }

        if (filesize($new_file_path) === 0) {
            @unlink($new_file_path);
            throw new Exception("cwebp created empty file.\nCommand: {$command}\nOutput: " . implode("\n", $output));
        }

        return new Image($new_file_path);
    }

    /**
     * Batch convert multiple images to WebP (parallel processing)
     * @param array $images Array of Image objects
     * @param string $folder Output folder
     * @param int $quality Quality (0-100)
     * @param int $maxParallel Maximum parallel processes (default: CPU cores)
     * @return array Array of converted Image objects
     */
    public static function batch_convert_to_webp($images, $folder, $quality = 80, $maxParallel = null)
    {
        if ($maxParallel === null) {
            $maxParallel = (int) shell_exec('nproc') ?: 4;
        }

        $results = [];
        $commands = [];

        // Připravit všechny příkazy
        foreach ($images as $img) {
            $outputFile = $folder . '/' . $img->get_name() . '.webp';

            // Zkontrolovat, jestli cwebp existuje
            exec('which cwebp 2>&1', $checkOutput, $checkReturnCode);

            if ($checkReturnCode === 0) {
                // Použít cwebp (rychlejší)
                $commands[] = [
                    'cmd' => sprintf(
                        'cwebp -q %d -m 4 -mt %s -o %s 2>&1',
                        $quality,
                        escapeshellarg($img->url),
                        escapeshellarg($outputFile)
                    ),
                    'output' => $outputFile,
                    'source' => $img
                ];
            } else {
                // Fallback na PHP GD (pomalejší, ale funguje vždy)
                try {
                    $results[] = $img->convert_to_webp($folder, null, $quality);
                } catch (Exception $e) {
                    error_log("WebP conversion failed for {$img->url}: " . $e->getMessage());
                }
            }
        }

        // Spustit příkazy paralelně
        if (!empty($commands)) {
            $chunks = array_chunk($commands, $maxParallel);

            foreach ($chunks as $chunk) {
                $handles = [];

                foreach ($chunk as $cmdData) {
                    $descriptorspec = [
                        0 => ["pipe", "r"],
                        1 => ["pipe", "w"],
                        2 => ["pipe", "w"]
                    ];

                    $process = proc_open($cmdData['cmd'], $descriptorspec, $pipes);

                    if (is_resource($process)) {
                        $handles[] = [
                            'process' => $process,
                            'pipes' => $pipes,
                            'output' => $cmdData['output']
                        ];
                    }
                }

                // Počkat na dokončení všech procesů v této dávce
                foreach ($handles as $handle) {
                    fclose($handle['pipes'][0]);
                    fclose($handle['pipes'][1]);
                    fclose($handle['pipes'][2]);
                    proc_close($handle['process']);

                    if (file_exists($handle['output'])) {
                        $results[] = new Image($handle['output']);
                    }
                }
            }
        }

        return $results;
    }


    /**
     * Try to convert using PHP GD
     * @param string $outputPath Output file path
     * @param int $quality Quality (0-100)
     * @return bool Success
     */
    public function convertToAvifUsingGD($outputPath, $quality)
    {
        try {
            list($originalWidth, $originalHeight, $imageType) = getimagesize($this->url);

            switch ($imageType) {
                case IMAGETYPE_AVIF:
                    $sourceImage = @imagecreatefromavif($this->url);
                    break;
                case IMAGETYPE_BMP:
                    $sourceImage = imagecreatefrombmp($this->url);
                    break;
                case IMAGETYPE_GIF:
                    $sourceImage = imagecreatefromgif($this->url);
                    break;
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($this->url);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($this->url);
                    break;
                case IMAGETYPE_WEBP:
                    $sourceImage = imagecreatefromwebp($this->url);
                    break;
                default:
                    return false;
            }

            if ($sourceImage === false) {
                return false;
            }

            $exifData = @exif_read_data($this->url);
            if (!empty($exifData['Orientation'])) {
                switch ($exifData['Orientation']) {
                    case 3:
                        $sourceImage = imagerotate($sourceImage, 180, 0);
                        break;
                    case 6:
                        $sourceImage = imagerotate($sourceImage, -90, 0);
                        $temp = $originalWidth;
                        $originalWidth = $originalHeight;
                        $originalHeight = $temp;
                        break;
                    case 8:
                        $sourceImage = imagerotate($sourceImage, 90, 0);
                        $temp = $originalWidth;
                        $originalWidth = $originalHeight;
                        $originalHeight = $temp;
                        break;
                }
            }

            $newImage = imagecreatetruecolor($originalWidth, $originalHeight);
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparentColor = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefill($newImage, 0, 0, $transparentColor);

            imagecopy($newImage, $sourceImage, 0, 0, 0, 0, $originalWidth, $originalHeight);

            $result = @imageavif($newImage, $outputPath, $quality);

            imagedestroy($sourceImage);
            imagedestroy($newImage);

            return $result !== false && file_exists($outputPath);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Convert image using external avifenc tool with multi-threading
     * @param string $source Source image path
     * @param string $destination Destination AVIF path
     * @param int $quality Quality (0-100)
     * @throws Exception if conversion fails
     */
    private function convertUsingAvifenc($source, $destination, $quality = 80)
    {
        // Zkontrolovat, zda zdrojový soubor existuje
        if (!file_exists($source)) {
            throw new Exception("Source file does not exist: {$source}");
        }

        // Zkontrolovat dostupnost avifenc
        exec('which avifenc 2>&1', $checkOutput, $checkReturnCode);

        if ($checkReturnCode !== 0) {
            throw new Exception("avifenc not found. Install: sudo apt install libavif-bin");
        }

        // Převést kvalitu (0-100) na rychlost (0-10, kde 0=nejpomalejší/nejlepší)
        $speed = max(0, min(10, (int) ((100 - $quality) / 10)));

        // Získat počet CPU jader
        $cpuCores = (int) shell_exec('nproc');
        if ($cpuCores < 1) {
            $cpuCores = 4; // Fallback
        }

        // Pokud je zdrojový soubor AVIF, vytvořit dočasný JPEG
        $tempSource = $source;
        $needsCleanup = false;

        $imageType = @exif_imagetype($source);
        if ($imageType === IMAGETYPE_AVIF) {
            $tempSource = sys_get_temp_dir() . '/' . uniqid('avif_convert_') . '.jpg';
            $img = @imagecreatefromavif($source);
            if ($img === false) {
                throw new Exception("Failed to load AVIF source image");
            }
            imagejpeg($img, $tempSource, 95);
            imagedestroy($img);
            $needsCleanup = true;
        }

        // Vytvořit příkaz pro avifenc s multi-threading
        // --jobs X - počet paralelních workerů
        // -c aom - použít AOM kodek (podporuje multi-threading)
        // nebo -c rav1e - rychlejší, ale menší komprese
        $command = sprintf(
            'avifenc --jobs %d -c aom -s %d -q %d %s %s 2>&1',
            $cpuCores,
            $speed,
            $quality,
            escapeshellarg($tempSource),
            escapeshellarg($destination)
        );

        // Spustit konverzi
        exec($command, $output, $returnCode);

        // Vyčistit dočasný soubor
        if ($needsCleanup && file_exists($tempSource)) {
            @unlink($tempSource);
        }

        // Zkontrolovat výsledek
        if ($returnCode !== 0) {
            $error = implode("\n", $output);
            throw new Exception("avifenc failed with code {$returnCode}.\nCommand: {$command}\nOutput: {$error}");
        }

        if (!file_exists($destination)) {
            throw new Exception("avifenc did not create output file.\nCommand: {$command}\nOutput: " . implode("\n", $output));
        }

        if (filesize($destination) === 0) {
            @unlink($destination);
            throw new Exception("avifenc created empty file.\nCommand: {$command}\nOutput: " . implode("\n", $output));
        }
    }

    /**
     * Convert image using external avifenc tool - FAST version
     * @param string $source Source image path
     * @param string $destination Destination AVIF path
     * @param int $quality Quality (0-100)
     * @throws Exception if conversion fails
     */
    public function convertUsingAvifencFast($source, $destination, $quality = 80)
    {
        if (!file_exists($source)) {
            throw new Exception("Source file does not exist: {$source}");
        }

        exec('which avifenc 2>&1', $checkOutput, $checkReturnCode);

        if ($checkReturnCode !== 0) {
            throw new Exception("avifenc not found. Install: sudo apt install libavif-bin");
        }

        $cpuCores = (int) shell_exec('nproc');
        if ($cpuCores < 1) {
            $cpuCores = 4;
        }

        $tempSource = $source;
        $needsCleanup = false;

        $imageType = @exif_imagetype($source);
        if ($imageType === IMAGETYPE_AVIF) {
            $tempSource = sys_get_temp_dir() . '/' . uniqid('avif_convert_') . '.jpg';
            $img = @imagecreatefromavif($source);
            if ($img === false) {
                throw new Exception("Failed to load AVIF source image");
            }
            imagejpeg($img, $tempSource, 95);
            imagedestroy($img);
            $needsCleanup = true;
        }

        // RAV1E kodek - mnohem rychlejší než AOM, ale o trochu menší komprese
        // Dobré pro web servery s vysokým provozem
        $command = sprintf(
            'avifenc --jobs %d -c rav1e -s 6 -q %d %s %s 2>&1',
            $cpuCores,
            $quality,
            escapeshellarg($tempSource),
            escapeshellarg($destination)
        );

        exec($command, $output, $returnCode);

        if ($needsCleanup && file_exists($tempSource)) {
            @unlink($tempSource);
        }

        if ($returnCode !== 0) {
            $error = implode("\n", $output);
            throw new Exception("avifenc failed with code {$returnCode}.\nCommand: {$command}\nOutput: {$error}");
        }

        if (!file_exists($destination)) {
            throw new Exception("avifenc did not create output file.\nCommand: {$command}\nOutput: " . implode("\n", $output));
        }

        if (filesize($destination) === 0) {
            @unlink($destination);
            throw new Exception("avifenc created empty file.\nCommand: {$command}\nOutput: " . implode("\n", $output));
        }
    }
}