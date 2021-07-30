<?php

// To customize look & feel of generated index page
class UserSettings
{

	public $HeaderName = 'HEADER';
	public $ReadmeName = 'README';
	public $Exclude_Patterns = ['.',
		'..',
		'.??*',
		'*~',
		'*#',
		'HEADER*',
		'README*',
		'RCS',
		'CVS',
		'*,v',
		'*,t',
		'*.lsz',
		];
	public $Time_Format = ' d-M-Y H:i ';
	public $IconPath = '/_autoindex/icons';
	public $nameWidth = 80;
	public $nameFormat;

	public function __construct()
	{
		$this->nameFormat = '%-' . ($this->nameWidth + 4) . '.' . ($this->nameWidth + 4) . 's';
	}

}

class IMG_Mapping
{

	public $suffixes;
	public $imageName;
	public $desc;
	public $alt;

	public function __construct($arrSuffix, $imgName, $altName, $descr='')
	{
		$this->suffixes = $arrSuffix;
		$this->imageName = $imgName;
		$this->alt = $altName;
		$this->desc = $descr;
	}

	public function found($ext)
	{
		return in_array($ext, $this->suffixes);
	}

}

class AllImgs
{

	public $mapping, $default_img, $dir_img;

	public function __construct()
	{
		$this->mapping = [
			new IMG_Mapping(['gif', 'png', 'jpg', 'jpeg', 'tif', 'tiff', 'bmp', 'svg', 'raw'],
					'image.svg', '[IMG]'),
			new IMG_Mapping(['html', 'htm', 'shtml', 'php', 'phtml', 'css', 'js'],
					'file.svg', '[HTM]'),
			new IMG_Mapping(['txt', 'md5', 'c', 'cpp', 'cc', 'h', 'sh'],
					'file-text.svg', '[TXT]'),
			new IMG_Mapping(['gz', 'tgz', 'zip', 'Z', 'z'],
					'file.svg', '[CMP]'),
			new IMG_Mapping(['bin', 'exe'],
					'file.svg', '[BIN]'),
			new IMG_Mapping(['mpg', 'avi', 'mpeg', 'ram', 'wmv'],
					'video.svg', '[VID]'),
			new IMG_Mapping(['mp3', 'mp2', 'ogg', 'wav', 'wma', 'aac', 'mp4', 'rm'],
					'music.svg', '[SND]'),
		];

		$this->default_img = new IMG_Mapping(null, 'file.svg', 'unknown', '');
		$this->dir_img = new IMG_Mapping(null, 'folder.svg', 'directory', '');
		$this->parent_img = new IMG_Mapping(null, 'corner-left-up.svg', 'up', '');
	}

	public function findImgMapping($file)
	{
		$found = null;
		$pos = strrpos($file, '.');
		if ($pos !== false) {
			$ext = substr($file, $pos + 1);
			if ($ext !== false) {
				$l = count($this->mapping);
				for ($i = 0; $i < $l; ++$i) {
					if (in_array($ext, $this->mapping[$i]->suffixes)) {
						$found = $this->mapping[$i];
						break;
					}
				}
			}
		}
		if (!isset($found))
			$found = $this->default_img;
		return $found;
	}

}

// END of customization section



class FileStat
{

	public $name;
	public $size;
	public $mtime;
	public $isdir;
	public $img;

	public function __construct($filename)
	{
		$this->name = $filename;
	}

}

function shouldExclude($file, &$excludes)
{
	$ex = reset($excludes);
	foreach ($excludes as $ex) {
		if (fnmatch($ex, $file))
			return true;
	}
	return false;
}

function readDirList($path, &$excludes, &$map)
{
	$handle = opendir($path);
	if ($handle === false) {
		return null;
	}
	clearstatcache();
	$list = [];
	if (isset($_SERVER['LS_AI_INDEX_IGNORE'])) {
		$ignore = explode(' ', $_SERVER['LS_AI_INDEX_IGNORE']);
		$excludes = array_merge($ignore, $excludes);
	}
	while (false !== ($file = readdir($handle))) {
		if (shouldExclude($file, $excludes)) {
			continue;
		}
		$fileStat = new FileStat($file);
		$s = stat("$path$file");
		$fileStat->mtime = $s[9];
		$fileStat->isdir = ($s[2] & 040000) ? '/' : '';

		// get image mapping
		if ($fileStat->isdir) {
			$fileStat->size = -1;
			$fileStat->img = $map->dir_img;
		} else {
			if ($s[12] > 0)
				$fileStat->size = 512 * $s[12];
			else
				$fileStat->size = $s[7];
			$fileStat->img = $map->findImgMapping($file);
		}

		$list[] = $fileStat;
	}
	closedir($handle);
	return $list;
}

function printOneEntry($base, $name, $fileStat, $setting)
{
        $encoded = str_replace(['%2F', '%26amp%3B'], ['/', '%26'],
                        rawurlencode($base . $fileStat->name)); 
        if (isset($_SERVER['LS_FI_OFF']) && $_SERVER['LS_FI_OFF']) {
                $buf = '<tr>' . '<a href="' . $encoded .
                                $fileStat->isdir . '">' . sprintf($setting->nameFormat, htmlspecialchars($name, ENT_SUBSTITUTE) . "</a></tr>\n");
        } else {
                $no_sort = ($name == 'Parent Directory') ? ' data-sort-method="none"' : '';
                $buf = "<tr${no_sort}><td>" . '<a href="' . $encoded . $fileSata->isdir . '">' . '<img class="icon" src="' . $setting->IconPath . '/' . $fileStat->img->imageName .
                                '" alt="' . $fileStat->img->alt . '">';
                if (strlen($name) > $setting->nameWidth) {
                        $name = substr($name, 0, $setting->nameWidth - 3) . '...';
                }
                $buf .= sprintf($setting->nameFormat, htmlspecialchars($name, ENT_SUBSTITUTE) . "</a></td>");
                if ($fileStat->mtime != -1)
                        $buf .= '<td>' . date($setting->Time_Format, $fileStat->mtime) . '</td>';
                else
                        $buf .= '<td>                   </td>';
                if ($fileStat->size != -1)
//                        $buf .= sprintf("<td>%7ldk  </td>", ( $fileStat->size + 1023 ) / 1024);
                        $buf .= sprintf("<td data-sort='1357656438'>%7ldk  </td>" , $fileStat->size);
                else
                        $buf .= '<td>       -  </td>';
                $buf .= '<td>     </td>' . '</tr>' . $fileStat->img->desc;
                $buf .= "\n";
        }
        echo $buf;
}


function printIncludes($path, $name)
{
	$testNames = ["$name.html", "$name.htm", $name];
	foreach ($testNames as $n) {
		$filename = $path . $n;

		if (file_exists($filename) && !is_link($filename)) {
			$content = file_get_contents($filename);
			if ($n == $name) {
				echo "<table>\n";
				echo $content;
				echo "</table>\n";
			} else { // html format
				echo $content;
			}
			break;
		}
	}
}

function printFileList($list, $base_uri, $setting)
{
	foreach ($list as $fileStat) {
		if ($fileStat->isdir) {
			printOneEntry($base_uri, $fileStat->name, $fileStat, $setting);
		}
	}

	foreach ($list as $fileStat) {
		if (!$fileStat->isdir) {
			printOneEntry($base_uri, $fileStat->name, $fileStat, $setting);
		}
	}
}

function cmpNA($a, $b)
{
	return strcmp($a->name, $b->name);
}

function cmpND($a, $b)
{
	return -cmpNA($a, $b);
}

function cmpSA($a, $b)
{
	$ret = $a->size - $b->size;
	if ($ret)
		return $ret;
	return cmpNA($a, $b);
}

function cmpSD($a, $b)
{
	return -cmpSA($a, $b);
}

function cmpMA($a, $b)
{
	return $a->mtime - $b->mtime;
}

function cmpMD($a, $b)
{
	return -cmpMA($a, $b);
}

function cmpDA($a, $b)
{
	$ret = strcmp($a->img->desc, $b->img->desc);
	if ($ret)
		return $ret;
	return strcmp($a->name, $b->name);
}

function cmpDD($a, $b)
{
	return -cmpDA($a, $b);
}

ini_set('open_basedir', $_SERVER['DOCUMENT_ROOT']);

$pos = strpos($_SERVER['REQUEST_URI'], '?');
if ($pos === false) {
	$uri = $_SERVER['REQUEST_URI'];
} else {
	$uri = substr($_SERVER['REQUEST_URI'], 0, $pos);
}

$uri = urldecode($uri);
$path = $_SERVER['LS_AI_PATH'];

$mime_type = $_SERVER['LS_AI_MIME_TYPE'];
if ($mime_type) {
	header("Content-Type: $mime_type");
}

if (!$path) {
	echo "[ERROR] Auto Index script can not be accessed directly!";
	exit;
}


$uri = htmlentities($uri, ENT_COMPAT, 'UTF-8');

$setting = new UserSettings();
$map = new AllImgs();

$list = readDirList($path, $setting->Exclude_Patterns, $map);
if ($list === null) {
	http_response_code(403);
	echo '<h1>403 Access Denied</h1>';
	exit;
}

$using_fancyIndex = !empty($_SERVER['LS_FI_OFF']);

echo "<!DOCTYPE html>
<html>
  <head>
  <meta http-equiv=\"Content-type\" content=\"text/html; charset=UTF-8\" />
  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />
  <link rel=\"stylesheet\" href=\"/_autoindex/assets/css/autoindex.css\" />
  <script src=\"/_autoindex/assets/js/tablesort.js\"></script>
  <script src=\"/_autoindex/assets/js/tablesort.number.js\"></script>
  <script src=\"/_autoindex/assets/js/tablesort.filesize.js\"></script>
  <script src=\"/_autoindex/assets/js/tablesort.date.js\"></script>
  <title>Index of ", $uri, "</title></head>
  <body>
    <div class=\"content\">
    <h1>Index of ", $uri, "</h1>";

if (isset($setting->HeaderName)) {
        printIncludes($path, $setting->HeaderName);
}

if ($using_fancyIndex) {
        $header = "<table>\n";
} else {
        $header = "<div id=\"table-list\"><table id=\"table-content\"><thead class=\"t-header\"><tr><th><a href=\"javascript:void(0)\" class=\"name\">";
        $header .= sprintf($setting->nameFormat, 'Name</a></th>');
        $header .= " <th><a href=\"javascript:void(0)\">Last modified</a></th>         <th><a href=\"javascript:void(0)\">Size</a></th>  <th><a href=\"javascript:void(0)\">Descri>
}
echo $header;

if ($uri != '/') {
        $fileStat = new FileStat('');
        $fileStat->mtime = filemtime($path);
        $fileStat->img = $map->parent_img;
        $fileStat->size = -1;
        $base = substr($uri, 0, strlen($uri) - 1);
        $off = strrpos($base, '/');
        if ($off !== false) {
                $base = substr($base, 0, $off + 1);
                printOneEntry($base, 'Parent Directory', $fileStat, $setting);
        }
}


printFileList($list, $uri, $setting);

if ($using_fancyIndex) {
	echo "</ul>\n";
} else {
	echo "</table></div>";
}

if (isset($setting->ReadmeName)) {
	printIncludes($path, $setting->ReadmeName);
}

echo '<address>by LiteSpeed Web Server at ' . $_SERVER['SERVER_NAME'] . ' Port ' . $_SERVER['SERVER_PORT'] . "</address>
</div>
<script>new Tablesort(document.getElementById('table-content'));</script>
</body>
</html>";
