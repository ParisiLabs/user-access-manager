<?php
/**
 * FileHandler.php
 *
 * The FileHandler class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $Id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\FileHandler;

use UserAccessManager\Config\Config;
use UserAccessManager\Wrapper\Php;
use UserAccessManager\Wrapper\Wordpress;

/**
 * Class FileHandler
 *
 * @package UserAccessManager\FileHandler
 */
class FileHandler
{
    /**
     * @var Php
     */
    protected $oPhp;

    /**
     * @var Wordpress
     */
    protected $oWordpress;

    /**
     * @var Config
     */
    protected $oConfig;

    /**
     * @var FileProtectionFactory
     */
    protected $oFileProtectionFactory;

    /**
     * FileHandler constructor.
     *
     * @param Php                   $oPhp
     * @param Wordpress             $oWordpress
     * @param Config                $oConfig
     * @param FileProtectionFactory $oFileProtectionFactory
     */
    public function __construct(
        Php $oPhp,
        Wordpress $oWordpress,
        Config $oConfig,
        FileProtectionFactory $oFileProtectionFactory
    ) {
        $this->oPhp = $oPhp;
        $this->oWordpress = $oWordpress;
        $this->oConfig = $oConfig;
        $this->oFileProtectionFactory = $oFileProtectionFactory;
    }

    /**
     * Delivers the content of the requested file.
     *
     * @param string $sFile
     * @param bool   $blIsImage
     *
     * @return null
     */
    public function getFile($sFile, $blIsImage)
    {
        //Deliver content
        if (file_exists($sFile) === true) {
            $sFileName = basename($sFile);

            /*
             * This only for compatibility
             * mime_content_type has been deprecated as the PECL extension file info
             * provides the same functionality (and more) in a much cleaner way.
             */
            $aFile = explode('.', $sFileName);
            $sLastElement = array_pop($aFile);
            $sFileExt = strtolower($sLastElement);

            $aMimeTypes = $this->oConfig->getMimeTypes();

            if ($this->oPhp->functionExists('finfo_open')) {
                $sFileInfo = finfo_open(FILEINFO_MIME);
                $sFileMimeType = finfo_file($sFileInfo, $sFile);
                finfo_close($sFileInfo);
            } elseif ($this->oPhp->functionExists('mime_content_type')) {
                $sFileMimeType = mime_content_type($sFile);
            } elseif (isset($aMimeTypes[$sFileExt])) {
                $sFileMimeType = $aMimeTypes[$sFileExt];
            } else {
                $sFileMimeType = 'application/octet-stream';
            }

            header('Content-Description: File Transfer');
            header('Content-Type: '.$sFileMimeType);

            if ($blIsImage === false) {
                $sBaseName = str_replace(' ', '_', basename($sFile));
                header('Content-Disposition: attachment; filename="'.$sBaseName.'"');
            }

            header('Content-Transfer-Encoding: binary');
            header('Content-Length: '.filesize($sFile));

            if ($this->oConfig->getDownloadType() === 'fopen'
                && $blIsImage === false
            ) {
                $oHandler = fopen($sFile, 'r');

                //TODO find better solution (prevent '\n' / '0A')
                ob_clean();
                flush();

                while (feof($oHandler) === false) {
                    if ($this->oPhp->iniGet('safe_mode') !== '') {
                        $this->oPhp->setTimeLimit(30);
                    }

                    echo fread($oHandler, 1024);
                }
            } else {
                ob_clean();
                flush();
                readfile($sFile);
            }
        } else {
            $this->oWordpress->wpDie(TXT_UAM_FILE_NOT_FOUND_ERROR);
        }

        return null;
    }

    /**
     * Creates a protection file.
     *
     * @param string $sDir        The destination directory.
     * @param string $sObjectType The object type.
     *
     * @return false
     */
    public function createFileProtection($sDir = null, $sObjectType = null)
    {
        $sDir = ($sDir === null) ? $this->oConfig->getUploadDirectory() : $sDir;

        if ($sDir !== null) {
            if ($this->oWordpress->isNginx() === true) {
                return $this->oFileProtectionFactory->createNginxFileProtection()->create($sDir, $sObjectType);
            } else {
                return $this->oFileProtectionFactory->createApacheFileProtection()->create($sDir, $sObjectType);
            }
        }

        return false;
    }


    /**
     * Deletes the protection files.
     *
     * @param string $sDir The destination directory.
     *
     * @return false
     */
    public function deleteFileProtection($sDir = null)
    {
        $sDir = ($sDir === null) ? $this->oConfig->getUploadDirectory() : $sDir;

        if ($sDir !== null) {
            if ($this->oWordpress->isNginx() === true) {
                return $this->oFileProtectionFactory->createNginxFileProtection()->delete($sDir);
            } else {
                return $this->oFileProtectionFactory->createApacheFileProtection()->delete($sDir);
            }
        }

        return false;
    }
}
