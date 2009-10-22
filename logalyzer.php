<?php
	//	logalyzer.php
	//	A PHP class for parsing and analyzing EAC logs

class Logalyzer {
	//	Class-level variables
	var $logfile_path;
	var $logfile = false;
	var $logfile_whole = false;

	function __construct($logfile_path) {
		if(!(file_exists($logfile_path))) {
			//	Constructors can't return false, so we set the logfile_path and check that when instantiating
			$this->logfile_path = false;
		} else {
			$this->logfile_path = $logfile_path;
		}
	}
	
	function load_logfile() {
		if(!($this->logfile_path)) {
			return false;
		}
		
		try {
			$this->logfile = file($this->logfile_path);
			$this->logfile_whole = file_get_contents($this->logfile_path);
		} catch(Exception $e) {
			return false;
		}
		
		return true;
	}
	
	function get_eac_version() {
		if(!($this->logfile)) {
			return false;
		}

		//	Look for "Exact Audio Copy V" string in the first line
		//	If not found, look for "EAC extraction logfile"
		//	If THAT'S not found, return false
		if(strpos($this->logfile[0], "extraction logfile")) {
			return "earlier than .95";
		} else {
			$version_string = preg_match('/ V(?<version>.+) from/', $this->logfile[0], $matches);
			if($version_string) {
				return $matches['version'];
			} else {
				return false;
			}
		}
	}
	
	function is_new_eac() {
		$version = $this->get_eac_version();
		
		if($version && strpos($version, "earlier than") === false) {
			return true;
		} else {
			return false;
		}
	}
	
	function get_rip_settings() {
		//	In one regex, we can get all of the drive's settings
		$rip = preg_match_all('/(.+)\s+:\s(.+)/', $this->logfile_whole, $rip_settings);

		//	Clean up the arrays generated...
		//	Get rid of the first array in the matches array
		array_shift($rip_settings);

		//	Trim all three arrays
		for($x = 0; $x < sizeof($rip_settings); $x++) {
			$rip_settings[$x] = array_map('trim', $rip_settings[$x]);
		}


		//	TODO: Parse out read settings that earlier versions put all on one line
		if(!($this->is_new_eac())) {
			//	Parse as if everything were one line
		}
		
		return $rip_settings;
	}
	
	function get_album_info() {
		//	Album info appears in the log as \n%artist% / %album%\n
		if(!($this->logfile_whole)) {
			return false;
		}
		
		$album_info = preg_match('/\n(?<artist>.+) \/ (?<album>.+)\n/', $this->logfile_whole, $matches);

		//	Trim extraneous spaces that are getting matched
		//	TODO: See if there's a way to not include the whitespace
		return array_map('trim', $matches);
	}
	
	function get_track_crcs() {
		//	Returns an array of track numbers, test CRCs, copy CRCs and bool true if they match
		$tracks = preg_match_all('/Track\s+(?<trackno>[0-9]+)[\S\s]+?Test CRC (?<test_crc>[A-F0-9]+)[\s]+?Copy CRC (?<copy_crc>[A-F0-9]+)/', $this->logfile_whole, $all_matches);
		
		if(!($tracks)) {
			return false;
		}	
		
		//	Assemble the array
		//	Assemble the results array
		for($c = 0; $c < sizeof($all_matches['trackno']); $c++) {
			$track_data['trackno'][$c] = $all_matches['trackno'][$c];
			$track_data['test_crc'][$c] = $all_matches['test_crc'][$c];
			$track_data['copy_crc'][$c] = $all_matches['copy_crc'][$c];

			if($all_matches['test_crc'][$c] === $all_matches['copy_crc'][$c]) {
				$track_data['ok'][$c] = true;
			} else {
				$track_data['ok'][$c] = false;
			}

		}
		
		return $track_data;
	}
	
	function get_logfile_path() {
		return $this->logfile;
	}	
}	
?>