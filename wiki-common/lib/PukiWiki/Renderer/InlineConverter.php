<?php
/**
 * インライン要素変換クラス
 *
 * @package   PukiWiki\Renderer
 * @access    public
 * @author    Logue <logue@hotmail.co.jp>
 * @copyright 2012-2013 PukiWiki Advance Developers Team
 * @create    2012/12/18
 * @license   GPL v2 or (at your option) any later version
 * @version   $Id: InlineConverter.php,v 1.0.0 2013/01/29 19:54:00 Logue Exp $
 */

namespace PukiWiki\Renderer;

use PukiWiki\Utility;
use PukiWiki\Renderer\Inline\Inline;
/**
 * Converters of inline element
 */
class InlineConverter
{
	/**
	 * デフォルトの変換パターン
	 */
	private static $default_converters = array(
		'InlinePlugin',     // Inline plugins
		'InlineMathJax',    // MathJax plugin
		'EasyRef',          // Easy Ref {{param|body}}
		'Note',             // Footnotes
		'Url',              // URLs
		'InterWiki',        // URLs (interwiki definition)
		'Mailto',           // mailto: URL schemes
		'InterWikiName',    // InterWikiName
		'BracketName',      // BracketName
		'WikiName',         // WikiName
		'AutoLink',         // AutoLink(cjk,other)
		'AutoAlias',        // AutoAlias(cjk,other)
		'Glossary',         // AutoGlossary(cjk,other)
		'AutoLink_Alphabet',    // AutoLink(alphabet)
		'AutoAlias_Alphabet',   // AutoAlias(alphabet)
		'Glossary_Alphabet',    // AutoGlossary(alphabet)
		'Telephone'        // tel: URL schemes
	);
	/**
	 * 変換クラス
	 */
	private $converters = array(); // as array()
	/**
	 * 変換処理に用いる正規表現パターン
	 */
	private $pattern;
	/**
	 * 結果
	 */
	private $result;

	/**
	 * コンストラクタ
	 * @global type $autolink
	 * @global type $autoalias
	 * @global type $autoglossary
	 * @staticvar type $converters
	 * @param array $converters 使用する変換クラス名
	 * @param a $excludes 除外する変換クラス名
	 */
	public function __construct($converters = NULL, $excludes = NULL)
	{
		if (!isset($converters)) {
			$converters = self::$default_converters;
		}
		// 除外する
		if ($excludes !== NULL)
			$converters = array_diff($converters, $excludes);

		$this->converters = $patterns = array();
		$start = 1;

		foreach ($converters as $name) {
			if (!isset($name)) continue;

			$classname = 'PukiWiki\Renderer\Inline\\' . $name;
			$converter = new $classname($start);

			$pattern   = $converter->getPattern();
			if ($pattern === FALSE) continue;

			$patterns[] = '(' . $pattern . ')';
			$this->converters[$start] = $converter;
			$start += $converter->getCount();
			++$start;
		}
		$this->pattern = join('|', $patterns);
	}
	/**
	 * 関数のクローン
	 */
	public function __clone() {
		$converters = array();
		foreach ($this->converters as $key=>$converter) {
			$converters[$key] = $this->getClone($converter);
		}
		$this->converters = $converters;
	}
	/**
	 * クローンした関数を取得
	 * @staticvar type $clone_func
	 * @param object $obj オブジェクト名
	 * @return function
	 */
	public function getClone($obj) {
		static $clone_func;

		if (! isset($clone_func)) {
			$clone_func = create_function('$a', 'return clone $a;');
		}
		return $clone_func($obj);
	}
	/**
	 * 変換
	 * @param string $string
	 * @param string $page
	 * @return type
	 */
	public function convert($string, $page)
	{
		$this->page   = $page;
		$this->result = array();
		$string = preg_replace_callback('/' . $this->pattern . '/x', array($this, 'replace'), $string);

		$arr = explode("\x08", Inline::setLineRules(Utility::htmlsc($string)));
		$retval = '';
		while (! empty($arr)) {
			$retval .= str_replace('\\$', '$', array_shift($arr)) . array_shift($this->result);
		}
		return trim($retval);
	}
	/**
	 * 置き換え
	 * @param array $arr
	 * @return string
	 */
	protected function replace($arr)
	{
		$obj = self::getConverter($arr);

		$this->result[] = ($obj !== NULL && $obj->setPattern($arr, $this->page) !== FALSE) ?
			$obj->__toString() : Inline::setLineRules(Utility::htmlsc($arr[0]));

		return "\x08"; // Add a mark into latest processed part
	}
	/**
	 * オブジェクトを取得
	 * @param string $string
	 * @param string $page
	 * @return array
	 */
	public function getObjects($string, $page)
	{
		$matches = $arr = array();
		preg_match_all('/' . $this->pattern . '/x', $string, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$obj = self::getConverter($match);
			if ($obj->setPattern($match, $page) !== FALSE) {
				$arr[] = $this->getClone($obj);
				if ( !empty($obj->body) )
					$arr = array_merge($arr, $this->getObjects($obj->body, $page));
			}
		}
		return $arr;
	}
	/**
	 * 変換クラスを取得
	 * @param array $arr
	 * @return object
	 */
	private function getConverter($arr)
	{
		foreach (array_keys($this->converters) as $start) {
			if (isset($arr[$start]) && $arr[$start] === $arr[0])
				return $this->converters[$start];
		}
		return NULL;
	}
}

/* End of file InlineConverter.php */
/* Location: /vender/PukiWiki/Lib/Renderer/Inline/InlineConverter.php */