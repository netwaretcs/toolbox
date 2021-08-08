<?php

/**
 * @brief      Proxy Singleton
 * @copyright  -storm_copyright-
 * @package    IPS Social Suite
 * @subpackage toolbox\Proxy
 * @since      -storm_since_version-
 * @version    -storm_version-
 */

namespace IPS\toolbox\Proxy\Generator;

use Exception;
use IPS\Data\Store;
use IPS\IPS;
use IPS\Patterns\ActiveRecord;
use IPS\Patterns\Bitwise;
use IPS\Settings;
use IPS\toolbox\Application;
use IPS\toolbox\Generator\DTClassGenerator;
use IPS\toolbox\Generator\DTFileGenerator;
use IPS\toolbox\Profiler\Debug;
use IPS\toolbox\Proxy\Helpers\HelpersAbstract;
use IPS\toolbox\Proxy\Proxyclass;
use IPS\toolbox\ReservedWords;
use IPS\toolbox\Shared\Write;
use IPS\Xml\_XMLReader;
use ParseError;
use ReflectionClass;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\AbstractTypeableTag;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\DocBlock\Tag\VarTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\Exception\InvalidArgumentException;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\PropertyGenerator;

use function array_filter;
use function array_merge;
use function array_shift;
use function class_exists;
use function constant;
use function count;
use function defined;
use function explode;
use function file_exists;
use function file_get_contents;
use function function_exists;
use function header;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_dir;
use function is_float;
use function is_int;
use function is_numeric;
use function json_decode;
use function mb_strlen;
use function mb_strpos;
use function mb_substr;
use function method_exists;
use function preg_match;
use function preg_match_all;
use function preg_replace_callback;
use function property_exists;
use function str_replace;
use function trim;
use function var_export;


if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * Proxy Class
 *
 * @mixin Proxy
 */
class _Proxy extends GeneratorAbstract
{
    use Write;

    /**
     * @brief Singleton Instances
     * @note  This needs to be declared in any child class.
     * @var static
     */
    protected static $instance;

    /**
     * helperClass stores
     *
     * @var array
     */
    protected $helperClasses = [];

    /**
     * if a ar relations.json exist, it will attempt to rebuild the model proxy class if a new field is added.
     *
     * @param $table
     */
    public static function adjustModel($table)
    {
        $apps = Application::applications();
        $relations = [[]];
        foreach ($apps as $app) {
            $dir = \IPS\ROOT_PATH . '/applications/' . $app->directory . '/data/arRelations.json';
            if (file_exists($dir)) {
                $relations[] = json_decode(file_get_contents($dir), true);
            }
        }

        $relations = array_merge(...$relations);

        if (isset($relations[$table])) {
            $class = \IPS\ROOT_PATH . '/' . $relations[$table];

            if (file_exists($class)) {
                $content = file_get_contents($class);
                static::i()->create($content);
            }
        }
    }

    /**
     * @param $content
     */
    public function create(string $content, string $originalFilePath = null)
    {
        try {
            $data = Proxyclass::i()->tokenize($content);
            $proxied = Store::i()->dt_cascade_proxy ?? [];

            if (isset($data['class'], $data['namespace'])) {
                preg_match('#\$bitOptions#', $content, $bitOptions);

                $namespace = $data['namespace'];
                $ns2 = explode('\\', $namespace);
                array_shift($ns2);
                $app = array_shift($ns2);
                $isApp = false;
                $appPath = \IPS\ROOT_PATH . '/applications/' . $app;

                $codes = Store::i()->dt_error_codes ?? [];
                $altCodes = Store::i()->dt_error_codes2 ?? [];
                $lines = preg_split("/\n|\r\n|\n/",   $content );
                $line = 1;
                foreach($lines as $cline){
                    preg_replace_callback(
                        '#[0-9]{1}([a-zA-Z]{1,})[0-9]{1,}/[a-zA-Z0-9]{1,}#msu',
                        static function ($m) use (&$codes,&$altCodes,$app,$originalFilePath,$line) {
                            if (!isset($m[1])) {
                                return;
                            }
                            $c = trim($m[0]);
                            $codes[] = $c;
                            $altCodes[$c][] = [
                                'path' => $originalFilePath,
                                'app' => $app,
                                'line' => $line
                            ];
                        },
                        trim($cline));
                    $line++;
                }

                Store::i()->dt_error_codes = $codes;
                Store::i()->dt_error_codes2 = $altCodes;
                if (isset($this->exclude[$namespace . '\\' . $data['class']])) {
                    return;
                }
                if ($app && is_dir($appPath)) {
                    $isApp = true;
                }

                $ipsClass = $data['class'];

                if (($namespace === 'IPS' && $ipsClass === '_Settings') || mb_strpos(
                        $namespace,
                        'IPS\convert'
                    ) !== false) {
                    return;
                }

                $first = mb_substr($ipsClass, 0, 1);
                if ($first === '_') {
                    $class = mb_substr($ipsClass, 1);

                    if (ReservedWords::check($class)) {
                        return;
                    }

                    $type = '';
                    $body = [];
                    $bitty = [];
                    $deepAssoc = [];
                    $classDefinition = [];
                    $classBlock = null;
                    $extraPath = $isApp ? $app : 'system';
                    $path = $this->save . '/class/' . $extraPath . '/';
                    $alt = str_replace(
                        [
                            "\\",
                            ' ',
                            ';',
                        ],
                        '_',
                        $namespace
                    );
                    $file = $alt . '_' . $class . '.php';

                    if ($data['final']) {
                        $type = 'final ';
                    }

                    if ($data['abstract']) {
                        $type = 'abstract ';
                    }

                    $new = new ClassGenerator();
                    $new->setName($class);
//                    $f = explode("\n", $content);

//                    foreach ($f as $l) {
//                        preg_match('#^use\s(.*?);$#', $l, $match);
//                        if (isset($match[1])) {
//                            // $new->addUse($match[ 1 ]);
//                        }
//                    }

                    $new->setNamespaceName($namespace);
                    $extendedClass = $namespace . '\\' . $ipsClass;

                    $new->setExtendedClass($extendedClass);
                    $this->cache->addClass($namespace . '\\' . $class);
                    $this->cache->addNamespace($namespace);
                    if ($type === 'abstract') {
                        $new->setAbstract(true);
                    }

                    if ($type === 'final') {
                        $new->setFinal(true);
                    }
                    if (isset($bitOptions[0])) {
                        $reflect = new ReflectionClass(
                            $data['namespace'] . '\\' . str_replace(
                                '_',
                                '',
                                $data['class']
                            )
                        );
                        if ($reflect->hasProperty('bitOptions')) {
                            $bits = $reflect->getProperty('bitOptions');
                            $bits->setAccessible(true);

                            if ($bits->isStatic()) {
                                $bt = $bits->getValue();
                                if (is_array($bt)) {
                                    foreach ($bt as $key => $value) {
                                        foreach ($value as $k => $v) {
                                            $tags = 'array $' . $k . ' =['.PHP_EOL;
                                            foreach($v as $keyed => $vvv){
                                                    $tags .= "'" . $keyed . "'" . ' => (bool),'.PHP_EOL;
                                            }
                                            $tags .= ']' . PHP_EOL;
                                            $deepAssoc[$k] = $tags;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $skip = false;
                    if (function_exists('str_contains')) {
                        //we are in php8 and the xml reader is broken here! and it breaks the proxyclass generator
                        if ($namespace . '\\' . $data['class'] === _XMLReader::class) {
                            $skip = true;
                        }
                    }
                    if (Proxyclass::i()->doProps && $skip === false) {
                        /* @var ActiveRecord $dbClass */
                        $dbClass = $namespace . '\\' . $class;
                        try {
                            if (property_exists(
                                    $dbClass,
                                    'databaseTable'
                                ) && class_exists($dbClass) && method_exists($dbClass, 'db')) {
                                $table = $dbClass::$databaseTable;
                                if ($table && $dbClass::db()->checkForTable($table)) {
                                    /* @var array $definitions */
                                    $definitions = $dbClass::db()->getTableDefinition($table);

                                    if (isset($definitions['columns'])) {
                                        /* @var array $columns */
                                        $columns = $definitions['columns'];
                                        $len = mb_strlen($dbClass::$databasePrefix);
                                        foreach ($columns as $key => $val) {
                                            if ($len && 0 === mb_strpos($key, $dbClass::$databasePrefix)) {
                                                $key = mb_substr($key, $len);
                                            }
                                            $key = trim($key);
                                            $this->buildHead($key, $val, $classDefinition, $deepAssoc);
                                        }
                                    }

                                    $this->buildProperty($dbClass, $classDefinition, $deepAssoc);
                                }
                            }
                        } catch (Exception $e) {
                            Debug::log($e, 'ProxyClass');
                            Debug::log($originalFilePath, 'ProxyClassFile');
                        } catch (ParseError $e) {
                            Debug::log($e, 'ParseError');
                            Debug::log($originalFilePath, 'ParseErrorFile');
                        }
                        $this->runHelperClasses($dbClass, $classDefinition, $ipsClass, $body);


                        if (empty($deepAssoc) === false) {
                            foreach ($deepAssoc as $k => $vs) {
                                unset($classDefinition[$k]);
                                try {
                                    $tags = $vs;
                                    if(\is_array($vs)){
                                        $tags = 'array $'.$k.' = [';
                                        foreach($vs as $kk => $v){
                                            $tags .= "'".$kk."'" .' => '.$v.','.PHP_EOL;
                                        }
                                        $tags .= ']'.PHP_EOL;
                                    }
                                    $propertyDocBlock = new DocBlockGenerator(
                                        'Deep-assoc-completion: '.$k, null, [new VarTag($k, $tags)]
                                    );
                                    $body[] = PropertyGenerator::fromArray(
                                        [
                                            'name'         => $k,
                                            'static'       => false,
                                            'docblock'     => $propertyDocBlock,
                                            'visibility'   => 'public',
                                            'defaultValue' => []
                                        ]
                                    );
                                } catch (InvalidArgumentException $e) {
                                }
                            }
                        }
                        $classBlock = $this->buildClassDoc($classDefinition);

                    }
                    if (empty($body) === false) {
                        $newMethods = [];
                        foreach ($body as $method) {
                            if ($method instanceof MethodGenerator) {
                                $newMethods[$method->getName()] = $method;
                            }

                            if ($method instanceof PropertyGenerator) {
                                $new->addPropertyFromGenerator($method);
                            }
                        }

                        if (count($newMethods)) {
                            $new->addMethods($newMethods);
                        }
                    }

                    if ($classBlock instanceof DocBlockGenerator) {
                        $new->setDocBlock($classBlock);
                    }

                    $proxyFile = new DTFileGenerator();
                    $proxyFile->isProxy = true;
                    $proxyFile->setClass($new);
                    $proxyFile->setFilename($path . '/' . $file);
                    $proxyFile->write();
                }
            }
        } catch (Exception $e) {
            // throw $e;
            Debug::add('Proxy Create', $e);
        }
    }


    /**
     * builds the docblock for proxy props
     *
     * @param $name
     * @param $def
     * @param $classDefinition
     *
     * @return void
     */
    protected function buildHead($name, $def, &$classDefinition,&$deepAssoc)
    {
        $ints = [
            'TINYINT',
            'SMALLINT',
            'MEDIUMINT',
            'INT',
            'BIGINT',
            'DECIMAL',
            'FLOAT',
            'BIT',
        ];

        $comment = null;

        if ($def['comment']) {
            $comment = $def['comment'];
        }

        $type = null;

        if (in_array($def['type'], $ints, true)) {
            $type = 'int';
        } else {
            $type = 'string';
        }

        if ($def['allow_null']) {
            $type .= '|null';
        }

        $classDefinition[$name] = ['pt' => 'p', 'prop' => $name, 'type' => $type, 'comment' => $comment];
        $check = str_replace('|null','',$type);

        $deepAssoc['_data'][$name] = '('.$check.')';
    }

    /**
     * builds props out of the setters and getters
     *
     * @param $class
     * @param $classDefinition
     */
    public function buildProperty($class, &$classDefinition,&$deepAssoc)
    {
        try {
            $data = [];
            $reflect = new ReflectionClass($class);
            $methods = $reflect->getMethods();
            if (empty($methods) !== true) {
                foreach ($methods as $method) {
                    $type = trim(mb_substr($method->name, 0, 4));
                    $key = trim(mb_substr($method->name, 4, mb_strlen($method->name)));
                    if ($type === 'set_' || $type === 'get_') {
                        $pt = null;
                        if (!isset($data[$key]) && !isset($classDefinition[$key])) {
                            if ($type === 'set_') {
                                $pt = 'w';
                            }

                            if ($type === 'get_') {
                                $pt = 'r';
                            }
                        } else {
                            $pt = 'p';
                        }

                        $comment = null;
                        $return = $type === 'set_' ? 'void' : 'string';
                        if ($method->hasReturnType()) {
                            $return = (string)$method->getReturnType();
                        } else {
                            $doc = $method->getDocComment();
                            preg_match_all('#@return([^\n]+)?#', $doc, $match);

                            if (isset($match[1][0])) {
                                $match = array_filter(explode(' ', str_replace(["\t"],['    '],$match[1][0])));
                                $mtype = trim(array_shift($match));
                                if (is_array($match) && count($match)) {
                                    $comment = implode(' ', $match);
                                }

                                $return = $mtype;
                            }

                        }

                        if (isset($data[$key])) {
                            if ($return === 'void' || $data[$key]['type'] !== 'void') {
                                $return = $data[$key]['type'];
                            }
                        }
                        if(mb_substr($return,0,1) === '?') {
                            $return = str_replace(['?'], ['\\'], $return);
                            $return .= '|null';
                        }
                        $data[$key] = [
                            'prop'    => trim($key),
                            'pt'      => $pt,
                            'type'    => $return,
                            'comment' => $comment,
                        ];
                    }
                }
                foreach ($data as $prop => $value) {
                    if(isset($classDefinition[$prop])){
                        $tt = $value['type'];
                        $check = str_replace('|null','',$tt);
                        if(class_exists($check)){
                            $check = 'new '.$check;
                        }
                        else{
                            $check = '('.$check.')';
                        }
                        $deepAssoc['_data'][$prop] = $check;
                    }
                    $classDefinition[$prop] = $value;
                }
            }
        } catch (Exception $e) {
           Debug::add( 'buildProperty', $e );
        }
    }

    /**
     * if there is a helper class, will run it here.
     *
     * @param $class
     * @param $classDoc
     * @param $classExtends
     * @param $body
     */
    protected function runHelperClasses($class, &$classDoc, &$classExtends, &$body)
    {
        $helpers = [];

        try {
            if (empty($this->helperClasses) === true) {
                /* @var Application $app */
                foreach (Application::appsWithExtension('toolbox', 'ProxyHelpers') as $app) {
                    $extensions = $app->extensions('toolbox', 'ProxyHelpers', true);
                    foreach ($extensions as $extension) {
                        if (method_exists($extension, 'map')) {
                            $extension->map($helpers);
                        }
                    }
                }
                $this->helperClasses = $helpers;
            }
            if (isset($this->helperClasses[$class]) && is_array($this->helperClasses[$class])) {
                /* @var HelpersAbstract $helperClass */
                foreach ($this->helperClasses[$class] as $helper) {
                    $helperClass = new $helper();
                    $helperClass->process($class, $classDoc, $classExtends, $body);
                }
            }
        } catch (Exception $e) {
            Debug::add('helpers', $e);
        }
    }

    /**
     * @param array $properties
     *
     * @return mixed
     */
    public function buildClassDoc(array $properties)
    {
        $done = [];
        $block = [];
        foreach ($properties as $key => $property) {
            try {
                if (!isset($done[$property['prop']])) {
                    if (class_exists($property['type'])) {
                        $property['type'] = '\\' . $property['type'];
                    }
                    $done[$property['prop']] = 1;
                    $comment = $property['comment'] ?? '';
                    $content = $property['type'] . ' $' . $property['prop'] . ' ' . $comment;
                    $pt = 'property';
                    switch ($property['pt']) {
                        case 'p':
                            $pt = 'property';
                            break;
                        case 'w':
                            $pt = 'property-write';
                            break;
                        case 'r':
                            $pt = 'property-read';
                    }
                    $block[] = new GenericTag($pt, $content);
                }
            } catch (Exception $e) {
            }
        }

        $docBlock = new DocBlockGenerator();
        $docBlock->setTags($block);

        return $docBlock;
    }

    /**
     * takes the settings from store and creates proxy props for them, so they will autocomplete
     */
    public function generateSettings()
    {
        try {
            $classDoc = [];

            /**
             * @var array $load
             */
            $load = Store::i()->settings;
            foreach ($load as $key => $val) {
                if (is_array(Settings::i()->{$key})) {
                    $type = 'array';
                } elseif (is_int(Settings::i()->{$key})) {
                    $type = 'int';
                } elseif (is_float(Settings::i()->{$key})) {
                    $type = 'float';
                } elseif (is_bool(Settings::i()->{$key})) {
                    $type = 'bool';
                } else {
                    $type = 'string';
                }

                $classDoc[] = ['pt' => 'p', 'prop' => $key, 'type' => $type];
            }

            $header = $this->buildClassDoc($classDoc);
            $class = new DTClassGenerator();
            $class->setNamespaceName('IPS');
            $class->setName('Settings');
            $class->setExtendedClass('IPS\_Settings');
            $class->setDocBlock($header);
            $file = new DTFileGenerator();
            $file->setClass($class);
            $file->setFilename($this->save . '/IPS_Settings.php');
            $file->write();

//            if (method_exists(\IPS\Theme::i(), 'get_css_vars')) {
//                $output = array();
//
//                foreach (\IPS\Theme::i()->settings as $key => $value) {
//                    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
//                        $value = str_replace('#', '', $value);
//                        $rgb = array();
//
//                        if (\strlen($value) === 3) {
//                            $rgb[] = hexdec(\substr($value, 0, 1) . \substr($value, 0, 1));
//                            $rgb[] = hexdec(\substr($value, 1, 1) . \substr($value, 1, 1));
//                            $rgb[] = hexdec(\substr($value, 2, 1) . \substr($value, 2, 1));
//                        } else {
//                            $rgb[] = hexdec(\substr($value, 0, 2));
//                            $rgb[] = hexdec(\substr($value, 2, 2));
//                            $rgb[] = hexdec(\substr($value, 4, 2));
//                        }
//
//                        $output[] = "\t--theme-" . $key . ": rgb(" . implode(', ', $rgb) . ");";
//                    }
//                }
//                $css = implode("\n", $output);
//                $body = <<<eof
//:root {
//{$css}
//}
//eof;
//                \file_put_contents($this->save . '/IPSVars.css', $body);
//                $file2 = new DTFileGenerator();
//                $file2->setBody($body);
//                $file2->setFilename($this->save . '/IPSVars.css');
//                $file2->write();
//            }

        } catch (Exception $e) {
        }
    }

    /**
     * builds the constants out since they are a mapped array in init.php
     */
    public function buildConstants()
    {
        if (Proxyclass::i()->doConstants) {
            $load = IPS::defaultConstants();
            $extra = "\n";
            foreach ($load as $key => $val) {
                $vals = null;
                if (defined($key)) {
                    $vals = constant($key);
                }

                if (is_bool($val)) {
                    $vals = (int)$vals;
                    $val = $vals === 1 ? 'true' : 'false';
                } elseif (is_array($val)) {
                    $val = var_export($val, true);
                } elseif (!is_numeric($val)) {
                    $val = "'" . $val . "'";
                }

                $extra .= 'define( "\\IPS\\' . $key . '",' . $val . ");\n";
                $extra .= 'define( "IPS\\' . $key . '",' . $val . ");\n";
            }
            $extra .= <<<eof
/**
 * @param string \$text
 * @return string
 */            
function mb_ucfirst(\$text)
{

}
eof;

            $file = new DTFileGenerator();
            $file->setBody($extra);
            $this->_writeFile('IPS_Constants.php', $file->generate(), $this->save, false);
        }
    }


}
