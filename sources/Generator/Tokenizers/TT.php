<?php

/**
 * @brief       TT Class
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Dev Toolbox
 * @since       4.0.0
 * @version     -storm_version-
 */


namespace Generator\Tokenizers;

use function count;
use function file_get_contents;
use function is_array;
use function realpath;
use function token_get_all;

use const T_ABSTRACT;
use const T_CLASS;
use const T_DOC_COMMENT;
use const T_EXTENDS;
use const T_FUNCTION;
use const T_IMPLEMENTS;
use const T_PRIVATE;
use const T_PROTECTED;
use const T_PUBLIC;
use const T_STATIC;
use const T_STRING;

class TT
{

    public const STATE_CLASS_HEAD = 100001;

    public const STATE_FUNCTION_HEAD = 100002;

    private $classes = [];

    private $extends = [];

    private $implements = [];

    public function getClasses()
    {
        return $this->classes;
    }

    public function getClassesImplementing($interface)
    {
        $implementers = [];
        if (isset($this->implements[$interface])) {
            foreach ($this->implements[$interface] as $name) {
                $implementers[$name] = $this->classes[$name];
            }
        }

        return $implementers;
    }

    public function getClassesExtending($class)
    {
        $extenders = [];
        if (isset($this->extends[$class])) {
            foreach ($this->extends[$class] as $name) {
                $extenders[$name] = $this->classes[$name];
            }
        }

        return $extenders;
    }

    public function parse($file)
    {
        $file = realpath($file);
        $tokens = token_get_all(file_get_contents($file));
        $classes = [];

        $si = null;
        $depth = 0;
        $mod = [];
        $doc = null;
        $state = null;
        $line = null;

        foreach ($tokens as $idx => &$token) {
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_DOC_COMMENT:
                        $doc = $token[1];
                        break;
                    case T_PUBLIC:
                    case T_PRIVATE:
                    case T_STATIC:
                    case T_ABSTRACT:
                    case T_PROTECTED:
                        $mod[] = $token[1];
                        break;
                    case T_CLASS:
                    case T_FUNCTION:
                        $state = $token[0];
                        $line = $token[2];
                        break;
                    case T_EXTENDS:
                    case T_IMPLEMENTS:
                        switch ($state) {
                            case self::STATE_CLASS_HEAD:
                            case T_EXTENDS:
                                $state = $token[0];
                                break;
                        }
                        break;
                    case T_STRING:
                        switch ($state) {
                            case T_CLASS:
                                $state = self::STATE_CLASS_HEAD;
                                $si = $token[1];
                                $classes[] = [
                                    'name'      => $token[1],
                                    'modifiers' => $mod,
                                    'line'      => $line,
                                    'doc'       => $doc,
                                ];
                                break;
                            case T_FUNCTION:
                                $state = self::STATE_FUNCTION_HEAD;
                                $clsc = count($classes);
                                if ($depth > 0 && $clsc) {
                                    $classes[$clsc - 1]['functions'][$token[1]] = [
                                        'modifiers' => $mod,
                                        'line'      => $line,
                                        'doc'       => $doc,
                                    ];
                                }
                                break;
                            case T_IMPLEMENTS:
                            case T_EXTENDS:
                                $clsc = count($classes);
                                $classes[$clsc - 1][$state == T_IMPLEMENTS ? 'implements' : 'extends'][] = $token[1];
                                break;
                        }
                        break;
                }
            } else {
                switch ($token) {
                    case '{':
                        $depth++;
                        break;
                    case '}':
                        $depth--;
                        break;
                }

                switch ($token) {
                    case '{':
                    case '}':
                    case ';':
                        $state = 0;
                        $doc = null;
                        $mod = [];
                        break;
                }
            }
        }

        foreach ($classes as $class) {
            $class['file'] = $file;
            $this->classes[$class['name']] = $class;

            if (!empty($class['implements'])) {
                foreach ($class['implements'] as $name) {
                    $this->implements[$name][] = $class['name'];
                }
            }

            if (!empty($class['extends'])) {
                foreach ($class['extends'] as $name) {
                    $this->extends[$name][] = $class['name'];
                }
            }
        }
    }
}

