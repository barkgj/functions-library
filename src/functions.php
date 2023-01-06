<?php

namespace barkgj;

final class functions
{
	public static function getstacktrace()
	{
		$result = debug_backtrace();
		
		return $result;
	}

	public static function getsitedatafolder()
	{
		$wpdocumentroot = $_SERVER['DOCUMENT_ROOT'];
		// for example "/srv/studios/bestwebsitetemplates/2/wordpress"
		$parentfolder = dirname($wpdocumentroot);
		// for example "/srv/studios/bestwebsitetemplates/2/"
		echo "getsitedatafolder:{$parentfolder}";
		die();

		// /srv/studios/bestwebsitetemplates
	}

	public static function getstudio()
	{
		// for example /srv/studios/bestwebsitetemplates
		$homedir =  $_SERVER['HOME'];
		$pieces = explode("/", $homedir);
		//
		if ($pieces[0] == "srv" && $pieces[1] == "studios")
		{
			$result = $pieces[2];
		}
		else
		{
			functions::throw_nack("unable to derive studio ({$homedir})");
		}

		return $result;
	}

	public static function outputbuffer_popall()
	{
		$existingoutput = array();
		
		$numlevels = ob_get_level();
		for ($i = 0; $i < $numlevels; $i++)
		{
			$existingoutput[] = ob_get_clean();
		}
		
		return $existingoutput;
	}

	public static function is_nxswebservice()
	{
		$result = false;
		if ($_REQUEST["webmethod"] != "")
		{
			$result = true;
		}
		return $result;
	}

	// public static function webmethod_return_nack is now throw_nack
	public static function throw_nack($message)
	{
		$lasterror = json_encode(error_get_last());
		
		// log nack on file system
		$stacktrace = debug_backtrace();
		$stacktrace_json = json_encode($stacktrace);
		$stacktrace_json_cut = substr($stacktrace_json, 0, 500);
		error_log("webmethod_return_nack;\r\n lasterror: $lasterror\r\nmessage: $message\r\ncut stacktrace: " . $stacktrace_json_cut ."\r\n\r\n");
		
		// cleanup output that was possibly produced before,
		// if we won't this could cause output to not be json compatible
		$existingoutput = functions::outputbuffer_popall();
		
		http_response_code(500);
		//header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error");
		//header("Status: 500 Internal Server Error"); // for fast cgi
		
		$output = array
		(
			"result" => "NACK",
			"message" => "Halted; " . $message,
			"stacktrace" => functions::getstacktrace(),
		);
		
		if (functions::is_nxswebservice())
		{
			// system is processing a nxs webmethod; output in json
			$output=json_encode($output);
			echo $output;
		}
		else
		{
			// system is processing regular request; output in text
			echo "<div style='background-color: white; color: black;'>NACK;<br />";
			echo "raw print:<br />";
			var_dump($output);
			echo "pretty print:<br />";
			if (isset($_REQUEST["pp"]) && $_REQUEST["pp"] == "false")
			{
				// in some situation the prettyprint can stall
			}
			else
			{
				echo "<!-- hint; in case code breaks after this comment, add querystring parameter pp with value false (pp=false) to output in non-pretty format -->";
				echo functions::prettyprint_array($output);
			}
			echo "<br />(raw printed)<br />";
			echo "</div>";
		}
		die();
	}


	// newguid createguid
	public static function create_guid($namespace = '') 
	{
		// kudos to https://stackoverflow.com/questions/21671179/how-to-generate-a-new-guid
		return trim(com_create_guid(), '{}');
	}

	// pretty_print
	public static function prettyprint_array($arr)
	{
		$retStr = '<ul>';
		if (is_array($arr))
		{
		foreach ($arr as $key=>$val)
		{
			if (is_array($val))
			{
			$retStr .= '<li>' . $key . ' => ' . functions::prettyprint_array($val) . '</li>';
			} 
			else if (is_string($val))
			{
			$retStr .= '<li>' . $key . ' => ' . $val . '</li>';
			}
			else
			{
			$type = get_class($val);
			if ($type === false)
			{
				// primitive
				$retStr .= '<li>' . $key . ' => ' . $val . '</li>';
			}
			else
			{
				$retStr .= '<li>' . $key . ' => {some object of type ' . $type . ' }</li>';
			}
			}
		}
		}
		else
		{
		$retStr .= '<li>Not an array</li>';
		}
		$retStr .= '</ul>';
		return $retStr;
	}

	public static function webmethod_return($args)
	{
		if ($args["result"] == "OK")
		{
			webmethod_return_ok($args);
		}
		else 
		{
			functions::throw_nack($args["message"]);
		}
	}

	public static function set_jsonheader()
	{	
		if(!headers_sent())
		{
			header('Content-Type: application/json; charset=utf-8');
		}
	}

	public static function webmethod_return_ok($args)
	{
		$content = $args;
		$content["result"] = "OK";
		functions::webmethod_return_raw($content);
	}

	public static function isutf8($string) 
	{
		if (function_exists("mb_check_encoding")) 
		{
			return mb_check_encoding($string, 'UTF8');
		}

		return (bool)preg_match('//u', serialize($string));
	}

	public static function toutf8string($in_str)
	{
		$in_str_v2=mb_convert_encoding($in_str,"UTF-8","auto");
		if ($in_str_v2 === false)
		{
			$in_str_v2 = $in_str;
		}
		
		$cur_encoding = mb_detect_encoding($in_str_v2) ; 
		if($cur_encoding == "UTF-8" && functions::isutf8($in_str_v2)) 
		{
			$result = $in_str_v2; 
		}
		else 
		{
			$result = utf8_encode($in_str_v2); 
		}

		return $result;
	}

	// 2012 06 04; GJ; in some particular situation (unclear yet when exactly) the result cannot be json encoded
	// erroring with 'Invalid UTF-8 sequence in range'.
	// Solution appears to be to UTF encode the input
	public static function array_toutf8string($result)
	{
		foreach ($result as $resultkey => $resultvalue)
		{
			if (is_string($resultvalue))
			{
				if (!functions::isutf8($resultvalue))
				{
					$result[$resultkey] = functions::toutf8string($resultvalue);
				}

				// also fix the special character \u00a0 (no breaking space),
				// as this one also could result into issues
				$result[$resultkey] = preg_replace('~\x{00a0}~siu', ' ', $result[$resultkey]);   
			}
			else if (is_array($resultvalue))
			{
				$result[$resultkey] = functions::array_toutf8string($resultvalue);
			}
			else
			{
				// leave as is...
			}
		}
		
		return $result;
	}

	public static function webmethod_return_raw($args)
	{
		if (headers_sent($filename, $linenum)) 
		{
			echo "headers already send; $filename $linenum";
			exit();
		}
		
		$existingoutput = array();
		
		$numlevels = ob_get_level();
		for ($i = 0; $i < $numlevels; $i++)
		{
			$existingoutput[] = ob_get_clean();
		}
		
		functions::set_jsonheader(); 
		http_response_code(200);

		// add 'result' to array
		// $args["result"] = "OK";
		
		// sanitize malformed utf8 (if the case)
		$args = functions::array_toutf8string($args);
		
		// in some very rare situations the json_encode
		// can stall/break the execution (see support ticket 13459)
		// if there's weird Unicode characters in the HTML such as (C2 A0)
		// which is a no-break character that is messed up
		// (invoking json_encode on that output would not throw an exception
		// but truly crash the server). To solve that problem, we use the following
		// kudos to:
		// http://stackoverflow.com/questions/12837682/non-breaking-utf-8-0xc2a0-space-and-preg-replace-strange-behaviour
		foreach ($args as $k => $v)
		{
			if (is_string($v))
			{
				$v = preg_replace('~\xc2\xa0~', ' ', $v);
				$args[$k] = $v;
			}
		}
		
		if ($_REQUEST["json_output_format"] == "prettyprint")
		{
			// only works in PHP 5.4 and above
			$options = 0;
			$options = $options | JSON_PRETTY_PRINT;
			$output = json_encode($args, $options);
		}
		else
		{
			// important!! the json_encode can return nothing,
			// on some servers, when the 2nd parameter (options),
			// is specified; ticket 22986!		
			$output = json_encode($args);
		}
		
		echo $output;
		
		exit();
	}

	public static function die()
	{
		error_log("nxs die");
		die();
	}

	public static function ob_start($output_callback = "")
	{
		$shouldbufferoutput = true;
		
		if ($shouldbufferoutput)
		{
			if ($output_callback  != "") 
			{
				$result = ob_start($output_callback); 
			} 
			else 
			{ 
				$result = ob_start(); 
			}
		}
		else
		{
			$result = "overruled (no output buffering)";
		}
		
		return $result;
	}


	public static function ob_get_contents()
	{
		$shouldbufferoutput = true;
			
		if ($shouldbufferoutput)
		{
			$result = ob_get_contents();
		}
		else
		{
			$result = "overruled (no output buffering)";
		}
		
		return $result;
	}

	public static function ob_end_clean()
	{
		$shouldbufferoutput = true;
			
		if ($shouldbufferoutput)
		{
			$result = ob_end_clean();
		}
		else
		{
			$result = "overruled (no output buffering)";
		}
		
		return $result;
	}

	public static function ob_get_clean()
	{
		$shouldbufferoutput = true;
		
		if ($shouldbufferoutput)
		{
			$result = ob_get_clean();
		}
		else
		{
			$result = "overruled (no output buffering)";
		}
		
		return $result;
	}

	// 2013 08 03; fixing unwanted WP3.6 notice errors
	// third party plugins and other php code (like sunrise.php) can
	// cause warnings that mess up the output of the webmethod
	// for example when activating the theme
	// to solve this, at this stage we clean the output buffer
	// 2014 12 07; in some cases the ob_clean() invoked here
	// can cause weird bogus output (diamonds with question marks),
	// as-if the encoding is messed up (dproost)
	// to avoid this from happening we don't do a ob_clean when
	// there's nothing to clean up in the first place
	public static function saveobclean()
	{
		if(ob_get_level() > 0)
		{
			$current = ob_get_contents();
			if ($current != "")
			{
			ob_clean();
			}
			else
			{
				// leave as-is
			}
		}
		else
		{
			// ignore
		}
	}
}