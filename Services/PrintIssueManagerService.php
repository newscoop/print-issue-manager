<?php
/**
 * @package Newscoop\PrintIssueManagerBundle
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 * @copyright 2013 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\PrintIssueManagerBundle\Services;

use Doctrine\ORM\EntityManager,
    Newscoop\Entity\Article,
    Newscoop\Entity\Language,
    Newscoop\Entity\Topic,
    Newscoop\PrintIssueManagerBundle\Entity\ContextArticle;

/**
 * Print Issue Manager service
 */
class PrintIssueManagerService
{
    /** @var Doctrine\ORM\EntityManager */
    private $em;

    /**
     * @param Doctrine\ORM\EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Get related articles
     *
     * @param Article $article
     *
     * @return Newscoop\Entity\Article
     */
    public function getRelatedArticles(Article $article)
    {
        $related = array();
        $contextBox = $this->getContextBoxByIdOrIssueId(null, $article->getId());
        $articleIds = $this->getContextBoxArticleList($contextBox->getId());
        foreach ($articleIds as $articleId) {
            $related[] = $this->find($article->getLanguage(), $articleId);
        }

        return $related;
    }

    /**
     * Find an article
     *
     * @param Newscoop\Entity\Language $language
     * @param int                      $number
     *
     * @return Newscoop\Entity\Article
     */
    public function find(Language $language, $number)
    {
        $article = $this->em->getRepository('Newscoop\Entity\Article')
            ->createQueryBuilder('a')
            ->select('a')
            ->where('a.number = :number')
            ->andWhere('a.language = :language')
            ->setParameters(array(
                'number'=> $number,
                'language' => $language->getId()
            ))
            ->getQuery()
            ->getResult();

        return $article;
    }

    /**
     * Find by given criteria
     *
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
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
     * @param int                   $limit
     *
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
     * @param int $number Article number
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
     * Find types
     *
     * @param string $type
     *
     * @return bool
     */
    public function checkArticleType($type)
    {
        $articleType = $this->em->getRepository('Newscoop\Entity\ArticleType')
            ->createQueryBuilder('a')
            ->andWhere('a.name = :type')
            ->getQuery()
            ->setParameters(array(
                'type' => $type,
            ))
            ->getResult();

        if ($articleType) {
            return true;
        }

        return false;
    }

    /**
     * Get articles by mobile_issue type
     *
     * @param string $type
     *
     * @return Newscoop\Entity\Article
     */
    public function getMobileIssues($type)
    {
        $article = $this->getArticleRepository()
            ->createQueryBuilder('a')
            ->andWhere('a.type = :mobile_issue')
            ->andWhere('a.workflowStatus = :workflowStatusPublished OR a.workflowStatus = :workflowStatusSubmited')
            ->orderBy('a.published', 'ASC')
            ->getQuery()
            ->setParameters(array(
                'mobile_issue' => $type,
                'workflowStatusPublished' => 'Y',
                'workflowStatusSubmited' => 'S'
            ))
            ->getResult();

        return $article;
    }

    /**
     * Get latest article mobile issue
     *
     * @param string $type
     *
     * @return Newscoop\Entity\Article
     */
    public function getLatestMobileIssueArticle($type)
    {
        $article = $this->getArticleRepository()
            ->createQueryBuilder('a')
            ->andWhere('a.type = :mobile_issue')
            ->orderBy('a.number', 'DESC')
            ->getQuery()
            ->setParameter('mobile_issue', $type)
            ->setMaxResults(1)
            ->getResult();

        return $article;
    }

    /**
     * Get latest issues
     *
     * @param int $number Limit
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

    /**
     * Find iPad section
     *
     * @return string
     */
    public function findIpadSection()
    {
        $section = $this->getSectionRepository()
            ->createQueryBuilder('s')
            ->select('s.number', 'p.id as publicationId',
                'i.number as issueId', 'l.id as languageId')
            ->leftJoin('s.publication', 'p')
            ->leftJoin('s.issue', 'i')
            ->leftJoin('s.language', 'l')
            ->where('s.name = :name')
            ->setParameter('name', 'iPad')
            ->orderBy('s.id', 'desc')
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        return $section;
    }

    /**
     * Get context box by id or issue id(article number)
     *
     * @param string $boxId
     * @param string $articleNumber
     *
     * @return int
     */
    public function getContextBoxByIdOrIssueId($boxId = null, $articleNumber = null) 
    {
        if (is_numeric($boxId) && $boxId > 0) {
            $contextBox = $this->em->getRepository('Newscoop\PrintIssueManagerBundle\Entity\ContextBox')
                ->createQueryBuilder('c')
                ->where('c.id = :id')
                ->setParameter('id', $boxId)
                ->getQuery()
                ->getSingleResult();
        } else {
            if (is_numeric($articleNumber) && $articleNumber > 0) {
                $contextBox = $this->em->getRepository('Newscoop\PrintIssueManagerBundle\Entity\ContextBox')
                    ->createQueryBuilder('c')
                    ->where('c.articleNumber = :id')
                    ->setParameter('id', $articleNumber)
                    ->getQuery()
                    ->getSingleResult();
            }
        }

        return $contextBox;
    }

    /**
     * Gets an issues list for given id.
     *
     * @param int $id
     *
     * @return array|exception
     */
    public function getContextBoxArticleList($id)
    {
        try{
            $rows = $this->em->getRepository('Newscoop\PrintIssueManagerBundle\Entity\ContextArticle')
                ->createQueryBuilder('ca')
                ->select('ca.articleNumber')
                ->where('ca.contextListId = :id')
                ->setParameter('id', $id)
                ->orderBy('ca.id', 'desc')
                ->getQuery()
                ->getArrayResult();

            $returnArray = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $returnArray[] = $row['articleNumber'];
                }
            }

            return array_reverse($returnArray);
        } catch (\Exception $e) {
            return new \Exception('Error occured while fetching ContextArticle entity data!');
        }
    }

    /**
     * Saves context list
     *
     * @param string $contextListId
     * @param array  $articlesNumbers
     *
     * @return void
     */
    public function saveList($contextListId, $articlesNumbers)
    {
        $this->removeList($contextListId);
        $this->insertList($contextListId, array_unique($articlesNumbers));
    }

    /**
     * Removes context list
     *
     * @param string $contextListId
     *
     * @return void|exception
     */
    public function removeList($contextListId)
    {
        try {
            $contextBoxes = $this->em->getRepository('Newscoop\PrintIssueManagerBundle\Entity\ContextArticle')
                ->findBy(array(
                    'contextListId' => $contextListId,
                ));

            foreach ($contextBoxes as $contextBox) {
                $this->em->remove($contextBox);
            }

            $this->em->flush();

        } catch (\Exception $e) {
            return new \Exception('Error occured while removing context list');
        }
    }

    /**
     * Inserts new context list
     *
     * @param string $contextBoxId
     * @param string $articlesNumbers
     *
     * @return void|exception
     */
    public function insertList($contextBoxId, $articlesNumbers)
    {
        try {
            foreach ($articlesNumbers as $articleNumber) {
                $contextList = new ContextArticle();
                $contextList->setContextListId($contextBoxId);
                $contextList->setArticleNumber($articleNumber);
                $this->em->persist($contextList);
            }

            $this->em->flush();

        } catch (\Exception $e) {
            return new \Exception('Error occured while creating new context list');
        }
    }

    /**
     * Gets artciles by user id
     *
     * @param int $userId
     *
     * @return Newscoop\Entity\Article|exception
     */
    public function getArticles($userId)
    {
        try {
            $articles = $this->getArticleRepository()
                ->createQueryBuilder('a')
                ->where('a.type = :news OR a.type = :pending')
                ->andWhere('a.creator = :userId')
                ->setParameters(array(
                    'news' => 'news',
                    'pending' => 'pending',
                    'userId' => $userId
                ))
                ->orderBy('a.number', 'desc')
                ->getQuery()
                ->getResult();

            return $articles;

        } catch(\Exception $e) {
            return new \Exception('Error occured while fetching articles by printdesk user');
        }
    }

    /**
     * Processes article types custom fields (X tables)
     *
     * @param array $params
     *
     * @return array|exception
     */
    public function processCustomField(array $params)
    {
        try {
            $articleTypes = $this->em->getRepository('Newscoop\PrintIssueManagerBundle\Entity\IPadAdArticleType')
                ->createQueryBuilder('i')
                ->select('i', 'a')
                ->innerJoin('i.article', 'a')
                ->where('a.type = :type')
                ->andWhere('i.active = :active')
                ->andWhere('i.weeklyIssue = :weekly_issue')
                ->setParameters($params)
                ->getQuery()
                ->getResult();

            $articlesArray = array();
            foreach ($articleTypes as $key => $value) {
                $articlesArray[] = $value->getArticle();
            }

            return $articlesArray;

        } catch (\Exception $e) {
            return new \Exception('Error occured while fetching articles based on article type data');
        }
    }

    /**
     * Processes articles with print switch
     *
     * @param int $switch
     *
     * @return array|exception
     */
    public function processPrintSwitchField($switch)
    {
        try {
            $articleTypes = $this->em->getRepository('Newscoop\PrintIssueManagerBundle\Entity\NewsArticleType')
                ->createQueryBuilder('i')
                ->select('i', 'a')
                ->innerJoin('i.article', 'a')
                ->where('i.print = :type')
                ->setParameter('type', $switch)
                ->getQuery()
                ->getResult();

            $articlesArray = array();
            foreach ($articleTypes as $key => $value) {
                $articlesArray[] = $value->getArticle();
            }

            return $articlesArray;

        } catch (\Exception $e) {
            return new \Exception('Error occured while fetching articles based on article type data');
        } 
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