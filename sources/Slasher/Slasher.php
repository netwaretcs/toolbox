<?php

/**
 * @brief       Slasher Class
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Dev Toolbox
 * @since       4.0.0
 * @version     -storm_version-
 */


namespace IPS\toolbox;

use IPS\Patterns\Singleton;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

use function _p;
use function array_combine;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function get_defined_constants;
use function get_defined_functions;
use function implode;
use function is_array;
use function ksort;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function str_replace;
use function token_get_all;
use function trim;

use const false;
use const true;
use const PHP_EOL;
use const T_CONST;
use const T_DOUBLE_COLON;
use const T_FUNCTION;
use const T_NAMESPACE;
use const T_NS_SEPARATOR;
use const T_OBJECT_OPERATOR;
use const T_STRING;
use const T_STRING_VARNAME;
use const T_VARIABLE;


Application::loadAutoLoader();

class _Slasher extends Singleton
{

    protected static $instance;

    public $defaultDirectories = [
        '3rdparty',
        'vendor',
        'Vendor',
        'hooks',
        'interface',
    ];

    public $defaultFiles = [
        'adminer.php'
    ];

    protected $functions = [];

    protected $constants = [];

    protected $foo = [];

    public function start(\IPS\Application $application, array $skippedFiles = [], array $skippedDirectories = [])
    {
        $this->getConstants();
        $this->getFunctions();
        $sd = $skippedDirectories;
        $sf = $skippedFiles;
        $dir = \IPS\Application::getRootPath() . '/applications/' . $application->directory . '/';
        $finder = new Finder();

        $finder->in($dir);

        foreach ($sd as $dirs) {
            $finder->exclude($dirs);
        }

        foreach ($sf as $file) {
            $finder->notName($file);
        }

        $filter = static function (SplFileInfo $file) {
            return !($file->getExtension() !== 'php');
        };

        $finder->filter($filter)->files();

        foreach ($finder as $file) {
            $this->parse($file->getContents(), $file->getRealPath());
        }
    }

    public function getConstants()
    {
        $constants = get_defined_constants() ;
        $userDefined = get_defined_constants(true);
        $constants = array_combine(array_keys($constants), array_values($constants));

        if (isset($userDefined['user'])) {
            foreach ($userDefined['user'] as $key => $value) {
                unset($constants[$key]);
            }
        }

        $this->constants = $constants;
    }

    public function getFunctions()
    {
        /** @noinspection PotentialMalwareInspection */
        $functions = get_defined_functions();
        $funcs = array_map('strtolower', $functions['internal']);
        $funcs = array_combine(array_values($funcs), $funcs);
        $this->functions = $funcs;
    }

    public function parse($content, $filename)
    {
        $f = [];
        $content = preg_replace_callback('#(\bFALSE|TRUE|NULL\b)#', static function($m) use(&$f) {
            return \mb_strtolower($m[1]);
        },$content);
        $source = explode("\n", $content);
        $tokens = token_get_all($content);
        $previousToken = null;
        $uses = [];
        $constants = [];
        foreach ($tokens as $key => $token) {
            if (!is_array($token)) {
                $tempToken = $token;
                $token = [0 => 0, 1 => $tempToken, 2 => 0];
            }

            $line = $token[2];
            $t = trim($token[1]);
            $token[1] = $t;
            $previousToken = $tokens[$key - 1] ?? null;
            if ($previousToken !== null && $foo = $this->isFunction($token, $previousToken) === true) {
                $uses[$t] = $t;
                if (isset($source[$line - 1])) {
                    $source[$line - 1] = preg_replace("#\\\\" . $t . '#u', $t, $source[$line - 1]);
                }
            } elseif ($previousToken !== null && $this->isConstant($token, $previousToken)) {
                $find = $token[1];
                $constants[$find] = $find;
            }
        }
        ksort($uses);
        ksort($constants);
        $this->finalize($uses, $constants, $content);

        if (empty($uses) !== true || empty($constants) !== true) {
            $this->write($uses, $constants, $source, $filename);
        }
    }

    protected function isFunction($tokenData, $previousToken): bool
    {
        $token = trim($tokenData[1]);
        $previous = $previousToken[0];

        return !empty($this->functions[$token]) && $previous !== T_NAMESPACE && $previous !== T_OBJECT_OPERATOR && $previous !== T_DOUBLE_COLON && $previous !== T_NS_SEPARATOR && $previous !== T_CONST && $previous !== T_STRING_VARNAME && $previous !== T_VARIABLE && $previous !== T_FUNCTION && $previous !== T_STRING;
    }

    protected function isConstant($token, $previousToken)
    {
        $find = $token[1];
        $previous = $previousToken[0];
        //
        //        if ( isset( $previousToken[ 1 ] ) && $previousToken[ 1 ] === 'IPS' ) {
        //            $find = 'IPS\\' . $find;
        //        }

        return (array_key_exists($find,$this->constants) || array_key_exists(\mb_strtoupper($find), $this->constants))  && $previous !== T_NAMESPACE && $previous !== T_OBJECT_OPERATOR && $previous !== T_DOUBLE_COLON && $previous !== T_NS_SEPARATOR && $previous !== T_CONST && $previous !== T_STRING_VARNAME && $previous !== T_VARIABLE && $previous !== T_FUNCTION;
    }

    protected function finalize(&$uses, &$constants, $content)
    {
        preg_match_all('#use(.*?)function([^;]+)#', $content, $matches);

        if (isset($matches[2])) {
            foreach ($matches[2] as $func) {
                $func = trim($func);
                if (function_exists($func) && isset($uses[$func])) {
                    unset($uses[$func]);
                }
            }
        }

        preg_match_all('#use(.*?)const([^;]+)#', $content, $matches);

        if (isset($matches[2])) {
            foreach ($matches[2] as $const) {
                $const = \trim($const);
                if (isset($constants[$const])) {
                    unset($constants[$const]);
                }
            }
        }
    }

    protected function write($uses, $constants, $source, $filename)
    {
        $content = implode("\n", $source);
        $add = [];
        $lines = false;

        foreach ($source as $key => $line) {
            preg_match('#use([^;]+);#', $line, $match);
            if (empty($match) !== true) {
                $lines = $key;
            }

            preg_match('#^(abstract|class|trait)(.*)#', $line, $match);
            if (empty($match) !== true) {
                break;
            }
        }

        if (empty($uses) !== true) {
            foreach ($uses as $use) {
                $add[] = 'use function ' . $use . ';';
            }
        }

        if (empty($constants) !== true) {
            foreach ($constants as $constant) {
                $add[] = 'use const ' . $constant . ';';
            }
        }
        if (empty($add) !== true) {
            $toUse = PHP_EOL . implode(PHP_EOL, $add);

            if ($lines !== false) {
                $newContent = [];
                foreach ($source as $key => $value) {
                    $newContent[] = $value;
                    if ($key === $lines) {
                        $newContent[] = $toUse;
                        $newContent[] = '';
                    }
                }

                $content = implode("\n", $newContent);
            } else {
                $toUse = "\n" . $toUse;
                $content = preg_replace('/namespace(.+?)([^\n]+)/', 'namespace $2' . $toUse, $content, 1);
            }
        }
        $content = $this->applyFinalFixes($content);
        file_put_contents($filename, $content);
    }

    public function test($file)
    {
        $this->getConstants();
        $this->getFunctions();

        $this->parse(file_get_contents($file), $file);
    }

    protected function applyFinalFixes($source): string
    {
        $source = str_replace(['function \\', 'const \\', "::\\", "$\\"], [
            'function ',
            'const ',
            '::',
            '$',
        ], $source);

        return (string)$source;
    }
}
