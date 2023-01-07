<?php

// to include this file, use:
// require_once("/srv/generic/libraries-available/nxs-fs/nxs-fs.php");

namespace barkgj\functions;

final class filesystem
{
	public static function createsymboliclink($args)
	{
		extract($args);
		
		$result = array();
		
		if ($startingpointpath == "")
		{
			brk_throw_nack("Error; startingpointpath not set");
		}	
		if ($willpointtopath == "")
		{
			brk_throw_nack("Error; willpointtopath not set");
		}
		
		// get rid of optional trailing /'s
		$willpointtopath = rtrim($willpointtopath, '/');
		$startingpointpath = rtrim($startingpointpath, '/');
		
		// check if the destination (where we will be pointing to) exists
		$willpointtopathexists = file_exists($willpointtopath);
		if (!$willpointtopathexists)
		{
			if ($willpointtopathnotfoundbehaviour == "docreate")
			{
				$result["stages"]["willpointtopathcheck"] = "willpointtopath $willpointtopath does not exist, will proceed";
			}
			else
			{
				brk_throw_nack("Error; willpointtopath $willpointtopath does not exists");
			}
		}
		else
		{
			$result["stages"]["willpointtopathexists"] = $willpointtopathexists;
		}
		
		// check if the source (starting point) already exists
		$startingpointpathexists = file_exists($startingpointpath);
		if ($startingpointpathexists)
		{
			if (is_link($startingpointpath))
			{
				if ($startingpointpathexistsaslinkbehaviour == "override")
				{
					$unlinkresult = unlink($startingpointpath);
					if ($unlinkresult === false)
					{
						// failed!
						brk_throw_nack("Error; unable to unlink old link");
					}
					
					$result["stages"]["unlinkexistingoldlink"] = $unlinkresult;
					
					// proceed
				}
				else
				{
					brk_throw_nack("Error; from already exists; not sure what to do now?");
				}
			}
			else
			{
				// its already there on the local fs
				if ($startingpointpathexistsaslocalfsbehaviour == "renameunique")
				{
					$uniquealtpath = $startingpointpath . "_renamed_" . time();
					$renameresult = rename($startingpointpath, $uniquealtpath);
					if ($renameresult === false)
					{
						// failed!
						brk_throw_nack("Error; unable to rename old link");
					}
					
					$result["stages"]["renameexisting"] = array
					(
						"startingpointpath" => $startingpointpath,
						"uniquealtpath" => $uniquealtpath,
						"renameresult" => $renameresult,					
					);
				}
				else
				{
					brk_throw_nack("Error; from already exists as local fs; not sure what to do now?");
				}
			}
		}
		else
		{
			$result["stages"]["startpointexists"] = $startingpointpathexists;
		}
		
		//step; create link
		if (true)
		{
			$execresult = shell_exec("ln -s $willpointtopath $startingpointpath 2>&1");
			$result["stages"]["lnexec"] = $execresult;
		}
		
		// step; ensure link created properly
		if (true)
		{
			$linkexistsresult = is_link($startingpointpath);
			if (!$linkexistsresult)
			{
				brk_throw_nack("Error; symbolic linked was not created?; $willpointtopath $startingpointpath");
			}
			$result["stages"]["ensurecheck"] = $linkexistsresult;
		}
		
		$result["didsucceed"] = "true";
		
		return $result;
	}

	public static function md5hashfolder($args)
	{
		$folder = $args["folder"];
		if ($folder == "")
		{
			echo "folder it not specified";
			die();
		}
		if ($folder == "/")
		{
			echo "can't use root folder at this moment in time";
			die();
		}
		
		$excludes = $args["excludes"];
		
		// 
		$excludepattern = "";
		foreach ($excludes as $exclude)
		{
			$excludepattern .= " ! -name '$exclude'";
		}
		
		//$ignoreuserandgrouppattern = " --owner=0 --group=0";
		//$command = "tar c {$ignoreuserandgrouppattern} --directory={$folder} {$excludepattern} . | md5sum | awk '{ print $1 }'";
		$command = "cd {$folder}; find . -type f {$excludepattern} -exec md5sum {} \; | sort -k 2 | md5sum";
		$execresult = shell_exec("$command 2>&1");
		$md5 = trim($execresult);
		
		$result = array();
		$result["md5"] = $md5;
		$result["phases"]["exec"] = $execresult;
		$result["folder"] = $folder;
		
		// error_log("brk_fs_md5hashfolder; [$command] equals [$md5]");
		
		return $result;
	}

	public static function createtempdir($dir=false,$prefix='php') 
	{
		$result = array();
		
		$tempfile = tempnam("/srv/tmp",'');
		error_log("nxs-fs; tempfile; $tempfile");
		if (file_exists($tempfile)) 
		{ 
			unlink($tempfile); 
		}
		mkdir($tempfile);
		if (is_dir($tempfile)) 
		{
			$result["didsucceed"] = true;
			$result["tempfolder"] = $tempfile;
		}
		else
		{
			$result["didsucceed"] = false;
			$result["tempfolder"] = false;
		}
		
		return $result;
	}

	public static function removedirectoryrecursively($path)
	{
		if ($path == "") { echo "path not specified"; die(); }
		if ($path == "/") { echo "path; / no way :)"; die(); }
		
		$execresult = shell_exec("rm -r {$path} 2>&1");
		
		$result = array();
		$result["phases"]["exec"] = $execresult;
	}

	// kudos to http://stackoverflow.com/questions/7497733/how-can-use-php-to-check-if-a-directory-is-empty
	public static function is_dir_empty($dir) 
	{
		if (!is_readable($dir)) return NULL; 
		$handle = opendir($dir);
		while (false !== ($entry = readdir($handle))) {
			if ($entry != "." && $entry != "..") {
			return false;
			}
		}
		return true;
	}

	public static function createcontainingfolderforfilepathifnotexists($filepath, $mode = 0777)
	{
		$folder = dirname($filepath);
		if (!file_exists($folder))
		{
			$r = mkdir($folder, $mode, true);
			
			if (!$r)
			{
				$error = error_get_last();
				brk_throw_nack("Error; unable to create folder; $folder; $error");
			}

			$result = array
			(
				"conclusion" => "CREATED_ON_THE_FLY",
			);
		}
		else
		{
			$result = array
			(
				"conclusion" => "WAS_ALREADY_THERE",
			);
		}
		
		return $result;
	}

	public static function human_filesize($bytes, $decimals = 2) 
	{
		$size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
	}

	// kudos to https://stackoverflow.com/questions/35299457/getting-mime-type-from-file-name-in-php
	public static function get_mime_type($filename)
	{
		$idx = explode( '.', $filename );
		$count_explode = count($idx);
		$idx = strtolower($idx[$count_explode-1]);
		
		$mimet = array
		( 
		'txt' => 'text/plain',
		'htm' => 'text/html',
		'html' => 'text/html',
		'php' => 'text/html',
		'css' => 'text/css',
		'js' => 'application/javascript',
		'json' => 'application/json',
		'xml' => 'application/xml',
		'swf' => 'application/x-shockwave-flash',
		'flv' => 'video/x-flv',

		// images
		'png' => 'image/png',
		'jpe' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpg' => 'image/jpeg',
		'gif' => 'image/gif',
		'bmp' => 'image/bmp',
		'ico' => 'image/vnd.microsoft.icon',
		'tiff' => 'image/tiff',
		'tif' => 'image/tiff',
		'svg' => 'image/svg+xml',
		'svgz' => 'image/svg+xml',

		// archives
		'zip' => 'application/zip',
		'rar' => 'application/x-rar-compressed',
		'exe' => 'application/x-msdownload',
		'msi' => 'application/x-msdownload',
		'cab' => 'application/vnd.ms-cab-compressed',

		// audio/video
		'mp3' => 'audio/mpeg',
		'qt' => 'video/quicktime',
		'mov' => 'video/quicktime',

		// adobe
		'pdf' => 'application/pdf',
		'psd' => 'image/vnd.adobe.photoshop',
		'ai' => 'application/postscript',
		'eps' => 'application/postscript',
		'ps' => 'application/postscript',

		// ms office
		'doc' => 'application/msword',
		'rtf' => 'application/rtf',
		'xls' => 'application/vnd.ms-excel',
		'ppt' => 'application/vnd.ms-powerpoint',
		'docx' => 'application/msword',
		'xlsx' => 'application/vnd.ms-excel',
		'pptx' => 'application/vnd.ms-powerpoint',


		// open office
		'odt' => 'application/vnd.oasis.opendocument.text',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
		);
		
		if (isset( $mimet[$idx] )) 
		{
			return $mimet[$idx];
		} 
		else 
		{
			return 'application/octet-stream';
		}
	}
}