<?php
/**
 * @package Newscoop\PrintIssueManagerBundle
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 * @copyright 2013 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\PrintIssueManagerBundle\Services;

use Doctrine\ORM\EntityManager,
    Doctrine\Common\Collections\Collection;

/**
 * Configuration service for article type
 */
class ArticleTypeConfigurationService
{
    /**
     * Name of the articleType
     *
     * @var string
     */
    private $name = 'iPad_Ad';

    /**
     * Configuration array for articleTypeField
     *
     * @var array
     */
    private $fieldArray = array(
        'ad_name' => array(
            'entryMethod' => 'getAdName',
            'type' => 'text', 'max_size' => 255
        ),
        'hyperlink' => array(
            'entryMethod' => 'getHyperlink',
            'type' => 'text', 'max_size' => 255
        ),
        'active' => array(
            'entryMethod' => 'getActive',
            'type' => 'switch'
        ),
        'weekly_issue ' => array(
            'entryMethod' => 'getWeeklyIssue ',
            'type' => 'switch'
        ),
        'ad_left' => array(
            'entryMethod' => 'getAdLeft',
            'type' => 'switch'
        ),
        'ad_right' => array(
            'entryMethod' => 'getAdRight',
            'type' => 'switch'
        ),
        'newshighlight ' => array(
            'entryMethod' => 'getNewshighlight ',
            'type' => 'switch'
        ),
        'sectionlists' => array(
            'entryMethod' => 'getSectionlists',
            'type' => 'switch'
        ),
        'blogs_dossiers' => array(
            'entryMethod' => 'getBlogsDossiers ',
            'type' => 'switch'
        ),
    );

    /**
     * Initialize service
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
        $this->connection = $em->getConnection();
    }

    /**
     * Getter for name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get tablename for articleType
     *
     * @return string
     */
    private function getTableName()
    {
        return 'X'.$this->getName();
    }

    /**
     * Get list of fields and configuration for adding ArticleTypeFields
     *
     * @return array
     */
    public function getArticleTypeConfiguration()
    {
        $confArray = array();
        foreach ($this->fieldArray AS $fieldID => $settingsArray) {
            unset($settingsArray['entryMethod']);
            $confArray[$fieldID] = $settingsArray;
        }
        return $confArray;
    }

    /**
     * Get list of articleTypeFieldIDs mapped to entry method. This way
     * each field is populated with the correct content.
     *
     * @return array
     */
    public function getArticleTypeMapping()
    {
        $mappingArray = array();
        foreach ($this->fieldArray as $fieldID => $settingsArray) {
            if (!array_key_exists('entryMethod', $settingsArray)) continue;
            $mappingArray[$fieldID] = $settingsArray['entryMethod'];
        }
        return $mappingArray;
    }

    /**
     * Creates a new article type for Newscoop
     */
    public function create()
    {
        $this->populateArticleTypeMetadata();
        $this->createArticleTypeTable();
        $this->extendArticleTypeTable();
    }

    /**
     * Remove article type
     */
    public function remove()
    {
        $this->removeArticleTypeTable();
        $this->removeArticleTypeMetadata();
    }

    /**
     * Creates table for article type
     * NOTE: This ia a hack, should be converted to new ArticleType Entity
     */
    private function createArticleTypeTable()
    {
        // Create article type
        $tableName = $this->getTableName();

        $this->connection->exec('DROP TABLE IF EXISTS `'.$tableName.'`');
        $this->connection->exec(
            "CREATE TABLE `".$tableName."` (\n"
          . "    NrArticle INT NOT NULL,\n"
          . "    IdLanguage INT NOT NULL,\n"
          . "    PRIMARY KEY(NrArticle, IdLanguage)\n"
          . ") DEFAULT CHARSET=utf8"
        );
    }

    /**
     * Extends article type table with configured fields
     * NOTE: This ia a hack, should be converted to new ArticleType Entity
     */
    private function extendArticleTypeTable()
    {
        // Create article type
        $tableName  = $this->getTableName();
        $query      = '';

        $types = \ArticleTypeField::DatabaseTypes(null, null);
        $articleTypeFields = $this->getArticleTypeConfiguration();

        foreach ($articleTypeFields AS $fieldId => $fieldData) {
            $query .= "ALTER TABLE `" . $tableName . "` ADD COLUMN `F"
                . $fieldId . '` ' . $types[$fieldData['type']] .';';
        }

        $this->connection->exec($query);
    }

    /**
     * Removes table for article type
     * NOTE: This ia a hack, should be using entity manager
     */
    private function removeArticleTypeTable()
    {
        // Create article type
        $tableName = $this->getTableName();

        $this->connection->exec('DROP TABLE IF EXISTS `'.$tableName.'`');
    }

    /**
     * Creates entries in articletypemetedata table for current article type
     * NOTE: This already uses entities, but should probably go in a better place
     */
    private function populateArticleTypeMetadata()
    {
        $fieldArray = $this->getArticleTypeConfiguration();
        $weight = 1;

        $newswireType = new \Newscoop\Entity\ArticleType();
        $newswireType->setName($this->getName());

        $this->em->persist($newswireType);

        foreach ($fieldArray AS $fieldID => $fieldParams) {

            $articleField = new \Newscoop\Entity\ArticleTypeField();
            $articleField->setName($fieldID);
            $articleField->setArticleType($newswireType);
            $articleField->setArticleTypeHack($newswireType);
            $articleField->setType($fieldParams['type']);
            $articleField->setFieldWeight($weight);
            $articleField->setCommentsEnabled(0);

            if (array_key_exists('is_hidden', $fieldParams)) {
                $articleField->setIsHidden($fieldParams['is_hidden']);
            } else {
                $articleField->setIsHidden(0);
            }
            if (array_key_exists('max_size', $fieldParams)) {
                $articleField->setLength($fieldParams['max_size']);
            }
            if (array_key_exists('field_type_param', $fieldParams)) {
                $articleField->setFieldTypeParam($fieldParams['field_type_param']);
            }
            if (array_key_exists('is_content_field', $fieldParams)) {
                $articleField->setIsContentField($fieldParams['is_content_field']);
            } else {
                $articleField->setIsContentField(0);
            }

            $this->em->persist($articleField);
            unset($articleField);
            $weight++;
        }
        $this->em->flush();
    }

    /**
     * Creates entries in articletypemetedata table for current article type
     * NOTE: This already uses entities, but should probably go in a better place
     */
    private function removeArticleTypeMetadata()
    {
        $fieldArray = $this->getArticleTypeConfiguration();
        $weight = 0;

        $articleTypes = $this->em
            ->getRepository('\Newscoop\Entity\ArticleType')
            ->findByName($this->getName());

        foreach ($articleTypes as $articleType) {
            $this->em->remove($articleType);
        }

        $this->em->flush();
    }
}
