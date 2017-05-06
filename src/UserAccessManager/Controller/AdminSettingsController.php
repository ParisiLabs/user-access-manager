<?php
/**
 * AdminSettingsController.php
 *
 * The AdminSettingsController class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\Controller;

use UserAccessManager\Config\Config;
use UserAccessManager\Config\ConfigParameter;
use UserAccessManager\FileHandler\FileHandler;
use UserAccessManager\ObjectHandler\ObjectHandler;
use UserAccessManager\Wrapper\Php;
use UserAccessManager\Wrapper\Wordpress;

class AdminSettingsController extends Controller
{
    const SETTING_GROUP_PARAMETER = 'settings_group';
    const GROUP_POST_TYPES = 'post_types';
    const GROUP_TAXONOMIES = 'taxonomies';
    const GROUP_FILES = 'file';
    const SECTION_FILES = 'file';
    const GROUP_AUTHOR = 'author';
    const SECTION_AUTHOR = 'author';
    const GROUP_OTHER = 'other';
    const SECTION_OTHER = 'other';

    /**
     * @var string
     */
    protected $template = 'AdminSettings.php';

    /**
     * @var ObjectHandler
     */
    private $objectHandler;

    /**
     * @var FileHandler
     */
    private $fileHandler;

    /**
     * AdminSettingsController constructor.
     *
     * @param Php           $php
     * @param Wordpress     $wordpress
     * @param Config        $config
     * @param ObjectHandler $objectHandler
     * @param FileHandler   $fileHandler
     */
    public function __construct(
        Php $php,
        Wordpress $wordpress,
        Config $config,
        ObjectHandler $objectHandler,
        FileHandler $fileHandler
    ) {
        parent::__construct($php, $wordpress, $config);
        $this->objectHandler = $objectHandler;
        $this->fileHandler = $fileHandler;
    }

    /**
     * Returns true if the server is a nginx server.
     *
     * @return bool
     */
    public function isNginx()
    {
        return $this->wordpress->isNginx();
    }

    /**
     * Returns the pages.
     *
     * @return array
     */
    public function getPages()
    {
        $pages = $this->wordpress->getPages('sort_column=menu_order');
        return is_array($pages) !== false ? $pages : [];
    }

    /**
     * Returns the config parameters.
     *
     * @return \UserAccessManager\Config\ConfigParameter[]
     */
    public function getConfigParameters()
    {
        return $this->config->getConfigParameters();
    }

    /**
     * Returns the post types as object.
     *
     * @return \WP_Post_Type[]
     */
    private function getPostTypes()
    {
        return $this->wordpress->getPostTypes(['public' => true], 'objects');
    }

    /**
     * Returns the taxonomies as objects.
     *
     * @return \WP_Taxonomy[]
     */
    private function getTaxonomies()
    {
        return $this->wordpress->getTaxonomies(['public' => true], 'objects');
    }

    /**
     * Returns the current settings group.
     *
     * @return string
     */
    public function getCurrentSettingsGroup()
    {
        return (string)$this->getRequestParameter(self::SETTING_GROUP_PARAMETER, self::GROUP_POST_TYPES);
    }

    /**
     * Returns the settings group link by the given group key.
     *
     * @param string $groupKey
     *
     * @return string
     */
    public function getSettingsGroupLink($groupKey)
    {
        $rawUrl = $this->getRequestUrl();
        $url = preg_replace('/&amp;'.self::SETTING_GROUP_PARAMETER.'[^&]*/i', '', $rawUrl);
        return $url.'&'.self::SETTING_GROUP_PARAMETER.'='.$groupKey;
    }

    /**
     * Returns the grouped config parameters.
     *
     * @return array
     */
    public function getGroupedConfigParameters()
    {
        $configParameters = $this->config->getConfigParameters();

        $groupedConfigParameters = [];
        $groupedConfigParameters[self::GROUP_POST_TYPES] = [];
        $postTypes = $this->getPostTypes();

        foreach ($postTypes as $postType => $postTypeObject) {
            if ($postType === ObjectHandler::ATTACHMENT_OBJECT_TYPE) {
                continue;
            }

            $groupedConfigParameters[self::GROUP_POST_TYPES][$postType] = [
                $configParameters["hide_{$postType}"],
                $configParameters["hide_{$postType}_title"],
                $configParameters["{$postType}_title"],
                $configParameters["{$postType}_content"],
                $configParameters["hide_{$postType}_comment"],
                $configParameters["{$postType}_comment_content"],
                $configParameters["{$postType}_comments_locked"]
            ];


            if ($postType === 'post') {
                $groupedConfigParameters[self::GROUP_POST_TYPES][$postType][] =
                    $configParameters["show_{$postType}_content_before_more"];
            }
        }

        $taxonomies = $this->getTaxonomies();

        foreach ($taxonomies as $taxonomy => $taxonomyObject) {
            $groupedConfigParameters[self::GROUP_TAXONOMIES][$taxonomy] =
                [$configParameters["hide_empty_{$taxonomy}"]];
        }

        $fileOptions = [
            $configParameters['lock_file'],
            $configParameters['download_type']
        ];

        if ($this->config->isPermalinksActive() === true) {
            $fileOptions[] = $configParameters['lock_file_types'];
            $fileOptions[] = $configParameters['file_pass_type'];
        }

        $groupedConfigParameters[self::GROUP_FILES] = [self::SECTION_FILES => $fileOptions];

        $groupedConfigParameters[self::GROUP_AUTHOR] = [
            self::SECTION_AUTHOR => [
                $configParameters['authors_has_access_to_own'],
                $configParameters['authors_can_add_posts_to_groups'],
                $configParameters['full_access_role'],
            ]
        ];

        $groupedConfigParameters[self::GROUP_OTHER] = [
            self::SECTION_OTHER => [
                $configParameters['lock_recursive'],
                $configParameters['protect_feed'],
                $configParameters['redirect'],
                $configParameters['blog_admin_hint'],
                $configParameters['blog_admin_hint_text'],
            ]
        ];

        return $groupedConfigParameters;
    }

    /**
     * Update settings action.
     */
    public function updateSettingsAction()
    {
        $this->verifyNonce('uamUpdateSettings');

        $newConfigParameters = $this->getRequestParameter('config_parameters');
        $newConfigParameters = array_map(
            function ($entry) {
                return htmlentities(str_replace('\\', '', $entry));
            },
            $newConfigParameters
        );
        $this->config->setConfigParameters($newConfigParameters);

        if ($this->config->lockFile() === false) {
            $this->fileHandler->deleteFileProtection();
        } else {
            $this->fileHandler->createFileProtection();
        }

        $this->wordpress->doAction('uam_update_options', $this->config);
        $this->setUpdateMessage(TXT_UAM_UPDATE_SETTINGS);
    }

    /**
     * Checks if the group is a post type.
     *
     * @param string $key
     *
     * @return bool
     */
    public function isPostTypeGroup($key)
    {
        $postTypes = $this->getPostTypes();

        return isset($postTypes[$key]);
    }

    /**
     * Returns the right translation string.
     *
     * @param string $key
     * @param string $ident
     * @param bool   $description
     *
     * @return mixed|string
     */
    private function getObjectText($key, $ident, $description = false)
    {
        $objects = $this->getPostTypes() + $this->getTaxonomies();
        $ident .= ($description === true) ? '_DESC' : '';

        if (isset($objects[$key]) === true) {
            $ident = str_replace(strtoupper($key), 'OBJECT', $ident);
            $text = constant($ident);
            $count = substr_count($text, '%s');
            $arguments = $this->php->arrayFill(0, $count, $objects[$key]->labels->name);
            return vsprintf($text, $arguments);
        }

        return constant($ident);
    }

    /**
     * @param string $key
     * @param bool   $description
     *
     * @return string
     */
    public function getSectionText($key, $description = false)
    {
        return $this->getObjectText(
            $key,
            'TXT_UAM_'.strtoupper($key).'_SETTING',
            $description
        );
    }

    /**
     * Returns the label for the parameter.
     *
     * @param string          $key
     * @param ConfigParameter $configParameter
     * @param bool            $description
     *
     * @return string
     */
    public function getParameterText($key, ConfigParameter $configParameter, $description = false)
    {
        $ident = 'TXT_UAM_'.strtoupper($configParameter->getId());

        return $this->getObjectText(
            $key,
            $ident,
            $description
        );
    }
}
