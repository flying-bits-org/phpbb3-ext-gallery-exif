<?php
/**
*
* @package phpBB Gallery Testing
* @copyright (c) 2013 nickvergessen
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

class phpbb_ext_gallery_tests_manager_manager_test extends phpbb_ext_gallery_database_test_case
{
	public function getDataSet()
	{
		return $this->createXMLDataSet(dirname(__FILE__) . '/fixtures/config.xml');
	}

	public function setUp()
	{
		parent::setUp();

		global $phpbb_root_path, $phpEx, $user;

		$config = new phpbb_config(array());

		$this->template_path = $this->test_path . '/templates';
		$this->style_resource_locator = new phpbb_style_resource_locator();
		$this->style_provider = new phpbb_style_path_provider();
		$this->template = new phpbb_template($phpbb_root_path, $phpEx, $config, $user, $this->style_resource_locator, new phpbb_template_context());

		$this->exif = new phpbb_ext_gallery_exif_manager($this->db, $this->template, new phpbb_user());
	}

	static public function read_data()
	{
		return array(
			array('has_exif.jpg', true),
			array('no_exif.jpg', false),
			array('no_exif.gif', false),
			array('no_exif.png', false),
		);
	}

	/**
	* @dataProvider read_data
	*/
	public function test_read($set_file, $expected)
	{
		$this->assertEquals($expected, $this->exif->read(dirname(__FILE__) . '/fixtures/' . $set_file));
	}

	static public function interpret_fileless_data()
	{
		return array(
			array(phpbb_ext_gallery_exif_manager::UNAVAILABLE, false),
			array(phpbb_ext_gallery_exif_manager::UNAVAILABLE, false),
			array(phpbb_ext_gallery_exif_manager::DBSAVED, true),
		);
	}

	/**
	* @dataProvider interpret_fileless_data
	*/
	public function test_interpret_fileless($status, $expected)
	{
		$this->assertEquals($expected, $this->exif->interpret($status, serialize(array())));
	}

	static public function interpret_data()
	{
		return array(
			array(phpbb_ext_gallery_exif_manager::AVAILABLE, 'has_exif.jpg', true),
			array(phpbb_ext_gallery_exif_manager::AVAILABLE, 'no_exif.jpg', false),
			array(phpbb_ext_gallery_exif_manager::UNKNOWN, 'has_exif.jpg', true),
			array(phpbb_ext_gallery_exif_manager::UNKNOWN, 'no_exif.jpg', false),
			array(phpbb_ext_gallery_exif_manager::UNKNOWN, 'no_exif.gif', false),
			array(phpbb_ext_gallery_exif_manager::UNKNOWN, 'no_exif.png', false),
		);
	}

	/**
	* @dataProvider interpret_data
	*/
	public function test_interpret($status, $filename, $expected)
	{
		$this->assertEquals($expected, $this->exif->interpret($status, serialize(array()), dirname(__FILE__) . '/fixtures/' . $filename));
	}
}
