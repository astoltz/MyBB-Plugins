<?php
/***************************************************************************
 *
 *   NewPoints Level plugin (/inc/plugins/newpoints/languages/english/admin/newpoints_levels.lang.php)
 *       Author: Andrew M. Stoltz
 *   Copyright: © 2024 Andrew M. Stoltz
 *
 *   Website: https://github.com/astoltz
 *
 *   Integrates a Level system with NewPoints.
 *
 ***************************************************************************/

/****************************************************************************
        This program is free software: you can redistribute it and/or modify
        it under the terms of the GNU General Public License as published by
        the Free Software Foundation, either version 3 of the License, or
        (at your option) any later version.

        This program is distributed in the hope that it will be useful,
        but WITHOUT ANY WARRANTY; without even the implied warranty of
        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
        GNU General Public License for more details.

        You should have received a copy of the GNU General Public License
        along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("newpoints_start", "newpoints_levels_page");

if (defined("IN_ADMINCP"))
{
    // Subscriptions ACP page (Add/Edit/Delete subscription plans)
    $plugins->add_hook('newpoints_admin_load', 'newpoints_levels_admin');
    $plugins->add_hook('newpoints_admin_newpoints_menu', 'newpoints_levels_admin_newpoints_menu');
    $plugins->add_hook('newpoints_admin_newpoints_action_handler', 'newpoints_levels_admin_newpoints_action_handler');
    $plugins->add_hook('admin_user_users_edit_profile', 'newpoints_levels_admin_admin_user_users_edit_profile');
    $plugins->add_hook('datahandler_user_validate', 'newpoints_levels_datahandler_user_validate');
    $plugins->add_hook('datahandler_user_update', 'newpoints_levels_datahandler_user_update');
}
else
{
    $plugins->add_hook("newpoints_default_menu", "newpoints_levels_menu");
    $plugins->add_hook("member_profile_end", "newpoints_levels_profile");
    $plugins->add_hook("member_profile_start", "newpoints_levels_profile_lang");
}

$plugins->add_hook("newpoints_task_backup_tables", "newpoints_levels_backup");


if(defined('THIS_SCRIPT'))
{
    global $templatelist;

    if(isset($templatelist))
    {
        $templatelist .= ',';
    }

    if(THIS_SCRIPT== 'newpoints.php' && $mybb->input['action'] == 'levels')
    {
        $templatelist .= 'newpoints_levels_row,newpoints_levels_current_level_row,newpoints_levels_next_level_row,newpoints_levels_empty,newpoints_levels';
    }
    elseif(THIS_SCRIPT== 'member.php')
    {
        $templatelist .= 'newpoints_levels_profile_current_level,newpoints_levels_profile_next_level,newpoints_levels_profile_current_level_not_enrolled,newpoints_levels_profile_next_level_not_enrolled';
    }
}

function newpoints_levels_info()
{
    return array(
        "name"          => "Levels",
        "description"   => "Integrates a Level system with NewPoints.",
        //"website"       => "https://github.com/DiogoParrinha",
        "author"        => "Andrew M. Stoltz",
        //"authorsite"    => "https://github.com/DiogoParrinha",
        "version"       => "1.0.1",
        //"guid"          => "",
        //"compatibility" => "2*"
    );
}


function newpoints_levels_install()
{
    global $db;

    // add settings
    newpoints_add_setting('newpoints_levels_pmadmins', 'newpoints_levels', 'PM Admins', 'Enter the user IDs of the users that get PMs whenever an item is bought (separated by a comma).', 'text', '1', 1);
    newpoints_add_setting('newpoints_levels_pm_default', 'newpoints_levels', 'Default PM', 'Enter the content of the message body that is sent by default to users when they buy an item (note: this PM can be customized for each item; this is used in case one is not present). You can use {levelid}, {levelname}, {leveldescription}, and {levelprice}.', 'textarea', '', 2);
    newpoints_add_setting('newpoints_levels_pmadmin_default', 'newpoints_levels', 'Default Admin PM', 'Enter the content of the message body that is sent by default to admins when a user buys an item (note: this PM can be customized for each item; this is used in case one is not present). You can use {levelid}, {levelname}, {leveldescription}, and {levelprice}.', 'textarea', '', 3);

    $collation = $db->build_create_table_collation();

    $db->write_query("CREATE TABLE `".TABLE_PREFIX."newpoints_levels` (
      `lid` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL DEFAULT '',
      `description` text NOT NULL,
      `price` DECIMAL(16,2) NOT NULL default 0.00,
      `icon` varchar(255) NOT NULL default '',
      `visible` smallint(1) NOT NULL default '1',
      `disporder` int(5) NOT NULL default '0',
      `infinite` smallint(1) NOT NULL default '0',
      `stock` int(10) NOT NULL default '0',
      `pm` text NOT NULL,
      `pmadmin` text NOT NULL,
      PRIMARY KEY  (`lid`)
    ) ENGINE=InnoDB{$collation}");

    $db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD `newpoints_level` bigint UNSIGNED;");
}

function newpoints_levels_is_installed()
{
    global $db;
    if($db->table_exists('newpoints_levels'))
    {
        return true;
    }
    return false;
}

function newpoints_levels_uninstall()
{
    global $db;

    if($db->table_exists('newpoints_levels'))
    {
        $db->drop_table('newpoints_levels');
    }

    if($db->field_exists('newpoints_level', 'users'))
        $db->write_query("ALTER TABLE `".TABLE_PREFIX."users` DROP `newpoints_level`;");

    // delete settings
    newpoints_remove_settings("'newpoints_levels_pmadmins','newpoints_levels_pm_default','newpoints_levels_pmadmin_default'");
    rebuild_settings();

    newpoints_remove_log(array('levels_level_up'));
}

function newpoints_levels_activate()
{
    global $db, $mybb;

    newpoints_add_template('newpoints_levels_profile_current_level', '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td colspan="2" class="thead"><strong>{$lang->newpoints_levels_current_level}</strong>: {$current_level[\'name\']}</td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->newpoints_levels_level_description}:</strong></td>
<td class="trow1">{$current_level[\'description\']}</td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->newpoints_levels_level_icon}:</strong></td>
<td class="trow1"><img src="{$mybb->settings[\'bburl\']}/{$current_level[\'icon\']}" width="300"></td>
</tr>
</table>');

    newpoints_add_template('newpoints_levels_profile_next_level', '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td colspan="2" class="thead"><strong>{$lang->newpoints_levels_next_level}</strong>: {$next_level[\'name\']}</td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->newpoints_levels_level_description}:</strong></td>
<td class="trow1">{$next_level[\'description\']}</td>
</tr>
<tr>
<td class="trow2"><strong>{$lang->newpoints_levels_level_icon}:</strong></td>
<td class="trow2"><img src="{$mybb->settings[\'bburl\']}/{$next_level[\'icon\']}" width="300"></td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->newpoints_levels_level_icon}:</strong></td>
<td class="trow1">{$next_level[\'price\']}</td>
</tr>
<tr>
<td class="trow2"><strong>{$lang->newpoints_levels_leveled_up_title}:</strong></td>
<td class="trow2">-</td>
</tr>
</table>');

    newpoints_add_template('newpoints_levels_profile_next_level_not_enough', '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td colspan="2" class="thead"><strong>{$lang->newpoints_levels_next_level}</strong>: {$next_level[\'name\']}</td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->newpoints_levels_level_description}:</strong></td>
<td class="trow1">{$next_level[\'description\']}</td>
</tr>
<tr>
<td class="trow2"><strong>{$lang->newpoints_levels_level_icon}:</strong></td>
<td class="trow2"><img src="{$mybb->settings[\'bburl\']}/{$next_level[\'icon\']}" width="300"></td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->newpoints_levels_level_icon}:</strong></td>
<td class="trow1">{$next_level[\'price\']}</td>
</tr>
<tr>
<td class="trow2"><strong>{$lang->newpoints_levels_leveled_up_title}:</strong></td>
<td class="trow2"><a href="{$mybb->settings[\'bburl\']}/newpoints.php?action=do_levelup&amp;return_to=profile">Level up</a></td>
</tr>
</table>');

    newpoints_add_template('newpoints_levels_profile_current_level_not_enrolled', '<!-- newpoints_levels_current_level_not_enrolled -->');

    newpoints_add_template('newpoints_levels_profile_next_level_not_enrolled', '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td colspan="2" class="thead"><strong>{$lang->newpoints_levels_next_level}</strong>: {$next_level[\'name\']}</td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->newpoints_levels_level_description}:</strong></td>
<td class="trow1">{$next_level[\'description\']}</td>
</tr>
<tr>
<td class="trow2"><strong>{$lang->newpoints_levels_level_icon}:</strong></td>
<td class="trow2"><img src="{$mybb->settings[\'bburl\']}/{$next_level[\'icon\']}" width="300"></td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->newpoints_levels_level_icon}:</strong></td>
<td class="trow1">{$next_level[\'price\']}</td>
</tr>
<tr>
<td class="trow2"><strong>{$lang->newpoints_levels_leveled_up_title}:</strong></td>
<td class="trow2"><a href="{$mybb->settings[\'bburl\']}/newpoints.php?action=do_levelup&amp;return_to=profile">{$lang->newpoints_levels_level_up}</a></td>
</tr>
</table>');

    newpoints_add_template('newpoints_levels_row', '<tr>
<td class="{$bgcolor}" width="21%"><img src="{$mybb->settings[\'bburl\']}/{$level[\'icon\']}" width="300"></td>
<td class="{$bgcolor}" width="42%">{$level[\'name\']}<br /><span class="smalltext">{$level[\'description\']}<span></td>
<td class="{$bgcolor}" width="21%" align="center">{$level[\'price_display\']}</td>
<td class="{$bgcolor}" width="16%" align="center"></td>
</tr>');
    newpoints_add_template('newpoints_levels_current_level_row', '<tr>
<td class="{$bgcolor}" width="21%"><img src="{$mybb->settings[\'bburl\']}/{$level[\'icon\']}" width="300"></td>
<td class="{$bgcolor}" width="42%">{$level[\'name\']} <span class="smalltext">[Current Level]</span><br />
<span class="smalltext">{$level[\'description\']}<span></td>
<td class="{$bgcolor}" width="21%" align="center">{$level[\'price_display\']}</td>
<td class="{$bgcolor}" width="16%" align="center"></td>
</tr>');
    newpoints_add_template('newpoints_levels_next_level_row', '<tr>
<td class="{$bgcolor}" width="21%"><img src="{$mybb->settings[\'bburl\']}/{$level[\'icon\']}" width="300"></td>
<td class="{$bgcolor}" width="42%">{$level[\'name\']} <span class="smalltext">[Next Level]</span><br />
<span class="smalltext">{$level[\'description\']}<span></td>
<td class="{$bgcolor}" width="21%" align="center">{$level[\'price_display\']}</td>
<td class="{$bgcolor}" width="16%" align="center"><a href="{$mybb->settings[\'bburl\']}/newpoints.php?action=do_levelup&amp;return_to=newpoints">{$lang->newpoints_levels_level_up}</a></td>
</tr>');
    newpoints_add_template('newpoints_levels_next_level_row_not_enough', '<tr>
<td class="{$bgcolor}" width="21%"><img src="{$mybb->settings[\'bburl\']}/{$level[\'icon\']}" width="300"></td>
<td class="{$bgcolor}" width="42%">{$level[\'name\']} <span class="smalltext">[Next Level]</span><br />
<span class="smalltext">{$level[\'description\']}<span></td>
<td class="{$bgcolor}" width="21%" align="center">{$level[\'price_display\']}</td>
<td class="{$bgcolor}" width="16%" align="center">[Not enough]<br />

<strong>{$currency}:</strong> <a href="{$mybb->settings[\'bburl\']}/newpoints.php">{$points}</a><br />
<strong>{$lang->newpoints_levels_price}:</strong> {$level[\'price\']}<br />
<strong>{$lang->newpoints_levels_needed}:</strong> {$needed}<br /></td>
</tr>');
    newpoints_add_template('newpoints_levels_empty', '<tr>
<td class="trow1" width="100%" colspan="3">{$lang->newpoints_levels_empty}</td>
</tr>');
    newpoints_add_template('newpoints_levels', '<html>
<head>
<title>{$lang->newpoints_levels_title} - {$lang->newpoints}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
<td valign="top" width="180">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead"><strong>{$lang->newpoints_menu}</strong></td>
</tr>
{$options}
</table>
</td>
<td valign="top">
{$inline_errors}

<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="5"><strong>{$lang->newpoints_levels_title}</strong></td>
</tr>
<tr>
<td class="tcat" width="21%"><strong>{$lang->newpoints_levels_icon}</strong></td>
<td class="tcat" width="42%"><strong>{$lang->newpoints_levels_name}</strong></td>
<td class="tcat" width="21%" align="center"><strong>{$lang->newpoints_levels_price}</strong></td>
<td class="tcat" width="16%" align="center"><strong>{$lang->newpoints_levels_actions}</strong></td>
</tr>
{$levels}
</table>
<br />
</td>
</tr>
</table>
{$footer}
</body>
</html>');

    require_once MYBB_ROOT."inc/adminfunctions_templates.php";
    find_replace_templatesets("member_profile", '#'.preg_quote('{$contact_details}').'#', '{$newpoints_levels_profile_current_level}'.'{$newpoints_levels_profile_next_level}'.'{$contact_details}');
}

function newpoints_levels_deactivate()
{
    global $db, $mybb;

    newpoints_remove_templates("'newpoints_levels_profile_current_level','newpoints_levels_profile_next_level','newpoints_levels_profile_next_level_not_enough','newpoints_levels_profile_current_level_not_enrolled','newpoints_levels_profile_next_level_not_enrolled','newpoints_levels_row','newpoints_levels_current_level_row','newpoints_levels_next_level_row','newpoints_levels_next_level_row_not_enough','newpoints_levels_empty','newpoints_levels'");

    require_once MYBB_ROOT."inc/adminfunctions_templates.php";
    find_replace_templatesets("member_profile", '#'.preg_quote('{$newpoints_levels_profile_current_level}').'#', '', 0);
    find_replace_templatesets("member_profile", '#'.preg_quote('{$newpoints_levels_profile_next_level}').'#', '', 0);
}

// backup the subscriptions table too
function newpoints_levels_backup(&$backup_fields)
{
    global $db, $table, $tables;

    $tables[] = TABLE_PREFIX.'newpoints_levels';
}

function newpoints_levels_admin_newpoints_menu(&$sub_menu)
{
    global $lang;

    newpoints_lang_load('newpoints_levels');
    $sub_menu[] = array('id' => 'levels', 'title' => $lang->newpoints_levels, 'link' => 'index.php?module=newpoints-levels');
}

function newpoints_levels_menu(&$menu)
{
    global $mybb, $lang;
    newpoints_lang_load("newpoints_levels");

    if ($mybb->input['action'] == 'levels')
        $menu[] = "&raquo; <a href=\"{$mybb->settings['bburl']}/newpoints.php?action=levels\">".$lang->newpoints_levels_title."</a>";
    else
        $menu[] = "<a href=\"{$mybb->settings['bburl']}/newpoints.php?action=levels\">".$lang->newpoints_levels_title."</a>";
}

function newpoints_levels_admin_newpoints_action_handler(&$actions)
{
    $actions['levels'] = array('active' => 'levels', 'file' => 'newpoints_levels');
}

function newpoints_levels_admin_admin_user_users_edit_profile()
{
    global $db, $form, $lang, $mybb;

    $options = array();
    $options[0] = $lang->newpoints_levels_not_enrolled;
    $query = $db->simple_select('newpoints_levels', 'lid,name', '', array('order_by' => 'disporder', 'order_dir' => 'ASC'));
    while($level = $db->fetch_array($query))
    {
        $options[$level['lid']] = htmlspecialchars_uni($level['name']);
    }

    $form_container = new FormContainer($lang->newpoints_levels);
    $form_container->output_row($lang->newpoints_levels_name." <em>*</em>", "", $form->generate_select_box('newpoints_level', $options, $mybb->get_input('newpoints_level'), array('id' => 'newpoints_level')), 'newpoints_level');
    $form_container->end();
}

function newpoints_levels_datahandler_user_validate(UserDataHandler $dataHandler)
{
    global $mybb;

    $user = &$dataHandler->data;
    if(isset($mybb->input['newpoints_level']) && $mybb->input['newpoints_level'] < 0)
    {
        $this->set_error("newpoints_levels_invalid_level");
        return false;
    }

    if (isset($mybb->input['newpoints_level']))
    {
        $dataHandler->data['newpoints_level'] = $mybb->input['newpoints_level'];
    }
}

function newpoints_levels_datahandler_user_update(UserDataHandler $dataHandler)
{
    if (isset($dataHandler->data['newpoints_level']))
    {
        $dataHandler->user_update_data['newpoints_level'] = $dataHandler->data['newpoints_level'];
    }
}

function newpoints_levels_messageredirect($message, $error=0, $action='')
{
    global $db, $mybb, $lang;

    if (!$message)
        return;

    if ($action)
        $parameters = '&amp;action='.$action;

    if ($error)
    {
        flash_message($message, 'error');
        admin_redirect("index.php?module=newpoints-levels".$parameters);
    }
    else {
        flash_message($message, 'success');
        admin_redirect("index.php?module=newpoints-levels".$parameters);
    }
}

function newpoints_levels_admin()
{
    global $db, $lang, $mybb, $page, $run_module, $action_file, $mybbadmin, $plugins;

    newpoints_lang_load('newpoints_levels');

    if($run_module == 'newpoints' && $action_file == 'newpoints_levels')
    {
        if ($mybb->request_method == "post")
        {
            switch ($mybb->input['action'])
            {
                case 'do_addlevel':
                    if ($mybb->input['name'] == '')
                    {
                        newpoints_levels_messageredirect($lang->newpoints_levels_missing_field, 1);
                    }

                    $name = $db->escape_string($mybb->input['name']);
                    $description = $db->escape_string($mybb->input['description']);

                    $icon = '';

                    if(isset($_FILES['icon']['name']) && $_FILES['icon']['name'] != '')
                    {
                        $icon = basename($_FILES['icon']['name']);
                        if ($icon)
                            $icon = "icon_".TIME_NOW."_".md5(uniqid(rand(), true)).".".get_extension($icon);

                        // Already exists?
                        if(file_exists(MYBB_ROOT."uploads/levels/".$icon))
                        {
                            flash_message($lang->mydownloads_background_upload_error2, 'error');
                            admin_redirect("index.php?module=newpoints-levels");
                        }

                        if(!move_uploaded_file($_FILES['icon']['tmp_name'], MYBB_ROOT."uploads/levels/".$icon))
                        {
                            flash_message($lang->mydownloads_background_upload_error, 'error');
                            admin_redirect("index.php?module=newpoints-levels");
                        }

                        $icon = $db->escape_string('uploads/levels/'.$icon);
                    }

                    $pm = $db->escape_string($mybb->input['pm']);
                    $pmadmin = $db->escape_string($mybb->input['pmadmin']);
                    $price = floatval($mybb->input['price']);

                    $infinite = intval($mybb->input['infinite']);
                    if ($infinite == 1)
                        $stock = 0;
                    else
                        $stock = intval($mybb->input['stock']);

                    $visible = intval($mybb->input['visible']);
                    $disporder = intval($mybb->input['disporder']);

                    $insert_array = array('name' => $name, 'description' => $description, 'icon' => $icon, 'visible' => $visible, 'disporder' => $disporder, 'price' => $price, 'infinite' => $infinite, 'stock' => $stock, 'pm' => $pm, 'pmadmin' => $pmadmin);

                    $plugins->run_hooks("newpoints_levels_commit", $insert_array);

                    $db->insert_query('newpoints_levels', $insert_array);

                    newpoints_levels_messageredirect($lang->newpoints_levels_level_added);
                break;
                case 'do_editlevel':
                    $lid = intval($mybb->input['lid']);
                    if ($lid <= 0 || (!($item = $db->fetch_array($db->simple_select('newpoints_levels', '*', "lid = $lid")))))
                    {
                        newpoints_levels_messageredirect($lang->newpoints_levels_invalid_item, 1);
                    }

                    if ($mybb->input['name'] == '')
                    {
                        newpoints_levels_messageredirect($lang->newpoints_levels_missing_field, 1);
                    }

                    $name = $db->escape_string($mybb->input['name']);
                    $description = $db->escape_string($mybb->input['description']);

                    $icon = '';

                    if(isset($_FILES['icon']['name']) && $_FILES['icon']['name'] != '')
                    {
                        $icon = basename($_FILES['icon']['name']);
                        if ($icon)
                            $icon = "icon_".TIME_NOW."_".md5(uniqid(rand(), true)).".".get_extension($icon);

                        // Already exists?
                        if(file_exists(MYBB_ROOT."uploads/levels/".$icon))
                        {
                            flash_message($lang->mydownloads_background_upload_error2, 'error');
                            admin_redirect("index.php?module=newpoints-levels");
                        }

                        if(!move_uploaded_file($_FILES['icon']['tmp_name'], MYBB_ROOT."uploads/levels/".$icon))
                        {
                            flash_message($lang->mydownloads_background_upload_error, 'error');
                            admin_redirect("index.php?module=newpoints-levels");
                        }

                        $icon = $db->escape_string('uploads/levels/'.$icon);
                    }

                    $price = floatval($mybb->input['price']);
                    $pm = $db->escape_string($mybb->input['pm']);
                    $pmadmin = $db->escape_string($mybb->input['pmadmin']);

                    $infinite = intval($mybb->input['infinite']);
                    if ($infinite == 1)
                        $stock = 0;
                    else
                        $stock = intval($mybb->input['stock']);

                    $visible = intval($mybb->input['visible']);
                    $disporder = intval($mybb->input['disporder']);

                    $update_array = array('name' => $name, 'description' => $description, 'icon' => $icon, 'visible' => $visible, 'disporder' => $disporder, 'price' => $price, 'infinite' => $infinite, 'stock' => $stock, 'pm' => $pm, 'pmadmin' => $pmadmin);
                    if ($icon == '')
                        unset($update_array['icon']);

                    $plugins->run_hooks("newpoints_levels_commit", $update_array);

                    $db->update_query('newpoints_levels', $update_array, 'lid=\''.$lid.'\'');

                    newpoints_levels_messageredirect($lang->newpoints_levels_level_edited);
                    break;
            }
        }


        if ($mybb->input['action'] == 'do_deletelevel')
        {
            $page->add_breadcrumb_item($lang->newpoints_levels, 'index.php?module=newpoints-levels');
            $page->output_header($lang->newpoints_levels);

            $lid = intval($mybb->input['lid']);

            if($mybb->input['no']) // user clicked no
            {
                admin_redirect("index.php?module=newpoints-levels");
            }

            if($mybb->request_method == "post")
            {
                if ($lid <= 0 || (!($level = $db->fetch_array($db->simple_select('newpoints_levels', 'lid', "lid = $lid")))))
                {
                    newpoints_levels_messageredirect($lang->newpoints_levels_invalid_level, 1);
                }

                // delete level plan
                $db->delete_query('newpoints_levels', "lid = $lid");

                newpoints_levels_messageredirect($lang->newpoints_levels_level_deleted);
            }
            else
            {
                $mybb->input['lid'] = intval($mybb->input['lid']);
                $form = new Form("index.php?module=newpoints-levels&amp;action=do_deletelevel&amp;lid={$mybb->input['lid']}&amp;my_post_key={$mybb->post_code}", 'post');
                echo "<div class=\"confirm_action\">\n";
                echo "<p>{$lang->newpoints_levels_confirm_deletelevel}</p>\n";
                echo "<br />\n";
                echo "<p class=\"buttons\">\n";
                echo $form->generate_submit_button($lang->yes, array('class' => 'button_yes'));
                echo $form->generate_submit_button($lang->no, array("name" => "no", 'class' => 'button_no'));
                echo "</p>\n";
                echo "</div>\n";
                $form->end();
            }

            $page->output_footer();
            exit;
        }

        $page->add_breadcrumb_item($lang->newpoints_levels, 'index.php?module=newpoints-levels');

        $page->output_header($lang->newpoints_levels);

        $sub_tabs['newpoints_levels'] = array(
            'title'        => $lang->newpoints_levels,
            'link'        => 'index.php?module=newpoints-levels',
            'description'    => $lang->newpoints_levels_desc
        );

        $sub_tabs['newpoints_levels_add'] = array(
            'title'            => $lang->newpoints_levels_add,
            'link'            => 'index.php?module=newpoints-levels&amp;action=addlevel',
            'description'    => $lang->newpoints_levels_add_desc
        );

        $sub_tabs['newpoints_levels_edit'] = array(
            'title'            => $lang->newpoints_levels_edit,
            'link'            => 'index.php?module=newpoints-levels&amp;action=editlevel',
            'description'    => $lang->newpoints_levels_edit_desc
        );

        if (!$mybb->input['action'])
        {
            $page->output_nav_tabs($sub_tabs, 'newpoints_levels');

            // table
            $table = new Table;
            $table->construct_header($lang->newpoints_shop_item_icon, array('width' => '1%'));
            $table->construct_header($lang->newpoints_shop_cat_name, array('width' => '30%'));
            $table->construct_header($lang->newpoints_shop_cat_description, array('width' => '35%'));
            $table->construct_header($lang->newpoints_shop_cat_disporder, array('width' => '10%', 'class' => 'align_center'));
            $table->construct_header($lang->newpoints_shop_cat_action, array('width' => '25%', 'class' => 'align_center'));

            $query = $db->simple_select('newpoints_levels', '*', '', array('order_by' => 'disporder', 'order_dir' => 'ASC'));
            while ($level = $db->fetch_array($query))
            {
                $table->construct_cell(htmlspecialchars_uni($level['icon']) ? '<img src="'.$mybb->settings['bburl'].'/'.htmlspecialchars_uni($level['icon']).'" style="width: 24px; height: 24px">' : '<img src="'.$mybb->settings['bburl'].'/images/newpoints/default.png">', array('class' => 'align_center'));
                $table->construct_cell(htmlspecialchars_uni($level['name']));
                $table->construct_cell(htmlspecialchars_uni($level['description']));
                $table->construct_cell(intval($level['disporder']), array('class' => 'align_center'));

                // actions column
                $table->construct_cell("<a href=\"index.php?module=newpoints-levels&amp;action=editlevel&amp;lid=".intval($level['lid'])."\">".$lang->newpoints_levels_edit."</a> - <a href=\"index.php?module=newpoints-levels&amp;action=do_deletelevel&amp;lid=".intval($level['lid'])."\">".$lang->newpoints_levels_delete."</a>", array('class' => 'align_center'));

                $table->construct_row();
            }

            if ($table->num_rows() == 0)
            {
                $table->construct_cell($lang->newpoints_levels_no_levels, array('colspan' => 5));
                $table->construct_row();
            }

            $table->output($lang->newpoints_levels);
        }
        elseif ($mybb->input['action'] == 'addlevel')
        {
            $page->output_nav_tabs($sub_tabs, 'newpoints_levels_add');

            $form = new Form("index.php?module=newpoints-levels&amp;action=do_addlevel", "post", "newpoints_levels", 1);
            $form_container = new FormContainer($lang->newpoints_levels_addlevel);
            $form_container->output_row($lang->newpoints_levels_name."<em>*</em>", $lang->newpoints_levels_name_desc, $form->generate_text_box('name', '', array('id' => 'name')), 'name');
            $form_container->output_row($lang->newpoints_levels_description."<em>*</em>", $lang->newpoints_levels_description_desc, $form->generate_text_area('description', '', array('id' => 'description')), 'description');
            $form_container->output_row($lang->newpoints_levels_addedit_item_price, $lang->newpoints_levels_addedit_item_price_desc, $form->generate_text_box('price', '0', array('id' => 'price')), 'price');
            $form_container->output_row($lang->newpoints_levels_addedit_item_icon, $lang->newpoints_levels_addedit_item_icon_desc, $form->generate_file_upload_box("icon", array('style' => 'width: 200px;')), 'icon');
            $form_container->output_row($lang->newpoints_levels_addedit_item_disporder, $lang->newpoints_levels_addedit_item_disporder_desc, $form->generate_text_box('disporder', '0', array('id' => 'disporder')), 'disporder');
            $form_container->output_row($lang->newpoints_levels_addedit_item_stock, $lang->newpoints_levels_addedit_item_stock_desc, $form->generate_text_box('stock', '0', array('id' => 'stock')), 'stock');
            $form_container->output_row($lang->newpoints_levels_addedit_item_infinite, $lang->newpoints_levels_addedit_item_infinite_desc, $form->generate_yes_no_radio('infinite', 1), 'infinite');
            $form_container->output_row($lang->newpoints_levels_addedit_item_visible, $lang->newpoints_levels_addedit_item_visible_desc, $form->generate_yes_no_radio('visible', 1), 'visible');
            $form_container->output_row($lang->newpoints_levels_addedit_item_pm, $lang->newpoints_levels_addedit_item_pm_desc, $form->generate_text_area('pm', ''), 'pm');
            $form_container->output_row($lang->newpoints_levels_addedit_item_pmadmin, $lang->newpoints_levels_addedit_item_pmadmin_desc, $form->generate_text_area('pmadmin', ''), 'pmadmin');
            $form_container->end();

            $buttons = array();
            $buttons[] = $form->generate_submit_button($lang->newpoints_levels_submit);
            $buttons[] = $form->generate_reset_button($lang->newpoints_levels_reset);
            $form->output_submit_wrapper($buttons);
            $form->end();
        }
        elseif ($mybb->input['action'] == 'editlevel')
        {
            $page->output_nav_tabs($sub_tabs, 'newpoints_levels_edit');

            $lid = intval($mybb->input['lid']);
            if ($lid <= 0 || (!($level = $db->fetch_array($db->simple_select('newpoints_levels', '*', "lid = $lid")))))
            {
                newpoints_levels_messageredirect($lang->newpoints_levels_invalid_level, 1);
            }

            $form = new Form("index.php?module=newpoints-levels&amp;action=do_editlevel", "post", "newpoints_levels", 1);

            echo $form->generate_hidden_field('lid', $level['lid']);

            $form_container = new FormContainer($lang->newpoints_levels_editlevel);
            $form_container->output_row($lang->newpoints_levels_name."<em>*</em>", $lang->newpoints_levels_name_desc, $form->generate_text_box('name', $level['name'], array('id' => 'name')), 'name');
            $form_container->output_row($lang->newpoints_levels_description."<em>*</em>", $lang->newpoints_levels_description_desc, $form->generate_text_area('description', $level['description'], array('id' => 'description')), 'description');

            $form_container->output_row($lang->newpoints_levels_addedit_item_price, $lang->newpoints_levels_addedit_item_price_desc, $form->generate_text_box('price', floatval($level['price']), array('id' => 'price')), 'price');
            $form_container->output_row($lang->newpoints_levels_addedit_item_icon, $lang->newpoints_levels_addedit_item_icon_desc, $form->generate_file_upload_box("icon", array('style' => 'width: 200px;')), 'icon');
            $form_container->output_row($lang->newpoints_levels_addedit_item_disporder, $lang->newpoints_levels_addedit_item_disporder_desc, $form->generate_text_box('disporder', intval($level['disporder']), array('id' => 'disporder')), 'disporder');
            $form_container->output_row($lang->newpoints_levels_addedit_item_stock, $lang->newpoints_levels_addedit_item_stock_desc, $form->generate_text_box('stock', intval($level['stock']), array('id' => 'stock')), 'stock');
            $form_container->output_row($lang->newpoints_levels_addedit_item_infinite, $lang->newpoints_levels_addedit_item_infinite_desc, $form->generate_yes_no_radio('infinite', intval($level['infinite'])), 'infinite');
            $form_container->output_row($lang->newpoints_levels_addedit_item_visible, $lang->newpoints_levels_addedit_item_visible_desc, $form->generate_yes_no_radio('visible', intval($level['visible'])), 'visible');
            $form_container->output_row($lang->newpoints_levels_addedit_item_pm, $lang->newpoints_levels_addedit_item_pm_desc, $form->generate_text_area('pm', htmlspecialchars_uni($level['pm']), array('id' => 'pm_text')), 'pm');
            $form_container->output_row($lang->newpoints_levels_addedit_item_pmadmin, $lang->newpoints_levels_addedit_item_pmadmin_desc, $form->generate_text_area('pmadmin', htmlspecialchars_uni($level['pmadmin'])), 'pmadmin');

            $form_container->end();

            $buttons = array();
            $buttons[] = $form->generate_submit_button($lang->newpoints_levels_submit);
            $buttons[] = $form->generate_reset_button($lang->newpoints_levels_reset);
            $form->output_submit_wrapper($buttons);
            $form->end();
        }

        $page->output_footer();
        exit;
    }
}

function newpoints_levels_profile_lang()
{
    global $lang;

    // load language
    newpoints_lang_load("newpoints_levels");
}

function newpoints_levels_profile()
{
    global $mybb, $lang, $db, $memprofile, $templates, $newpoints_levels_profile_current_level, $newpoints_levels_profile_next_level, $theme;

    $newpoints_levels_profile_current_level = '';
    $newpoints_levels_profile_next_level = '';

    /*if ($mybb->settings['newpoints_levels_current_levelprofile'] == 0)
    {
        $newpoints_levels_profile = '';
        return;
    }*/

    $current_level = ['disporder' => 0, 'price' => 0, 'icon' => 'images/newpoints/default.png', 'visible' => 0];
    if (!empty($memprofile['newpoints_level']))
    {
        $lid = intval($memprofile['newpoints_level']);
        $current_level = $db->fetch_array($db->simple_select('newpoints_levels', '*', "lid = $lid"));
        if (empty($current_level['icon']))
        {
            $current_level['icon'] = 'images/newpoints/default.png';
        }
        if (!empty($current_level['price']))
        {
            $current_level['price'] = newpoints_format_points($current_level['price']);
        }
    }

    $next_level = $db->fetch_array($db->simple_select('newpoints_levels', '*', "disporder > {$current_level['disporder']}", array('order_by' => 'disporder', 'order_dir' => 'ASC', 'limit' => '1')));
    if ($next_level === null)
    {
        $next_level = ['disporder' => 0, 'price' => 0, 'icon' => 'images/newpoints/default.png', 'visible' => 0];
    }
    if (empty($next_level['icon']))
    {
        $next_level['icon'] = 'images/newpoints/default.png';
    }
    if (!empty($next_level['price']))
    {
        $next_level['price'] = newpoints_format_points($next_level['price']);
    }

    if ($current_level['visible'] == '1')
    {
        $template = "newpoints_levels_profile_current_level";
        if (empty($memprofile['newpoints_level'])) {
            $template .= '_not_enrolled';
        }
        eval("\$newpoints_levels_profile_current_level = \"".$templates->get($template)."\";");
    }

    if ($next_level['visible'] == '1')
    {
        $template = "newpoints_levels_profile_next_level";
        if (empty($memprofile['newpoints_level'])) {
            $template .= '_not_enrolled';
        }
        else if (floatval($next_level['price']) > floatval($mybb->user['newpoints']))
        {
            $template .= '_not_enough';
        }
        eval("\$newpoints_levels_profile_next_level = \"".$templates->get($template)."\";");
    }
}

function newpoints_levels_page()
{
    global $mybb, $db, $lang, $cache, $theme, $header, $templates, $plugins, $headerinclude, $footer, $options, $inline_errors;

    if (!$mybb->user['uid'])
        return;

    newpoints_lang_load("newpoints_levels");

    $current_level = ['lid' => -1, 'disporder' => 0, 'icon' => 'images/newpoints/default.png'];
    if (!empty($mybb->user['newpoints_level']))
    {
        $lid = intval($mybb->user['newpoints_level']);
        $current_level = $db->fetch_array($db->simple_select('newpoints_levels', '*', "lid = $lid"));
        if (empty($current_level['icon']))
        {
            $current_level['icon'] = 'images/newpoints/default.png';
        }
    }

    $next_level = $db->fetch_array($db->simple_select('newpoints_levels', '*', "disporder > {$current_level['disporder']}", array('order_by' => 'disporder', 'order_dir' => 'ASC', 'limit' => '1')));
    if (!$next_level)
    {
        $next_level = ['lid' => -1, 'disporder' => 0, 'icon' => 'images/newpoints/default.png'];
    }
    if (empty($next_level['icon']))
    {
        $next_level['icon'] = 'images/newpoints/default.png';
    }

    $plugins->run_hooks("newpoints_levels_start");

    if ($mybb->input['action'] == "do_levelup")
    {
        $plugins->run_hooks("newpoints_levels_level_up_start");

        if ($next_level['visible'] == 0)
            error_no_permission();

        if (floatval($next_level['price']) > floatval($mybb->user['newpoints']))
        {
            redirect(get_profile_link($mybb->user['uid']), $lang->newpoints_levels_not_enough, $lang->newpoints_levels_leveled_up_title);
        }

        if ($next_level['infinite'] != 1 && $next_level['stock'] <= 0)
        {
            redirect(get_profile_link($mybb->user['uid']), $lang->newpoints_levels_out_of_stock, $lang->newpoints_levels_leveled_up_title);
        }

        $db->update_query('users', array('newpoints_level' => $next_level['lid']), 'uid=\''.$mybb->user['uid'].'\'');

        // update stock
        if ($next_level['infinite'] != 1)
            $db->update_query('newpoints_levels', array('stock' => $next_level['stock']-1), 'lid=\''.$next_level['lid'].'\'');

        // get money from user
        newpoints_addpoints($mybb->user['uid'], -(floatval($next_level['price'])));

        if(!empty($next_level['pm']) || $mybb->settings['newpoints_levels_pm_default'] != '')
        {
            // send PM if item has private message
            if($next_level['pm'] == '' && $mybb->settings['newpoints_levels_pm_default'] != '')
            {
                $next_level['pm'] = $mybb->settings['newpoints_levels_pm_default'];
            }
            $next_level['pm'] = str_replace(array(
                '{levelid}','{levelname}','{leveldescription}','{levelprice}',
                '{userid}', '{username}', '{usertitle}',
            ), array(
                $next_level['lid'], $next_level['name'], $next_level['description'], newpoints_format_points($next_level['price']),
                $mybb->user['uid'], $mybb->user['username'], $mybb->user['usertitle'],
            ), $next_level['pm']);

            newpoints_send_pm(array('subject' => $lang->newpoints_levels_level_up_pm_subject, 'message' => $next_level['pm'], 'touid' => $mybb->user['uid'], 'receivepms' => 1), -1);
        }

        if (!empty($next_level['pmadmin']) || $mybb->settings['newpoints_levels_pmadmins'] != '')
        {
            // send PM if item has private message
            if($next_level['pmadmin'] == '' && $mybb->settings['newpoints_levels_pmadmin_default'] != '')
            {
                $next_level['pmadmin'] = $mybb->settings['newpoints_levels_pmadmin_default'];
            }
            $next_level['pmadmin'] = str_replace(array(
                '{levelid}','{levelname}','{leveldescription}','{levelprice}',
                '{userid}', '{username}', '{usertitle}',
            ), array(
                $next_level['lid'], $next_level['name'], $next_level['description'], newpoints_format_points($next_level['price']),
                $mybb->user['uid'], $mybb->user['username'], $mybb->user['usertitle'],
            ), $next_level['pmadmin']);

            newpoints_send_pm(array('subject' => $lang->newpoints_levels_level_up_pmadmin_subject, 'message' => $next_level['pmadmin'], 'touid' => array(explode(',', $mybb->settings['newpoints_levels_pmadmins'])), 'receivepms' => 1), $mybb->user['uid']);
        }

        $plugins->run_hooks("newpoints_levels_level_up_end", $next_level);

        // log purchase
        newpoints_log('levels_level_up', $lang->sprintf($lang->newpoints_levels_leveled_log, $next_level['lid'], $next_level['price']));

        if ($mybb->input['return_to'] == 'profile') {
            redirect(get_profile_link($mybb->user['uid']), $lang->newpoints_levels_leveled_up, $lang->newpoints_levels_leveled_up_title);
        } else /* if ($mybb->input['return_to'] == 'newpoints') */ {
            redirect('newpoints.php?action=levels', $lang->newpoints_levels_leveled_up, $lang->newpoints_levels_leveled_up_title);
        }
    }
    else if ($mybb->input['action'] == "levels")
    {
        $levels = '';

        // Show levels
        $query = $db->simple_select('newpoints_levels', 'lid,name,description,price,icon,disporder', 'visible = 1', array('order_by' => 'disporder', 'order_dir' => 'ASC'));
        while ($level = $db->fetch_array($query))
        {
            $bgcolor = alt_trow();

            if (empty($level['icon']))
            {
                $level['icon'] = 'images/newpoints/default.png';
            }
            $level['price_display'] = newpoints_format_points($level['price']);
            $currency = $mybb->settings['newpoints_main_curname'];
            $points = newpoints_format_points($mybb->user['newpoints']);
            $needed = newpoints_format_points($level['price'] - $mybb->user['newpoints']);
            $percentage = my_number_format($mybb->user['newpoints'] / $level['price'] * 100);

            if ($level['lid'] == $current_level['lid'])
            {
                eval("\$levels .= \"".$templates->get('newpoints_levels_current_level_row')."\";");
            }
            else if ($level['lid'] == $next_level['lid'])
            {
                if (floatval($next_level['price']) > floatval($mybb->user['newpoints']))
                {
                    eval("\$levels .= \"".$templates->get('newpoints_levels_next_level_row_not_enough')."\";");
                }
                else
                {
                    eval("\$levels .= \"".$templates->get('newpoints_levels_next_level_row')."\";");
                }
            }
            else
            {
                eval("\$levels .= \"".$templates->get('newpoints_levels_row')."\";");
            }
        }

        if (empty($levels))
        {
            eval("\$levels = \"".$templates->get('newpoints_levels_empty')."\";");
        }

        eval("\$page = \"".$templates->get('newpoints_levels')."\";");
    }
    else
    {
        return;
    }

    $plugins->run_hooks("newpoints_levels_end");

    // output page
    output_page($page);
}
