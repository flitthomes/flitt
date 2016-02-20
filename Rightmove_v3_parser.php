<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CodeIgniter Rightmove V3 .blm Parser Class
 *
 * Parses a Rightmove V3 .blm file and returns the properties
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		BIOSTALL (Steve Marks)
 * @link		http://biostall.com
 */
 
class Rightmove_v3_parser {

	var $file_name					= "";
	var $file_location 				= "";
	var $convert_lookup_fields		= true;
	var $archive_location			= '';
	
	var $image_destination			= "";
	var $floorplan_destination		= "";
	var $document_destination		= "";
	var $hip_destination			= "";
	var $epc_destination			= "";
	
	var $eof_char 					= "^";
	var $eor_char 					= "|";
	var $definition_property_count 	= 0;
	
	var $field_definitions 			= array();
	
	var $properties 				= array();
	
	var	$_debug_msg					= array();
	
	#### BEGIN DECLARE CUSTOM FIELDS ####
	var $custom_field = array(
		'STATUS_ID' => array(
			"Available",
			"SSTC (Sales only)",
			"SSTCM(Scottish Sales only)",
			"Under Offer (Sales only)",
			"Reserved (Lettings only)",
			"Let Agreed (Lettings only)"
		),
		'PRICE_QUALIFIER' => array(
			0 => "Default",
			1 => "POA",
			2 => "Guide Price",
			3 => "Fixed Price",
			4 => "Offers in Excess of",
			5 => "OIRO",
			6 => "Sale by Tender",
			7 => "From",
			9 => "Shared Ownership",
			10 => "Offers Over",
			11 => "Part Buy Part Rent",
			12 => "Shared Equity"
		),
		'PUBLISHED_FLAG' => array(
			0 => "Hidden/invisible", 
			1 => "Visible"
		),
		'LET_TYPE_ID' => array(
			0 => "Not Specified", 
			1 => "Long Term", 
			2 => "Short Term", 
			3 => "Student", 
			4 => "Commercial"
		),
		'LET_FURN_ID' => array(
			0 => "Furnished", 
			1 => "Part Furnished", 
			2 => "Unfurnished", 
			3 => "Not Specified", 
			4 => "Furnished/Un Furnished"
		),
		'LET_RENT_FREQUENCY' => array(
			0 => "Weekly", 
			1 => "Monthly", 
			2 => "Quarterly", 
			3 => "Annual"
		),
		'TENURE_TYPE_ID' => array(
			1 => "Freehold", 
			2 => "Leasehold", 
			3 => "Feudal", 
			4 => "Commonhold", 
			5 => "Share of Freehold"
		),
		'TRANS_TYPE_ID' => array(
			1 => "Resale", 
			2 => "Lettings"
		),
		'NEW_HOME_FLAG' => array(
			"Y" => "New Home", 
			"N" => "Non New Home"
		),
		'PROP_SUB_ID' => array(
			0=>"Not Specified",
			1=>"Terraced",
			2=>"End of Terrace",
			3=>"Semi-Detached",
			4=>"Detached",
			5=>"Mews",
			6=>"Cluster House",
			7=>"Ground Flat",
			8=>"Flat",
			9=>"Studio",
			10=>"Ground Maisonette",
			11=>"Maisonette",
			12=>"Bungalow",
			13=>"Terraced Bungalow",
			14=>"Semi-Detached Bungalow",
			15=>"Detached Bungalow",
			16=>"Mobile Home",
			17=>"Hotel",
			18=>"Guest House",
			19=>"Commercial Property",
			20=>"Land",
			21=>"Link Detached House",
			22=>"Town House",
			23=>"Cottage",
			24=>"Chalet",
			27=>"Villa",
			28=>"Apartment",
			29=>"Penthouse",
			30=>"Finca",
			43=>"Barn Conversion",
			44=>"Serviced Apartments",
			45=>"Parking",
			46=>"Sheltered Housing",
			47=>"Retirement Property",
			48=>"House Share",
			49=>"Flat Share",
			50=>"Park Home",
			51=>"Garages",
			52=>"Farm House",
			53=>"Equestrian",
			56=>"Duplex",
			59=>"Triplex",
			62=>"Longere",
			65=>"Gite",
			68=>"Barn",
			71=>"Trulli",
			74=>"Mill",
			77=>"Ruins",
			80=>"Restaurant",
			83=>"Cafe",
			86=>"Mill",
			89=>"Trulli",
			92=>"Castle",
			95=>"Village House",
			101=>"Cave House",
			104=>"Cortijo",
			107=>"Farm Land",
			110=>"Plot",
			113=>"Country House",
			116=>"Stone House",
			117=>"Caravan",
			118=>"Lodge",
			119=>"Log Cabin",
			120=>"Manor House",
			121=>"Stately Home",
			125=>"Off-Plan",
			128=>"Semi-detached Villa",
			131=>"Detached Villa",
			134=>"Bar",
			137=>"Shop",
			140=>"Riad",
			141=>"House Boat",
			142=>"Hotel Room"
		)
	);

	#### END DECLARE CUSTOM FIELDS ####
	
	function Rightmove_v3_parser($config = array())
	{
		if (count($config) > 0)
		{
			$this->initialize($config);
		}

		log_message('debug', "Rightmove_v3_parser Class Initialized");
		$this->_debug_msg[] = "Rightmove_v3_parser Class Initialized<br />";
	}

	function initialize($config = array())
	{
		foreach ($config as $key => $val)
		{
			if (isset($this->$key))
			{
				$this->$key = $val;
			}
		}
	}
	
	function process()
	{
		
		// FORMAT DIRECTORIES
		if ($this->file_location!="") { $this->file_location .= '/'; }else{ if ($this->file_name=="") { $this->file_location = './'; } }
		if ($this->archive_location!="") { $this->archive_location .= '/'; }
		if ($this->image_destination!="") { $this->image_destination .= '/'; }
		if ($this->floorplan_destination!="") { $this->floorplan_destination .= '/'; }
		if ($this->document_destination!="") { $this->document_destination .= '/'; }
		if ($this->hip_destination!="") { $this->hip_destination .= '/'; }
		if ($this->epc_destination!="") { $this->epc_destination .= '/'; }
		
		// GET LATEST FILE IF NONE PROVIDED
		if ($this->file_name=='') {
			
			$this->_debug_msg[] = "No file name passed. Attempting to locate zip of blm file in directory (".$this->file_location.")<br />";
			
			if (!$handle = opendir($this->file_location)) {
				$this->_debug_msg[] = "Unable to open file location (".$this->file_location.") to get file to process<br />";
			}else{
				$last_mod_time = 0;
				$last_mod_file = '';
				while (false !== ($file = readdir($handle))) {
					$explode_file_name = explode(".", $file);
					$ext = $explode_file_name[count($explode_file_name)-1];
					if (strtolower($ext)=="zip" || strtolower($ext)=="blm") { // if a zip or blm file then use this file
						if ($last_mod_time<filemtime($this->file_location.$file)) { 
							$last_mod_time = filemtime($this->file_location.$file);
							$last_mod_file = $file;
						}
						break;
					}
				}
				$this->file_name = $last_mod_file;
				$this->_debug_msg[] = "Found file ".$this->file_name.". Continuing to process<br />";
				closedir($handle);
			}
			
		}
		
		$zip_name = '';
		$files_processed_array = array();
		
		if (file_exists($this->file_location.$this->file_name)) {
			
			// UNZIP ZIP IF IN ZIP FORMAT
			$explode_file_name = explode(".", $this->file_name);
			$ext = $explode_file_name[count($explode_file_name)-1];
			if (strtolower($ext)=="zip") { // if a zip file then unzip
				
				$this->_debug_msg[] = "File appears to be a zip. Extracting files...<br />";
			
				$CI =& get_instance();
				$CI->load->library("unzip");
				$CI->unzip->extract($this->file_location.$this->file_name);
				$CI->unzip->close();
				
				$this->_debug_msg[] = "Extracted files successfully<br />";
				
				// get unzipped blm
				if (!$handle = opendir($this->file_location)) {
					$this->_debug_msg[] = "Unable to open file location (".$this->file_location.") to get unzipped blm file to process<br />";
				}else{
					$last_mod_time = 0;
					$last_mod_blm = '';
					while (false !== ($file = readdir($handle))) {
						$explode_file_name = explode(".", $file);
						$ext = $explode_file_name[count($explode_file_name)-1];
						if (strtolower($ext)=="blm") { // if a blm file update last mod blm if latest
							if ($last_mod_time<filemtime($this->file_location.$file)) { 
								$last_mod_time = filemtime($this->file_location.$file);
								$last_mod_blm = $file;
							}
						}
					}
					$zip_name = $this->file_name;
					$this->file_name = $last_mod_blm;
					array_push($files_processed_array, $this->file_name);
					closedir($handle);
				}
				
			}
			
			// READ FILE CONTENTS
			$this->_debug_msg[] = "Reading file contents<br />";
			$handle = fopen($this->file_location.$this->file_name, 'r');
			$contents = fread($handle, filesize($this->file_location.$this->file_name));
			fclose($handle);
			
			// BEGIN PROCESS #HEADER#
			$header = trim(substr($contents, strpos($contents,'#HEADER#')+8, strpos($contents,'#DEFINITION#')-8));
			$header_data = explode("\n", $header);
			foreach ($header_data as $row) {
				// get end of field character
				if (strstr($row,"EOF")) {
	            	$replace_array = array("EOF", " ", ":", "'", "\n", "\r");
	            	$this->eof_char = str_replace($replace_array, "", $row);
					$this->_debug_msg[] = "Found the EOF character to be ".$this->eof_char."<br />";
	            }
				// get end of record character
				if (strstr($row,"EOR")) {
	            	$replace_array = array("EOR", " ", ":", "'", "\n", "\r");
	            	$this->eor_char = str_replace($replace_array, "", $row);
					$this->_debug_msg[] = "Found the EOR character to be ".$this->eor_char."<br />";
	            }
				// get property count
				if (strstr($row,"Property Count")) {
	            	$replace_array = array("Property Count", " ", ":", "'", "\n", "\r");
	            	$this->definition_property_count = (int)str_replace($replace_array, "", $row);
					$this->_debug_msg[] = "Found the Property Count to be ".$this->definition_property_count."<br />";
	            }
			}
			// END PROCESS #HEADER#
			
			if ($this->definition_property_count>0) { // only process data if property count in definition is more than 0
			
				// BEGIN PROCESS #DEFINITION#
				$definition_length = strpos($contents, $this->eor_char, strpos($contents,'#DEFINITION#'))-strpos($contents,'#DEFINITION#')-12;
				$definition = trim(substr($contents, strpos($contents, '#DEFINITION#')+12, $definition_length));
				$field_definitions = explode($this->eof_char, $definition);
				array_pop($field_definitions); // remove last blank definition field
				// END PROCESS #DEFINITION#
				
				// BEGIN PROCESS #DATA#
				$data_length = strpos($contents, '#END#')-strpos($contents, '#DATA#')-6;
				$data = trim(substr($contents, strpos($contents, '#DATA#')+6, $data_length)); 
				$data = explode($this->eor_char, $data);
				array_pop($data); // remove last blank property
				if (count($data)==$this->definition_property_count) { // if actual number of properties matches property count in definition
					
					$this->_debug_msg[] = "Looping through property data<br />";
					
					foreach ($data as $property) { // loop through properties
						
						$property = trim($property); // remove any new lines from beginning of property row
						
						$field_values = explode($this->eof_char, $property);
						array_pop($field_values); // remove last blank data field
						
						$this->_debug_msg[] = "Processing property ".$field_values[0]."<br />";
						
						if (count($field_definitions)==count($field_values)) { // if the correct number of fields expected
							
							$this->properties[count($this->properties)] = array();
							
							foreach ($field_values as $field_number=>$field) { // loop through property fields
								
								// standard fields
								$this->properties[count($this->properties)-1][$field_definitions[$field_number]] = $field; // set by default to value in .blm
								
								// custom fields (status, price qualifier etc)
								if ($this->convert_lookup_fields && isset($this->custom_field[$field_definitions[$field_number]])) { // if this field is a custom field
									foreach ($this->custom_field[$field_definitions[$field_number]] as $custom_field_key=>$custom_field_value) {
										if ($custom_field_key==$field) { 
											$this->properties[count($this->properties)-1][$field_definitions[$field_number]] = $custom_field_value;
											break; 
										}
									}
								}
								
								// images
								if (preg_match("/MEDIA_IMAGE_[0-9]{2}/", $field_definitions[$field_number])) { // if this field is an image field
									$field = trim($field);
									if ($field!="") { // if an image exists in this field
										$prefix = substr($field, 0, strpos($field, ":"));
										if ($prefix!="http" && $prefix!="https") { // if not a url
											if (file_exists($this->file_location.$field)) {
												if ($this->image_destination!="") { // if copying to a different directory 
													if (!@rename($this->file_location.$field, $this->image_destination.$field)) {
														$this->_debug_msg[] = "Unable to move image ".$this->file_location.$field." to ".$this->image_destination.$field."<br />";
													}else{
														$this->_debug_msg[] = "Moved image ".$this->file_location.$field." to ".$this->image_destination.$field."<br />";
													}
												}
											}else{
												$this->_debug_msg[] = "Image file (".$field.") doesn't exist<br />";
											}
										}else{
											$this->_debug_msg[] = "Image file (".$field.") is a URL. Do nothing<br />";
										}
									}
								}
								
								// floorplans
								if (preg_match("/MEDIA_FLOOR_PLAN_[0-9]{2}/", $field_definitions[$field_number])) { // if this field is a floorplan field
									$field = trim($field);
									if ($field!="") { // if a floorplan exists in this field
										$prefix = substr($field, 0, strpos($field, ":"));
										if ($prefix!="http" && $prefix!="https") { // if not a url
											if (file_exists($this->file_location.$field)) {
												if ($this->floorplan_destination!="") { // if copying to a different directory 
													if (!@rename($this->file_location.$field, $this->floorplan_destination.$field)) {
														$this->_debug_msg[] = "Unable to move floorplan ".$this->file_location.$field." to ".$this->floorplan_destination.$field."<br />";
													}else{
														$this->_debug_msg[] = "Moved floorplan ".$this->file_location.$field." to ".$this->floorplan_destination.$field."<br />";
													}
												}
											}else{
												$this->_debug_msg[] = "Floorplan file (".$field.") doesn't exist<br />";
											}
										}else{
											$this->_debug_msg[] = "Floorplan file (".$field.") is a URL. Do nothing<br />";
										}
									}
								}
								
								// documents
								if (preg_match("/MEDIA_DOCUMENT_[0-9]{2}/", $field_definitions[$field_number], $preg_match)) { // if this field is a document field
									$field = trim($field);
									if ($field!="") { // if a document exists in this field
										$prefix = substr($field, 0, strpos($field, ":"));
										if ($prefix!="http" && $prefix!="https") { // if not a url
											if (preg_match("/MEDIA_DOCUMENT_5[0-9]{1}/", $preg_match[0]) && ($field_values[$field_number+1]=="EPC" || $field_values[$field_number+1]=="HIP")) { 
												if ($field_values[$field_number+1]=="EPC") { // if an EPC graph
													if (file_exists($this->file_location.$field)) {
														if ($this->epc_destination!="") { // if copying to a different directory 
															if (!@rename($this->file_location.$field, $this->epc_destination.$field)) {
																$this->_debug_msg[] = "Unable to move EPC ".$this->file_location.$field." to ".$this->epc_destination.$field."<br />";
															}else{
																$this->_debug_msg[] = "Moved EPC ".$this->file_location.$field." to ".$this->epc_destination.$field."<br />";
															}
														}
													}else{
														$this->_debug_msg[] = "EPC file (".$field.") doesn't exist<br />";
													}
												}else{ // if a HIP document
													if (file_exists($this->file_location.$field)) {
														if ($this->hip_destination!="") { // if copying to a different directory 
															if (!@rename($this->file_location.$field, $this->hip_destination.$field)) {
																$this->_debug_msg[] = "Unable to move HIP ".$this->file_location.$field." to ".$this->hip_destination.$field."<br />";
															}else{
																$this->_debug_msg[] = "Moved HIP ".$this->file_location.$field." to ".$this->hip_destination.$field."<br />";
															}
														}
													}else{
														$this->_debug_msg[] = "HIP file (".$field.") doesn't exist<br />";
													}
												}
											}else{
												if (file_exists($this->file_location.$field)) {
													if ($this->document_destination!="") { // if copying to a different directory 
														if (!@rename($this->file_location.$field, $this->document_destination.$field)) {
															$this->_debug_msg[] = "Unable to move document ".$this->file_location.$field." to ".$this->document_destination.$field."<br />";
														}else{
															$this->_debug_msg[] = "Moved document ".$this->file_location.$field." to ".$this->document_destination.$field."<br />";
														}
													}
												}else{
													$this->_debug_msg[] = "Document file (".$field.") doesn't exist<br />";
												}
											}
										}else{
											$this->_debug_msg[] = "Document file (".$field.") is a URL. Do nothing<br />";
										}
									}
								}
								
							} // finish looping through property fields
						
						}else{
							$this->_debug_msg[] = "The number of fields for property ".$field_values[0]." (".count($field_values).") does not match the number of definition fields (".count($field_definitions).")<br />";
						}
						
					} // finish looping through properties
					
					if ($this->archive_location!="") {
		
						$this->_debug_msg[] = "Beginning archiving files to ".$this->archive_location."<br />";
		
						if (is_dir($this->archive_location)) {
		
							if ($zip_name!="") { // if it's a zip then archive the zip
								
								if (!@rename($this->file_location.$zip_name, $this->archive_location.$zip_name)) {
									$this->_debug_msg[] = "Unable to move zip ".$this->file_location.$zip_name." to ".$this->archive_location.$zip_name."<br />";
								}else{
									$this->_debug_msg[] = "Moved zip ".$this->file_location.$zip_name." to ".$this->archive_location.$zip_name."<br />";
								}
								foreach ($files_processed_array as $processed_file) {
									@unlink($this->file_location.$processed_file);
								}
							
							}else{
							
								if (!@rename($this->file_location.$this->file_name, $this->archive_location.$this->file_name)) {
									$this->_debug_msg[] = "Unable to move blm ".$this->file_location.$this->file_name." to ".$this->archive_location.$this->file_name."<br />";
								}else{
									$this->_debug_msg[] = "Moved blm ".$this->file_location.$this->file_name." to ".$this->archive_location.$this->file_name."<br />";
								}
							
							}
						
						}else{
							$this->_debug_msg[] = "Archiving directory (".$this->archive_location.") doesn't exist or has incorrect permissions<br />";
						}
					
					}else{
						$this->_debug_msg[] = "No archiving required<br />";
					}
					
				}else{
					$this->_debug_msg[] = "The number of properties (".count($data).") differs from the amount declared in the file definition (".$this->definition_property_count.")<br />";
				}
				// END PROCESS #DATA#
				
			}else{
				$this->_debug_msg[] = "Header Property Count set as zero<br />";
			}
			
		}else{
			$this->_debug_msg[] = "The file ".$this->file_location.$this->file_name." doesn't exist<br />";
		}
		
		$this->_debug_msg[] = "Processing complete<br />";
		
		return $this->properties;
	
	}
	
	function print_debugger()
	{
		$msg = '';
		if (count($this->_debug_msg) > 0) {
			foreach ($this->_debug_msg as $val) {
				$msg .= $val;
			}
		}
		return '<pre>'.$msg.'</pre>';
	}

}

?>