<?php

/**
 * Data Migration Helper
 *
 * Include all helper function which helps to migrate posts
 *
 * @author Anusha Priyamal <anusha@eight25meida.com>
 * @date 2020-05-24
 *
 */
ini_set('max_execution_time', 0);
ini_set('memory_limit', '9999M');

class DataMigrationHelper
{

    private $log_file;

    public function __construct()
    {
        if (defined('CBXPHPSPREADSHEET_PLUGIN_NAME') && file_exists(CBXPHPSPREADSHEET_ROOT_PATH . 'lib/vendor/autoload.php')) {
            //Include PHPExcel
            require_once(CBXPHPSPREADSHEET_ROOT_PATH . 'lib/vendor/autoload.php');
        }
        $this->log_file = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/logs/info.log';
    }

    /**
     * Return associate array for given csv
     * @param string $filename
     * @return array|bool
     */
    function excelToArray($filename)
    {
        if (!file_exists($filename) || !is_readable($filename)) {
            return false;
        }
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filename);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        $results = array();
        for ($i = 1; $i < count($rows); $i++) {
            $header = array_map(function ($col) {
                        return str_replace(' ', '_', strtolower($col));
                    }, $rows[0]);
            $results[] = array_combine($header, $rows[$i]);
        }
        return $results;
    }


    function csvToArray($filename = '', $delimiter = ',')
    {
        if (!file_exists($filename) || !is_readable($filename)) {
            return false;
        }
        $miss_matched = 0;
        $header = null;
        $data = array();
        if (($handle = fopen($filename, 'r')) !== FALSE) {
            while (($row = fgetcsv($handle, 1000000, $delimiter, '"', '\\')) !== FALSE) {
                if (!$header) {
                    //					$header = $row;
                    $header = array_map(function ($col) {
                        return str_replace(' ', '_', strtolower($col));
                    }, $row);
                } else {
                    $id = $row[0];
                    if (count($header) != count($row)) {
                        $miss_matched = 1;
                        echo '<pre>';
                        echo 'Header==>';
                        print_r($header);
                        echo 'Row==>';
                        print_r($row);
                        echo '<pre>';
                        die();
                    }
                    $data[$id] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }


        echo '<pre>';
        var_dump($data);
        echo '</pre>';
        die();

        if (!$miss_matched) {
            return $data;
        }
        echo '<pre>';
        print_r($data);
        echo '<pre>';
    }

    /** D.K. Himas Khan
     * Return associate array for given json
     * @param string $filename
     * @return array|bool
     */
    function jsonToArray($filename)
    {
        if (!file_exists($filename) || !is_readable($filename)) {
            return false;
        }

        $jsonData = file_get_contents($filename);
        $results = json_decode($jsonData, true);

        return $results;
    }

    /**
     * Write logs
     * @param $message
     * @param string $type
     */
    function writeLog($message, $type = 'Success')
    {
        $message = date('Y-d-m H:i:s : ') . strtoupper($type) . ' : ' . $message;
        //Update log file
        file_put_contents($this->log_file, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Check if post is exist by given post type and post type
     * @param $post_slug
     * @param string $post_type
     * @return bool|int
     */
    function isPostExistsBySlug($post_slug, $post_type = 'post')
    {
        $args_posts = array(
            'post_type' => $post_type,
            'post_status' => ['publish','draft'],
            'name' => $post_slug,
            'posts_per_page' => 1,
        );
        $loop_posts = new WP_Query($args_posts);
        if (!$loop_posts->have_posts()) {
            return false;
        } else {
            $loop_posts->the_post();
            return $loop_posts->post->ID;
        }
    }

    /**
     * Check whether given URL is working or not
     * @param $url
     * @return bool
     */
    function isURLExists($url)
    {
        // Use condition to check the existence of URL
        $file_headers = @get_headers($url);
        if (!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
            $exists = false;
        } else {
            $exists = true;
        }
        return $exists;
    }

    /**
     * Upload image and set it as featured image for given post
     * Ref: https://www.wpastronaut.com/blog/upload-files-wordpress-programmatically/
     * @param $file
     * @param int $post_id
     * @param null $desc
     * @return int|WP_Error
     */
    function uploadAndAttachedImageToPost($file, $post_id = 0, $desc = null, $thumb = TRUE)
    {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        if (empty($file)) {
            return new \WP_Error('error', 'File is empty');
        }

        $file_array = array();

        // Get filename and store it into $file_array
        // Add more file types if necessary
        preg_match('/[^\?]+\.(jpe?g|jpe|gif|png|pdf)\b/i', $file, $matches);
        $file_array['name'] = basename($matches[0]);

        // Download file into temp location.
        $file_array['tmp_name'] = download_url($file);

        // If error storing temporarily, return the error.
        if (is_wp_error($file_array['tmp_name'])) {
            return new \WP_Error('error', 'Error while storing file temporarily');
        }

        // Store and validate
        $id = media_handle_sideload($file_array, $post_id, $desc);

        // Unlink if couldn't store permanently
        if (is_wp_error($id)) {
            unlink($file_array['tmp_name']);
            return new \WP_Error('error', "Couldn't store upload permanently");
        }

        if (empty($id)) {
            return new \WP_Error('error', "Upload ID is empty");
        }
        if($thumb){
            add_post_meta($post_id, '_thumbnail_id', $id, true);
        }
        
        return $id;
    }


    /**
     * replace unwanted chars from the post content
     * @param $post_content
     * @return string|string[]
     */
    function replaceUnwantedChars($post_content)
    {
        $post_content = preg_replace('/\xc2\xa0/', ' ', $post_content);
        $post_content = str_replace("—", '&mdash;', $post_content);
        $post_content = str_replace("–", '&ndash;', $post_content);
        $post_content = str_replace("°", '&deg;', $post_content);
        $post_content = str_replace("“", '&ldquo;', $post_content);
        $post_content = str_replace("”", '&rdquo;', $post_content);
        $post_content = str_replace("", ' ', $post_content);
        $post_content = str_replace("‘", '&lsquo;', $post_content);
        $post_content = str_replace("’", '&rsquo;', $post_content);
        $post_content = str_replace("®", '&reg;', $post_content);
        $post_content = str_replace("©", '&copy;', $post_content);
        $post_content = str_replace("™", '&trade;', $post_content);
        $post_content = str_replace("ó", '&oacute;', $post_content);
        $post_content = str_replace("£", '&pound;', $post_content);
        $post_content = str_replace("€", '&euro;', $post_content);
        $post_content = str_replace("Ć", '&#262;', $post_content);
        $post_content = str_replace("…", '&hellip;', $post_content);
        $post_content = str_replace("è", '&egrave;', $post_content);


        $post_content = str_replace(array("\r\n", "\n", "\r", "\t"), ' ', $post_content);

        $regex = '/(?![^<]*>)"(.*?)(?<!=)"(?!>)/';
        $subst = '&quot;$1&quot;';
        $post_content = preg_replace($regex, $subst, $post_content);
        $post_content = str_replace("\"", "'", $post_content);

        return $post_content;
    }

    /**
     * Get lines from given string
     * @param $content
     * @param $lineNo
     * @return string
     */
    function getLinesFromContent($content, $lineNo)
    {
        $i = 1;
        $result = '';
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $content) as $line) {
            $result .= $line;
            if ($i == $lineNo) {
                return $result;
            }
            $i++;
        }
    }

    /**
     * clear the log file content
     */
    function clearLogFile()
    {
        file_put_contents($this->log_file, '');
    }

    /**
     * get post category ids from given category names
     * @param $post_categories
     * @param string $delimiter
     * @return array
     */
    function getCategoryIds($post_categories, $delimiter = '|')
    {
        $post_category_ids_arr = [];
        $post_categories_arr = explode($delimiter, $post_categories);
        if (is_array($post_categories_arr) && !empty($post_categories_arr)) {
            foreach ($post_categories_arr as $post_category) {
                $post_category_ids_arr[] = get_cat_ID($post_category);
            }
        }
        return $post_category_ids_arr;
    }
}
