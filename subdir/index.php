<?
/***************************************************************************

snif 1.2.1
"snif is not an index file"
"simple and nice index file"
(c) Kai Blankenhorn
www.bitfolge.de
kaib@bitfolge.de


THIS IS THE SUBDIR/INDEX.PHP FILE.


This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details: <http://www.gnu.org/licenses/gpl.txt>

****************************************************************************


Changelog:

see the real snif index file


****************************************************************************
**  THIS FILE IS SUPPOSED TO SHOW A DIRECTORY LISTING??                   **
****************************************************************************

No, this file is used as a forwarder to the main snif file. See the example
in the real snif index file.



****************************************************************************/


$dir = dirname($_SERVER["PHP_SELF"]);
$pathArr = explode("/",$dir);

$path = $_GET["path"];
$path = str_replace("../","",$path);
if ($path[0]=='/')
	$path = substr($path,1);

$subDirectory = $pathArr[count($pathArr)-1]."/".$path;

Header("Location: ../?path=".$subDirectory);
?>