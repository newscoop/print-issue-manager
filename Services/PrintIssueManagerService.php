<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\PrintIssueManagerBundle\Services;

use Doctrine\ORM\EntityManager,
    Newscoop\Entity\Article,
    Newscoop\Entity\Language,
    Newscoop\Entity\Topic;

/**
 * Print Issue Manager service
 */
class PrintIssueManagerService
{
    /** @var Doctrine\ORM\EntityManager */
    private $em;


    /**
     * @param array $config
     * @param Doctrine\ORM\EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function getRelatedArticles(Article $article)
    {
        $related = array();
        $contextBox = new \ContextBox(null, $article->getId());
        $articleIds = $contextBox->getArticlesList();
        foreach($articleIds as $articleId) {
            $related[] = $this->find($article->getLanguage(), $articleId);
        }

        return $related;
    }

    /**
     * Find an article
     *
     * @param Newscoop\Entity\Language
     * @param int $number
     * @return Newscoop\Entity\Article
     */
    public function find(Language $language, $number)
    {
        return $this->getRepository()
            ->find(array('language' => $language->getId(), 'number' => $number));
    }
    
    /**
     * Find by given criteria
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return mixed
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->getRepository()->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Find by given topic
     *
     * @param Newscoop\Entity\Topic $topic
     * @param int $limit
     * @return array
     */
    public function findByTopic(Topic $topic, $limit)
    {
        return $this->getRepository()->createQueryBuilder('a')
            ->innerJoin('a.topics', 't')
            ->where('t.topic = :topic')
            ->orderBy('a.published', 'DESC')
            ->getQuery()
            ->setParameter('topic', $topic->getTopicId())
            ->setMaxResults($limit)
            ->getResult();
    }

    /**
     * Find article by given number
     *
     * @param  int $number Article number
     *
     * @return Newscoop\Entity\Article
     */
    public function findOneByNumber($number)
    {
        return $this->getRepository()->findOneBy(array(
            'number' => $number,
        ));
    }

    /**
     * Get articles by mobile_issue type
     *
     * @return Newscoop\Entity\Article
     */
    public function getMobileIssues()
    {   
        $article = $this->getArticleRepository()
            ->createQueryBuilder('a')
            ->andWhere('a.type = :mobile_issue')
            ->andWhere('a.workflowStatus = :workflowStatusPublished OR a.workflowStatus = :workflowStatusSubmited')
            ->orderBy('a.published', 'ASC')
            ->getQuery()
            ->setParameters(array(
                'mobile_issue' => 'news',
                'workflowStatusPublished' => 'Y',
                'workflowStatusSubmited' => 'S'
            ))
            ->getResult();

        return $article;
    }

    /**
     * Get latest article mobile issue
     *
     * @return Newscoop\Entity\Article
     */
    public function getLatestMobileIssueArticle()
    {
        $article = $this->getArticleRepository()
            ->createQueryBuilder('a')
            ->andWhere('a.type = :mobile_issue')
            ->orderBy('a.number', 'DESC')
            ->getQuery()
            ->setParameter('mobile_issue', 'news')
            ->setMaxResults(1)
            ->getResult();

        return $article;
    }

    /**
     * Get latest issues
     *
     * @param  int $number Limit
     *
     * @return Newscoop\Entity\Issue
     */
    public function getLatestIssues($number = 1)
    {
        $issues = $this->getIssueRepository()
            ->createQueryBuilder('i')
            ->orderBy('i.number', 'DESC')
            ->getQuery()
            ->setMaxResults($number)
            ->getResult();

        return $issues;
    }

    public function findIpadSection() {
        $sectionNumber = $this->getSectionRepository()
            ->createQueryBuilder('s')
            ->where('s.name = :name')
            ->setParameter('name', 'iPad')
            ->getQuery()
            ->getResult();

        return $sectionNumber;
    }

    /**
     * Get article repository
     *
     * @return Doctrine\ORM\EntityRepository
     */
    private function getArticleRepository()
    {
        return $this->em->getRepository('Newscoop\Entity\Article');
    }

    /**
     * Get issue repository
     *
     * @return Doctrine\ORM\EntityRepository
     */
    private function getIssueRepository()
    {
        return $this->em->getRepository('Newscoop\Entity\Issue');
    }

    /**
     * Get section repository
     *
     * @return Doctrine\ORM\EntityRepository
     */
    private function getSectionRepository()
    {
        return $this->em->getRepository('Newscoop\Entity\Section');
    }
}
