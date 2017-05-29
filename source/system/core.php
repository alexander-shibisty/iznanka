<?php
define("ROOT_DIR", getcwd());

class view {
	private $_path = ROOT_DIR . '/system/tpl/';
	private $_cachepath;
	private $_template;
	private $rawprint = false;
	private $_alive;
	private $_var = array();

	public function __construct($raw = false) {
		if ($raw) $this->rawprint = true;
	}

	protected $_dict = array(
		"include file='(.*)'" => '$this->_include("$1")',
		"anticache file='(.*)'" => '$this->_anticache("$1")',
		"if \((.*)\)" => 'if ($1){',
		"else" => '}else{',
		"end" => '}',
		"#" => ' echo ',
		"@" => '$this->',
		'for \((.*)=(.*) to (.*)\)' => 'for ($1=$2; $1 < $3; ++$1){',
		"switch \((.*)\)" => 'switch ($1){',
		"case \((.*)\)" => 'case $1:',
		"break" => 'break;'
	);
	public function set($name, $value) {
		$this->_var[$name] = $value;
	}

	private	function _include($template) {
		$content = file_get_contents($this->_path . $template);
		if ($this->rawprint)
			echo $this->_render($content);
		else
			eval('?>' . $this->_render($content));
	}

	private	function _anticache($filename) {
		try {
			$md5 = filemtime(ROOT_DIR . $filename);
			echo $filename . '?' . $md5;
		}
		catch (Exception $e) {
			echo $filename;
		}
	}

	private	function _cachedinclude($template) {
		$content = file_get_contents($this->_path . $template);
		eval('?>' . $this->_render($content));
	}

	public function __get($name) {
		if (isset($this->_var[$name]))
			return $this->_var[$name];
		return '';
	}

	public function compile($path) {
		ob_start();
		$content = file_get_contents($path);
		if ($this->rawprint)
			$this->_render($content);
		else
			eval('?>' . $this->_render($content));
		return ob_get_clean();
	}

	private	function _compile($content) {
		ob_start();
		eval('?>' . $this->_render($content));
		return ob_get_clean();
	}
	private function _render($content) {
		$patterns = array_keys($this->_dict);
		$values = array_values($this->_dict);
		foreach($patterns as & $pattern)
			$pattern = str_replace('"', '\"', '/' . $pattern . '/');
		preg_match_all("/{{(.[^}]*)}}/", $content, $blocks);
		foreach($blocks[0] as $block)
			$content = str_replace($block, preg_replace($patterns, $values, $block) , $content);
		$content = preg_replace('/{{\$this-\>(.[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)}}/', '<?php echo $this->$1 ?>', $content);
		$content = preg_replace('/{{(.[^}]*)}}/', "<?php $1 ?>", $content);
    return $content;
	}

	public function display($template, $cache = false) {
		$this->_template = $this->_path . $template;
		if (!file_exists($this->_template)) die('Шаблона ' . $this->_template . ' не существует!');
		$content = $this->_compile(file_get_contents($this->_template));
		echo $content;
	}
}

$config = include('config.php');
$db = null;

function connectdb(){
	global $db, $config;
	$db = new mysqli("localhost", $config['dbusername'], $config['dbpass'], $config['dbname']);
	$db->set_charset("utf8");
	if ($db->connect_errno)
	{
		printf("Не удалось подключиться: %s\n", $db->connect_error);
		exit();
	}
}
function iznanka() {
	session_start();
	global $db, $config;
	$view = new view(false);
	$view->set('template', ' ');
	$view->set('path', explode("/", $_SERVER["REQUEST_URI"]));
	$view->set('uri', $_SERVER['REQUEST_URI']);
	foreach(glob(ROOT_DIR . '/system/includes/' . "*.php") as $php_file)
		include ($php_file);
	if ($view->uri == '/' && $view->template == ' ') {
		$view->set('template', $config['deftemplate']);
		$view->set('title', $config['title']);
	}
	elseif ($view->template == ' ') {
		header("HTTP/1.0 404 Not Found");
		$view->set('template', '404.tpl');
		$view->set('title', '404');
		$view->set('error', true);
	}
	header('X-Powered-By: Iznanka '.$config['ver']);
	$view->display('index.tpl');
	if ($config['usedb'] && $db)
		$db->close();
}
function runModule($module, $view, $db){
  include ROOT_DIR . '/system/modules/' . $module . '.php';
}