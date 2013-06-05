<?php
/**
*
* @package phpBB Gallery Exif Extension
* @copyright (c) 2013 nickvergessen
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

class phpbb_ext_gallery_exif_migrations_1_1_6 extends phpbb_db_migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'gallery_users', 'user_viewexif');
	}

	static public function depends_on()
	{
		return array('phpbb_ext_gallery_core_migrations_1_1_6');
	}

	public function update_schema()
	{
		return array(
			'add_columns'	=> array(
				$this->table_prefix . 'gallery_users'			=> array(
						'user_viewexif'		=> array('UINT:1', 0),
				),
				$this->table_prefix . 'gallery_images'	=> array(
						'image_has_exif'		=> array('UINT:3', 2),
						'image_exif_data'		=> array('TEXT', ''),
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_columns'	=> array(
				$this->table_prefix . 'gallery_users'			=> array(
					'user_viewexif',
				),
				$this->table_prefix . 'gallery_images'			=> array(
					'image_has_exif',
					'image_exif_data',
				),
			),
		);
	}

	public function update_data()
	{
		return array(
			// @todo: ADD config values
			array('custom', array(array(&$this, 'install_config'))),
		);
	}

	public function install_config()
	{
		global $config;

		foreach (self::$configs as $name => $value)
		{
			if (isset(self::$is_dynamic[$name]))
			{
				$config->set('phpbb_gallery_' . $name, $value, true);
			}
			else
			{
				$config->set('phpbb_gallery_' . $name, $value);
			}
		}

		return true;
	}

	static public $is_dynamic = array(
	);

	static public $configs = array(
		'disp_exifdata'				=> true,
	);
}
