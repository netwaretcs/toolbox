<?php

/**
 * @brief       Hook Class
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Dev Toolbox
 * @since       5.0.11
 * @version     -storm_version-
 */


namespace IPS\toolbox\Code\Parser;

use Symfony\Component\Finder\SplFileInfo;

class Hook
{
    protected SplFileInfo $file;
    protected array $info;
    public function __construct(SplFileInfo $file, array $info){
        $this->file = $file;
        $this->info = $info;
    }

    public function isThemHook(){
        return $this->info['type'] === 'S';
    }

    public function isClassHook(){
        return $this->info['type'] === 'C';
    }

}