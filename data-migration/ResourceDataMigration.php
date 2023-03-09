<?php

/**
 * Blog Data Migration
 *
 */

require_once('DataMigrationHelper.php');

class ResourceDataMigration
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
     * Create posts from csv data
     * @param $file
     * @param null $offset
     * @param null $length
     * @return string
     */
    public function importDataFromExcel($file, $offset = null, $length = null)
    {
        $message = '';

        //$inputFileNameImages = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/excel/SwimlaneLiveImages.xlsx';
        //$inputFileNameUsers = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/excel/SwimlaneLiveUsers.xlsx';
        //$inputFileNameBlogUsers = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/excel/SwimlaneLiveBlogAuthorsExpend.xlsx';

        $excel_data_array = $this->data_migration_helper->excelToArray($file);


        if (isset($offset) && isset($length)) {
            $excel_data_array = array_slice($excel_data_array, $offset, $length);
        }

        if (is_array($excel_data_array) && !empty($excel_data_array)) {
            $total = count($excel_data_array);

            $unpublished_or_draft_count = 0;
            $published_count = 0;

            $i = 0;
            $success_records = 0;
            foreach ($excel_data_array as $row) {
                $post_title = $row['title'];
                $post_excerpt = ($row['excerpt'])?$row['excerpt']:"";
                $post_content = $row['content'];
                $post_image_url = $row['featured'];
                $post_status = $row['status'];
                $post_slug =  $row['slug'];
                $post_author_id = $this->get_user_id_by_name($row['author_username']);
                $post_date = $row['date'];
               // $resource_type = array(63);
              //  $resource_tags =  explode('|', $row['tags']);
              //  $resource_categories =  explode('|', $row['categories']);
                $meta_title = $row['yoast_wpseo_title'];
                $meta_description = $row['yoast_wpseo_metadesc'];

                $resource_categories = explode('|', $row['resource_categories']); //var_dump($resource_categories);
                $resource_categories_array = [];
                if (is_array($resource_categories) && !empty($resource_categories)) {
                    foreach ($resource_categories as $term) {
                        if(!empty($term)){
                            $termObj = get_term_by('name', $term, 'resource-type');
                            if ($termObj) {
                                $resource_categories_array[] = $termObj->term_id;
                            } else {
                                $insertedTerm =  wp_insert_term($term, 'resource-type'); //var_dump($insertedTerm);
                                if($insertedTerm) {
                                    $this->data_migration_helper->writeLog('New term created: Term: ' . $term . 'and ID (' . $insertedTerm['term_id'] . ')', 'success');
                                    $resource_categories_array[] = $insertedTerm['term_id'];
                                }
                            }
                        }
                    }
                }

                if ($post_status == 'publish') {
                    $published_count++;

                    if ($this->data_migration_helper->isPostExistsBySlug($post_slug, 'resource')) {

                        $post_id = $this->data_migration_helper->isPostExistsBySlug($post_slug, 'resource');



                        $this->data_migration_helper->writeLog('Post already exist by slug. Slug: ' . $post_slug, 'error');
                        continue;
                    }

                    $post_data = array(
                        'post_title' => $post_title,
                        'post_excerpt' => $post_excerpt,
                        'post_name' => $post_slug,
                        'post_status' => $post_status,
                        'post_type' => 'resource',
                        'post_date' => $post_date,
                        'tax_input' => array(
                            'resource-type' => $resource_categories_array,
                        ),
                        'meta_input' => array(
                            '_meta_title' => $meta_title,
                            '_meta_description' => $meta_description,
                            '_yoast_wpseo_metadesc' => $meta_description,
                            '_yoast_wpseo_title' => $meta_title,
                        ),
                        'post_author' => $post_author_id
                    );

                    $result = wp_insert_post($post_data, true); // insert the post and allow WP_Error object

                    if (is_wp_error($result)) {
                        $this->data_migration_helper->writeLog($result->get_error_message() . ' Post: ' . $post_title, 'error');
                    } else {
                        $post_id = $result;
                        $this->data_migration_helper->writeLog('----------------------------ROW: ' . $i . '-----------------------------------------------------', 'success');
                        $this->data_migration_helper->writeLog('post created success: Post ID: ' . $post_id, 'success');

                        $featured_img_id = $this->attachImage($post_image_url, $post_id);
                        $feature_img_url = '';
                        if ($featured_img_id) {
                            $feature_img_url = wp_get_attachment_url($featured_img_id);
                        }

                        $post_content = $this->setPageLayout($post_content, $post_id);

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
                } else {
                    $unpublished_or_draft_count++;
                }
            }
            if ($success_records > 0) {
                // Tell user that the process is completed
                $message = 'Total posts count: ' . $total .
                    '<br>' . 'Published posts count: ' . $published_count .
                    '<br>' . 'Draft or Unpublished posts count: ' . $unpublished_or_draft_count .
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
    function attachImage($post_image_url, $post_id)
    {
        if (!empty($post_image_url)) {
            $post_image_url_list = explode('|', $post_image_url);
            $post_image_url = $post_image_url_list[0];
            if ($this->data_migration_helper->isURLExists($post_image_url)) {
                $result = $this->data_migration_helper->uploadAndAttachedImageToPost($post_image_url, $post_id);
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

    function setPageLayout($post_content, $post_id)
    {
        $arrCat = [];
         $post = get_the_terms( $post_id, 'resource-type' );  // var_dump($post);
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
        $postData = $post_content;
        $bodyContent = '<!-- wp:freeform -->%s<!-- /wp:freeform -->';
        if ( has_blocks($postData) ) { $bodyContent = '%s'; }

        $post_content = sprintf('<!-- wp:e25m/section {"sectionBlockID":"fbe94291d154dcd93704446b7108cd0b17355fc4","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dsection-bottom-radius","label":"Section Bottom Radius"},{"value":"bs-section\u002d\u002dblog-inner-banner","label":"Blog Inner Banner"}],"backgroundType":"background_image","backgroundImageOptions":{"regular":{"settings":{"sectionImgId":693,"sectionImgUrl":"https://vipre-uploads.s3.amazonaws.com/uploads/2021/11/Group-27-scaled.webp","sectionImgAlt":"","image2xId":"","image2xUrl":"","image2xAlt":""}},"desktop":{"breakpoint":1280,"settings":{"sectionImgId":"","sectionImgUrl":"","sectionImgAlt":"","image2xId":"","image2xUrl":"","image2xAlt":""}},"mobile":{"breakpoint":575,"settings":{"sectionImgId":"","sectionImgUrl":"","sectionImgAlt":"","image2xId":"","image2xUrl":"","image2xAlt":""}}}} -->
        <section class="wp-block-e25m-section bs-section bs-section-fbe94291d154dcd93704446b7108cd0b17355fc4 bs-section---default bs-section--section-bottom-radius bs-section--blog-inner-banner"><style>.bs-section.bs-section-fbe94291d154dcd93704446b7108cd0b17355fc4{ background-image: url(https://vipre-uploads.s3.amazonaws.com/uploads/2021/11/Group-27-scaled.webp);background-position: center center;background-size: cover;} </style><div class="container"><!-- wp:e25m/row {"justifyContentClass":"justify-content-center"} -->
        <div class="wp-block-e25m-row bs-row row justify-content-center  bs-row---default"><!-- wp:e25m/column {"sizeMd":12,"sizeLg":8,"colClassList":["bs-column","col-sm-12",null,"col-lg-8","col-md-12"],"columnBlockID":"abfa8e36c6101645103b6e4d402d24a5fd0e57dc"} -->
        <div class=" bs-column col-sm-12  col-lg-8 col-md-12   bs-column-abfa8e36c6101645103b6e4d402d24a5fd0e57dc bs-column---default     "><!-- wp:post-date {"format":"M d, Y","textColor":"black"} /-->
        
        <!-- wp:post-title /-->
        
        <!-- wp:post-featured-image /--></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        
        <!-- wp:e25m/section {"sectionBlockID":"70312d4cdc051fd3d392a2d2bb93ed10d42e42ec","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dblog-inner-author","label":"Blog Inner Author"},{"value":"bs-section\u002d\u002dsassy-social-share","label":"Sassy Social Share"},{"value":"bs-section\u002d\u002dblog-inner-content","label":"Blog Inner Content"}],"backgroundColorOptions":{"regular":{"settings":{"backgroundColor":"#001845"}},"desktop":{"breakpoint":1280,"settings":{"backgroundColor":""}},"mobile":{"breakpoint":575,"settings":{"backgroundColor":""}}}} -->
        <section class="wp-block-e25m-section bs-section bs-section-70312d4cdc051fd3d392a2d2bb93ed10d42e42ec bs-section---default bs-section--blog-inner-author bs-section--sassy-social-share bs-section--blog-inner-content"><div class="container"><!-- wp:e25m/row -->
        <div class="wp-block-e25m-row bs-row row  bs-row---default"><!-- wp:e25m/column {"sizeMd":2,"sizeLg":2,"colClassList":["bs-column","col-sm-12",null,"col-lg-2","col-md-2"],"columnBlockID":"3983a17fd97d4f953c395d7f36446ef6c524ee55"} -->
        <div class=" bs-column col-sm-12  col-lg-2 col-md-2   bs-column-3983a17fd97d4f953c395d7f36446ef6c524ee55 bs-column---default     "><!-- wp:shortcode -->
        [Sassy_Social_Share]
        <!-- /wp:shortcode --></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeMd":8,"sizeLg":8,"colClassList":["bs-column","col-sm-12",null,"col-lg-8","col-md-8"],"columnClassNames":[{"value":"bs-column\u002d\u002d-default","label":"Default"},{"value":"bs-column\u002d\u002dblog-inner-tags","label":"Blog Inner Tags"}],"columnBlockID":"1ca2941759c923e2c1aada87854c0e268646c7c4"} -->
        <div class=" bs-column col-sm-12  col-lg-8 col-md-8   bs-column-1ca2941759c923e2c1aada87854c0e268646c7c4 bs-column---default bs-column--blog-inner-tags     "><!-- wp:post-author {"showBio":true,"byline":"","textColor":"vipre-dark-blue"} /-->

        <!-- wp:e25m-custom/post-tag /-->
        '.$bodyContent.'
        </div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeMd":12,"sizeLg":2,"colClassList":["bs-column","col-sm-12","col-md-12","col-lg-2"],"columnBlockID":"a66cbe9a3973eab2d598c65cdfe5c57af13a6952"} -->
        <div class=" bs-column col-sm-12 col-md-12 col-lg-2   bs-column-a66cbe9a3973eab2d598c65cdfe5c57af13a6952 bs-column---default     "></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->

        <!-- wp:e25m/section {"sectionBlockID":"819ae6007dcdfc08c3f28781b213f9fa93f5000e","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002drelated-resources","label":"Related Resources"}]} -->
        <section class="wp-block-e25m-section bs-section bs-section-819ae6007dcdfc08c3f28781b213f9fa93f5000e bs-section---default bs-section--related-resources"><div class="container"><!-- wp:e25m/row -->
        <div class="wp-block-e25m-row bs-row row  bs-row---default"><!-- wp:e25m/column {"colClassList":["bs-column","col-sm-12",null,"col-md-0","col-lg-0"],"columnBlockID":"ffb56445f295eab4ae67bb7666d540cf1ee5aff9"} -->
        <div class=" bs-column col-sm-12  col-md-0 col-lg-0   bs-column-ffb56445f295eab4ae67bb7666d540cf1ee5aff9 bs-column---default     "><!-- wp:heading {"textAlign":"center"} -->
        <h2 class="has-text-align-center">You might also be interested in</h2>
        <!-- /wp:heading --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row -->

        <!-- wp:e25m/row -->
        <div class="wp-block-e25m-row bs-row row  bs-row---default"><!-- wp:e25m/column {"sizeMd":12,"sizeLg":12,"colClassList":["bs-column","col-sm-12",null,"col-md-12","col-lg-12"],"columnBlockID":"e5e7f203a0ed53034553e18278a536b7b9c53de2"} -->
        <div class=" bs-column col-sm-12  col-md-12 col-lg-12   bs-column-e5e7f203a0ed53034553e18278a536b7b9c53de2 bs-column---default     "><!-- wp:e25m/related-posts {"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-tag","label":"Tag"},{"value":"date","label":"Date"},{"value":"title","label":"Title"},{"value":"excerpt","label":"Excerpt"}],"postTaxonomies":'.json_encode($arrCat).',"dateFormat":"M d, Y"} -->
        <div class="bs-related-posts bs-related-posts-block---default"><div class="bs-related-posts__container"><div class="bs-related-posts__items"><!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"resource","selectedPost":{"label":"VIPRE Security Knowledge Base Introduction","value":"7054"},"dateFormat":"M d, Y","displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-tag","label":"Tag"},{"value":"date","label":"Date"},{"value":"title","label":"Title"},{"value":"excerpt","label":"Excerpt"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"resource","selectedPost":{"label":"A Google Security Alert That Looks Suspiciously Like a Phishing Attempt","value":"7057"},"dateFormat":"M d, Y","displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-tag","label":"Tag"},{"value":"date","label":"Date"},{"value":"title","label":"Title"},{"value":"excerpt","label":"Excerpt"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"resource","selectedPost":{"label":"VIPRE Agents: Staying Up to Date","value":"7053"},"dateFormat":"M d, Y","displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-tag","label":"Tag"},{"value":"date","label":"Date"},{"value":"title","label":"Title"},{"value":"excerpt","label":"Excerpt"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column --></div></div></div>
        <!-- /wp:e25m/related-posts --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        
        <!-- wp:e25m/section {"sectionBlockID":"c0fdc9b10f994b325514decd77c15f2ff5eaf7d2","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dblog-join-our-newsletter","label":"Blog Join Our Newsletter"},{"value":"bs-section\u002d\u002dsection-bottom-radius","label":"Section Bottom Radius"},{"value":"bs-section\u002d\u002dblog-inner-news-letter","label":"Blog Inner News Letter"}]} -->
        <section class="wp-block-e25m-section bs-section bs-section-c0fdc9b10f994b325514decd77c15f2ff5eaf7d2 bs-section---default bs-section--blog-join-our-newsletter bs-section--section-bottom-radius bs-section--blog-inner-news-letter"><div class="container"><!-- wp:e25m/row -->
        <div class="wp-block-e25m-row bs-row row  bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:e25m/div {"divBlockID":"1b6f5840086ff255711cd135550f4730bb84fb00","divClassNames":[{"value":"bs-div\u002d\u002d-default","label":"Default"},{"value":"bs-div\u002d\u002djoin-our-newsletter","label":"Join Our Newsletter"}]} -->
        <div class="wp-block-e25m-div bs-div bs-div-1b6f5840086ff255711cd135550f4730bb84fb00 bs-div---default bs-div--join-our-newsletter"><div class="bs-div__inner     "><!-- wp:e25m/div {"divBlockID":"fbb78b887198cd909a1c6b39ec7a8acda89e2bef"} -->
        <div class="wp-block-e25m-div bs-div bs-div-fbb78b887198cd909a1c6b39ec7a8acda89e2bef bs-div---default"><div class="bs-div__inner     "><!-- wp:heading -->
        <h2>Join our newsletter</h2>
        <!-- /wp:heading -->

        <!-- wp:paragraph -->
        <p>We will use the details in this form to contact you about VIPRE Services.</p>
        <!-- /wp:paragraph --></div></div>
        <!-- /wp:e25m/div -->

        <!-- wp:shortcode -->
        [fluentform id="2"]
        <!-- /wp:shortcode --></div></div>
        <!-- /wp:e25m/div --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->', $postData);
        return $post_content;
    }
}
