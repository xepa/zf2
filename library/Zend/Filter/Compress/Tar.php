<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Filter
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @namespace
 */
namespace Zend\Filter\Compress;
use Zend\Filter\Exception;

/**
 * Compression adapter for Tar
 *
 * @uses       Archive_Tar
 * @uses       RecursiveDirectoryIterator
 * @uses       RecursiveIteratorIterator
 * @uses       \Zend\Filter\Compress\AbstractCompressionAlgorithm
 * @uses       \Zend\Filter\Exception
 * @uses       \Zend\Loader
 * @category   Zend
 * @package    Zend_Filter
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Tar extends AbstractCompressionAlgorithm
{
    /**
     * Compression Options
     * array(
     *     'archive'  => Archive to use
     *     'target'   => Target to write the files to
     * )
     *
     * @var array
     */
    protected $_options = array(
        'archive'  => null,
        'target'   => '.',
        'mode'     => null,
    );

    /**
     * Class constructor
     *
     * @param array $options (Optional) Options to set
     */
    public function __construct($options = null)
    {
        if (!class_exists('Archive_Tar')) {
            try {
                \Zend\Loader::loadClass('Archive_Tar');
            } catch (\Exception $e) {
                throw new Exception\ExtensionNotLoadedException('This filter needs PEARs Archive_Tar', 0, $e);
            }
        }

        parent::__construct($options);
    }

    /**
     * Returns the set archive
     *
     * @return string
     */
    public function getArchive()
    {
        return $this->_options['archive'];
    }

    /**
     * Sets the archive to use for de-/compression
     *
     * @param string $archive Archive to use
     * @return \Zend\Filter\Compress\Tar
     */
    public function setArchive($archive)
    {
        $archive = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $archive);
        $this->_options['archive'] = (string) $archive;

        return $this;
    }

    /**
     * Returns the set targetpath
     *
     * @return string
     */
    public function getTarget()
    {
        return $this->_options['target'];
    }

    /**
     * Sets the targetpath to use
     *
     * @param string $target
     * @return \Zend\Filter\Compress\Tar
     */
    public function setTarget($target)
    {
        if (!file_exists(dirname($target))) {
            throw new Exception\InvalidArgumentException("The directory '$target' does not exist");
        }

        $target = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $target);
        $this->_options['target'] = (string) $target;
        return $this;
    }

    /**
     * Returns the set compression mode
     */
    public function getMode()
    {
        return $this->_options['mode'];
    }

    /**
     * Compression mode to use
     * Eighter Gz or Bz2
     *
     * @param string $mode
     */
    public function setMode($mode)
    {
        $mode = ucfirst(strtolower($mode));
        if (($mode != 'Bz2') && ($mode != 'Gz')) {
            throw new Exception\InvalidArgumentException("The mode '$mode' is unknown");
        }

        if (($mode == 'Bz2') && (!extension_loaded('bz2'))) {
            throw new Exception\ExtensionNotLoadedException('This mode needs the bz2 extension');
        }

        if (($mode == 'Gz') && (!extension_loaded('zlib'))) {
            throw new Exception\ExtensionNotLoadedException('This mode needs the zlib extension');
        }
    }

    /**
     * Compresses the given content
     *
     * @param  string $content
     * @return string
     */
    public function compress($content)
    {
        $archive = new \Archive_Tar($this->getArchive(), $this->getMode());
        if (!file_exists($content)) {
            $file = $this->getTarget();
            if (is_dir($file)) {
                $file .= DIRECTORY_SEPARATOR . "tar.tmp";
            }

            $result = file_put_contents($file, $content);
            if ($result === false) {
                throw new Exception\RuntimeException('Error creating the temporary file');
            }

            $content = $file;
        }

        if (is_dir($content)) {
            // collect all file infos
            foreach (new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($content, \RecursiveDirectoryIterator::KEY_AS_PATHNAME),
                        \RecursiveIteratorIterator::SELF_FIRST
                    ) as $directory => $info
            ) {
                if ($info->isFile()) {
                    $file[] = $directory;
                }
            }

            $content = $file;
        }

        $result  = $archive->create($content);
        if ($result === false) {
            throw new Exception\RuntimeException('Error creating the Tar archive');
        }

        return $this->getArchive();
    }

    /**
     * Decompresses the given content
     *
     * @param  string $content
     * @return boolean
     */
    public function decompress($content)
    {
        $archive = $this->getArchive();
        if (file_exists($content)) {
            $archive = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, realpath($content));
        } elseif (empty($archive) || !file_exists($archive)) {
            throw new Exception\RuntimeException('Tar Archive not found');
        }

        $archive = new \Archive_Tar($archive, $this->getMode());
        $target  = $this->getTarget();
        if (!is_dir($target)) {
            $target = dirname($target);
        }

        $result = $archive->extract($target);
        if ($result === false) {
            throw new Exception\RuntimeException('Error while extracting the Tar archive');
        }

        return true;
    }

    /**
     * Returns the adapter name
     *
     * @return string
     */
    public function toString()
    {
        return 'Tar';
    }
}
