<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
/**
 * Blog Data Migration
 *
 */

require_once('DataMigrationHelper.php');

class BlogDataMigration
{
    protected $data_migration_helper;

    /**
     * BlogDataMigration constructor.
     */
    public function __construct()
    {
        $this->data_migration_helper = new DataMigrationHelper();
        $this->data_migration_helper->clearLogFile();
    }


    /**
     * Create posts from json data
     * @param $file
     * @param null $offset
     * @param null $length
     * @return string
     */
    public function importDataFromFile($file, $offset = null, $length = null)
    {
        $message = '';

        $inputFileNameUsers = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/json/users-09-01-2023.json';
        $inputFileNameCatogeries = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/json/blog-categories-09-01-2023.json';
        $inputFileNameImages = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/json/images-09-01-2023.json';
        
        $file_data_array = $this->data_migration_helper->jsonToArray($file);

        $file_data_array_users = $this->data_migration_helper->jsonToArray($inputFileNameUsers);
        $file_data_array_catogeries = $this->data_migration_helper->jsonToArray($inputFileNameCatogeries);
        $file_data_array_images = $this->data_migration_helper->jsonToArray($inputFileNameImages);

        if (isset($offset) && isset($length)) {
            $file_data_array = array_slice($file_data_array, $offset, $length);
        }

        // echo '<pre>';
        // var_dump($file_data_array);
        // echo '</pre>';
//exit();
        if (is_array($file_data_array) && !empty($file_data_array)) {
            $total = count($file_data_array);

            // $unpublished_or_draft_count = 0;
            // $published_count = 0;

            $i = 0;
            $success_records = 0;
            foreach ($file_data_array as $row) { //var_dump($row['title']); //exit();
                $post_title = $row['title'];
                $post_excerpt = ($row['secondaryTitle'])?$row['secondaryTitle']:"";
                $post_content = $row['body'];
                $post_content_bottom = $row['blogCta'];
                $post_status = ($row['status']=="live")?"publish":"draft";
                $post_slug =  $row['slug'];
                $post_date = $row['postDate'];
                $resource_type = "Blogs";
                $blog_tags = $row['tags'];
                $meta_title = ($row['blogSeoNew']['metaGlobalVars']['seoTitle'])?$row['blogSeoNew']['metaGlobalVars']['seoTitle']:$row['title'];
                $meta_description = ($row['blogSeoNew']['metaGlobalVars']['seoDescription'])?$row['blogSeoNew']['metaGlobalVars']['seoDescription']:$row['title'];

                //import users
                $post_author_id = $row['authorId'];
                if (is_array($file_data_array_users) && !empty($file_data_array_users)) { 
                    foreach ($file_data_array_users as $rowa) { 
                        if($post_author_id == $rowa['id']){
                            $adminUsr = ($rowa['admin']==1)?'administrator':'author';
                            $user_id = username_exists( $rowa['username'] );
                            if (!$user_id) {
                                $userdata = array(
                                    'user_login' => $rowa['username'],
                                    'user_pass'  => null,
                                    'first_name' => $rowa['firstName'],
                                    'last_name' => $rowa['lastName'],
                                    'user_email' => $rowa['email'],
                                    'user_url' => '',
                                    'role' => $adminUsr
                                );
                                $user_id = wp_insert_user( $userdata );
                            }
                        
                        }
                    }
                }

                //import resource type
                $blog_typ_array = [];
                $termType = term_exists( $resource_type, 'resource-type' );
                if ( $termType == 0 && $termType == null ) { 
                    $insertedTermType =  wp_insert_term($resource_type, 'resource-type'); 
                    if($insertedTermType) {
                        $this->data_migration_helper->writeLog('New term created: Term: ' . $rowc['title'] . 'and ID (' . $insertedTermType['term_id'] . ')', 'success');
                        $blog_typ_array[] = $insertedTermType['term_id']; 
                    }
                }else{
                    $blog_typ_array[] = $termType['term_id'];
                }

                //import categories
                $blog_cat_array = [];
                if (is_array($file_data_array_catogeries) && !empty($file_data_array_catogeries)) { 
                    foreach ($file_data_array_catogeries as $rowc) {
                        if(in_array($rowc['id'], $row['categories'])){  
                            $term = term_exists( $rowc['title'], 'blog-category' );
                            if ( $term == 0 && $term == null ) { 
                                $insertedTerm =  wp_insert_term($rowc['title'], 'blog-category'); 
                                if($insertedTerm) {
                                    $this->data_migration_helper->writeLog('New term created: Term: ' . $rowc['title'] . 'and ID (' . $insertedTerm['term_id'] . ')', 'success');
                                    $blog_cat_array[] = $insertedTerm['term_id']; 
                                }
                            }else{
                                $blog_cat_array[] = $term['term_id'];
                            }
                        }
                    }
                }

                //if ($post_status == 'publish') {
                  //  $published_count++;

                    if ($this->data_migration_helper->isPostExistsBySlug($post_slug, 'sw_resource')) {

                        $post_id = $this->data_migration_helper->isPostExistsBySlug($post_slug, 'sw_resource');

                        $this->data_migration_helper->writeLog('Post already exist by slug. Slug: ' . $post_slug, 'error');
                        continue;
                    }

                    $post_data = array(
                        'post_title' => $post_title,
                        'post_excerpt' => $post_excerpt,
                        'post_name' => $post_slug,
                        'post_status' => $post_status,
                        'post_type' => 'sw_resource',
                        'post_date' => $post_date,
                        'tax_input' => array(
                            'resource-type' => $blog_typ_array,
                            'blog-category' => $blog_cat_array,
                        ),
                        'meta_input' => array(
                            'post_tags' => $blog_tags,
                            '_meta_title' => $meta_title,
                            '_meta_description' => $meta_description,
                            '_yoast_wpseo_metadesc' => $meta_description,
                            '_yoast_wpseo_title' => $meta_title,
                        ),
                        'post_author' => $user_id
                    );

                    $result = wp_insert_post($post_data, true); // insert the post and allow WP_Error object

                    if (is_wp_error($result)) {
                        $this->data_migration_helper->writeLog($result->get_error_message() . ' Post: ' . $post_title, 'error');
                    } else {
                        $post_id = $result;
                        $this->data_migration_helper->writeLog('----------------------------ROW: ' . $i . '-----------------------------------------------------', 'success');
                        $this->data_migration_helper->writeLog('post created success: Post ID: ' . $post_id, 'success');

                        //import images
                        if (is_array($file_data_array_images) && !empty($file_data_array_images)) { 
                            foreach ($file_data_array_images as $rowi) {
                                if(in_array($rowi['id'], $row['blogImage'])){  //var_dump($rowi['url']);
                                    $featured_img_id = $this->attachImage($rowi['url'], $post_id, TRUE);
                                    $feature_img_url = '';
                                    if ($featured_img_id) {
                                        $feature_img_url = wp_get_attachment_url($featured_img_id);
                                    }
                                }
                            }
                        }

                        $post_content = $this->setPageLayout($post_content, $post_id, $post_excerpt, $post_content_bottom);

                        $result = wp_update_post(wp_slash(array(
                            'ID' => $post_id,
                            'post_content' => $post_content,
                        )));

                        if (is_wp_error($result)) {
                            $this->data_migration_helper->writeLog($result->get_error_message() . ' Post: ' . $post_title, 'error');
                        } else {
                            $this->data_migration_helper->writeLog('post content update success: Post ID: ' . $post_id, 'success');
                        }

                        $success_records++;
                    }
                    $i++;
                // } else {
                //     $unpublished_or_draft_count++;
                // }
            }
            if ($success_records > 0) {
                // Tell user that the process is completed
                $message = 'Total posts count: ' . $total .
                    // '<br>' . 'Published posts count: ' . $published_count .
                    // '<br>' . 'Draft or Unpublished posts count: ' . $unpublished_or_draft_count .
                    '<br>' . 'Imported posts count: ' . $success_records;
            } else {
                $message = 'something went wrong. see log file for more details';
            }
        } else {
            $message = 'something wrong in file.';
        }
        return $message;
    }

    /**
     * Get current user id for given old user id from users mapping table
     * @param $old_user_id
     * @return mixed
     */
    function getNewUserIdByOldUserId($old_user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "users_mapping";
        $user = $wpdb->get_results("SELECT * FROM {$table_name} WHERE old_user_id = {$old_user_id}");
        if (!empty($user) && is_array($user)) {
            return $user[0]->new_user_id;
        }
    }

    function getUserIdByEmail($email)
    {
        $user = get_user_by('email', $email);
        if ($user) {
            return $user->ID;
        }
    }

    function get_user_id_by_name($login_name)
    {
        //$login_name = str_replace(" ", "_", strtolower($full_name));
        $user = get_user_by('login', $login_name);
        if ($user) {
            return $user->ID;
        } else {
            return '1'; // Returning the default admin user id 
        }
    }

    function get_user_email_by_name($full_name)
    {
        $login_name = str_replace(" ", "_", strtolower($full_name));
        $user = get_user_by('login', $login_name);
        if ($user) {
            return $user->user_email;
        }
    }

    // function get_post_slug_by_url($post_url, $post_status)
    // {
    //     if ($post_status == 'publish') {
    //         return str_replace("https://www.nano-di.com/blog/","",  $post_url);
    //     }
    //     return '';
    // }

    function get_post_status_label($status)
    {
        $status = ($status == 'publish') ? 'publish' : 'draft';
        return $status;
    }

    /**
     * Attach post featured image for given post id and post image url
     * @param $post_image_url
     * @param $post_id
     * @return bool|int|WP_Error
     */
    function attachImage($post_image_url, $post_id, $thumb)
    {
        if (!empty($post_image_url)) {
            $post_image_url_list = explode('|', $post_image_url);
            $post_image_url = $post_image_url_list[0];
            if ($this->data_migration_helper->isURLExists($post_image_url)) {
                $result = $this->data_migration_helper->uploadAndAttachedImageToPost($post_image_url, $post_id, $thumb);
                if (is_wp_error($result)) {
                    $this->data_migration_helper->writeLog('image upload fail for post : Post ID: ' . $post_id . ' => ' . $result->get_error_message(), 'error');
                    return false;
                } else {
                    $this->data_migration_helper->writeLog('image uploaded and attached to the post : Post ID:' . $post_id . ' Attachment ID: ' . $result, 'success');
                    return $result;
                }
            } else {
                $this->data_migration_helper->writeLog('image cannot access from the url : URL: ' . $post_image_url . ' Post ID:' . $post_id, 'error');
                return false;
            }
        }
    }

    function attachImagetoBodyDownload($imageId, $post_id){

        $inputFileNameImages = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/json/images-09-01-2023.json';
        $file_data_array_images = $this->data_migration_helper->jsonToArray($inputFileNameImages);

        if (is_array($file_data_array_images) && !empty($file_data_array_images)) { 
            foreach ($file_data_array_images as $rowi) {
                if(in_array($rowi['id'], $imageId)){  //var_dump($rowi['url']);
                    $featured_img_id = $this->attachImage($rowi['url'], $post_id, FALSE); //var_dump($featured_img_id);
                    return $featured_img_id;
                }
            }
        }
    }

    function setPageLayout($post_content, $post_id, $post_excerpt, $post_content_bottom)
    {
        $arrCat = [];
        $post = get_the_terms( $post_id, 'resource-type' );   //var_dump($post);
        // $post = wp_get_post_terms($post_id); var_dump($post_id);
        foreach($post as $c){ 
            if($c->taxonomy=="category"){
                $category_id = get_cat_ID($c->name);
            }else{
                $term = get_term_by('slug', $c->slug, $c->taxonomy); // depends on post type nad taxonomy
                $category_id = $term->term_id;
            }
            $arrCat[] = array( 'label' => $c->name, 'value' => $category_id, 'type' => $c->taxonomy);
        }
//var_dump($arrCat);
        
        if(!empty($post_content_bottom)){
            foreach($post_content_bottom as $psb){
                $psbTitle = $psb['fields']['heading'];
                $psbBody = str_replace(['<p>', '</p>', '<p dir="ltr">'], '',$psb['fields']['body']);;
                $psbImage = $psb['fields']['image'];
                $psblinkUrl = $psb['fields']['linkUrl'];
                $psblinkLabel = ($psb['fields']['linkLabel'])?$psb['fields']['linkLabel']:"Read More";
                $psbnewTab = $psb['fields']['newTab'];
            }

            $newTab="";
            $linkOpenType = "";
            if($psbnewTab==TRUE){ $newTab='target="_blank"'; $linkOpenType = ',"linkOpenType":"true"'; }

            $bodyImageID = $this->attachImagetoBodyDownload($psbImage, $post_id);
            $bodyImageIDUrl = '';
            $bodyMedia = '';
            if ($bodyImageID) {
                $bodyImageIDUrl = wp_get_attachment_url($bodyImageID); //var_dump($bodyImageIDUrl);
                $bodyMedia = '<!-- wp:e25m/media-elements {"image":'.$bodyImageID.',"image_url":"'.$bodyImageIDUrl.'"} /-->';
            }

            $pc_bottom = '<!-- wp:e25m/div {"divBlockID":"3976ad67b0e56002b425580d511756b46bff9ee9","divClassNames":[{"value":"bs-div\u002d\u002d-default","label":"Default"},{"value":"bs-div\u002d\u002dblog-inner-download-guide","label":"Blog Inner Download Guide"}],"extraSmallFlexClassNames":[{"value":"d-flex","label":"d-flex"},{"value":"flex-wrap","label":"flex-wrap"},{"value":"justify-content-center","label":"justify-content-center"}],"mediumFlexClassNames":[{"value":"flex-md-row-reverse","label":"flex-md-row-reverse"},{"value":"align-items-md-center","label":"align-items-md-center"},{"value":"justify-content-md-between","label":"justify-content-md-between"},{"value":"flex-md-nowrap","label":"flex-md-nowrap"}],"backgroundTypeDiv":"background_image","backgroundImageOptions":{"regular":{"settings":{"divImgId":4619,"divImgUrl":"https://swimelane-uploads.s3.amazonaws.com/uploads/2022/10/download-report.png","divImgAlt":"","image2xId":"","image2xUrl":"","image2xAlt":""}},"desktop":{"breakpoint":1280,"settings":{"divImgId":"","divImgUrl":"","divImgAlt":"","image2xId":"","image2xUrl":"","image2xAlt":""}},"mobile":{"breakpoint":575,"settings":{"divImgId":"","divImgUrl":"","divImgAlt":"","image2xId":"","image2xUrl":"","image2xAlt":""}}}} -->
                <div class="bs-div bs-div-3976ad67b0e56002b425580d511756b46bff9ee9 bs-div---default bs-div--blog-inner-download-guide"><style>.bs-div.bs-div-3976ad67b0e56002b425580d511756b46bff9ee9 {background-image: url(https://swimelane-uploads.s3.amazonaws.com/uploads/2022/10/download-report.png); background-position: center center;
                    background-size: cover;} </style><div class="bs-div__inner d-flex flex-wrap justify-content-center  flex-md-row-reverse align-items-md-center justify-content-md-between flex-md-nowrap  ">'.$bodyMedia.'
                
                <!-- wp:e25m/div {"divBlockID":"2aebcd1b2c11849d7c87d8462be32842b8c42b50"} -->
                <div class="bs-div bs-div-2aebcd1b2c11849d7c87d8462be32842b8c42b50 bs-div---default"><div class="bs-div__inner     "><!-- wp:heading {"textColor":"white"} -->
                <h2 class="has-white-color has-text-color">'.$psbTitle.'</h2>
                <!-- /wp:heading -->
                <!-- wp:paragraph {"textColor":"white"} -->
                <p class="has-white-color has-text-color">'.$psbBody.'</p>
                <!-- /wp:paragraph -->
                <!-- wp:e25m/pro-button {"popupID":"p-btn-643e780f4c9b9cea74bbb1872189ff1253ad04e5","customUrl":"'.$psblinkUrl.'","text":"Click here"'.$linkOpenType.',"buttonClassNames":[{"value":"bs-pro-button\u002d\u002d-default","label":"Default"},{"value":"bs-pro-button\u002d\u002dprimary-with-arrow-small","label":"Primary With Arrow Small"}]} -->
                <span class="bs-pro-button bs-pro-button---default bs-pro-button--primary-with-arrow-small bs-pro-button-p-btn-643e780f4c9b9cea74bbb1872189ff1253ad04e5"><style>.bs-pro-button-p-btn-643e780f4c9b9cea74bbb1872189ff1253ad04e5 .bs-pro-button__container {background-color: #abb8c3; color: #000000;}</style><a href="'.$psblinkUrl.'" '.$newTab.' rel="noopener noreferrer" class="bs-pro-button__container">'.ucwords($psblinkLabel).'</a></span>
                <!-- /wp:e25m/pro-button --></div></div>
                <!-- /wp:e25m/div --></div></div>
                <!-- /wp:e25m/div -->';
            }

        $postData = '<h2>'.$post_excerpt.'</h2>'.$post_content;
        $bodyContent = '<!-- wp:freeform -->%s<!-- /wp:freeform -->';
        if ( has_blocks($postData) ) { $bodyContent = '%s'; }

        $post_content = sprintf('<!-- wp:e25m/mobile-content -->
        <!-- wp:e25m/desktop-content-wrapper -->
        <!-- wp:e25m/section {"sectionBlockID":"f49668dca89a07af4c4bed27713f079b6839f643","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dblog-inner-banner","label":"Blog Inner Banner"}],"backgroundType":"background_color","backgroundColorOptions":{"regular":{"settings":{"backgroundColor":"#000743"}},"desktop":{"breakpoint":1280,"settings":{"backgroundColor":""}},"mobile":{"breakpoint":575,"settings":{"backgroundColor":""}}}} -->
        <section class="bs-section bs-section-f49668dca89a07af4c4bed27713f079b6839f643 bs-section---default bs-section--blog-inner-banner  "><style>.bs-section.bs-section-f49668dca89a07af4c4bed27713f079b6839f643{ background-color: #000743;} </style><div class="container"><!-- wp:e25m/row {"directionClass":"flex-md-row-reverse"} -->
        <div class="bs-row row  flex-md-row-reverse bs-row---default"><!-- wp:e25m/column {"sizeMd":12,"sizeLg":6,"colClassList":["bs-column","col-sm-12","col-md-12","col-lg-6"],"columnClassNames":[{"value":"bs-column\u002d\u002d-default","label":"Default"},{"value":"bs-column\u002d\u002dright","label":"Right"}],"extraSmallFlexClassNames":[{"value":"d-flex","label":"d-flex"},{"value":"flex-column","label":"flex-column"},{"value":"justify-content-end","label":"justify-content-end"}],"columnBlockID":"3c02e72bbbdd27fbc2206a57dc520373f8b450b2"} -->
        <div class=" bs-column col-sm-12 col-md-12 col-lg-6   bs-column-3c02e72bbbdd27fbc2206a57dc520373f8b450b2 bs-column---default bs-column--right d-flex flex-column justify-content-end    "><!-- wp:post-featured-image /--></div>
        <!-- /wp:e25m/column -->
        
        <!-- wp:e25m/column {"sizeMd":12,"sizeLg":6,"colClassList":["bs-column","col-sm-12",null,"col-md-12","col-lg-6"],"extraSmallFlexClassNames":[{"value":"d-flex","label":"d-flex"},{"value":"flex-column","label":"flex-column"}],"columnBlockID":"6a13826d98ae006805bf00373c567a95c3c65a9a"} -->
        <div class=" bs-column col-sm-12  col-md-12 col-lg-6   bs-column-6a13826d98ae006805bf00373c567a95c3c65a9a bs-column---default d-flex flex-column    "><!-- wp:post-date {"format":"M j, Y"} /-->
        
        <!-- wp:post-title {"level":1,"textColor":"white"} /-->
        
        <!-- wp:e25m/div {"divBlockID":"f106fb945b2c4610a440b9e5b4f63c0c1cbbec02","extraSmallFlexClassNames":[{"value":"d-flex","label":"d-flex"},{"value":"flex-wrap","label":"flex-wrap"},{"value":"align-items-center","label":"align-items-center"}]} -->
        <div class="bs-div bs-div-f106fb945b2c4610a440b9e5b4f63c0c1cbbec02 bs-div---default"><div class="bs-div__inner d-flex flex-wrap align-items-center    "><!-- wp:e25m-custom/author {"prefix":""} /-->
        
        <!-- wp:e25m-custom/read-time {"descriptionTag":"p"} /--></div></div>
        <!-- /wp:e25m/div --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/desktop-content-wrapper -->
        
        <!-- wp:e25m/mobile-content-wrapper -->
        <!-- wp:e25m/section {"sectionBlockID":"f49668dca89a07af4c4bed27713f079b6839f643","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dblog-inner-banner","label":"Blog Inner Banner"}],"backgroundType":"background_color","backgroundColorOptions":{"regular":{"settings":{"backgroundColor":"#000743"}},"desktop":{"breakpoint":1280,"settings":{"backgroundColor":""}},"mobile":{"breakpoint":575,"settings":{"backgroundColor":""}}}} -->
        <section class="bs-section bs-section-f49668dca89a07af4c4bed27713f079b6839f643 bs-section---default bs-section--blog-inner-banner  "><style>.bs-section.bs-section-f49668dca89a07af4c4bed27713f079b6839f643{ background-color: #000743;} </style><div class="container"><!-- wp:e25m/row {"directionClass":"flex-md-row-reverse"} -->
        <div class="bs-row row  flex-md-row-reverse bs-row---default"><!-- wp:e25m/column {"sizeMd":12,"sizeLg":6,"colClassList":["bs-column","col-sm-12","col-md-12","col-lg-6"],"columnClassNames":[{"value":"bs-column\u002d\u002d-default","label":"Default"},{"value":"bs-column\u002d\u002dright","label":"Right"}],"extraSmallFlexClassNames":[{"value":"d-flex","label":"d-flex"},{"value":"flex-column","label":"flex-column"},{"value":"justify-content-end","label":"justify-content-end"}],"columnBlockID":"3c02e72bbbdd27fbc2206a57dc520373f8b450b2"} -->
        <div class=" bs-column col-sm-12 col-md-12 col-lg-6   bs-column-3c02e72bbbdd27fbc2206a57dc520373f8b450b2 bs-column---default bs-column--right d-flex flex-column justify-content-end    "><!-- wp:post-featured-image /--></div>
        <!-- /wp:e25m/column -->
        
        <!-- wp:e25m/column {"sizeMd":12,"sizeLg":6,"colClassList":["bs-column","col-sm-12",null,"col-md-12","col-lg-6"],"extraSmallFlexClassNames":[{"value":"d-flex","label":"d-flex"},{"value":"flex-column","label":"flex-column"}],"columnBlockID":"6a13826d98ae006805bf00373c567a95c3c65a9a"} -->
        <div class=" bs-column col-sm-12  col-md-12 col-lg-6   bs-column-6a13826d98ae006805bf00373c567a95c3c65a9a bs-column---default d-flex flex-column    "><!-- wp:post-date {"format":"M j, Y"} /-->
        
        <!-- wp:post-title {"level":1,"textColor":"white"} /-->
        
        <!-- wp:e25m/div {"divBlockID":"f106fb945b2c4610a440b9e5b4f63c0c1cbbec02","extraSmallFlexClassNames":[{"value":"d-flex","label":"d-flex"},{"value":"flex-wrap","label":"flex-wrap"},{"value":"align-items-center","label":"align-items-center"}]} -->
        <div class="bs-div bs-div-f106fb945b2c4610a440b9e5b4f63c0c1cbbec02 bs-div---default"><div class="bs-div__inner d-flex flex-wrap align-items-center    "><!-- wp:e25m-custom/author {"prefix":""} /-->
        
        <!-- wp:e25m-custom/read-time {"descriptionTag":"p"} /--></div></div>
        <!-- /wp:e25m/div --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/mobile-content-wrapper -->
        <!-- /wp:e25m/mobile-content -->
        
        <!-- wp:e25m/section {"sectionBlockID":"f08f74d0af2cf0ad40ac7f7e7ff47f7939ce6b43","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dblog-inner-table-of-contents","label":"Blog Inner Table Of Contents"}],"backgroundType":"background_color","backgroundColorOptions":{"regular":{"settings":{"backgroundColor":"#eef4fa"}},"desktop":{"breakpoint":1280,"settings":{"backgroundColor":""}},"mobile":{"breakpoint":575,"settings":{"backgroundColor":""}}}} -->
        <section class="bs-section bs-section-f08f74d0af2cf0ad40ac7f7e7ff47f7939ce6b43 bs-section---default bs-section--blog-inner-table-of-contents  "><style>.bs-section.bs-section-f08f74d0af2cf0ad40ac7f7e7ff47f7939ce6b43{ background-color: #eef4fa;} </style><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:heading -->
        <h2>Table of Contents</h2>
        <!-- /wp:heading --></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":6,"colClassList":["bs-column","col-sm-6"],"columnBlockID":"2119ec9d0f7bbcc95647d0ccaa955654ac2a575a"} -->
        <div class=" bs-column col-sm-6   bs-column-2119ec9d0f7bbcc95647d0ccaa955654ac2a575a bs-column---default     "><!-- wp:list -->
        <ul><!-- wp:list-item -->
        <li><a href="#overview">OVERVIEW</a></li>
        <!-- /wp:list-item -->

        <!-- wp:list-item -->
        <li><a href="#integrate-anything">INTEGRATE ANYTHING</a></li>
        <!-- /wp:list-item -->

        <!-- wp:list-item -->
        <li><a href="#a-system-of-record-for-security">A SYSTEM OF RECORD FOR SECURITY</a></li>
        <!-- /wp:list-item --></ul>
        <!-- /wp:list --></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":6,"colClassList":["bs-column","col-sm-6"],"columnBlockID":"2119ec9d0f7bbcc95647d0ccaa955654ac2a575a"} -->
        <div class=" bs-column col-sm-6   bs-column-2119ec9d0f7bbcc95647d0ccaa955654ac2a575a bs-column---default     "><!-- wp:list -->
        <ul><!-- wp:list-item -->
        <li><a href="#action-at-the-point-of-inception">ACTION AT THE POINT OF INCEPTION</a></li>
        <!-- /wp:list-item -->

        <!-- wp:list-item -->
        <li><a href="#democratized-automation">DEMOCRATIZED AUTOMATION</a></li>
        <!-- /wp:list-item --></ul>
        <!-- /wp:list --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->

        <!-- wp:e25m/section {"sectionBlockID":"050e6505c2b06c7ce9ca858e2f56661a365e6ba8","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dblog-inner-main-contents","label":"Blog Inner Main Contents"}]} -->
        <section class="bs-section bs-section-050e6505c2b06c7ce9ca858e2f56661a365e6ba8 bs-section---default bs-section--blog-inner-main-contents  "><div class="container"><!-- wp:e25m/row {"justifyContentClass":"justify-content-between"} -->
        <div class="bs-row row justify-content-between  bs-row---default"><!-- wp:e25m/column {"sizeMd":1,"colClassList":["bs-column","col-sm-12","col-md-1"],"columnBlockID":"b9d738473a055284b615b4f50be5a383dfe4cc38"} -->
        <div class=" bs-column col-sm-12 col-md-1   bs-column-b9d738473a055284b615b4f50be5a383dfe4cc38 bs-column---default     "><!-- wp:shortcode -->
        [Sassy_Social_Share]
        <!-- /wp:shortcode --></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeMd":11,"sizeLg":8,"colClassList":["bs-column","col-sm-12",null,"col-lg-8","col-md-11"],"columnClassNames":[{"value":"bs-column\u002d\u002d-default","label":"Default"},{"value":"bs-column\u002d\u002dcontents","label":"Contents"}],"columnBlockID":"5fcbee853797bef68c609dd2715f511e3ead78a3"} -->
        <div class=" bs-column col-sm-12  col-lg-8 col-md-11   bs-column-5fcbee853797bef68c609dd2715f511e3ead78a3 bs-column---default bs-column--contents     ">
        '.$bodyContent.'
        '.$pc_bottom.'
        </div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeMd":12,"sizeLg":3,"colClassList":["bs-column","col-sm-12",null,"col-md-12","col-lg-3"],"columnBlockID":"4ffac197d945e44dadadc9d8f52ba4737135ba21"} -->
        <div class=" bs-column col-sm-12  col-md-12 col-lg-3   bs-column-4ffac197d945e44dadadc9d8f52ba4737135ba21 bs-column---default     "><!-- wp:e25m/div {"divBlockID":"ffc71f24880cf5ca65c4a54e87fb14a656cc562d","divClassNames":[{"value":"bs-div\u002d\u002d-default","label":"Default"},{"value":"bs-div\u002d\u002dtags","label":"Tags"}]} -->
        <div class="bs-div bs-div-ffc71f24880cf5ca65c4a54e87fb14a656cc562d bs-div---default bs-div--tags"><div class="bs-div__inner     "><!-- wp:heading -->
        <h2>Tags</h2>
        <!-- /wp:heading -->
        
        <!-- wp:e25m-custom/post-tag {"descriptionTag":"p"} /--></div></div>
        <!-- /wp:e25m/div -->
        
        <!-- wp:e25m/div {"divBlockID":"69c461f15bb5fa3fc09d1aa73a0e5865005218ff","divClassNames":[{"value":"bs-div\u002d\u002d-default","label":"Default"},{"value":"bs-div\u002d\u002drelated-posts","label":"Related Posts"}]} -->
        <div class="bs-div bs-div-69c461f15bb5fa3fc09d1aa73a0e5865005218ff bs-div---default bs-div--related-posts"><div class="bs-div__inner     "><!-- wp:heading -->
        <h2>Related Posts</h2>
        <!-- /wp:heading -->

        <!-- wp:e25m/related-posts {"displayOrder":[{"value":"date","label":"Date"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"postTaxonomies":'.json_encode($arrCat).',"dateFormat":"M j, Y"} -->
        <div class="bs-related-posts bs-related-posts-block---default"><div class="bs-related-posts__container"><div class="bs-related-posts__items"><!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2788"},"dateFormat":"M j, Y","displayOrder":[{"value":"date","label":"Date"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column -->
        
        <!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2764"},"dateFormat":"M j, Y","displayOrder":[{"value":"date","label":"Date"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column -->
        
        <!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2766"},"dateFormat":"M j, Y","displayOrder":[{"value":"date","label":"Date"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column --></div></div></div>
        <!-- /wp:e25m/related-posts --></div></div>
        <!-- /wp:e25m/div -->
        
        <!-- wp:e25m/div {"divBlockID":"5f1a05c47bd78d88ac3be0e745c2c126161649fe","divClassNames":[{"value":"bs-div\u002d\u002d-default","label":"Default"},{"value":"bs-div\u002d\u002dtable-of-contents-right","label":"Table Of Contents Right"}]} -->
        <div class="bs-div bs-div-5f1a05c47bd78d88ac3be0e745c2c126161649fe bs-div---default bs-div--table-of-contents-right"><div class="bs-div__inner     "><!-- wp:heading -->
        <h2>Table of Contents</h2>
        <!-- /wp:heading -->
        
        <!-- wp:list -->
        <ul><!-- wp:list-item -->
        <li><a href="#overview">OVERVIEW</a></li>
        <!-- /wp:list-item -->
        
        <!-- wp:list-item -->
        <li><a href="#action-at-the-point-of-inception">ACTION AT THE POINT OF INCEPTION</a></li>
        <!-- /wp:list-item -->
        
        <!-- wp:list-item -->
        <li><a href="#integrate-anything">INTEGRATE ANYTHING</a></li>
        <!-- /wp:list-item -->
        
        <!-- wp:list-item -->
        <li><a href="#democratized-automation">DEMOCRATIZED AUTOMATION</a></li>
        <!-- /wp:list-item -->
        
        <!-- wp:list-item -->
        <li><a href="#a-system-of-record-for-security">A SYSTEM OF RECORD FOR SECURITY</a></li>
        <!-- /wp:list-item --></ul>
        <!-- /wp:list --></div></div>
        <!-- /wp:e25m/div --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        
        <!-- wp:e25m/mobile-content -->
        <!-- wp:e25m/desktop-content-wrapper -->
        <!-- wp:e25m/section {"sectionBlockID":"864d197cc91ce4c6a9210b1daba304013c5f2f9d","containerType":"container-fluid","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dnewsletter","label":"Newsletter"}],"backgroundType":"background_color","backgroundColorOptions":{"regular":{"settings":{"backgroundColor":"#000743"}},"desktop":{"breakpoint":1280,"settings":{"backgroundColor":""}},"mobile":{"breakpoint":575,"settings":{"backgroundColor":""}}}} -->
        <section class="bs-section bs-section-864d197cc91ce4c6a9210b1daba304013c5f2f9d bs-section---default bs-section--newsletter  "><style>.bs-section.bs-section-864d197cc91ce4c6a9210b1daba304013c5f2f9d{ background-color: #000743;} </style><div class="container-fluid"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"sizeSm":0,"sizeLg":6,"colClassList":["bs-column","col-sm-0","col-md-0","col-lg-6"],"columnBlockID":"df5e10bef85c15055718b4d93887855962017939"} -->
        <div class=" bs-column col-sm-0 col-md-0 col-lg-6   bs-column-df5e10bef85c15055718b4d93887855962017939 bs-column---default     "><!-- wp:heading {"textColor":"white"} -->
        <h2 class="has-white-color has-text-color">Interested in Learning More?</h2>
        <!-- /wp:heading --></div>
        <!-- /wp:e25m/column -->
        
        <!-- wp:e25m/column {"sizeSm":0,"sizeLg":6,"colClassList":["bs-column","col-sm-0","col-md-0","col-lg-6"],"columnBlockID":"df5e10bef85c15055718b4d93887855962017939"} -->
        <div class=" bs-column col-sm-0 col-md-0 col-lg-6   bs-column-df5e10bef85c15055718b4d93887855962017939 bs-column---default     "><!-- wp:paragraph {"textColor":"white"} -->
        <p class="has-white-color has-text-color">Subscribe today to stay informed and get regular updates from Swimlane.</p>
        <!-- /wp:paragraph -->
        
        <!-- wp:html -->
        <iframe src="https://go.swimlane.com/l/288742/2022-12-15/2cbgfdq" width="100%%" height="1000" type="text/html" frameborder="0" allowTransparency="true" style="border: 0"></iframe>
        <!-- /wp:html -->
        
        <!-- wp:e25m/media-elements {"image":5490,"image_url":"https://swimelane-uploads.s3.amazonaws.com/uploads/2022/11/newsletter-bg.svg"} /--></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/desktop-content-wrapper -->
        
        <!-- wp:e25m/mobile-content-wrapper -->
        <!-- wp:paragraph {"placeholder":"This content will only be appeared on Mobile devices..."} -->
        <p></p>
        <!-- /wp:paragraph -->
        
        <!-- wp:e25m/section {"sectionBlockID":"864d197cc91ce4c6a9210b1daba304013c5f2f9d","containerType":"container-fluid","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dnewsletter","label":"Newsletter"}],"backgroundType":"background_color","backgroundColorOptions":{"regular":{"settings":{"backgroundColor":"#000743"}},"desktop":{"breakpoint":1280,"settings":{"backgroundColor":""}},"mobile":{"breakpoint":575,"settings":{"backgroundColor":""}}}} -->
        <section class="bs-section bs-section-864d197cc91ce4c6a9210b1daba304013c5f2f9d bs-section---default bs-section--newsletter  "><style>.bs-section.bs-section-864d197cc91ce4c6a9210b1daba304013c5f2f9d{ background-color: #000743;} </style><div class="container-fluid"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"sizeSm":0,"sizeLg":6,"colClassList":["bs-column","col-sm-0","col-md-0","col-lg-6"],"columnBlockID":"df5e10bef85c15055718b4d93887855962017939"} -->
        <div class=" bs-column col-sm-0 col-md-0 col-lg-6   bs-column-df5e10bef85c15055718b4d93887855962017939 bs-column---default     "><!-- wp:heading {"textColor":"white"} -->
        <h2 class="has-white-color has-text-color">Interested in Learning More?</h2>
        <!-- /wp:heading --></div>
        <!-- /wp:e25m/column -->
        
        <!-- wp:e25m/column {"sizeSm":0,"sizeLg":6,"colClassList":["bs-column","col-sm-0","col-md-0","col-lg-6"],"columnBlockID":"df5e10bef85c15055718b4d93887855962017939"} -->
        <div class=" bs-column col-sm-0 col-md-0 col-lg-6   bs-column-df5e10bef85c15055718b4d93887855962017939 bs-column---default     "><!-- wp:paragraph {"textColor":"white"} -->
        <p class="has-white-color has-text-color">Subscribe today to stay informed and get regular updates from Swimlane.</p>
        <!-- /wp:paragraph -->
        
        <!-- wp:html -->
        <iframe src="https://go.swimlane.com/l/288742/2022-12-15/2cbgfdq" width="100%%" height="1000" type="text/html" frameborder="0" allowTransparency="true" style="border: 0"></iframe>
        <!-- /wp:html --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/mobile-content-wrapper -->
        <!-- /wp:e25m/mobile-content -->', $postData);
        return $post_content;
    }
}
