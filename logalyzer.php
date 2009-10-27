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
		
		//	First, the settings that don't depend on version
		$settings['drive'] = $rip_settings[1][0];

		if(!($this->is_new_eac())) {
			//	Parse as if everything were one line
			$read_settings = explode(',', $rip_settings[1][1]);
			
			//	Now we've got the read settings, so put them in the array
			$settings['read_mode'] = $read_settings[0];
			
			//	Look only for the string "Secure." If we match just 'Secure' it will fail due to C2 data
			if(false !== strpos($settings['read_mode'], 'Secure')) {
				$settings['accurate_stream'] = trim($read_settings[1]);
				$settings['disable_cache'] = trim($read_settings[2]);

				//	TODO:	What about C2 on or off?
			}

			$settings['offset'] = $rip_settings[1][2];

		} else {
			$settings['read_mode'] = $rip_settings[1][1];
				
			//	If secure mode, check for accurate stream, caching and C2
			if($settings['read_mode'] == 'Secure') {
				$settings['accurate_stream'] = $rip_settings[1][2];
				$settings['disable_cache'] = $rip_settings[1][3];
				$settings['c2'] = $rip_settings[1][4];
				$settings['offset'] = $rip_settings[1][5];
			} else {
				$settings['offset'] = $rip_settings[1][2];
			}
			
		}
		
		return $settings;
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
		
		//	Assemble the results array
		for($c = 0; $c < sizeof($all_matches['trackno']); $c++) {
			$track_data[$c]['trackno'] = $all_matches['trackno'][$c];
			$track_data[$c]['test_crc'] = $all_matches['test_crc'][$c];
			$track_data[$c]['copy_crc'] = $all_matches['copy_crc'][$c];

			if($all_matches['test_crc'][$c] === $all_matches['copy_crc'][$c]) {
				$track_data[$c]['ok'] = true;
			} else {
				$track_data[$c]['ok'] = false;
			}

		}
				
		return $track_data;
	}
	
	function get_logfile_path() {
		return $this->logfile;
	}
	
	function score_rip() {
		/*	Computes an accuracy score for a rip out of 100
			This is not weighted; it's a percentage based on the number of problems.
			
			So, ripping in burst with no offset correction = 2 problems out of 10 possible problems
			=	80% score. Should also return an array with plaintext representations of the problem(s).
		*/
	}
}	
?>