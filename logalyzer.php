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
		/*
			1.	Determine EAC version
				1a.	If < .99, get the next x lines after "Used drive"
				1b.	If > .99, get even more lines.
		*/

		//	Some things are the same in both logs - the used drive line, for example
		//	The regex grabs everything between "Used drive:" and "Adapter"
		$used_drive = preg_match('/Used drive\s+:\s(?<drive>.+)\sAdapter/', $this->logfile_whole, $drive_matches);
		$rip_settings['drive'] = trim($drive_matches['drive']);

		//	Read info: In old EAC, this has everything on one line
		$read_info = preg_match('/Read mode\s+:\s(?<readmode>.+)/', $this->logfile_whole, $read_matches);

		if(!($this->is_new_eac())) {
			//	Parse $read_matches as if everything were one line
		} else {
			//	Do a few more preg_match()es to get the rest of the read settings
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
	
	function get_logfile_path() {
		return $this->logfile;
	}	
}	
?>