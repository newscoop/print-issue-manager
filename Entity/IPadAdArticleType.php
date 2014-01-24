<?php
/**
 * @package Newscoop\PrintIssueManagerBundle
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 * @copyright 2013 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\PrintIssueManagerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Article type entity
 *
 * @ORM\Entity()
 * @ORM\Table(name="XiPad_Ad")
 */
class IPadAdArticleType
{
    /**
     * @ORM\Id()
     * @ORM\ManyToOne(targetEntity="Newscoop\Entity\Article", cascade={"all"})
     * @ORM\JoinColumn(name="NrArticle", referencedColumnName="number")
     * @var int
     */
    private $article;

    /**
     * @ORM\Column(type="integer", name="IdLanguage")
     * @ORM\ManyToOne(targetEntity="Newscoop\Entity\Language", cascade={"all"})
     * @var int
     */
    private $language;

    /**
     * @ORM\Column(type="boolean", name="Factive")
     * @var bool
     */
    private $active;

    /**
     * @ORM\Column(type="boolean", name="Fweekly_issue")
     * @var bool
     */
    private $weeklyIssue;

    public function __construct() {
        $this->article = new ArrayCollection();
        $this->language = new ArrayCollection();
    }

    /**
     * Get article
     *
     * @return integer
     */
    public function getArticle()
    {
        return $this->article;
    }

    /**
     * Get language
     *
     * @return integer
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Get active
     *
     * @return bool
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Get weekly issue
     *
     * @return bool
     */
    public function getWeeklyIssue()
    {
        return $this->weeklyIssue;
    }
}