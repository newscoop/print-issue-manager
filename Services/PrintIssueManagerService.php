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
        $contextBox = $this->getContextBoxByIssueId($article->getId());
        $articleIds = $this->getContextBoxArticleList($contextBox->getId());
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
        return $this->getArticleRepository()
            ->find(array(
                'language' => $language->getId(), 
                'number' => $number
            ));
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

    public function findIpadSection() 
    {
        $section = $this->getSectionRepository()
            ->createQueryBuilder('s')
            ->where('s.name = :name')
            ->setParameter('name', 'iPad')
            ->orderBy('s.id', 'desc')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $section;
    }

    public function getContextBoxByIssueId($id) 
    {
        $contextBox = $this->em->getRepository('Newscoop\PrintIssueManagerBundle\Entity\ContextBox')
            ->createQueryBuilder('c')
            ->where('c.articleNumber = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleResult();

        return $contextBox;
    }

    public function createList($relatedArticles)
    {
        $items = array();
        if (count($relatedArticles) > 0) {
            foreach ($relatedArticles as $article) {
                $one_art_info = array();
                $one_art_obj = new stdClass();

                $one_art_obj->id = $article->getNumber();
                $one_art_obj->Title = '<a href='.$this->view->serverUrl('/admin/articles/edit.php' . '?f_publication_id=' . $article->getPublicationId()
                        . '&amp;f_issue_number=' . $article->getIssueId() . '&amp;f_section_number=' . $article->getSectionId()
                        . '&amp;f_article_number=' . $article->getNumber() . '&amp;f_language_id=' . $article->getLanguageId()
                        . '&amp;f_language_selected=' . $article->getLanguageId().'">'.$article->getTitle().'</a>'
                    );
                $one_art_obj->ArticleType = $article->getType();
                
                try {
                    $one_art_obj->OnlineSections = $article->getSectionName();
                    $publication_id = $article->getPublicationId();
                } catch(\Doctrine\ORM\EntityNotFoundException $e) {
                    $one_art_obj->OnlineSections = null;
                    $publication_id = null;
                }

                try {
                    $one_art_obj->PrintSection = $article->getData('printsection');
                } catch(\InvalidPropertyException $e) {
                    $one_art_obj->PrintSection = null;
                }

                try {
                    $one_art_obj->PrintStory = $article->getData('printstory');
                } catch(\InvalidPropertyException $e) {
                    $one_art_obj->PrintStory = null;
                }

                try {
                    $one_art_obj->Prominent = $article->getData('iPad_prominent');
                } catch(\InvalidPropertyException $e) {
                    $one_art_obj->Prominent = null;
                }

                $one_art_obj->LanguageId = $article->getLanguageId();
                $one_art_obj->Webcode = Manager::getWebcoder('')->encode($article->getNumber());

                $issue_number = $article->getIssueNumber();
                $section_number = $article->getSectionNumber();
                $language_id = $article->getLanguageId();

                $meta_article = new \MetaArticle((int) $language_id, $article->getNumber());
                $meta_publication = new \MetaPublication($publication_id);
                $meta_issue = new \MetaIssue($publication_id, $language_id, $issue_number);
                $meta_section = new \MetaSection($publication_id, $issue_number, $language_id, $section_number);

                $url = \CampSite::GetURIInstance();
                $url->publication = $meta_publication;
                $url->issue = $meta_issue;
                $url->section = $meta_section;
                $url->article = $meta_article;
                $frontendURI = $url->getURI('article');
                $one_art_obj->Uri = $frontendURI;

                $one_art_obj->Preview = $this->view->serverUrl($this->view->url(array(
                    'module' => 'api',
                    'controller' => 'articles',
                    'action' => 'item',
                ), 'default') .'?'. http_build_query(array(
                    'article_id' => $article->getNumber(),
                    'side' => 'front',
                    'language_id' => $article->getLanguageId(),
                    'allow_unpublished' => true
                )));

                $items[] = $one_art_obj;
            }
        }

        return $items;
    }

    /**
     * Gets an issues list for given id.
     *
     * @param int $id
     *
     * @return array
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
                foreach($rows as $row) {
                    $returnArray[] = $row['fk_article_no'];
                }
            }

            return array_reverse($returnArray);
        } catch (\Exception $e) {
            return new \Exception('Error occured while fetching ContextArticle entity data!');
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