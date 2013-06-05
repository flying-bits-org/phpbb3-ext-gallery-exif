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
* Base class for Exif handling
*/
class phpbb_ext_gallery_exif_manager
{
	/**
	* Default value for new users
	*/
	const DEFAULT_DISPLAY	= true;

	/**
	* phpBB will treat the time from the Exif data like UTC.
	* If your images were taken with an other timezone, you can insert an offset here.
	* The offset is than added to the timestamp before it is converted into the users time.
	*
	* Offset must be set in seconds.
	*/
	const TIME_OFFSET	= 0;

	/**
	* Constants for the status of the Exif data.
	*/
	const UNAVAILABLE	= 0;
	const AVAILABLE		= 1;
	const UNKNOWN		= 2;
	const DBSAVED		= 3;

	/**
	* Exif data array with all allowed groups and keys.
	*/
	protected $raw_data = array();

	/**
	* Filtered data array. We don't have empty or invalid values here.
	*/
	protected $prepared_data = array();

	/**
	* Does the image have exif data?
	* Values see constant declaration at the beginning of the class.
	*/
	protected $status = 2;

	/**
	* Full data array, but serialized to a string
	*/
	public $serialized = '';

	/**
	* Full link to the image-file
	*/
	protected $file = '';

	/**
	* Image-ID, just needed to update the Exif status
	*/
	protected $image_id	= false;

	/** @var phpbb_db_driver */
	protected $db;

	/** @var phpbb_template */
	protected $template;

	/** @var phpbb_user */
	protected $user;

	/**
	* Constructor
	*
	* @param phpbb_db_driver	$db			Database object
	* @param phpbb_template		$template	Template object
	* @param phpbb_user			$user		User object
	*/
	public function __construct(phpbb_db_driver $db, phpbb_template $template, phpbb_user $user)
	{
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
	}

	/**
	* Set link to file and image id
	*
	* @param	string	$file		Full link to the image-file
	* @param	mixed	$image_id	False or integer
	*/
	public function set_image($file, $image_id = false)
	{
		$this->file = $file;
		$this->image_id = (int) $image_id;
		$this->raw_data = array();
		$this->prepared_data = array();
	}

	public function get_status()
	{
		return (int) $this->status;
	}

	public function get_serialized_data()
	{
		return (string) $this->serialized;
	}

	/**
	* Intepret the values from the database, and read the data if we don't have it.
	*
	* @param	int		$status		Value of a status constant (see beginning of the class)
	* @param	mixed	$data		Either an empty string or the serialized array of the Exif from the database
	* @return	bool		True if any data is available, false otherwise
	*/
	public function interpret($status, $data, $file = '', $image_id = false)
	{
		if ($file !== '')
		{
			$this->set_image($file, $image_id = false);
		}

		$this->orig_status = $status;
		$this->status = $status;

		if ($this->status == self::DBSAVED)
		{
			$this->data = unserialize($data);
			return true;
		}
		elseif (($this->status == self::AVAILABLE) || ($this->status == self::UNKNOWN))
		{
			return $this->read();
		}

		return false;
	}

	/**
	* Read Exif data from the image
	*
	* @param	string	$file		Full link to the image-file
	* @param	mixed	$image_id	False or integer
	* @return	bool		True if any data is available, false otherwise
	*/
	public function read($file = '', $image_id = false)
	{
		if ($file !== '')
		{
			$this->set_image($file, $image_id = false);
		}

		if (!function_exists('exif_read_data') || !$this->file || !file_exists($this->file))
		{
			var_dump(function_exists('exif_read_data'));
			var_dump($this->file);
			var_dump(file_exists($this->file));
			return false;
		}

		$this->raw_data = @exif_read_data($this->file, 0, true);

		if (!empty($this->raw_data["EXIF"]))
		{
			// Unset invalid Exifs
			foreach ($this->raw_data as $key => $array)
			{
				if (!in_array($key, self::$allowed_groups))
				{
					unset($this->raw_data[$key]);
				}
				else
				{
					foreach ($this->raw_data[$key] as $subkey => $array)
					{
						if (!in_array($subkey, self::$allowed_keys))
						{
							unset($this->raw_data[$key][$subkey]);
						}
					}
				}
			}

			$this->serialized = serialize($this->raw_data);
			$this->status = self::DBSAVED;
		}
		else
		{
			$this->status = self::UNAVAILABLE;
		}

		if ($this->image_id)
		{
			$this->write();
		}

		return $this->status != self::UNAVAILABLE;
	}

	/**
	* Validate and prepare the data, so we can send it into the template.
	*/
	private function prepare_data()
	{
		$this->user->add_lang_ext('gallery/exif', 'exif');

		$this->prepared_data = array();
		if (isset($this->raw_data["EXIF"]["DateTimeOriginal"]))
		{
			$timestamp_year = substr($this->raw_data["EXIF"]["DateTimeOriginal"], 0, 4);
			$timestamp_month = substr($this->raw_data["EXIF"]["DateTimeOriginal"], 5, 2);
			$timestamp_day = substr($this->raw_data["EXIF"]["DateTimeOriginal"], 8, 2);
			$timestamp_hour = substr($this->raw_data["EXIF"]["DateTimeOriginal"], 11, 2);
			$timestamp_minute = substr($this->raw_data["EXIF"]["DateTimeOriginal"], 14, 2);
			$timestamp_second = substr($this->raw_data["EXIF"]["DateTimeOriginal"], 17, 2);
			$timestamp = (int) @mktime($timestamp_hour, $timestamp_minute, $timestamp_second, $timestamp_month, $timestamp_day, $timestamp_year);
			if ($timestamp)
			{
				$this->prepared_data['exif_date'] = $this->user->format_date($timestamp + self::TIME_OFFSET);
			}
		}
		if (isset($this->raw_data["EXIF"]["FocalLength"]))
		{
			list($num, $den) = explode("/", $this->raw_data["EXIF"]["FocalLength"]);
			if ($den)
			{
				$this->prepared_data['exif_focal'] = sprintf($this->user->lang['EXIF_FOCAL_EXP'], ($num / $den));
			}
		}
		if (isset($this->raw_data["EXIF"]["ExposureTime"]))
		{
			list($num, $den) = explode("/", $this->raw_data["EXIF"]["ExposureTime"]);
			$exif_exposure = '';
			if (($num > $den) && $den)
			{
				$exif_exposure = $num / $den;
			}
			else if ($num)
			{
				$exif_exposure = ' 1/' . $den / $num ;
			}
			if ($exif_exposure)
			{
				$this->prepared_data['exif_exposure'] = sprintf($this->user->lang['EXIF_EXPOSURE_EXP'], $exif_exposure);
			}
		}
		if (isset($this->raw_data["EXIF"]["FNumber"]))
		{
			list($num, $den) = explode("/", $this->raw_data["EXIF"]["FNumber"]);
			if ($den)
			{
				$this->prepared_data['exif_aperture'] = "F/" . ($num / $den);
			}
		}
		if (isset($this->raw_data["EXIF"]["ISOSpeedRatings"]) && !is_array($this->raw_data["EXIF"]["ISOSpeedRatings"]))
		{
			$this->prepared_data['exif_iso'] = $this->raw_data["EXIF"]["ISOSpeedRatings"];
		}
		if (isset($this->raw_data["EXIF"]["WhiteBalance"]))
		{
			$this->prepared_data['exif_whiteb'] = $this->user->lang['EXIF_WHITEB_' . (($this->raw_data["EXIF"]["WhiteBalance"]) ? 'MANU' : 'AUTO')];
		}
		if (isset($this->raw_data["EXIF"]["Flash"]))
		{
			if (isset($this->user->lang['EXIF_FLASH_CASE_' . $this->raw_data["EXIF"]["Flash"]]))
			{
				$this->prepared_data['exif_flash'] = $this->user->lang['EXIF_FLASH_CASE_' . $this->raw_data["EXIF"]["Flash"]];
			}
		}
		if (isset($this->raw_data["IFD0"]["Model"]))
		{
			$this->prepared_data['exif_cam_model'] = ucwords($this->raw_data["IFD0"]["Model"]);
		}
		if (isset($this->raw_data["EXIF"]["ExposureProgram"]))
		{
			if (isset($this->user->lang['EXIF_EXPOSURE_PROG_' . $this->raw_data["EXIF"]["ExposureProgram"]]))
			{
				$this->prepared_data['exif_exposure_prog'] = $this->user->lang['EXIF_EXPOSURE_PROG_' . $this->raw_data["EXIF"]["ExposureProgram"]];
			}
		}
		if (isset($this->raw_data["EXIF"]["ExposureBiasValue"]))
		{
			list($num,$den) = explode("/", $this->raw_data["EXIF"]["ExposureBiasValue"]);
			if ($den)
			{
				if (($num / $den) == 0)
				{
					$exif_exposure_bias = 0;
				}
				else
				{
					$exif_exposure_bias = $this->raw_data["EXIF"]["ExposureBiasValue"];
				}
				$this->prepared_data['exif_exposure_bias'] = sprintf($this->user->lang['EXIF_EXPOSURE_BIAS_EXP'], $exif_exposure_bias);
			}
		}
		if (isset($this->raw_data["EXIF"]["MeteringMode"]))
		{
			if (isset($this->user->lang['EXIF_METERING_MODE_' . $this->raw_data["EXIF"]["MeteringMode"]]))
			{
				$this->prepared_data['exif_metering_mode'] = $this->user->lang['EXIF_METERING_MODE_' . $this->raw_data["EXIF"]["MeteringMode"]];
			}
		}
	}

	/**
	* Sends the Exif into the template
	*
	* @param	bool	$expand_view	Shall we expand the Exif data on page view or collapse?
	* @param	string	$block			Name of the template loop the Exifs are displayed in.
	*/
	public function send_to_template($expand_view = true, $block = 'exif_value')
	{
		$this->prepare_data();

		if (!empty($this->prepared_data))
		{
			foreach ($this->prepared_data as $exif => $value)
			{
				$this->template->assign_block_vars($block, array(
					'EXIF_NAME'			=> $this->user->lang[strtoupper($exif)],
					'EXIF_VALUE'		=> htmlspecialchars($value),
				));
			}
			$this->template->assign_vars(array(
				'S_EXIF_DATA'	=> true,
				'S_VIEWEXIF'	=> $expand_view,
			));
		}
	}

	/**
	* Save the new Exif status in the database
	*/
	private function write()
	{
		if (!$this->image_id || ($this->orig_status == $this->status))
		{
			return false;
		}

		$update_data = ($this->status == self::DBSAVED) ? ", image_exif_data = '" . $this->db->sql_escape($this->serialized) . "'" : '';
		$sql = 'UPDATE ' . GALLERY_IMAGES_TABLE . '
			SET image_has_exif = ' . $this->status . $update_data . '
			WHERE image_id = ' . $this->image_id;
		$this->db->sql_query($sql);
	}

	/**
	* There are lots of possible Exif Groups and Values.
	* But you will never heard of the missing ones. so we just allow the most common ones.
	*/
	static private $allowed_groups		= array(
		'EXIF',
		'IFD0',
	);

	static private $allowed_keys		= array(
		'DateTimeOriginal',
		'FocalLength',
		'ExposureTime',
		'FNumber',
		'ISOSpeedRatings',
		'WhiteBalance',
		'Flash',
		'Model',
		'ExposureProgram',
		'ExposureBiasValue',
		'MeteringMode',
	);
}
