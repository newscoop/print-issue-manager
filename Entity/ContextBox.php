<?php
/**
 * @package Newscoop\PrintIssueManagerBundle
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 * @copyright 2013 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\PrintIssueManagerBundle\Entity;

use Doctrine\ORM\Mapping AS ORM;

/**
 * Context articles
 *
 * @ORM\Entity()
 * @ORM\Table(name="context_boxes")
 */
class ContextBox
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="id")
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="integer", name="fk_article_no")
     * @var int
     */
    private $articleNumber;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get article number
     *
     * @return integer
     */
    public function getArticleNumber()
    {
        return $this->articleNumber;
    }

    /**
     * Set article number
     *
     * @param  string $articleNumber
     * @return string
     */
    public function setArticleNumber($articleNumber)
    {
        $this->articleNumber = $articleNumber;
        
        return $this;
    }
}