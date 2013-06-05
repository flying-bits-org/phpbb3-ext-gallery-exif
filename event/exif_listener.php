<?php
/**
*
* @package phpBB Gallery Exif Extension
* @copyright (c) 2013 nickvergessen
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* @ignore
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* Event listener
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class phpbb_ext_gallery_exif_manager_event_exif_listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'gallery.core.acp.config.get_display_vars'		=> 'acp_config_get_display_vars',
			//@todo: 'gallery.core.config.load_config_sets'			=> 'config_load_config_sets',
			'gallery.core.massimport.update_image_before'	=> 'massimport_update_image_before',
			'gallery.core.massimport.update_image'			=> 'massimport_update_image',
			'gallery.core.posting.edit_before_rotate'		=> 'update_image_exif_data',
			'gallery.core.ucp.set_settings_submit'			=> 'ucp_set_settings_submit',
			'gallery.core.ucp.set_settings_nosubmit'		=> 'ucp_set_settings_nosubmit',
			'gallery.core.upload.prepare_file_before'		=> 'upload_prepare_file_before',
			'gallery.core.upload.update_image_before'		=> 'update_image_exif_data',
			'gallery.core.upload.update_image_nofilechange'	=> 'upload_update_image_nofilechange',
			'gallery.core.user.get_default_values'			=> 'user_get_default_values',
			'gallery.core.user.validate_data'				=> 'user_validate_data',
			'gallery.core.viewimage'						=> 'viewimage',
		);
	}

	public function acp_config_get_display_vars($event)
	{
		if ($event['mode'] == 'main')
		{
			$return_ary = $event['return_ary'];
			if (isset($return_ary['vars']['IMAGE_SETTINGS']))
			{
				global $user;
				$user->add_lang_ext('gallery/exif', 'exif');

				$return_ary['vars']['IMAGE_SETTINGS']['exif:disp_exifdata'] = array('lang' => 'DISP_EXIF_DATA',		'validate' => 'bool',	'type' => 'radio:yes_no');
				$event['return_ary'] = $return_ary;
			}
		}
	}

	public function config_load_config_sets($event)
	{
		$additional_config_sets = $event['additional_config_sets'];
		$additional_config_sets['exif'] = 'phpbb_ext_gallery_exif_manager_config_sets_exif';
		$event['additional_config_sets'] = $additional_config_sets;
	}

	public function reset_additional_sql_data($additional_sql_data)
	{
		$additional_sql_data['image_exif_data'] = '';
		$additional_sql_data['image_has_exif'] = phpbb_ext_gallery_exif_manager::UNKNOWN;

		return $additional_sql_data;
	}

	public function upload_update_image_nofilechange($event)
	{
		$event['additional_sql_data'] = $this->reset_additional_sql_data($event['additional_sql_data']);
	}

	public function massimport_update_image($event)
	{
		if (!$event['file_updated'])
		{
			$event['additional_sql_data'] = $this->reset_additional_sql_data($event['additional_sql_data']);
		}
	}

	public function get_additional_sql_data($additional_sql_data, $file_link)
	{
		global $db, $template, $user;
		$exif_manager = new phpbb_ext_gallery_exif_manager_manager($db, $template, $user);

		$exif_manager->read($file_link);
		$additional_sql_data['image_exif_data'] = $exif_manager->get_serialized_data();
		$additional_sql_data['image_has_exif'] = $exif_manager->get_status();

		return $additional_sql_data;
	}

	public function massimport_update_image_before($event)
	{
		$event['additional_sql_data'] = $this->get_additional_sql_data($event['additional_sql_data'], $event['file_link']);
	}

	public function upload_prepare_file_before($event)
	{
		if (in_array($event['file']->extension, array('jpg', 'jpeg')))
		{
			$event['additional_sql_data'] = $this->get_additional_sql_data($event['additional_sql_data'], $event['file']->destination_file);
		}
	}

	public function update_image_exif_data($event)
	{
		$image_data = $event['image_data'];

		if (($image_data['image_has_exif'] == phpbb_ext_gallery_exif_manager::AVAILABLE) ||
		 ($image_data['image_has_exif'] == phpbb_ext_gallery_exif_manager::UNKNOWN))
		{
			$event['additional_sql_data'] = $this->get_additional_sql_data($event['additional_sql_data'], $event['file_link']);
		}
	}

	public function posting_edit_before_rotate($event)
	{
		$image_data = $event['image_data'];

		if (($image_data['image_has_exif'] == phpbb_ext_gallery_exif_manager::AVAILABLE) ||
		 ($image_data['image_has_exif'] == phpbb_ext_gallery_exif_manager::UNKNOWN))
		{
			$event['additional_sql_data'] = $this->get_additional_sql_data($event['additional_sql_data'], $event['file_link']);
		}
	}

	public function upload_update_image_before($event)
	{
		$image_data = $event['image_data'];

		if (($image_data['image_has_exif'] == phpbb_ext_gallery_exif_manager::AVAILABLE) ||
		 ($image_data['image_has_exif'] == phpbb_ext_gallery_exif_manager::UNKNOWN))
		{
			$event['additional_sql_data'] = $this->get_additional_sql_data($event['additional_sql_data'], $event['file_link']);
		}
	}

	public function ucp_set_settings_nosubmit()
	{
		global $template, $user, $phpbb_ext_gallery;
		$user->add_lang_ext('gallery/exif', 'exif');

		$template->assign_vars(array(
			'S_VIEWEXIFS'		=> $phpbb_ext_gallery->user->get_data('user_viewexif'),
		));
	}

	public function user_get_default_values($event)
	{
		$default_values = $event['default_values'];
		if (!in_array('user_viewexif', $default_values))
		{
			$default_values['user_viewexif'] = (bool) phpbb_ext_gallery_exif_manager::DEFAULT_DISPLAY;
			$event['default_values'] = $default_values;
		}
	}

	public function ucp_set_settings_submit($event)
	{
		$additional_settings = $event['additional_settings'];
		if (!in_array('user_viewexif', $additional_settings))
		{
			$additional_settings['user_viewexif'] = request_var('viewexifs', false);
			$event['additional_settings'] = $additional_settings;
		}
	}

	public function user_validate_data($event)
	{
		if ($event['name'] == 'user_viewexif')
		{
			$event['value'] = (bool) $event['value'];
			$event['is_validated'] = true;
		}
	}

	public function viewimage($event)
	{
		global $user;
		$user->add_lang_ext('gallery/exif', 'exif');

		$status = (int) $event['image_data']['image_has_exif'];
		$exif_data = $event['image_data']['image_exif_data'];
		$file_path = $event['phpbb_ext_gallery']->url->path('upload') . $event['image_data']['image_filename'];

		if (//@todo: $event['phpbb_ext_gallery']->config->get(array('exif', 'disp_exifdata')) &&
		 ($status != phpbb_ext_gallery_exif_manager_manager::UNAVAILABLE) &&
		 (substr($file_path, -4) == '.jpg') && function_exists('exif_read_data') &&
		 ($event['phpbb_ext_gallery']->auth->acl_check('m_status', $event['image_data']['image_album_id'], $event['album_data']['album_user_id']) ||
		  ($event['image_data']['image_contest'] != phpbb_ext_gallery_core_image::IN_CONTEST)))
		{
			global $db, $template, $user;
			$exif_manager = new phpbb_ext_gallery_exif_manager_manager($db, $template, $user);

			if ($exif_manager->interpret($status, $exif_data, $file_path, $event['image_id']))
			{
				$exif_manager->send_to_template($event['phpbb_ext_gallery']->user->get_data('user_viewexif'));
			}
		}
	}
}
