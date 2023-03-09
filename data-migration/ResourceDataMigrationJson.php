<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
/**
 * Resource Data Migration
 *
 */

require_once('DataMigrationHelper.php');

class ResourceDataMigration
{
    protected $data_migration_helper;

    /**
     * DataMigration constructor.
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
      //  $inputFileNameCatogeries = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/json/resources-categories.json';
       // $inputFileNametypes = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/json/resources-types.json';
        $inputFileNameImages = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/json/images-09-01-2023.json';
        
        $file_data_array = $this->data_migration_helper->jsonToArray($file);

        $file_data_array_users = $this->data_migration_helper->jsonToArray($inputFileNameUsers);
       // $file_data_array_catogeries = $this->data_migration_helper->jsonToArray($inputFileNameCatogeries);
       // $file_data_array_types = $this->data_migration_helper->jsonToArray($inputFileNametypes);
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
                $titleAdditional = ($row['titleAdditional'])?$row['titleAdditional']:""; //need to do
                $post_excerpt = ($row['shortDescription'])?$row['shortDescription']:"";
                $bodyHeading = ($row['bodyHeading'])?$row['bodyHeading']:""; //need to do
                $post_content = $row['body'];
                $post_status = ($row['status']=="live")?"publish":"draft";
                $post_slug =  $row['slug'];
                $post_date = $row['postDate'];
                $resource_type = $row['resourceTypes'];
                $resource_categories = $row['resourceCategories_'];

                $linkLabel = ($row['linkLabel'])?$row['linkLabel']:"Download";
                $externalLink = ($row['externalLink'])?$row['externalLink']:"";
                $footnotes = ($row['footnotes'])?$row['footnotes']:"";
                $formCode = ($row['html'])?$row['html']:"";
                $wistiaId = ($row['wistiaId'])?$row['wistiaId']:"";

                $meta_title = ($row['resourceSeoNew']['metaGlobalVars']['seoTitle'])?$row['resourceSeoNew']['metaGlobalVars']['seoTitle']:"";
                $meta_description = ($row['resourceSeoNew']['metaGlobalVars']['seoDescription'])?$row['resourceSeoNew']['metaGlobalVars']['seoDescription']:"";
                
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
                $res_cat_array = [];
                    foreach ($resource_type as $resType) { 
                            $termType = term_exists( $resType, 'resource-type' );
                            if ( $termType == 0 && $termType == null ) { 
                                $insertedTermType =  wp_insert_term($resType, 'resource-type'); 
                                if($insertedTermType) {
                                    $this->data_migration_helper->writeLog('New term created: Term: ' . $resType . 'and ID (' . $insertedTermType['term_id'] . ')', 'success');
                                    $res_cat_array[] = $insertedTermType['term_id']; 
                                }
                            }else{
                                $res_cat_array[] = $termType['term_id'];
                            }
                        
                    }

                //import industry
                $ind_cat_array = [];
                    foreach ($resource_categories as $industry) { 
                            $term = term_exists( $industry, 'resource-industry' );
                            if ( $term == 0 && $term == null ) { 
                                $insertedTerm =  wp_insert_term($industry, 'resource-industry'); 
                                if($insertedTerm) {
                                    $this->data_migration_helper->writeLog('New term created: Term: ' . $industry . 'and ID (' . $insertedTerm['term_id'] . ')', 'success');
                                    $ind_cat_array[] = $insertedTerm['term_id']; 
                                }
                            }else{
                                $ind_cat_array[] = $term['term_id'];
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
                            'resource-type' => $res_cat_array,
                            'resource-industry' => $ind_cat_array,
                            'resource-topic' => '',
                        ),
                        'meta_input' => array(
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
                                if(in_array($rowi['id'], $row['image'])){  //var_dump($rowi['url']);
                                    $featured_img_id = $this->attachImage($rowi['url'], $post_id);
                                    $feature_img_url = '';
                                    if ($featured_img_id) {
                                        $feature_img_url = wp_get_attachment_url($featured_img_id);
                                    }
                                }
                            }
                        }
                        
                        if($externalLink!=""){
                            $post_content = $this->setPageLayoutNonGated($post_content, $post_id, $linkLabel, $externalLink, $titleAdditional, $bodyHeading);
                        }elseif($formCode!=""){
                            $post_content = $this->setPageLayoutGated($post_content, $post_id, $footnotes, $formCode, $titleAdditional, $bodyHeading);
                        }elseif(in_array('Videos',$resource_type)){
                            $post_content = $this->setPageLayoutVideos($post_content, $post_id, $wistiaId, $titleAdditional, $bodyHeading);
                        }else{
                            $post_content = $this->setPageLayout($post_content, $post_id);
                        }
                        
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

    function setPageLayoutVideos($post_content, $post_id, $wistiaId, $titleAdditional, $bodyHeading)
    {
        $post = get_the_terms( $post_id, 'resource-type' );
        $termName = $post[0]->name;

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

        $titleAdditionalDec = "";
        if($titleAdditional){
            $titleAdditionalDec = '<!-- wp:paragraph {"fontSize":"large"} -->
            <p class="has-large-font-size">'.$titleAdditional.'</p>
            <!-- /wp:paragraph -->';
        }

        $bodyHeadingDec = "";
        if($bodyHeading){
            $bodyHeadingDec = '<!-- wp:paragraph {"fontSize":"medium"} -->
            <p class="has-medium-font-size">'.$bodyHeading.'</p>
            <!-- /wp:paragraph -->';
        }

        $wistiaIdContent = "";
        if($wistiaId!=""){
        $wistiaIdContent = '<!-- wp:html -->
                            <script src="//fast.wistia.com/embed/medias/s3lqfi0zn7.jsonp" async></script>
                            <script src="//fast.wistia.com/assets/external/E-v1.js" async></script>
                            <div class="wistia_embed wistia_async_'.$wistiaId.'" style="height:349px;width:620px">&nbsp;</div>
                            <!-- /wp:html -->';
        }

        $postData = $post_content;
        $bodyContent = '<!-- wp:freeform -->%s<!-- /wp:freeform -->';
        if ( has_blocks($postData) ) { $bodyContent = '%s'; }

        $post_contentDesktop = sprintf('<!-- wp:e25m/mobile-content -->
        <!-- wp:e25m/desktop-content-wrapper -->
        <!-- wp:e25m/section {"sectionBlockID":"0ace0c84eaa888bce49c4f2de89bf8347ace13a6","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dcommon-header-with-form","label":"Common Header With Form"}]} -->
        <section class="bs-section bs-section-0ace0c84eaa888bce49c4f2de89bf8347ace13a6 bs-section---default bs-section--common-header-with-form  "><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:e25m/lottie-animator {"animationSettings":{"desktop":{"settings":{"animateViewPort":0.5,"direction":1,"delay":0,"loop":false,"fileName":"Contact_lines1.json","mouseOutAction":"none","numberOfLoops":1,"playSpeed":1,"path":"https://swimlaneqa1.wpengine.com/wp-content/uploads/2022/12/Contact_lines1.json","scrollRelativeTo":"withinSection","trigger":false,"triggerMethod":"pageHover"}},"mobile":{"breakpoint":576,"settings":{"animateViewPort":50,"direction":1,"delay":0,"loop":false,"fileName":"","mouseOutAction":"none","numberOfLoops":0,"playSpeed":1,"path":"","scrollRelativeTo":"withinSection","trigger":false,"triggerMethod":"pageHover"}}},"uniqueId":"lottie-c7d5ff8e-a115-40ae-aecb-0a84b90fe739"} /--></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row -->
        
        <!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"sizeSm":0,"sizeMd":10,"colClassList":["bs-column",null,"col-sm-0","col-md-10"],"columnBlockID":"e324ba46e668efb8a167f6c6f82d98452b10172b"} -->
        <div class=" bs-column  col-sm-0 col-md-10   bs-column-e324ba46e668efb8a167f6c6f82d98452b10172b bs-column---default     "><!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","textTransform":"uppercase"}},"textColor":"blue","className":"common-header__blue-text"} -->
        <p class="common-header__blue-text has-blue-color has-text-color" style="font-size:18px;text-transform:uppercase"><span>'.$termName.'</span></p>
        <!-- /wp:paragraph -->
        
        <!-- wp:post-title {"level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"400"}},"textColor":"black","className":"common-header__gated-page"} /-->
        '. $titleAdditionalDec.' '. $bodyHeadingDec.' '.$bodyContent.' '.$wistiaIdContent.'
        </div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/desktop-content-wrapper -->', $postData);

        $postDataMobile = $post_content;
        $bodyContentMobile = '<!-- wp:freeform -->%s<!-- /wp:freeform -->';
        if ( has_blocks($postDataMobile) ) { $bodyContentMobile = '%s'; }

        $post_contentMobile = sprintf('<!-- wp:e25m/mobile-content-wrapper -->
        <!-- wp:paragraph {"placeholder":"This content will only be appeared on Mobile devices..."} -->
        <p></p>
        <!-- /wp:paragraph -->

        <!-- wp:e25m/section {"sectionBlockID":"0ace0c84eaa888bce49c4f2de89bf8347ace13a6","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dcommon-header-with-form","label":"Common Header With Form"}]} -->
        <section class="bs-section bs-section-0ace0c84eaa888bce49c4f2de89bf8347ace13a6 bs-section---default bs-section--common-header-with-form  "><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:e25m/lottie-animator {"animationSettings":{"desktop":{"settings":{"animateViewPort":0.5,"direction":1,"delay":0,"loop":false,"fileName":"Contact_lines1.json","mouseOutAction":"none","numberOfLoops":1,"playSpeed":1,"path":"https://swimlaneqa1.wpengine.com/wp-content/uploads/2022/12/Contact_lines1.json","scrollRelativeTo":"withinSection","trigger":false,"triggerMethod":"pageHover"}},"mobile":{"breakpoint":576,"settings":{"animateViewPort":50,"direction":1,"delay":0,"loop":false,"fileName":"","mouseOutAction":"none","numberOfLoops":0,"playSpeed":1,"path":"","scrollRelativeTo":"withinSection","trigger":false,"triggerMethod":"pageHover"}}},"uniqueId":"lottie-c7d5ff8e-a115-40ae-aecb-0a84b90fe739"} /--></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row -->

        <!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"sizeSm":0,"sizeMd":12,"colClassList":["bs-column",null,"col-sm-0","col-md-12"],"columnBlockID":"c159ec6f0d52b4e29fc347b6fcdc073da0d4fbba"} -->
        <div class=" bs-column  col-sm-0 col-md-12   bs-column-c159ec6f0d52b4e29fc347b6fcdc073da0d4fbba bs-column---default     "><!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","textTransform":"uppercase"}},"textColor":"blue","className":"common-header__blue-text"} -->
        <p class="common-header__blue-text has-blue-color has-text-color" style="font-size:18px;text-transform:uppercase"><span>'.$termName.'</span></p>
        <!-- /wp:paragraph -->

        <!-- wp:post-title {"level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"400"}},"textColor":"black","className":"common-header__gated-page"} /-->
        '.$bodyContentMobile.' '.$wistiaIdContent.'
        </div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/mobile-content-wrapper -->
        <!-- /wp:e25m/mobile-content -->

        <!-- wp:e25m/mobile-content -->
        <!-- wp:e25m/desktop-content-wrapper -->
        <!-- wp:e25m/section {"sectionBlockID":"14ca56fdc3be4b5f0a8616120c8005c221b5c55b","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002drelated-items","label":"Related Items"}]} -->
        <section class="bs-section bs-section-14ca56fdc3be4b5f0a8616120c8005c221b5c55b bs-section---default bs-section--related-items  "><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:heading {"textColor":"deep"} -->
        <h2 class="has-deep-color has-text-color">You Might Also Be Interested in</h2>
        <!-- /wp:heading --></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":0,"sizeMd":12,"colClassList":["bs-column","col-sm-0","col-md-12"],"columnBlockID":"33aba5295b76f4052fb226277a0b4e1e4747889c"} -->
        <div class=" bs-column col-sm-0 col-md-12   bs-column-33aba5295b76f4052fb226277a0b4e1e4747889c bs-column---default     "><!-- wp:e25m/related-posts {"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"postTaxonomies":'.json_encode($arrCat).'} -->
        <div class="bs-related-posts bs-related-posts-block---default"><div class="bs-related-posts__container"><div class="bs-related-posts__items"><!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2828"},"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2830"},"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column --></div></div></div>
        <!-- /wp:e25m/related-posts --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/desktop-content-wrapper -->

        <!-- wp:e25m/mobile-content-wrapper -->
        <!-- wp:paragraph {"placeholder":"This content will only be appeared on Mobile devices..."} -->
        <p></p>
        <!-- /wp:paragraph -->

        <!-- wp:e25m/section {"sectionBlockID":"14ca56fdc3be4b5f0a8616120c8005c221b5c55b","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002drelated-items","label":"Related Items"}]} -->
        <section class="bs-section bs-section-14ca56fdc3be4b5f0a8616120c8005c221b5c55b bs-section---default bs-section--related-items  "><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:heading {"textColor":"deep"} -->
        <h2 class="has-deep-color has-text-color">You Might Also Be Interested in</h2>
        <!-- /wp:heading --></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":0,"sizeMd":12,"colClassList":["bs-column","col-sm-0","col-md-12"],"columnBlockID":"33aba5295b76f4052fb226277a0b4e1e4747889c"} -->
        <div class=" bs-column col-sm-0 col-md-12   bs-column-33aba5295b76f4052fb226277a0b4e1e4747889c bs-column---default     "><!-- wp:e25m/related-posts {"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"postTaxonomies":'.json_encode($arrCat).'} -->
        <div class="bs-related-posts bs-related-posts-block---default"><div class="bs-related-posts__container"><div class="bs-related-posts__items"><!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2828"},"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2830"},"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column --></div></div></div>
        <!-- /wp:e25m/related-posts --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/mobile-content-wrapper -->
        <!-- /wp:e25m/mobile-content -->', $postDataMobile );

        return $post_contentDesktop.$post_contentMobile;
    }

    function setPageLayoutNonGated($post_content, $post_id, $linkLabel, $externalLink, $titleAdditional, $bodyHeading)
    {
        $post = get_the_terms( $post_id, 'resource-type' );
        $termName = $post[0]->name;

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

        $titleAdditionalDec = "";
        if($titleAdditional){
            $titleAdditionalDec = '<!-- wp:paragraph {"fontSize":"large"} -->
            <p class="has-large-font-size">'.$titleAdditional.'</p>
            <!-- /wp:paragraph -->';
        }

        $bodyHeadingDec = "";
        if($bodyHeading){
            $bodyHeadingDec = '<!-- wp:paragraph {"fontSize":"medium"} -->
            <p class="has-medium-font-size">'.$bodyHeading.'</p>
            <!-- /wp:paragraph -->';
        }

        $postData = $post_content;
        $bodyContent = '<!-- wp:freeform -->%s<!-- /wp:freeform -->';
        if ( has_blocks($postData) ) { $bodyContent = '%s'; }

        $post_contentDesktop = sprintf('<!-- wp:e25m/mobile-content -->
        <!-- wp:e25m/desktop-content-wrapper -->
        <!-- wp:e25m/section {"sectionBlockID":"0ace0c84eaa888bce49c4f2de89bf8347ace13a6","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dcommon-header-with-form","label":"Common Header With Form"}]} -->
        <section class="bs-section bs-section-0ace0c84eaa888bce49c4f2de89bf8347ace13a6 bs-section---default bs-section--common-header-with-form  "><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:e25m/lottie-animator {"animationSettings":{"desktop":{"settings":{"animateViewPort":0.5,"direction":1,"delay":0,"loop":false,"fileName":"Contact_lines1.json","mouseOutAction":"none","numberOfLoops":1,"playSpeed":1,"path":"https://swimlaneqa1.wpengine.com/wp-content/uploads/2022/12/Contact_lines1.json","scrollRelativeTo":"withinSection","trigger":false,"triggerMethod":"pageHover"}},"mobile":{"breakpoint":576,"settings":{"animateViewPort":50,"direction":1,"delay":0,"loop":false,"fileName":"","mouseOutAction":"none","numberOfLoops":0,"playSpeed":1,"path":"","scrollRelativeTo":"withinSection","trigger":false,"triggerMethod":"pageHover"}}},"uniqueId":"lottie-c7d5ff8e-a115-40ae-aecb-0a84b90fe739"} /--></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row -->
        
        <!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"sizeSm":0,"sizeMd":10,"colClassList":["bs-column",null,"col-sm-0","col-md-10"],"columnBlockID":"e324ba46e668efb8a167f6c6f82d98452b10172b"} -->
        <div class=" bs-column  col-sm-0 col-md-10   bs-column-e324ba46e668efb8a167f6c6f82d98452b10172b bs-column---default     "><!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","textTransform":"uppercase"}},"textColor":"blue","className":"common-header__blue-text"} -->
        <p class="common-header__blue-text has-blue-color has-text-color" style="font-size:18px;text-transform:uppercase"><span>'.$termName.'</span></p>
        <!-- /wp:paragraph -->
        
        <!-- wp:post-title {"level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"400"}},"textColor":"black","className":"common-header__gated-page"} /-->
        '. $titleAdditionalDec.'
        '. $bodyHeadingDec.'
        '.$bodyContent.'
        <!-- wp:e25m/pro-button {"popupID":"p-btn-9ef3b4cf5621e5a7e90291a5c898f6a8cc727450","customUrl":"'.$externalLink.'","text":"Click here","linkOpenType":true,"buttonClassNames":[{"value":"bs-pro-button\u002d\u002d-default","label":"Default"},{"value":"bs-pro-button\u002d\u002dprimary-with-arrow","label":"Primary With Arrow"}]} -->
        <span class="bs-pro-button bs-pro-button---default bs-pro-button--primary-with-arrow bs-pro-button-p-btn-9ef3b4cf5621e5a7e90291a5c898f6a8cc727450"><style>.bs-pro-button-p-btn-9ef3b4cf5621e5a7e90291a5c898f6a8cc727450 .bs-pro-button__container {background-color: #abb8c3; color: #000000;}</style><a href="'.$externalLink.'" target="_blank" rel="noopener noreferrer" class="bs-pro-button__container">'.ucwords($linkLabel).'</a></span>
        <!-- /wp:e25m/pro-button --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/desktop-content-wrapper -->', $postData);

        $postDataMobile = $post_content;
        $bodyContentMobile = '<!-- wp:freeform -->%s<!-- /wp:freeform -->';
        if ( has_blocks($postDataMobile) ) { $bodyContentMobile = '%s'; }

        $post_contentMobile = sprintf('<!-- wp:e25m/mobile-content-wrapper -->
        <!-- wp:paragraph {"placeholder":"This content will only be appeared on Mobile devices..."} -->
        <p></p>
        <!-- /wp:paragraph -->

        <!-- wp:e25m/section {"sectionBlockID":"0ace0c84eaa888bce49c4f2de89bf8347ace13a6","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dcommon-header-with-form","label":"Common Header With Form"}]} -->
        <section class="bs-section bs-section-0ace0c84eaa888bce49c4f2de89bf8347ace13a6 bs-section---default bs-section--common-header-with-form  "><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:e25m/lottie-animator {"animationSettings":{"desktop":{"settings":{"animateViewPort":0.5,"direction":1,"delay":0,"loop":false,"fileName":"Contact_lines1.json","mouseOutAction":"none","numberOfLoops":1,"playSpeed":1,"path":"https://swimlaneqa1.wpengine.com/wp-content/uploads/2022/12/Contact_lines1.json","scrollRelativeTo":"withinSection","trigger":false,"triggerMethod":"pageHover"}},"mobile":{"breakpoint":576,"settings":{"animateViewPort":50,"direction":1,"delay":0,"loop":false,"fileName":"","mouseOutAction":"none","numberOfLoops":0,"playSpeed":1,"path":"","scrollRelativeTo":"withinSection","trigger":false,"triggerMethod":"pageHover"}}},"uniqueId":"lottie-c7d5ff8e-a115-40ae-aecb-0a84b90fe739"} /--></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row -->

        <!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"sizeSm":0,"sizeMd":12,"colClassList":["bs-column",null,"col-sm-0","col-md-12"],"columnBlockID":"c159ec6f0d52b4e29fc347b6fcdc073da0d4fbba"} -->
        <div class=" bs-column  col-sm-0 col-md-12   bs-column-c159ec6f0d52b4e29fc347b6fcdc073da0d4fbba bs-column---default     "><!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","textTransform":"uppercase"}},"textColor":"blue","className":"common-header__blue-text"} -->
        <p class="common-header__blue-text has-blue-color has-text-color" style="font-size:18px;text-transform:uppercase"><span>'.$termName.'</span></p>
        <!-- /wp:paragraph -->

        <!-- wp:post-title {"level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"400"}},"textColor":"black","className":"common-header__gated-page"} /-->
        '.$bodyContentMobile.'
        <!-- wp:e25m/pro-button {"popupID":"p-btn-9ef3b4cf5621e5a7e90291a5c898f6a8cc727450","customUrl":"'.$externalLink.'","text":"Click here","linkOpenType":true,"buttonClassNames":[{"value":"bs-pro-button\u002d\u002d-default","label":"Default"},{"value":"bs-pro-button\u002d\u002dprimary-with-arrow","label":"Primary With Arrow"}]} -->
        <span class="bs-pro-button bs-pro-button---default bs-pro-button--primary-with-arrow bs-pro-button-p-btn-9ef3b4cf5621e5a7e90291a5c898f6a8cc727450"><style>.bs-pro-button-p-btn-9ef3b4cf5621e5a7e90291a5c898f6a8cc727450 .bs-pro-button__container {background-color: #abb8c3; color: #000000;}</style><a href="'.$externalLink.'" target="_blank" rel="noopener noreferrer" class="bs-pro-button__container">'.$linkLabel.'</a></span>
        <!-- /wp:e25m/pro-button --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/mobile-content-wrapper -->
        <!-- /wp:e25m/mobile-content -->

        <!-- wp:e25m/mobile-content -->
        <!-- wp:e25m/desktop-content-wrapper -->
        <!-- wp:e25m/section {"sectionBlockID":"14ca56fdc3be4b5f0a8616120c8005c221b5c55b","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002drelated-items","label":"Related Items"}]} -->
        <section class="bs-section bs-section-14ca56fdc3be4b5f0a8616120c8005c221b5c55b bs-section---default bs-section--related-items  "><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:heading {"textColor":"deep"} -->
        <h2 class="has-deep-color has-text-color">You Might Also Be Interested in</h2>
        <!-- /wp:heading --></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":0,"sizeMd":12,"colClassList":["bs-column","col-sm-0","col-md-12"],"columnBlockID":"33aba5295b76f4052fb226277a0b4e1e4747889c"} -->
        <div class=" bs-column col-sm-0 col-md-12   bs-column-33aba5295b76f4052fb226277a0b4e1e4747889c bs-column---default     "><!-- wp:e25m/related-posts {"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"postTaxonomies":'.json_encode($arrCat).'} -->
        <div class="bs-related-posts bs-related-posts-block---default"><div class="bs-related-posts__container"><div class="bs-related-posts__items"><!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2828"},"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2830"},"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column --></div></div></div>
        <!-- /wp:e25m/related-posts --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/desktop-content-wrapper -->

        <!-- wp:e25m/mobile-content-wrapper -->
        <!-- wp:paragraph {"placeholder":"This content will only be appeared on Mobile devices..."} -->
        <p></p>
        <!-- /wp:paragraph -->

        <!-- wp:e25m/section {"sectionBlockID":"14ca56fdc3be4b5f0a8616120c8005c221b5c55b","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002drelated-items","label":"Related Items"}]} -->
        <section class="bs-section bs-section-14ca56fdc3be4b5f0a8616120c8005c221b5c55b bs-section---default bs-section--related-items  "><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:heading {"textColor":"deep"} -->
        <h2 class="has-deep-color has-text-color">You Might Also Be Interested in</h2>
        <!-- /wp:heading --></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":0,"sizeMd":12,"colClassList":["bs-column","col-sm-0","col-md-12"],"columnBlockID":"33aba5295b76f4052fb226277a0b4e1e4747889c"} -->
        <div class=" bs-column col-sm-0 col-md-12   bs-column-33aba5295b76f4052fb226277a0b4e1e4747889c bs-column---default     "><!-- wp:e25m/related-posts {"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"postTaxonomies":'.json_encode($arrCat).'} -->
        <div class="bs-related-posts bs-related-posts-block---default"><div class="bs-related-posts__container"><div class="bs-related-posts__items"><!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2828"},"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2830"},"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column --></div></div></div>
        <!-- /wp:e25m/related-posts --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/mobile-content-wrapper -->
        <!-- /wp:e25m/mobile-content -->', $postDataMobile );

        return $post_contentDesktop.$post_contentMobile;
    }

    function setPageLayoutGated($post_content, $post_id, $footnotes, $formCode, $titleAdditional, $bodyHeading)
    {
        $post = get_the_terms( $post_id, 'resource-type' );
        $termName = $post[0]->name;

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

        $titleAdditionalDec = "";
        if($titleAdditional){
            $titleAdditionalDec = '<!-- wp:paragraph {"fontSize":"large"} -->
            <p class="has-large-font-size">'.$titleAdditional.'</p>
            <!-- /wp:paragraph -->';
        }

        $bodyHeadingDec = "";
        if($bodyHeading){
            $bodyHeadingDec = '<!-- wp:paragraph {"fontSize":"medium"} -->
            <p class="has-medium-font-size">'.$bodyHeading.'</p>
            <!-- /wp:paragraph -->';
        }

        $postData = $post_content;
        $bodyContent = '<!-- wp:freeform -->%s<!-- /wp:freeform -->';
        if ( has_blocks($postData) ) { $bodyContent = '%s'; }

        $post_contentDesktop = sprintf('<!-- wp:e25m/mobile-content -->
        <!-- wp:e25m/desktop-content-wrapper -->
        <!-- wp:e25m/section {"sectionBlockID":"0ace0c84eaa888bce49c4f2de89bf8347ace13a6","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dcommon-header-with-form","label":"Common Header With Form"}]} -->
        <section class="bs-section bs-section-0ace0c84eaa888bce49c4f2de89bf8347ace13a6 bs-section---default bs-section--common-header-with-form  "><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:e25m/lottie-animator {"animationSettings":{"desktop":{"settings":{"animateViewPort":0.5,"direction":1,"delay":0,"loop":false,"fileName":"Contact_lines1.json","mouseOutAction":"none","numberOfLoops":1,"playSpeed":1,"path":"https://swimlaneqa1.wpengine.com/wp-content/uploads/2022/12/Contact_lines1.json","scrollRelativeTo":"withinSection","trigger":false,"triggerMethod":"pageHover"}},"mobile":{"breakpoint":576,"settings":{"animateViewPort":50,"direction":1,"delay":0,"loop":false,"fileName":"","mouseOutAction":"none","numberOfLoops":0,"playSpeed":1,"path":"","scrollRelativeTo":"withinSection","trigger":false,"triggerMethod":"pageHover"}}},"uniqueId":"lottie-c7d5ff8e-a115-40ae-aecb-0a84b90fe739"} /--></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row -->
        
        <!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"sizeSm":0,"sizeMd":7,"colClassList":["bs-column",null,"col-md-7","col-sm-0"],"columnBlockID":"3d962b00decd4fef444449c96db45c42599741ec"} -->
        <div class=" bs-column  col-md-7 col-sm-0   bs-column-3d962b00decd4fef444449c96db45c42599741ec bs-column---default     "><!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","textTransform":"uppercase"}},"textColor":"blue","className":"common-header__blue-text"} -->
        <p class="common-header__blue-text has-blue-color has-text-color" style="font-size:18px;text-transform:uppercase"><span>'.$termName.'</span></p>
        <!-- /wp:paragraph -->
        
        <!-- wp:post-title {"level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"400"}},"textColor":"black","className":"common-header__gated-page"} /-->
        '. $titleAdditionalDec.'
        '.$bodyHeadingDec.'
        '.$bodyContent.'
        <!-- wp:e25m/div {"divBlockID":"55fcd1d264d5e2b220b95765fe5f62e2fba6086e","divClassNames":[{"value":"bs-div\u002d\u002d-default","label":"Default"},{"value":"bs-div\u002d\u002dfootnotes","label":"Footnotes"}]} -->
        <div class="bs-div bs-div-55fcd1d264d5e2b220b95765fe5f62e2fba6086e bs-div---default bs-div--footnotes"><div class="bs-div__inner     "><!-- wp:html -->
        '.$footnotes.'
        <!-- /wp:html --></div></div>
        <!-- /wp:e25m/div -->
        </div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":0,"sizeMd":5,"colClassList":["bs-column",null,"col-md-5","col-sm-0"],"columnClassNames":[{"value":"bs-column\u002d\u002d-default","label":"Default"},{"value":"bs-column\u002d\u002dform","label":"Form"}],"columnBlockID":"4e7708ec1047c5de435a1fcaa9bd912b51781ebd"} -->
        <div class=" bs-column  col-md-5 col-sm-0   bs-column-4e7708ec1047c5de435a1fcaa9bd912b51781ebd bs-column---default bs-column--form     "><!-- wp:e25m/div {"divBlockID":"2e97af6cb73103c2a77ad3d0146f9045e143873d","divClassNames":[{"value":"bs-div\u002d\u002d-default","label":"Default"},{"value":"bs-div\u002d\u002dform-wrapper","label":"Form Wrapper"}]} -->
        <div class="bs-div bs-div-2e97af6cb73103c2a77ad3d0146f9045e143873d bs-div---default bs-div--form-wrapper"><div class="bs-div__inner     ">
        <!-- wp:html -->
        '.$formCode.'
        <!-- /wp:html --></div></div>
        <!-- /wp:e25m/div --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/desktop-content-wrapper -->', $postData);

        $postDataMobile = $post_content;
        $bodyContentMobile = '<!-- wp:freeform -->%s<!-- /wp:freeform -->';
        if ( has_blocks($postDataMobile) ) { $bodyContentMobile = '%s'; }

        $post_contentMobile = sprintf('<!-- wp:e25m/mobile-content-wrapper -->
        <!-- wp:e25m/section {"sectionBlockID":"0ace0c84eaa888bce49c4f2de89bf8347ace13a6","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002dcommon-header-with-form","label":"Common Header With Form"}]} -->
        <section class="bs-section bs-section-0ace0c84eaa888bce49c4f2de89bf8347ace13a6 bs-section---default bs-section--common-header-with-form  "><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:e25m/lottie-animator {"animationSettings":{"desktop":{"settings":{"animateViewPort":0.5,"direction":1,"delay":0,"loop":false,"fileName":"Contact_lines1.json","mouseOutAction":"none","numberOfLoops":1,"playSpeed":1,"path":"https://swimlaneqa1.wpengine.com/wp-content/uploads/2022/12/Contact_lines1.json","scrollRelativeTo":"withinSection","trigger":false,"triggerMethod":"pageHover"}},"mobile":{"breakpoint":576,"settings":{"animateViewPort":50,"direction":1,"delay":0,"loop":false,"fileName":"","mouseOutAction":"none","numberOfLoops":0,"playSpeed":1,"path":"","scrollRelativeTo":"withinSection","trigger":false,"triggerMethod":"pageHover"}}},"uniqueId":"lottie-c7d5ff8e-a115-40ae-aecb-0a84b90fe739"} /--></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row -->
        
        <!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"sizeSm":0,"sizeMd":7,"colClassList":["bs-column",null,"col-md-7","col-sm-0"],"columnBlockID":"3d962b00decd4fef444449c96db45c42599741ec"} -->
        <div class=" bs-column  col-md-7 col-sm-0   bs-column-3d962b00decd4fef444449c96db45c42599741ec bs-column---default     "><!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","textTransform":"uppercase"}},"textColor":"blue","className":"common-header__blue-text"} -->
        <p class="common-header__blue-text has-blue-color has-text-color" style="font-size:18px;text-transform:uppercase"><span>'.$termName.'</span></p>
        <!-- /wp:paragraph -->
        
        <!-- wp:post-title {"level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"400"}},"textColor":"black","className":"common-header__gated-page"} /-->
        '.$bodyContentMobile.'
        <!-- wp:e25m/div {"divBlockID":"55fcd1d264d5e2b220b95765fe5f62e2fba6086e","divClassNames":[{"value":"bs-div\u002d\u002d-default","label":"Default"},{"value":"bs-div\u002d\u002dfootnotes","label":"Footnotes"}]} -->
        <div class="bs-div bs-div-55fcd1d264d5e2b220b95765fe5f62e2fba6086e bs-div---default bs-div--footnotes"><div class="bs-div__inner     "><!-- wp:html -->
        '.$footnotes.'
        <!-- /wp:html --></div></div>
        <!-- /wp:e25m/div --></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":0,"sizeMd":5,"colClassList":["bs-column",null,"col-md-5","col-sm-0"],"columnClassNames":[{"value":"bs-column\u002d\u002d-default","label":"Default"},{"value":"bs-column\u002d\u002dform","label":"Form"}],"columnBlockID":"4e7708ec1047c5de435a1fcaa9bd912b51781ebd"} -->
        <div class=" bs-column  col-md-5 col-sm-0   bs-column-4e7708ec1047c5de435a1fcaa9bd912b51781ebd bs-column---default bs-column--form     "><!-- wp:e25m/div {"divBlockID":"2e97af6cb73103c2a77ad3d0146f9045e143873d","divClassNames":[{"value":"bs-div\u002d\u002d-default","label":"Default"},{"value":"bs-div\u002d\u002dform-wrapper","label":"Form Wrapper"}]} -->
        <div class="bs-div bs-div-2e97af6cb73103c2a77ad3d0146f9045e143873d bs-div---default bs-div--form-wrapper"><div class="bs-div__inner     ">
        <!-- wp:html -->
        '.$formCode.'
        <!-- /wp:html --></div></div>
        <!-- /wp:e25m/div --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/mobile-content-wrapper -->
        <!-- /wp:e25m/mobile-content -->

        <!-- wp:e25m/mobile-content -->
        <!-- wp:e25m/desktop-content-wrapper -->
        <!-- wp:e25m/section {"sectionBlockID":"14ca56fdc3be4b5f0a8616120c8005c221b5c55b","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002drelated-items","label":"Related Items"}]} -->
        <section class="bs-section bs-section-14ca56fdc3be4b5f0a8616120c8005c221b5c55b bs-section---default bs-section--related-items  "><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:heading {"textColor":"deep"} -->
        <h2 class="has-deep-color has-text-color">You Might Also Be Interested in</h2>
        <!-- /wp:heading --></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":0,"sizeMd":12,"colClassList":["bs-column","col-sm-0","col-md-12"],"columnBlockID":"33aba5295b76f4052fb226277a0b4e1e4747889c"} -->
        <div class=" bs-column col-sm-0 col-md-12   bs-column-33aba5295b76f4052fb226277a0b4e1e4747889c bs-column---default     "><!-- wp:e25m/related-posts {"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"postTaxonomies":'.json_encode($arrCat).'} -->
        <div class="bs-related-posts bs-related-posts-block---default"><div class="bs-related-posts__container"><div class="bs-related-posts__items"><!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2828"},"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2830"},"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column --></div></div></div>
        <!-- /wp:e25m/related-posts --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/desktop-content-wrapper -->

        <!-- wp:e25m/mobile-content-wrapper -->
        <!-- wp:paragraph {"placeholder":"This content will only be appeared on Mobile devices..."} -->
        <p></p>
        <!-- /wp:paragraph -->

        <!-- wp:e25m/section {"sectionBlockID":"14ca56fdc3be4b5f0a8616120c8005c221b5c55b","sectionClassNames":[{"value":"bs-section\u002d\u002d-default","label":"Default"},{"value":"bs-section\u002d\u002drelated-items","label":"Related Items"}]} -->
        <section class="bs-section bs-section-14ca56fdc3be4b5f0a8616120c8005c221b5c55b bs-section---default bs-section--related-items  "><div class="container"><!-- wp:e25m/row -->
        <div class="bs-row row   bs-row---default"><!-- wp:e25m/column {"columnBlockID":"5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332"} -->
        <div class=" bs-column col-sm-12   bs-column-5f6574ebb29ac8d58ab608d2aff5b1bbe4f96332 bs-column---default     "><!-- wp:heading {"textColor":"deep"} -->
        <h2 class="has-deep-color has-text-color">You Might Also Be Interested in</h2>
        <!-- /wp:heading --></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":0,"sizeMd":12,"colClassList":["bs-column","col-sm-0","col-md-12"],"columnBlockID":"33aba5295b76f4052fb226277a0b4e1e4747889c"} -->
        <div class=" bs-column col-sm-0 col-md-12   bs-column-33aba5295b76f4052fb226277a0b4e1e4747889c bs-column---default     "><!-- wp:e25m/related-posts {"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"postTaxonomies":'.json_encode($arrCat).'} -->
        <div class="bs-related-posts bs-related-posts-block---default"><div class="bs-related-posts__container"><div class="bs-related-posts__items"><!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2828"},"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column -->

        <!-- wp:e25m/column {"sizeSm":4,"colClassList":["bs-column","col-sm-4"],"columnBlockID":"4bb8c1b66cb5e72c43988fbaf017046daf38fc18"} -->
        <div class=" bs-column col-sm-4   bs-column-4bb8c1b66cb5e72c43988fbaf017046daf38fc18 bs-column---default     "><!-- wp:e25m/single-post {"selectedPostType":"sw_resource","selectedPost":{"label":"","value":"2830"},"displayOrder":[{"value":"image","label":"Image"},{"value":"taxonomy_resource-type","label":"Resource Type"},{"value":"title","label":"Title"},{"value":"more","label":"Read more"}],"popupDisplayOrder":[{"value":"title","label":"Title"}]} /--></div>
        <!-- /wp:e25m/column --></div></div></div>
        <!-- /wp:e25m/related-posts --></div>
        <!-- /wp:e25m/column --></div>
        <!-- /wp:e25m/row --></div></section>
        <!-- /wp:e25m/section -->
        <!-- /wp:e25m/mobile-content-wrapper -->
        <!-- /wp:e25m/mobile-content -->', $postDataMobile);


        return $post_contentDesktop.$post_contentMobile;
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

        $postData = $post_content;
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
