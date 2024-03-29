<?php

/**
 * @brief       SequentialStream Class
 * @author      -storm_author-
 * @copyright   -storm_copyright-
 * @package     IPS Social Suite
 * @subpackage  Babble
 * @since       3.2.0
 * @version     -storm_version-
 */


namespace IPS\toolbox\Elephant\Stream;

class _SequentialStream
{
    /**
     * @var string
     */
    protected $data = null;

    /**
     * Constructor.
     *
     * @param string $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Read a fixed size data.
     *
     * @param int $len
     * @return string
     */
    public function read($len = 1)
    {
        if (!$this->isEof()) {
            $result = \substr($this->data, 0, $len);
            $this->data = \substr($this->data, $len);

            return $result;
        }
    }

    /**
     * Read data up to delimiter.
     *
     * @param string $delimiter
     * @param array $noskips
     * @return string
     */
    public function readUntil($delimiter = ',', $noskips = [])
    {
        if (!$this->isEof()) {
            list($p, $d) = $this->getPos($this->data, $delimiter);
            if (false !== $p) {
                $result = \substr($this->data, 0, $p);
                // skip delimiter
                if (!\in_array($d, $noskips)) {
                    $p++;
                }
                $this->data = \substr($this->data, $p);

                return $result;
            }
        }
    }

    /**
     * Get first position of delimiters.
     *
     * @param string $data
     * @param string $delimiter
     * @return boolean|number
     */
    protected function getPos($data, $delimiter)
    {
        $pos = false;
        $delim = null;
        for ($i = 0; $i < \strlen($delimiter); $i++) {
            $d = \substr($delimiter, $i, 1);
            if (false !== ($p = \strpos($data, $d))) {
                if (false === $pos || $p < $pos) {
                    $pos = $p;
                    $delim = $d;
                }
            }
        }

        return [$pos, $delim];
    }

    /**
     * Get unprocessed data.
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Is EOF.
     *
     * @return boolean
     */
    public function isEof()
    {
        return 0 === \strlen($this->data) ? true : false;
    }
}
