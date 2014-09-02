<?
/***************************************************************************

snif 1.5.2
(c) Kai Blankenhorn
www.bitfolge.de

THIS IS THE REAL SNIF INDEX.PHP FILE.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details: <http://www.gnu.org/licenses/gpl.txt>

****************************************************************************
**  DESCRIPTION FILE FORMAT                                               **
****************************************************************************

Hardcore definition:

<descriptionfile>  ::= <line>*
<line>             ::= <filename><separationString><description><EOL>
<filename>         ::= <anythingExceptSeparationString>+
<separationString> ::= defined by the $separationString variable, default "\t"
<description>      ::= <anyting>+
<EOL>              ::= "\r\n" | "\n"			// OS dependent

Simple example:

.	This directory contains downloadable files for MyProgram 12.0.
myprogram_12.0.exe	Installer version of MyProgram 12.0 (recommended)
myprogram_12.0.zip	Zip file distribution of MyProgram 12.0
releasenotes.txt	Release notes for MyProgram 12.0

Please note that the room between the filename and the description is not
filled with multiple spaces, but with one single tab. It doesn't matter if
the descriptions in a file align or not, just use one tab.
If you use a description for the current directory (.) as in the first line
in the above example, it will be used as a heading in the directory listing.
Put your descriptions in a text file within the same directory as the files 
to describe. Then put the text file's name in the $useDescriptionsFrom 
variable below. It is suggested that you use the same description file name
in all subdirectories you want to list. Reason: Read the next paragraph.
To make it even easier: For my download folder at 
http://www.bitfolge.de/download, I have put the description file at
http://www.bitfolge.de/download/descript.ion
You can download it and use it as another example.
Filenames in the description file are case insensitive as of 1.2.10. This
means that myprogram.zip and MyProgram.ZIP both are regarded as the same
file. If case sensitivity matters for you, you can disable this with the
$descriptionFilenamesCaseSensitive variable in the advanced settings.

****************************************************************************
**  HANDLING SUBDIRECTORY LISTINGS                                        **
****************************************************************************

Say you've put the snif index.php into www.yourhost.com/download.
Now somebody makes a request to www.yourhost.com/download/releases. In
order to deal with this properly, you would have to copy the snif index.php
to that directory, too. But this will prevent the user to go to 
www.yourhost.com/download from www.yourhost.com/download/releases
directly by selecting the .. link.
If you have this situation, use the index.php file from the subdirectory
called "subdir" in the snif archive file. All it does is automatically 
forward the user to the parent directory and set URL parameters so that
the real snif will handle the request.

OK, that may be confusing. Again, a simple example:

/download/descript.ion                       << descriptions for /download/*.*
/download/index.php                          << this file you're reading now, >25 KB
/download/license.txt
/download/notes.txt
/download/releases/bigprogram_2.0.zip
/download/releases/descript.ion              << descriptions for /download/releases/*.*
/download/releases/index.php                 << subdir/index.php, <2 KB
/download/releases/nightly/2.1_20031103.zip
/download/releases/nightly/2.1_20031104.zip
/download/releases/nightly/index.php         << subdir/index.php, <2 KB

If a users points his browser to
  www.yourhost.com/download/releases/nightly/
The small index.php will forward him to rewrite URL
  www.yourhost.com/download/releases/path/nightly/
And then the index file in that directory will forward him again, this time to
  www.yourhost.com/download/path/releases/nightly/

Now we've reached the directory with the real snif, which will take over and miraculously
lists the directory the user typed as an URL.*/



// crap to hack counting hindusness
$ScriptFName = "index.php";
$cacheThumbnails = true;
$hiddenFilesWildcards = Array("*.php", "*~", "error_log");
$allowSubDirs = true;
$useIndexFiles = false; // In case if subdir uses it's own index page and you want show it, you should enable it
$indexFiles = Array("index.html", "index.htm", "index.xhtml");
$listingServer = $_SERVER['HTTP_HOST'];
$hiddenFilesRegex = Array();
$useDescriptionsFrom = "description.txt";
$separationString = "\t";
$descriptionFilenamesCaseSensitive = false;
/**
 * If a directory contains more than this number of files, display it on
 * multiple pages. Useful for very large directories. $usePaging sets the
 * number of files displayed per page. Set to 0 to disable multiple pages.
 **/
$usePaging = 0;
$thumbnailHeight = 50;
$thumbnailWidth = 150;
/**
 * Use "back" instead of ".." to go up in directories.
 **/
$useBackForDirUp = true;
$displayColumns = Array(
	"icon",
	"name",
	"type",
	"size",
	"date",
	"description"
);
/**
 * Sets the listing to always occupy the whole width of the screen instead of
 * only the necessary space.
 **/
$tableWidth100Percent = true;
/**
 * Turns on and sets fixed width description column. Set to 0 to not restrict
 * description column width.
 * Can lead to strange results when not zero and $tableWidth100Percent==true and
 * does not fully work with IE.
 **/
$descriptionColumnWidth = 0;
/**
 * Specifies how long file and directory names are to be truncated. Defaults
 * to 30, set to 0 to turn off truncation.
 **/
$truncateLength = 30;
$protectDirsWithHtaccess = false;
/***************************************************************************/
/**  REAL CODE STARTS HERE, NO NEED TO CHANGE ANYTHING                    **/
/***************************************************************************/



/***************************************************************************/
/**  INITIALIZATION                                                       **/
/***************************************************************************/

// make sure all the notices don't come up in some configurations
error_reporting (E_ALL ^ E_NOTICE);

$displayError = Array();

// safify all GET variables
foreach($_GET AS $key => $value) {
	$_GET[$key] = strip_tags($value);
	if ($_GET[$key] != $value) {
		$displayError[] = "Неверные символы в URL, игнорируется.";
	}
	if (!get_magic_quotes_gpc()) {
		$_GET[$key] = stripslashes($value);
	}
}



// first of all, security: prevent any unauthorized paths
// if sub directories are forbidden, ignore any path setting
if (!$allowSubDirs) {
	$path = "";
} else {
	$path = $_GET["path"];
	
	// ignore any potentially malicious paths
	$path = safeDirectory($path);
}

// default sorting is by name
if ($_GET["sort"]=="") 
	$_GET["sort"] = "name";

// default order is ascending
if ($_GET["order"]=="") {
	$_GET["order"] = "asc";
} else {
	$_GET["order"] = strtolower($_GET["order"]);
}

// hide descriptions column if no description file is specified
if ($useDescriptionsFrom=="") {
	$index = array_search("description", $displayColumns);
	if ($index!==false && $index!==null) {
		unset($displayColumns[$index]);
	}
}
	
// add files used by listing to hidden file list
if ($useDescriptionsFrom!="") {
	$hiddenFilesWildcards[] = $useDescriptionsFrom;
}
$hiddenFilesWildcards[] = ".";
$hiddenFilesWildcards[] = basename($_SERVER["PHP_SELF"]);

// build hidden files regular expression
for ($i=0;$i<count($hiddenFilesWildcards);$i++) {
	$translate = Array(
		"." => "\\.",
		"*" => ".*",
		"?" => ".?",
		"+" => "\\+",
		"[" => "\\[",
		"]" => "\\]",
		"(" => "\\(",
		")" => "\\)",
		"{" => "\\{",
		"}" => "\\}",
		"^" => "\\^",
		"\$" => "\\\$",
		"\\" => "\\\\",
	);
	$hiddenFilesRegex[] = "^".strtr($hiddenFilesWildcards[$i],$translate)."$";
}
// hide .*
$hiddenFilesRegex[] = "^\\.[^.].*$";
$hiddenFilesWholeRegex = "/".join("|",$hiddenFilesRegex)."/i";



/***************************************************************************/
/**  REQUEST HANDLING                                                     **/
/***************************************************************************/

// handle image requests
if ($_GET["getimage"]!="") {
	$imagesEncoded = Array(
		"archive"  => "R0lGODlhEAAQAJECAAAAAP///////wAAACH5BAEAAAIALAAAAAAQABAAAAI3lA+pxxgfUhNKPRAbhimu2kXiRUGeFwIlN47qdlnuarokbG46nV937UO9gDMHsMLAcSYU0GJSAAA7",
		"asc"      => "R0lGODlhBQADAIABAN3d3f///yH5BAEAAAEALAAAAAAFAAMAAAIFTGAHuF0AOw==",
		"binary"   => "R0lGODlhEAAQAJECAAAAAP///////wAAACH5BAEAAAIALAAAAAAQABAAAAI0lICZxgYBY0DNyfhAfROrxoVQBo5mpzFih5bsFLoX5iLYWK6xyur5ubPAbhPZrKhSKCmCAgA7",
		"desc"     => "R0lGODlhBQADAIABAN3d3f///yH5BAEAAAEALAAAAAAFAAMAAAIFhB0XC1sAOw==",
		"dirup"    => "R0lGODlhEAAQAJECAAAAAP///////wAAACH5BAEAAAIALAAAAAAQABAAAAIulI+JwKAJggzuiThl2wbnT3WZN4oaA1bYRobXCLpkq5nnVr9xqe85C2xYhkRFAQA7",
		"folder"   => "R0lGODlhEAAQAJECAAAAAP///////wAAACH5BAEAAAIALAAAAAAQABAAAAIplI+JwKAJggzuiThl2wbnT3UgWHmjJp5Tqa5py7bhJc/mWW46Z/V+UgAAOw==",
		"HTML"     => "R0lGODlhEAAQAKIHABsb/2ho/4CA/0BA/zY2/wAAAP///////yH5BAEAAAcALAAAAAAQABAAAANEeFfcrVAVQ6thUdo6S57b9UBgSHmkyUWlMAzCmlKxAZ9s5Q5AjWqGwIAS8OVsNYJxJgDwXrHfQoVLEa7Y6+Wokjq+owQAOw==",
		"image"    => "R0lGODlhEAAQAKIEAK6urmRkZAAAAP///////wAAAAAAAAAAACH5BAEAAAQALAAAAAAQABAAAANCSCTcrVCJQetgUdo6RZ7b9UBgSHnkAKwscEZTy74pG9zuBavA7dOanu+H0gyGxN0RGdClKEjgwvKTlkzFhWOLISQAADs=",
		"text"     => "R0lGODlhEAAQAJECAAAAAP///////wAAACH5BAEAAAIALAAAAAAQABAAAAI0lICZxgYBY0DNyfhAfXcuxnWQBnoKMjXZ6qUlFroWLJHzGNtHnat87cOhRkGRbGc8npakAgA7",
		"blank"    => "R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==",
		"unknown"  => "R0lGODlhEAAQAJECAAAAAP///////wAAACH5BAEAAAIALAAAAAAQABAAAAI1lICZxgYBY0DNyfhAfXcuxnkI1nCjB2lgappld6qWdE4vFtprR+4sffv1ZjwdkSc7KJYUQQEAOw=="
	);
	$imageDataEnc = $imagesEncoded[$_GET["getimage"]];
	if ($imageDataEnc) {
		$maxAge = 31536000; // one year
		doConditionalGet($_GET["getimage"], gmmktime(1,0,0,1,1,2004));
		$imageDataRaw = base64_decode($imageDataEnc);
		Header("Content-Type: image/gif");
		Header("Content-Length: ".strlen($imageDataRaw));
		Header("Cache-Control: public, max-age=$maxAge, must-revalidate");
		Header("Expires: ".createHTTPDate(time()+$maxAge));
		echo $imageDataRaw;
	}
	
	die();
}

function save_jpegimage($img, $fname) 
{
	ob_start();
	imagejpeg($img);
	file_put_contents($fname, ob_get_contents(), 
	ob_end_clean());
}

// handle thumbnail creation
if ($_GET["thumbnail"]!="") {
	GLOBAL $thumbnailHeight, $cacheThumbnails;
	$thumbnailCacheSubdir = ".thumbs";
	
	$file = safeDirectory(urldecode($_GET["thumbnail"]));
	doConditionalGet($_GET["thumbnail"],filemtime($file));

	$thumbDir = dirname($file)."/".$thumbnailCacheSubdir;
	$thumbFile = $thumbDir."/".basename($file);
	if ($cacheThumbnails) {
		if (file_exists($thumbDir)) {
			if (!is_dir($thumbDir)) {
				$cacheThumbnails = false;
			}
		} else {
			if (@mkdir($thumbDir, 0777)) {
				;
			} else {
				$cacheThumbnails = false;
			}
		}
		if (file_exists($thumbFile)) {
			if (filemtime($thumbFile)>=filemtime($file)) {
				Header("Location: ".dirname($_SERVER["PHP_SELF"])."/".$thumbFile);
				die();
			}
		}
	}
	$contentType = "";
	$extension = strtolower(substr(strrchr($file, "."), 1));
	switch ($extension) {
		case "gif":		$src = imagecreatefromgif($file); $contentType="image/gif"; break;
		case "jpg":		// fall through
		case "jpeg":	$src = imagecreatefromjpeg($file); $contentType="image/jpeg"; break;
		case "png":		$src = imagecreatefrompng($file); $contentType="image/png"; break;
		default:	die(); break;
	}
	$srcWidth = imagesx($src);
	$srcHeight = imagesy($src);
	$srcAspectRatio = $srcWidth / $srcHeight;
	
	$maxAge = 3600; // one hour
	Header("Cache-Control: public, max-age=$maxAge, must-revalidate");
	Header("Expires: ".createHTTPDate(time()+$maxAge));

	if ($srcHeight<=$thumbnailHeight AND $srcWidth<=$thumbnailWidth) {
		Header("Content-Type: $contentType");
		readfile($file);
	} else {
		if ($srcWidth > $srcHeight) {
			$thumbWidth = $thumbnailWidth;
			$thumbHeight = $thumbWidth / $srcAspectRatio;
		} else {
			$thumbHeight = $thumbnailHeight;
			$thumbWidth = $thumbHeight * $srcAspectRatio;
		}
		if (function_exists('imagecreatetruecolor')) {
			$thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
		} else {
			$thumb = imagecreate($thumbWidth, $thumbHeight);
		} 
		imagecopyresampled($thumb, $src, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $srcWidth, $srcHeight);
		Header("Content-Type: image/jpeg");
		if ($cacheThumbnails) {
			save_jpegimage($thumb, $thumbFile);
			readfile($thumbFile);
		} else {
			imagejpeg($thumb);
		}
	}
	die();
}


/***************************************************************************/
/**  FUNCTIONS                                                            **/
/***************************************************************************/

// create a HTTP conform date
function createHTTPDate($time) {
	return gmdate("D, d M Y H:i:s", $time)." GMT";
}


// this function is from http://simon.incutio.com/archive/2003/04/23/conditionalGet
function doConditionalGet($file, $timestamp) {
	$last_modified = createHTTPDate($timestamp);
	$etag = '"'.md5($file.$last_modified).'"';
	// Send the headers
	Header("Last-Modified: $last_modified");
	Header("ETag: $etag");
	// See if the client has provided the required headers
	$if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ?
		stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']) :
		false;
	$if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ?
		stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) : 
		false;
	if (!$if_modified_since && !$if_none_match) {
		return;
	}
	// At least one of the headers is there - check them
	if ($if_none_match && $if_none_match != $etag) {
		return; // etag is there but doesn't match
	}
	if ($if_modified_since && $if_modified_since != $last_modified) {
		return; // if-modified-since is there but doesn't match
	}
	// Nothing has changed since their last request - serve a 304 and exit
	Header('HTTP/1.0 304 Not Modified');
	die();
}


function safeDirectory($path) {
	GLOBAL $displayError;
	$result = $path;
	if (strpos($path,"..")!==false)
		$result = "";
	if (substr($path,0,1)=="/") {
		$result = "";
	}
	if ($result!=$path) {
		$displayError[] = "Указан неверный путь, игнорируется.";
	}
	return $result;
}


/**
 * Formats a file's size nicely (750 B, 3.4 KB etc.)
 **/
function niceSize($size) {
	define("SIZESTEP", 1024.0);
	static $sizeUnits = Array();
	if (count($sizeUnits)==0) {
		$sizeUnits[] = "&nbsp;б";
		$sizeUnits[] = "Кб";
		$sizeUnits[] = "Мб";
		$sizeUnits[] = "Гб";
		$sizeUnits[] = "Тб";
	}
	
	if ($size==="")
		return "";
	
	$unitIndex = 0;
	while ($size>SIZESTEP) {
		$size = $size / SIZESTEP;
		$unitIndex++;
	}
	
	if ($unitIndex==0) {
		return number_format($size, 0)."&nbsp;".$sizeUnits[$unitIndex];
	} else {
		return number_format($size, 1, ".", ",")."&nbsp;".$sizeUnits[$unitIndex];
	}
}

/**
 * Compare two strings or numbers. Return values as strcmp().
 **/
function myCompare($arrA, $arrB, $caseSensitive=false) {
	$a = $arrA[$_GET["sort"]];
	$b = $arrB[$_GET["sort"]];
	
	// sort .. first
	if ($arrA["isBack"]) return -1;
	if ($arrB["isBack"]) return 1;
	// sort directories above everything else
	if ($arrA["isDirectory"]!=$arrB["isDirectory"]) {
		$result = $arrB["isDirectory"]-$arrA["isDirectory"];
	} else if ($arrA["isDirectory"] && $arrB["isDirectory"] && ($_GET["sort"]=="type" || $_GET["sort"]=="size")) {
		$result = 0;
	} else {
		if (is_string($a) OR is_string($b)) {
			if (!$caseSensitive) {
				$a = strtoupper($a);
				$b = strtoupper($b);
			}
			$result = strcoll($a,$b);
		} else {
			$result = $a-$b;
		}
	}
	
	if (strtolower($_GET["order"])=="desc") {
		return -$result;
	} else {
		return $result;
	}
}


/**
 * URLEncodes some characters in a string. PHP's urlencode and rawurlencode
 * produce very unsatisfying results for special and reserved characters in
 * filenames.
 **/
function myEncode($path, $filename) {
	// % must be the first, as it is the escape character
	/*
	$from = Array("%"," ","#","&");
	$to = Array("%25","%20","%23","%26");
	return str_replace($from, $to, $string);
	*/
	return $path.rawurlencode($filename);
}


/**
 * Build a URL using new sorting settings.
 **/
function getNewSortURL($newSort) {
	GLOBAL $path;
	$base = "http://".$_SERVER['SERVER_NAME'].dirname($_SERVER['PHP_SELF']).'/';
	$url = $base."sort-$newSort";
	if ($newSort==$_GET["sort"]) {
		if ($_GET["order"]=="asc" OR $_GET["order"]=="") {
			$url.= "/order-desc";
		}
	}
	if ($path!="") {
		$url.= "/path/$path";
	}
	return $url;
}

/**
 * Determine a file's file type based on its extension.
 **/
function getFileType($fileInfo) {
	// put any additional extensions in here
	$extension = $fileInfo["type"];
	static $fileTypes = Array(
		"HTML"		=> Array("html","htm"),
		"image"		=> Array("gif","jpg","jpeg","png","tif","tiff","bmp","art"),
		"text"		=> Array("asp","c","cfg","cpp","css","csv","conf","cue","h","inf","ini","java","js","log","nfo","php","phps","pl","py","rdf","rss","rtf","sql","txt","vbs","xml"),
		//"code"		=> Array("asp","c","cpp","h","java","js","php","phps","pl","py","sql","vbs"),
		//"xml"			=> Array("rdf","rss","xml"),
		"binary"	=> Array("asf","au","avi","bin","class","divx","doc","exe","mov","mpg","mpeg","mp3","ogg","ogm","pdf","ppt","ps","rm","swf","wmf","wmv","xls"),
		//"document"=> Array("doc","pdf","ppt","ps","rtf","xls"),
		"archive"	=> Array("ace","arc","bz2","cab","gz","lha","jar","rar","sit","tar","tbz2","tgz","z","zip","zoo")
	);
	static $extensions = null;

	if ($extensions==null) {
		$extensions = Array();
		foreach($fileTypes AS $keyType => $value) {
			foreach($value AS $ext) $extensions[$ext] = $keyType;
		}
	}

	if ($fileInfo["isDirectory"]) {
		if ($fileInfo["isBack"]) {
			return "dirup";
		} else {
			return "folder";
		}
	}
	
	$type = $extensions[strtolower($extension)];
	if ($type=="") {
		return "unknown";
	} else {
		return $type;
	}
}

function getIcon($fileType) {
		return dirname($_SERVER['PHP_SELF'])."/getimage-$fileType";
}

function dirContainsHtAccess($dirname) {
	if(is_dir($dirname)) {
		if ($dirname=="." || $dirname=="..") return false;
		$d = dir($dirname);
		while($f = $d->read()) {
			if ($f==".htaccess")
				return true;
		}
	}
	return false;
}

// checks if a file is hidden from view
function fileIsHidden($filename) {
	GLOBAL $hiddenFilesWholeRegex,$protectDirsWithHtaccess;
	
	if (is_dir($filename) && $protectDirsWithHtaccess) {
		if (!($filename=="." || $filename=="..")) {
			$d = dir($filename);
			while($f = $d->read()) {
				if ($f==".htaccess")
					return true;
			}
		}
	}
	return preg_match($hiddenFilesWholeRegex,$filename);
}


function getVersion($filename) {
	$version = "&ndash;";
	$contents = file_get_contents($filename);
	$no_matches = preg_match("/Id: (\S+) (\d+.\d+)/i", $contents, $matches);
	if ($no_matches>0) $version = $matches[2];
	return $version;
}


/**
 * Gets a file's description from the description array.
 **/
function getDescription($filename) {
	GLOBAL $descriptions, $descriptionFilenamesCaseSensitive;
	
	if (!$descriptionFilenamesCaseSensitive) {
		$filename = strtolower($filename);
	}
	return $descriptions[$filename];
}

function getPageLink($startNumber, $linkText, $linkTitle="") {
	GLOBAL $listingServer, $path;
	$url = "http://".$_SERVER['SERVER_NAME'].dirname($_SERVER['PHP_SELF'])."/path/".$path."/sort-".$_GET["sort"]."/order-".$_GET["order"]."/start-".$startNumber;
	if ($linkTitle!="") {
		$titleAttribute = " title=\"$linkTitle\"";
	} else {
		$titleAttribute = "";
	}
	return "<a href=\"$url\"$titleAttribute>$linkText</a>&nbsp;";
}

function getPagingHeader() {
	GLOBAL $pageStart, $usePaging, $pagingNumberOfPages, $pagingActualPage, $pageNumber, $files;
	static $displayPages = Array();
	if (count($displayPages)==0) {
		$displayPages[] = 0;
		for ($i=$pagingActualPage-1; $i<$pagingActualPage+3; $i++) {
			if ($i>=0 && $i<$pagingNumberOfPages) {
				$displayPages[] = $i;
			}
		}
		$displayPages[] = $pagingNumberOfPages-1;
		$displayPages = array_unique($displayPages);
	}
	
	$header = "страница &nbsp;&nbsp;";
	if ($pageStart>0) {
		$header.= getPageLink($pageStart-$usePaging, "&laquo;", "предыдущая");
	}
	if ($pageStart+$usePaging<count($files)) {
		$header.= getPageLink($pageStart+$usePaging, "&raquo;", "следующая");
	}
	foreach($displayPages as $i => $pageNumber) {
		if ($pageNumber-$displayPages[$i-1] > 1) {
			$header.= ".. ";
		}
		if ($pageNumber==$pagingActualPage) {
			$header.= "<span class=\"White\">".($pageNumber+1)."&nbsp;</span>";
		} else {
			$header.= getPageLink($pageNumber*$usePaging, $pageNumber+1);
		}
	}
	
	return $header;
}

function getPathLink($directory) {
		global $useIndexFiles, $indexFiles;

		if($useIndexFiles)
			for($i=0;$i<count($indexFiles);$i++)
				if(file_exists($directory."/".$indexFiles[$i]))
					return "http://".$_SERVER['SERVER_NAME'].dirname($_SERVER['PHP_SELF'])."/".$directory."/";
		return "http://".$_SERVER['SERVER_NAME'].dirname($_SERVER['PHP_SELF'])."/path/".$directory."/";
}

/**
 * Truncates a string to a certain length at the most sensible point.
 * First, if there's a '.' character near the end of the string, the string is truncated after this character.
 * If there is no '.', the string is truncated after the last ' ' character.
 * If the string is truncated, " ..." is appended.
 * If the string is already shorter than $length, it is returned unchanged.
 * 
 * @static
 * @param string    string A string to be truncated.
 * @param int        length the maximum length the string should be truncated to
 * @return string    the truncated string
 */
function iTrunc($string, $length) {
	if ($length==0) {
		return $string;
	}
	if (strlen($string)<=$length) {
		return $string;
	}
	
	$pos = strrpos($string,".");
	if ($pos>=$length-4) {
		$string = substr($string,0,$length-4);
		$pos = strrpos($string,".");
	}
	if ($pos>=$length*0.4) {
		return substr($string,0,$pos+1)."...";
	}
	
	$pos = strrpos($string," ");
	if ($pos>=$length-4) {
		$string = substr($string,0,$length-4);
		$pos = strrpos($string," ");
	}
	if ($pos>=$length*0.4) {
		return substr($string,0,$pos)."...";
	}
	
	return substr($string,0,$length-4)."...";
}

function getDirSize($dirname) {
	GLOBAL $ScriptFName;
	$dir = dir($dirname);
	$fileCount = 0;
	while ($filename = $dir->read())
		if (!fileIsHidden($dirname."/".$filename)) 
			$fileCount++;
	if($_SERVER['PHP_SELF'] == $_SERVER['REQUEST_URI'].$ScriptFName) {
		if($fileCount==2) $fileCount++;
		if($fileCount==1) return $fileCount--;
		if($fileCount<1) return 0;
	}
	return $fileCount-2; // . and .. do not count
}


/***************************************************************************/
/**  LIST BUILDING                                                        **/
/***************************************************************************/

// change directory
// must be done before description file is parsed
if ($path!="") {
	$hidden = fileIsHidden(substr($path,0,-1));
	if ($hidden || !@chdir($path)) {
		$displayError[] = sprintf("%s не найдена в текущей директории.", $path);
		$path = "";
	}
} 
$dir = dir(".");

// parsing description file
$descriptions = Array();
if ($useDescriptionsFrom!="") {
	$descriptionsFile = @file($useDescriptionsFrom);
	if ($descriptionsFile!==false) {
		for ($i=0;$i<count($descriptionsFile);$i++) {
			$d = explode($separationString,$descriptionsFile[$i]);
			if (!$descriptionFilenamesCaseSensitive) {
				$d[0] = strtolower($d[0]);
			}
			$descriptions[$d[0]] = mb_convert_encoding(join($separationString, array_slice($d, 1)), 
			"HTML-ENTITIES", "auto");
		}
	}
}

// build a two dimensional array containing the files in the chosen directory and their meta data
$files = Array();
while($entry = $dir->read()) {
	// if the filename matches one of the hidden files wildcards, skip the file
	if (fileIsHidden($entry))
		continue;
		
	// if the file is a directory and if directories are forbidden, skip it
	if (!$allowSubDirs AND is_dir($entry))
		continue;
	
	$f = Array();

	$f["name"] = $entry;
	$f["isDirectory"] = is_dir($entry);
	$fDate = @filemtime($entry);
	$f["date"] = $fDate;
	$f["fullDate"] = date("r", $fDate);
	$f["shortDate"] = date("d.m.y", $fDate);
	//setlocale(LC_ALL,"German");
	//$f["shortDate"] = strftime("%x");
	$f["description"] = getDescription($entry);
	if ($f["isDirectory"]) {
		$f["type"] = "&lt;DIR&gt;";
		$f["size"] = "";
		$f["niceSize"] = "";
		
		// building the link
		if ($entry=="..") {
			// strip the last directory from the path
			$pathArr = explode("/",$path);
			$link = implode("/",array_slice($pathArr,0,count($pathArr)-2));
			
			// if there is no path set, don't add it to the link
			if ($link=="") {
				// we're already in $baseDir, so skip the file
				if ($path=="")
					continue;
				$f["link"] = "http://".$_SERVER['SERVER_NAME'].
				dirname($_SERVER['PHP_SELF']).'/';
			} else {
				$link.= "/";
				$f["link"] = "http://".$_SERVER['SERVER_NAME'].
				dirname($_SERVER['PHP_SELF'])."/path/".$link;
			}
			$f["isBack"] = true;
			if ($useBackForDirUp) {
				$f["displayName"] = "[ назад ]";
			}
		} else {
			$filesInDir = getDirSize($entry);
			if ($filesInDir==1) {
				$f["niceSize"] = "1 элемент";
			} else {
				$f["niceSize"] = sprintf("элементов: %d",$filesInDir);
			}
			
			$f["link"] = getPathLink($path.$entry);
		}
	} else {
		if (is_link($entry)) {
			$linkTarget = readlink($entry);
			$pi = pathinfo($linkTarget);
			$scriptDir = dirname($_SERVER["SCRIPT_FILENAME"]);
			if (strpos($pi["dirname"], $scriptDir)===0) {
				$f["type"] = "&lt;LINK&gt;";
				// links have no date, so take the target's date
				$f["date"] = filemtime($linkTarget);
				$f["link"] = $path.urlencode(substr($linkTarget, strlen($scriptDir)+1));
			} else {
				// link target is outside of script directory, so skip it
				continue;
			}
		} else {
			$fSize = @filesize($entry);
			$f["size"] = $fSize;
			$f["fullSize"] = number_format($fSize,0,".",",");
			$f["niceSize"] = nicesize($fSize);
			$pi = pathinfo($entry);
			$f["type"] = $pi["extension"];
			$f["link"] = myEncode(dirname($_SERVER['PHP_SELF']).'/'.$path,$entry);
		}
	}
	if (!$f["isBack"]) {
		$f["displayName"] = htmlentities(iTrunc($f["name"], $truncateLength));
	}
	$f["filetype"] = getFileType($f);
	$f["icon"] = getIcon($f["filetype"]);
	if ($f["filetype"]=="image") {
		$f["thumbnail"] = "<img src=\"".dirname($_SERVER['PHP_SELF'])."/thumbnail-".$path.$f["name"]."\" style=\"text-align:left;\" alt=\"\"/>";
	}

	$files[] = $f;
}

usort($files, "myCompare");


$pagingInEffect = $usePaging>0 && count($files)>$usePaging;
if ($usePaging>0) {
	$pageStart = $_GET["start"];
	if ($pageStart=="" || $pageStart<0 || $pageStart>count($files))
		$pageStart = 0;
	$pagingActualPage = floor($pageStart / $usePaging);
	$pagingNumberOfPages = ceil(count($files) / $usePaging);
} else {
	$pageStart = 0;
	$usePaging = count($files);
}
$pageEnd = min(count($files),$pageStart+$usePaging);

function say($ft)
{
	if($ft=="HTML") return $ft;
	if($ft=="image") return "изображение";
	if($ft=="text") return "текст";
	if($ft=="binary") return "бинарный";
	if($ft=="archive") return "архив";
	return "неизвестно";
}

/***************************************************************************/
/**  xHTML OUTPUT                                                          **/
/***************************************************************************/

$columns = count($displayColumns);

Header("Content-Type: text/html; charset=UTF-8");
echo '<'.'?xml version="1.0" encoding="UTF-8"?'.'>';?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ru"><head><meta http-equiv="Content-Type" content="application/xhtml+xml;charset=utf-8"/><meta http-equiv="Content-Style-Type" content="text/css"/><title><?echo"Содержимое ".htmlentities(dirname($_SERVER["PHP_SELF"])."/".$path);?></title><style type="text/css">body.listing{background:#fff;}table.listing{border:1px solid #444;}td.dir{color:#fff;background-color:#000;}td.dir a{color:white;}tr.heading,td.heading,td.heading a{color:#ddd;background-color:#444;}tr.f td a{color:#000;}tr.f td a:hover,a.listing:hover{background-color:#bbbbee;}tr.even{background-color:#eee;}tr.Odd{background-color: #ddd;}tr.f td{color:#444;}.white{color:white;}.listing *{font-family:Tahoma,Sans-Serif;font-size:10pt;}.listing a,a.listing{text-decoration:none;}.listing a:hover,a.listing:hover{text-decoration:underline;}.listingSmaller{font-weight:normal;font-size:8pt;}td.dir{font-weight:bold;}tr.heading,td.heading,td.heading a{font-weight:bold;}table.listing{<?if($tableWidth100Percent){echo"width:100%;";}?>}table.listing td{padding-left:10px;padding-right:10px;}table.listing td.littlepadding{padding-left:4px;padding-right:0px;}td.dir{padding-top:3px;padding-bottom:3px;}tr.heading,td.heading,td.heading a{padding-top:3px;padding-bottom:3px;}tr.f td{padding-top:2px;padding-bottom:2px;vertical-align:top;padding-left:10px;padding-right:10px;}.listing img{border:none;}.w{white-space:normal;}</style></head><body class="listing"><?if(count($displayError)>0){foreach($displayError AS$error){echo"<b style=\"color:red\">$error</b><br/>";}echo"<br/>";}?>
<table cellpadding="0" cellspacing="0" class="listing"><tr><td class="dir" colspan="<?echo count($displayColumns)?>"><?$baseDirname=$listingServer.htmlentities(dirname($_SERVER["PHP_SELF"]));$pathTolisting=explode("/",$baseDirname);echo"http://".join("/",array_slice($pathTolisting, 0, -1))."/";echo "<a href=\"".dirname($_SERVER["PHP_SELF"])."/\">".join("/",array_slice($pathTolisting,-1))."</a>";$pathArr=explode("/",$path);for($i=0; $i<count($pathArr)-1; $i++){$dirLink=getPathLink(join("/",array_slice($pathArr,0,$i+1)));echo"/<a href=\"$dirLink\">".htmlentities($pathArr[$i])."</a>";}?><br/><span class="listingSmaller"><?echo $descriptions["."];?></span></td></tr><?if($pagingInEffect){?><tr class="heading"><td class="heading" colspan="<?echo count($displayColumns)?>"><?echo getPagingHeader();?></td></tr><?}?><tr class="heading"><?foreach($displayColumns AS$column){switch($column){case"icon":?><td class="heading littlepadding">&nbsp;</td><?break;case"name":?><td class="heading" style="white-space:nowrap;"><a href="<?echo getNewSortURL("name");?>"><?echo"Имя";?></a>
<?$sort=$_GET["sort"];if($sort=="name")echo"<img src=\"".getIcon($_GET["order"])."\" width=\"5\" height=\"3\" style=\"vertical-align:middle;\" alt=\"Имя\"/>";?></td><?break;case"type":?><td class="heading" style="white-space:nowrap;"><a href="<?echo getNewSortURL("type");?>"><?echo"Тип";?></a>
<?if($sort=="type")echo"<img src=\"".getIcon($_GET["order"])."\" width=\"5\" height=\"3\" style=\"vertical-align:middle;\" alt=\"Тип\"/>";?></td><?break;case"size":?><td class="heading" align="right" style="white-space:nowrap;"><?if($sort=="size")echo"<img src=\"".getIcon($_GET["order"])."\" width=\"5\" height=\"3\" style=\"vertical-align:middle;\" alt=\"Размер\"/>";?>

<a href="<?echo getNewSortURL("size");?>"><? echo"Размер";?></a></td><?break;case"date":?><td class="heading" style="white-space:nowrap;"><a href="<?echo getNewSortURL("date");?>"><?echo"Дата";?></a>
<?if($sort=="date")echo"<img src=\"".getIcon($_GET["order"])."\" width=\"5\" height=\"3\" style=\"vertical-align:20%;\" alt=\"Дата\"/>";?></td><?break;case"description":?><td class="heading"<?if($descriptionColumnWidth>0)echo" style=\"width:".$descriptionColumnWidth."px;white-space:nowrap;\"";?>><? echo "Описание";?></td><?break;}}?></tr><?for($i=$pageStart;$i<$pageEnd;$i++){?><tr class="f <?echo($i%2==0)?"even":"Odd"?>"><?foreach($displayColumns AS$column){switch($column){case"icon":echo"<td class=\"littlepadding\">";?><a href="<?echo$files[$i]["link"];?>" title="<?echo htmlentities($files[$i]["name"]);?>"><img src="<?echo$files[$i]["icon"]?>" alt="" title="<?echo say($files[$i]["filetype"]);?>" width="16" height="16" style="vertical-align:middle;"/></a><? echo"</td>";break;case"name":echo"<td>";?><a href="<? echo $files[$i]["link"];?>" title="<? echo htmlentities($files[$i]["name"]);?>"><?echo$files[$i]["displayName"]."&nbsp;</a>";echo"</td>";break;case"type":echo"<td>";echo$files[$i]["type"];echo"</td>";break;case"size":echo"<td align=\"right\">";if($files[$i]["fullSize"]!="")echo "<span title=\"".$files[$i]["fullSize"]." байт\">";echo$files[$i]["niceSize"];if($files[$i]["fullSize"]!="")echo"</span>";echo"</td>";break;case"date":echo"<td>";echo"<span title=\"".$files[$i]["fullDate"]."\">".$files[$i]["shortDate"]."</span>";echo"</td>";break;case"description":?><td class="w" style="white-space:normal;"><?if($files[$i]["filetype"]=="image"){echo$files[$i]["thumbnail"];}?><?echo$files[$i]["description"];?></td><?break;}}?></tr><?}if($pagingInEffect){?><tr class="heading"><td class="heading" colspan="<? echo $columns?>"><? echo getPagingHeader();?></td></tr><?}?></table></body></html>