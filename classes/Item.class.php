<?php
/**
 * Class to manage library items
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2018 Lee Garner <lee@leegarner.com>
 * @package     library
 * @version     0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Library;

/**
 * Class for library item.
 * @package library
 */
class Item
{
    /** Property fields.  Accessed via Set() and Get()
     * @var array */
    private $properties;

    /** Indicate whether the current user is an administrator
     * @var boolean */
    private $isAdmin;

    /** Indicate that this is a new item
     * @var boolean */
    private $isNew;

    /** URL to item list, including search params
     * @var string */
    private $ListingUrl;

    /** Array of error messages
     * @var mixed */
    var $Error;

    /** Category object for related category.
     * @var object */
    public $Category;

    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is empty,
     * then a new entry is being created.
     *
     * @param   string  $id     Optional item ID
     */
    public function __construct($id = '')
    {
        global $_CONF_LIB;

        $this->properties = array();
        $this->user_ckout_limit = $_CONF_LIB['def_checkout_limit'];
        if ($id == '') {
            $this->isNew = true;
            $this->id = COM_makeSid();
            $this->oldid = '';
            $this->title = '';
            $this->subtitle = '';
            $this->cat_id = '';
            $this->dscp= '';
            $this->publisher = '';
            $this->pub_date = '';
            $this->author = '';
            $this->daysonhold = $_CONF_LIB['daysonhold'];
            $this->type = 0;
            $this->maxcheckout = $_CONF_LIB['maxcheckout'];
            $this->enabled = 1;
            $this->dt_add = time();
            $this->views = 0;
            $this->rating = 0;
            $this->votes = 0;
            $this->keywords = '';
            $this->status = 0;
            $this->uid = 0;
            $this->Category = NULL;
        } elseif (is_array($id)) {
            $this->setVars($id, true);
            $this->Category = Category::getInstance($this->cat_id);
        } else {
            $this->id = $id;
            if (!$this->Read()) {
                $this->id = COM_makeSid();
                $this->isNew = true;
            } else {
                $this->isNew = false;
                $this->Category = Category::getInstance($this->cat_id);
            }
        }
        $this->ListingUrl = $_CONF_LIB['url'] . '/index.php';
        $this->isAdmin = SEC_hasRights('library.admin') ? 1 : 0;
    }


    /**
     * Set a property's value.
     *
     * @param   string  $var    Name of property to set.
     * @param   mixed   $value  New value for property.
     */
    public function __set($var, $value='')
    {
        switch ($var) {
        case 'id':
        case 'oldid':
            $this->properties[$var] = COM_sanitizeID($value, false);
            break;

        case 'views':
        case 'dt_add':
        case 'daysonhold':
        case 'maxcheckout':
        case 'votes':
        case 'type':
        case 'cat_id':
        case 'comments_enabled':
        case 'uid':
        case 'status':
        case 'due':
        case 'user_ckout_limit':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'rating':
            // Float values
            $this->properties[$var] = (float)$value;
            break;

        case 'dscp':
        case 'title':
        case 'subtitle':
        case 'keywords':
        case 'publisher':
        case 'pub_date':
        case 'author':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'enabled':
            // Boolean values
            $this->properties[$var] = $value == 1 ? 1 : 0;
            break;

        default:
            // Undefined values (do nothing)
            break;
        }
    }


    /**
     * Get the value of a property.
     *
     * @param   string  $var    Name of property to retrieve.
     * @return  mixed           Value of property, NULL if undefined.
     */
    public function __get($var)
    {
        if (array_key_exists($var, $this->properties)) {
            return $this->properties[$var];
        } else {
            return NULL;
        }
    }


    /**
     * Get an item object
     *
     * @param   string|array  $item_id    Item ID
     * @return  object              Item Object
     */
    public static function getInstance($item_id)
    {
        global $_TABLES;
        static $items = array();

        if (empty($item_id)) {
            return new self();
        } elseif (is_array($item_id)) {
            // Already have the DB record, load the vars
            return new self($item_id);
        } elseif (isset($items[$item_id])) {
            return new self($items[$item_id]);
        } else {
            $item_id = COM_sanitizeId($item_id, false);
            $key = 'item_' . $item_id;
            $items[$item_id] = Cache::get($key);
            if ($items[$item_id] === NULL) {
                $result = DB_query("SELECT * FROM {$_TABLES['library.items']}
                    WHERE id='$item_id'");
                if (!$result || DB_numRows($result) != 1) {
                    return new self();
                } else {
                    $items[$item_id] = DB_fetchArray($result, false);
                    Cache::set($key, $items[$item_id], 'items');
                    return new self($items[$item_id]);
                }
            } else {
                return new self($items[$item_id]);
            }
        }
    }


    /**
     * Sets all variables to the matching values from $rows.
     *
     * @param   array   $row        Array of values, from DB or $_POST
     * @param   boolean $fromDB     True if read from DB, false if from $_POST
     */
    public function setVars($row, $fromDB=false)
    {
        if (!is_array($row)) return;

        $this->id   = $row['id'];
        $this->dscp = $row['dscp'];
        $this->publisher = $row['publisher'];
        $this->pub_date = $row['pub_date'];
        $this->author = $row['author'];
        $this->enabled = isset($row['enabled']) ? 1 : 0;
        $this->title = $row['title'];
        $this->subtitle = $row['subtitle'];
        $this->cat_id = $row['cat_id'];
        $this->daysonhold = $row['daysonhold'];
        $this->maxcheckout = $row['maxcheckout'];
        $this->dt_add = $row['dt_add'];
        $this->views = $row['views'];
        $this->keywords = $row['keywords'];
        $this->type = $row['type'];
        $this->votes = $row['votes'];
        $this->rating = $row['rating'];
        $this->comments_enabled = $row['comments_enabled'];

        if ($fromDB) {
            $this->oldid = $row['id'];
            $this->status = $row['status'];
        } else {
            $this->oldid = $row['oldid'];
            $this->status = 0;
        }
    }


    /**
     * Read a specific record and populate the local values.
     *
     * @param   string  $id     Optional ID.  Current ID is used if zero.
     * @return  boolean     True if a record was read, False on failure
     */
    public function Read($id = '')
    {
        global $_TABLES;

        if ($id == '') $id = $this->id;
        if ($id == '') {
            $this->error = 'Invalid ID in Read()';
            return false;
        }
        $id = COM_sanitizeId($id);
        $result = DB_query("SELECT *
                    FROM {$_TABLES['library.items']}
                    WHERE id='$id'");
        if (!$result || DB_numRows($result) != 1) {
            return false;
        } else {
            $row = DB_fetchArray($result, false);
            $this->setVars($row, true);
            return true;
        }
    }


    /**
     * Save the current values to the database.
     * Appends error messages to the $Error property.
     *
     * @param   array   $A      Optional array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save($A = '')
    {
        global $_TABLES, $_CONF_LIB;

        if (is_array($A)) {
            $this->setVars($A);
        }

        $add_instances = isset($A['add_instances']) ? (int)$A['add_instances'] : 0;

        // Insert or update the record, as appropriate.  Make sure the new ID
        // being inserted or changed to doesn't already exist.
        $allowed = ($this->isNew || $this->id != $this->oldid) ? 0 : 1;
        $num1 = DB_count($_TABLES['library.items'], 'id', $this->id);
        if ($num1 > $allowed) {
            $this->Error[] = 'Duplicate ID ' . $this->id;
        } else {
            if ($this->isNew) {
                $sql1 = "INSERT INTO {$_TABLES['library.items']} SET ";
                $sql3 = '';
            } else {
                $sql1 = "UPDATE {$_TABLES['library.items']} SET ";
                $sql3 = " WHERE id = '" . DB_escapeString($this->oldid) . "'";
            }
            $sql2 = "id = '" . DB_escapeString($this->id) . "',
                title ='" . DB_escapeString($this->title) . "',
                subtitle ='" . DB_escapeString($this->subtitle) . "',
                cat_id = '{$this->cat_id}',
                type = '{$this->type}',
                dscp = '" . DB_escapeString($this->dscp) . "',
                publisher = '" . DB_escapeString($this->publisher) . "',
                pub_date = '" . DB_escapeString($this->pub_date) . "',
                author = '" . DB_escapeString($this->author) . "',
                keywords='" . DB_escapeString($this->keywords) . "',
                daysonhold='{$this->daysonhold}',
                maxcheckout='{$this->maxcheckout}',
                enabled='{$this->enabled}',
                views='{$this->views}'";
            $sql = $sql1 . $sql2 . $sql3;
            DB_query($sql, 1);
            if (DB_error()) {
                $this->Error[] = "Failed to insert or update record";
            } else {
                // Handle image uploads.  This is done last because we need
                // the product id to name the images filenames.
                $U = new Image($this->id, 'images');
                $U->uploadFiles();
                if ($U->areErrors() > 0) {
                    $this->Error[] = $U->printErrors(false);
                }
            }
        }
        if (empty($this->Error)) {
            self::makeClone($this->id, $add_instances);
            Cache::clear('items');
            PLG_itemSaved($this->id, $_CONF_LIB['pi_name']);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Delete the current item record from the database.
     */
    public function Delete()
    {
        global $_TABLES, $_CONF_LIB;

        if ($this->id == '')
            return false;

        // Locate and delete photos
        $sql = "SELECT img_id
                FROM {$_TABLES['library.images']}
                WHERE item_id='{$this->id}'";
        //echo $sql;
        $photo= DB_query($sql, 1);
        if (!$photo) return false;
        while ($prow = DB_fetchArray($photo)) {
            $this->DeleteImage($prow['img_id']);
        }

        PLG_itemDeleted($this->id, $_CONF_LIB['pi_name']);
        DB_delete($_TABLES['library.items'], 'id', $this->id);
        $this->id = 0;
        return true;
    }


    /**
     * Deletes a single image from disk.
     *
     * @param   integer $img_id     DB ID of image to delete
     */
    public function DeleteImage($img_id)
    {
        global $_TABLES, $_CONF_LIB;

        $img_id = (int)$img_id;
        if ($img_id < 1) return;

        $filename = DB_getItem($_TABLES['library.images'], 'filename',
                "img_id=$img_id");

        if (file_exists("{$_CONF_LIB['image_dir']}/{$filename}"))
                unlink( "{$_CONF_LIB['image_dir']}/{$filename}" );

        if (file_exists("{$_CONF_LIB['image_dir']}/thumbs/{$filename}"))
                unlink("{$_CONF_LIB['image_dir']}/thumbs/{$filename}");

        DB_delete($_TABLES['library.images'], 'img_id', $img_id);
    }


    /**
     * Determines if the current record is valid.
     *
     * @return  boolean     True if ok, False when first test fails.
     */
    private function isValidRecord()
    {
        // Check that basic required fields are filled in
        if ($this->title == '') {
            return false;
        }

        return true;
    }


    /**
     * Creates the edit form
     *
     * @param   integer     $id Optional ID, current record used if zero
     * @return  string      HTML for edit form
     */
    public function showForm($id = 0)
    {
        global $_TABLES, $_CONF, $_CONF_LIB, $LANG_LIB, $LANG24,
                $LANG_postmodes;

        if ($id != '') {
            // If an id is passed in, then read that record
            if (!$this->Read($id)) {
                return LIBRARY_errorMessage($LANG_LIB['invalid_item_id'], 'info');
            }
        }
        $id = $this->id;

        $T = new \Template($_CONF_LIB['pi_path'] . '/templates');
        $T->set_file(array(
            'product'   => 'item_form.thtml',
            'tips'      => 'tooltipster.thtml',
        ) );
        $action_url = $_CONF_LIB['admin_url'] . '/index.php';
        if ($this->oldid != '') {
            $retval = COM_startBlock($LANG_LIB['edit'] . ': ' . $this->title);
        } else {
            $retval = COM_startBlock($LANG_LIB['new_item']);
        }

        // Set up the wysiwyg editor, if available
        switch (PLG_getEditorType()) {
        case 'ckeditor':
            $T->set_var('show_htmleditor', true);
            PLG_requestEditor('library','library_entry','ckeditor_library.thtml');
            PLG_templateSetVars('library_entry', $T);
            break;
        case 'tinymce':
            $T->set_var('show_htmleditor',true);
            PLG_requestEditor('library','library_entry','tinymce_library.thtml');
            PLG_templateSetVars('library_entry', $T);
            break;
        default:
            // don't support others right now
            $T->set_var('show_htmleditor', false);
            break;
        }

        $T->set_var(array(
            'oldid'         => $this->oldid,
            'dt_add'        => $this->dt_add,
            'id'            => $this->id,
            'title'         => htmlspecialchars($this->title),
            'subtitle'      => htmlspecialchars($this->subtitle),
            'category'      => $this->cat_id,
            'dscp'          => htmlspecialchars($this->dscp),
            'publisher'     => $this->publisher,
            'pub_date'      => $this->pub_date,
            'author'        => $this->author,
            'daysonhold'    => $this->daysonhold,
            'maxcheckout'   => $this->maxcheckout,
            'pi_admin_url'  => $_CONF_LIB['admin_url'],
            'keywords'      => htmlspecialchars($this->keywords),
            'cat_select'    => Category::buildSelection($this->cat_id),
            'pi_url'        => $_CONF_LIB['url'],
            'doc_url'       => LIBRARY_getDocURL('item_form.html',
                                            $_CONF['language']),
            'lookup_method' => $_CONF_LIB['lookup_method'],
            'add_instances' => $this->isNew ? 1 : 0,
            'total_instances' => sprintf($LANG_LIB['total_instances'],
                    DB_count($_TABLES['library.instances'], 'item_id',$this->id)),
            'type_select'   => MediaType::buildSelection($this->type, false),
            'ena_chk'       => $this->enabled == 1 ? ' checked="checked"' : '',
        ) );
        if (!$this->isNew && !self::isUsed($this->id)) {
            $T->set_var('candelete', 'true');
        }

        // Set up the photo fields.  Use $photocount defined above.
        // If there are photos, read the $photo result.  Otherwise,
        // or if this is a new ad, just clear the photo area
        $T->set_block('product', 'PhotoRow', 'PRow');
        $i = 0;

        // Get the existing photos.  Will only have photos with an
        // existing product entry.
        $photocount = 0;
        if ($this->id != NULL) {
            $sql = "SELECT img_id, filename
                    FROM {$_TABLES['library.images']}
                    WHERE item_id='{$this->id}'";
            $photo = DB_query($sql);

            // save the count of photos for later use
            if ($photo)
                $photocount = DB_numRows($photo);
            else
                $photocount = 0;

            // While we're checking the ID, set it as a hidden value
            // for updating this record
            $T->set_var('product_id', $this->id);
        } else {
            $T->set_var('product_id', '');
        }

        // If there are any images, retrieve and display the thumbnails.
        if ($photocount > 0) {
            while ($prow = DB_fetchArray($photo)) {
                $i++;
                $filepath = $_CONF_LIB['image_dir'] . '/' . $prow['filename'];
                $T->set_var(array(
                    'img_url'   => LGLIB_ImageUrl($filepath, 800, 600), // todo
                    'thumb_url' => LGLIB_ImageUrl($filepath,
                            $_CONF_LIB['max_thumb_size'], $_CONF_LIB['max_thumb_size']),
                    'seq_no'    => $i,
                    'id'        => $this->id,
                    'del_img_url' => $_CONF_LIB['admin_url'] . '/index.php' .
                        '?mode=delete_img' .
                        "&img_id={$prow['img_id']}".
                        "&id={$this->id}",
                ) );
                $T->parse('PRow', 'PhotoRow', true);
            }
        } else {
            $T->parse('PRow', '');
        }

        // add upload fields for unused images
        $T->set_block('product', 'UploadFld', 'UFLD');
        for ($j = $i; $j < $_CONF_LIB['max_images']; $j++) {
            $T->parse('UFLD', 'UploadFld', true);
        }
        $T->parse('tooltipster', 'tips');
        $retval .= $T->parse('output', 'product');
        @setcookie($_CONF['cookie_name'].'fckeditor',
                SEC_createTokenGeneral('advancededitor'),
                time() + 1200, $_CONF['cookie_path'],
                $_CONF['cookiedomain'], $_CONF['cookiesecure']);

        $retval .= COM_endBlock();
        return $retval;
    }   // function showForm()


    /**
     * Toggles the value of a field.
     *
     * @param   integer $oldvalue   Original value to change
     * @param   string  $varname    Name of field
     * @param   string  $id         Item ID number of element to modify
     * @return         New value, or old value upon failure
     */
    private static function _toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        // Determing the new value (opposite the old)
        $newvalue = $oldvalue == 1 ? 0 : 1;
        $id = COM_sanitizeID($id, false);

        $sql = "UPDATE {$_TABLES['library.items']}
                SET $varname=$newvalue
                WHERE id='$id'";
        //COM_errorLog($sql);
        DB_query($sql, 1);
        return DB_error() ? $oldvalue : $newvalue;
    }


    /**
     * Sets the "enabled" field to the specified value.
     *
     * @param   integer $oldvalue   Original value to be changed
     * @param   string  $id         ID number of element to modify
     * @return         New value, or old value upon failure
     */
    public static function toggleEnabled($oldvalue, $id)
    {
        $oldvalue = $oldvalue == 0 ? 0 : 1;
        if ($id == '') return $oldval;
        return self::_toggle($oldvalue, 'enabled', $id);
    }


    /**
     * Determine if this item has any transaction or waitlist records.
     * Typically used to prevent deletion of product records that have
     * dependencies.
     *
     * @param   string  $item_id    Library Item ID
     * @return  boolean         True if used, False if not
     */
    public static function isUsed($item_id)
    {
        global $_TABLES;

        $item_id = DB_escapeString($item_id);
        if (DB_count($_TABLES['library.log'], 'id',
                $item_id) > 0) {
            return true;
        } elseif (DB_count($_TABLES['library.waitlist'], 'id',
                $item_id) > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Display the detail page for the product.
     *
     * @return  string      HTML for the product page.
     */
    public function Detail()
    {
        global $_CONF, $_CONF_LIB, $_TABLES, $LANG_LIB, $_USER;

        USES_lib_comments();

        if ($this->id == '') {
            return LIBRARY_errorMessage($LANG_LIB['invalid_item_id'], 'info');
        }

        $retval = COM_startBlock();
        $T = new \Template($_CONF_LIB['pi_path'] . '/templates');
        $T->set_file(array(
            'item' => 'item_detail.thtml',
            'formjs' => 'checkinout_js.thtml',
        ) );

        // Highlight the query terms if coming from a search
        if (isset($_REQUEST['query']) && !empty($_REQUEST['query'])) {
            $title = COM_highlightQuery($this->title, $_REQUEST['query']);
            $subtitle = COM_highlightQuery($this->subtitle, $_REQUEST['query']);
            $l_desc = COM_highlightQuery($this->dscp, $_REQUEST['query']);
        } else {
            $title = $this->title;
            $subtitle = $this->subtitle;
            $l_desc = $this->dscp;
        }
        $T->set_var(array(
            'user_id'           => $_USER['uid'],
            'id'                => $this->id,
            'title'             => $title,
            'subtitle'          => $subtitle,
            'dscp'              => $l_desc,
            'img_cell_width'    => ($_CONF_LIB['max_thumb_size'] + 20),
            'pi_url'            => $_CONF_LIB['url'],
            'avail_blk'         => $this->AvailBlock(),
            'publisher'         => $this->publisher,
            'pub_date'          => $this->pub_date,
            'author'            => $this->author,
            'listing_url'       => $this->ListingUrl,
            'can_edit'          => plugin_ismoderator_library(),
        ) );

        // Retrieve the photos and put into the template
        $sql = "SELECT img_id, filename
                FROM {$_TABLES['library.images']}
                WHERE item_id='{$this->id}'";
        //echo $sql;die;
        $img_res = DB_query($sql);
        $photo_detail = '';
        $T->set_var('have_photo', '');     // assume no photo available
        for ($i = 0; $prow = DB_fetchArray($img_res, false); $i++) {
            $img_file = "{$_CONF_LIB['image_dir']}/{$prow['filename']}";
            $tn_url = LGLIB_ImageUrl($img_file, $_CONF_LIB['max_thumb_size'],
                    $_CONF_LIB['max_thumb_size']);
            $img_url = LGLIB_ImageUrl($img_file, 800, 600);
            if ($tn_url !== '') {
                if ($i == 0) {
                    $T->set_var('main_img_url', LGLIB_ImageUrl($img_file,
                        //$_CONF_LIB['max_img_width'], $_CONF_LIB['max_img_height']));
                        800, 600));
                }
                $T->set_block('item', 'Thumbnail', 'PBlock');
                $T->set_var('tn_url', $tn_url);
                $T->set_var('img_url', $img_url);
                $T->parse('PBlock', 'Thumbnail', true);
                $T->set_var('have_photo', 'true');
            }
        }
        // Show the user comments
        if (plugin_commentsupport_library() &&
            $this->comments_enabled < 2) {
            if ($_CONF['commentsloginrequired'] == 1 && COM_isAnonUser()) {
                $mode = -1;
            } else {
                $mode = $this->comments_enabled;
            }
            $T->set_var('usercomments',
                CMT_userComments($this->id, $this->title, 'library', '',
                    '', 0, 1, false, false, $mode));
        }

        if ($_CONF_LIB['ena_ratings'] == 1) {
            $ratedIds = RATING_getRatedIds('library');
            if (in_array($this->id, $ratedIds)) {
                $static = true;
                $voted = 1;
            } elseif (plugin_canuserrate_library($this->id, $_USER['uid'])) {
                $static = 0;
                $voted = 0;
            } else {
                $static = 1;
                $voted = 0;
            }
            $rating_box = RATING_ratingBar('library', $this->id,
                    $this->votes, $this->rating,
                    $voted, 5, $static, 'sm');
            $T->set_var('rating_bar', $rating_box);
        } else {
            $T->set_var('ratign_bar', '');
        }

        if (plugin_ismoderator_library()) {
            $T->parse('checkinout_js', 'formjs');
        }

        $retval .= $T->parse('output', 'item');

        // Update the hit counter
        DB_query("UPDATE {$_TABLES['library.items']}
                SET views = views + 1
                WHERE id = '{$this->id}'");

        $retval .= COM_endBlock();

        return $retval;

    }


    /**
     * Create a formatted display-ready version of the error messages.
     *
     * @return  string      Formatted error messages.
     */
    public function PrintErrors()
    {
        $retval = '';
        foreach($this->Error as $key=>$msg) {
            $retval .= "<li>$msg</li>\n";
        }
        return $retval;
    }


    /**
     * Hightlight keywords found in text string.
     * Credits: http://www.bitrepository.com/
     *
     * @param   string  $str        Text to highlight
     * @param   string  $keywords   String of keywords to highlight in $str
     * @return  string          New text with highlighting
     */
    private function highlight($str = '', $keywords = '')
    {
        $patterns = Array();
        $replaces = Array();
        $style = 'background: #66FF66;';

        if ($keywords == "") {
            return $str;
        }

        $words = explode(" ", $keywords);

        foreach($words as $word) {

            $patterns[] = '/' . $word . '/i';
            $replaces[] = "<span style=\"$style\">$0</span>";

        }

        return preg_replace($patterns, $replaces, $str);

        return $str;
    }


    /**
     * Check out this item to a user.
     *
     * @param   integer $to     User ID of the borrower
     * @param   string  $due    Optional due date
     */
    public function checkOut($to, $due='')
    {
        $to = (int)$to;
        if ($to == 1)           // Can't check out to anonymous
            return;
        if ($to == 0 && empty($_POST['co_username'])) {
            return;
        }

        if (empty($due)) {
            $due = LIBRARY_dueDate($this->maxcheckout)->toUnix();
        } else {
            $due = (int)$due;
        }

        $instances = Instance::getAll($this->id, LIB_STATUS_AVAIL);
        if (empty($instances)) {
            return;
        }
        Instance::checkOut($instances[0], $to, $due);
        // Clear the instance cache so the counters will be updated
        Cache::clear(array('instance', $this->id));
        // Delete this user from the waitlist, if applicable
        Waitlist::Remove($this->id, $to);

        // Reset other waitlist expirations. If this borrower was not the next
        // in line, the actual first borrower probably has a reservation
        // expiration that should be removed.
        Waitlist::resetExpirations($this->id);
    }


    /**
     * Check in an item.
     *
     * @param   integer $instance_id    Item instance ID
     */
    public function checkIn($instance_id)
    {
        $I = new Instance($instance_id);
        $I->checkIn();
        // If there's a reservation for this item, notify the reserver.
        Waitlist::notifyNext($this->id);
    }


    /**
     * Creates a block showing the item availability and links.
     * Meant to be added to the detail or listing.
     *
     * @return  string      HTML for block.
     */
    public function AvailBlock()
    {
        global $_TABLES, $LANG_LIB, $_USER, $_CONF_LIB;

        $T = new \Template($_CONF_LIB['pi_path'] . '/templates');
        $T->set_file('avail', 'avail_block.thtml');
        $avail = Instance::getAll($this->id, LIB_STATUS_AVAIL);
        $waitlisters = Waitlist::countByItem($this->id);
        $num_avail = max(count($avail) - $waitlisters, 0);

        if (!$this->canCheckout()) {
            $can_reserve = false;
            $is_reserved = false;
            $reserve_txt = $LANG_LIB['login_req'];
            $waitlisters = 0;
            $user_wait_items = 0;
            $waitlist_txt = '';
        } else {
            $waitlist_pos = Waitlist::getUserPosition($this->id, $_USER['uid']);
            //$waitlisters = Waitlist::countByItem($this->id);
            $user_wait_items = Waitlist::countByUser($_USER['uid']);
            $reserve_txt = $waitlisters ? sprintf($LANG_LIB['has_waitlist'], $waitlisters) : '';
            $waitlist_txt = '';
            if ($waitlist_pos > 0) {
                $can_reserve = false;
                $is_reserved = true;
                $waitlist_txt = sprintf($LANG_LIB['on_waitlist'], $waitlist_pos);
            } else {
                $can_reserve = $user_wait_items < $_CONF_LIB['max_wait_items'] ? true : false;
                $is_reserved = false;
            }
        }

        // Check if the current user already has the item checked out
        $item_checked_out = Instance::UserHasItem($this->id, $_USER['uid']);
        $all_checked_out= Instance::countByUser($_USER['uid']);
        if ($item_checked_out) {
        //if ($all_checked_out >= $this->user_ckout_limit) {
            $avail_txt = $LANG_LIB['by_you'];
            $can_reserve = false;
            $wait_action_txt = '';
            $reserve_txt = '';
        } else {
            if ($num_avail > 0) {
                if (($user_wait_items + $all_checked_out) < $_CONF_LIB['max_wait_items']) {
                    $avail_txt = sprintf($LANG_LIB['avail_cnt'], $num_avail);
                } elseif (!$is_reserved) {
                    $avail_txt = $LANG_LIB['max_wait_items'];
                    $can_reserve = false;
                } else {
                    $avail_txt = '';
                }
            } else {
                $avail_txt = $LANG_LIB['checkedout'];
                $avail_icon = 'red.png';
            }
        }
        $total_instances = count(self::getInstances($this->id));
        if ($total_instances > count($avail)) {
            $can_checkin = true;
        } else {
            $can_checkin = false;
        }
        $T->set_var(array(
            'can_reserve'   => $can_reserve,
            'is_reserved'   => $is_reserved,
            'wait_limit_reached' => $user_wait_items >= $_CONF_LIB['max_wait_items'],
            'avail_txt'     => $avail_txt,
            'waitlist_txt'  => $waitlist_txt,
            'due_dt'        => '',
            'reserve_txt' => $reserve_txt,
            'id'            => $this->id,
            'pi_url'        => $_CONF_LIB['url'],
            'pi_admin_url'  => $_CONF_LIB['admin_url'],
            'is_librarian'  => plugin_ismoderator_library(),
            'can_checkout'  => count($avail),
            'can_checkin'   => $can_checkin,
            'num_avail'     => sprintf($LANG_LIB['avail_cnt'], count($avail) . '/' . $total_instances),
        ) );
        $T->parse('output', 'avail');
        $retval = $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Allow the listing URL to be overridden with parameters, etc.
     *
     * @param   string  $url    New complete listing URL
     */
    public function setListUrl($url)
    {
        $this->ListingUrl = $url;
    }


    /**
     * Add one or more instances of an item
     *
     * @param   string  $item_id    Item ID
     * @param   integer $count      Number of instances to add
     */
    public static function makeClone($item_id, $count = 1)
    {
        global $_TABLES;

        $count = (int)$count;
        $item_id = DB_escapeString($item_id);
        $values = array();
        for ($i = 0; $i < $count; $i++) {
            $values[] = "('$item_id')";
        }
        if (!empty($values)) {
            $values = implode(',', $values);
            $sql = "INSERT INTO {$_TABLES['library.instances']}
                    (item_id) VALUES $values";
            DB_query($sql);
            Cache::clear(array('instance', $item_id));
        }
    }


    /**
     * Shortcut function to get all the instances of an item with a given status
     *
     * @param   string  $item_id    Item ID
     * @param   integer $status     One of 0 = all, 1 = available, 2 = checked out, 3 = all
     * @return  array       Array of instance records
     */
    public static function getInstances($item_id, $status = 0)
    {
        return Instance::getAll($item_id, $status);
    }


    /**
     * Check if the current user can view this item.
     * Just calls Category::hasAccess() if the category is valid.
     *
     * @return  boolean     True if view access is allowed.
     */
    public function canView()
    {
        if ($this->Category !== NULL &&
            $this->Category->hasAccess()) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Check if the current user can check out this item.
     * Requires view access to the category along with the checkout
     * privilege.
     *
     * @return  boolean     True if the user can checkout this item.
     */
    public function canCheckout($uid = 0)
    {
        global $_USER;
        static $ckouts = array();

        if (!$this->canView() || !SEC_hasRights('library.checkout')) {
            return false;
        }
/*
        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;
        $checkouts = Instance::countByUser($uid);
        if ($checkouts >= $this->user_ckout_limit) {
            return false;
        }
 */
        // Didn't fail any conditions, return OK
        return true;
    }


    /**
     * Display the item checkin form.
     *
     * @param   string  $id     Item ID
     * @param   boolean $ajax   True if this is an AJAX form
     * @return  string          HTML for the form
     */
    public static function checkinForm($id, $ajax=false)
    {
        global $_CONF, $LANG_LIB, $_CONF_LIB;

        $I = self::getInstance($id);
        if ($I->isNew || $I->id == '') {
            return 'Invalid Item Selected';
        }

        $instances = self::getInstances($id, LIB_STATUS_OUT);
        $opts = '';
        foreach ($instances as $inst_id=>$inst) {
            $username = COM_getDisplayName($inst->uid);
            $due = new \Date($inst->due, $_CONF['timezone']);
            $due = $due->format('Y-m-d', true);

            $opts .= '<option value="' . $inst->instance_id . '">' .
                $username . ' - ' . $LANG_LIB['dt_due'] . ': ' . $due .
                '</option>';
        }
        $T = new \Template($_CONF_LIB['pi_path'] . '/templates');
        $T->set_file('form', 'checkin_form.thtml');
        $T->set_var(array(
            'title'         => $LANG_LIB['admin_title'],
            'action_url'    => $_CONF_LIB['admin_url'] . '/index.php',
            'pi_url'        => $_CONF_LIB['url'],
            'item_id'       => $I->id,
            'title'         => $I->title,
            'subtitle'      => $I->subtitle,
            'item_desc'     => $I->dscp,
            'instance_select' => $opts,
            'is_ajax'       => $ajax,
        ) );
        $T->parse('output', 'form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Display the item checkout form.
     *
     * @param   string  $id     Item ID
     * @param   boolean $ajax   True if this is an AJAX form
     * @return  string          HTML for the form
     */
    public static function checkoutForm($id, $ajax=false)
    {
        global $_CONF, $LANG_LIB, $_CONF_LIB;

        $I = self::getInstance($id);
        if ($I->isNew || $I->id == '') {
            return '';
        }
        USES_library_functions();

        // Get the ISO language.  This is to load the correct language for
        // the calendar popup, so make sure a corresponding language file
        // exists.  Default to English if not found.
        $iso_lang = $_CONF['iso_lang'];
        if (!is_file($_CONF['path_html'] . $_CONF_LIB['pi_name'] .
            '/js/calendar/lang/calendar-' . $iso_lang . '.js')) {
            $iso_lang = 'en';
        }

        // Default to the plugin config for maxcheckout if not set for
        // this item.
        if ($I->maxcheckout < 1) {
            $I->maxcheckout = (int)$_CONF_LIB['maxcheckout'];
        }

        $T = new \Template($_CONF_LIB['pi_path'] . '/templates');
        $T->set_file('form', 'checkout_form.thtml');
        $T->set_var(array(
            'title'         => $LANG_LIB['admin_title'],
            'action_url'    => $_CONF_LIB['admin_url'] . '/index.php',
            'pi_url'        => $_CONF_LIB['url'],
            'item_id'       => $I->id,
            'title'         => $I->title,
            'subtitle'      => $I->subtitle,
            'item_desc'     => $I->dscp,
            'user_select'   => LIBRARY_userSelect($I->id),
            'due'           => LIBRARY_dueDate($I->maxcheckout)->format('Y-m-d'),
            'iso_lang'      => $iso_lang,
            'is_ajax'       => $ajax,
        ) );
        $T->parse('output', 'form');
        return $T->finish($T->get_var('output'));
    }

}   // class Item

?>
