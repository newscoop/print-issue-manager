<?php
/**
 * @package Newscoop\PrintIssueManagerBundle
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 * @copyright 2013 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\PrintIssueManagerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
/**
 *  Admin controller
 */
class AdminController extends Controller
{
    const MOBILE_ISSUE = "mobile_issue";
    const IPAD_AD = "iPad_Ad";

    /**
     * @Route("/admin/print-issue-manager")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $service = $this->container->get('newscoop_print_issue_manager.service');
        $em = $this->container->get('em');
        $typeIPadExists = $service->checkArticleType(self::IPAD_AD);
        $typeMobileExists = $service->checkArticleType(self::MOBILE_ISSUE);
        $mobileIssuesArticles = $service->getMobileIssues(self::MOBILE_ISSUE);
        $latestPrintIssues = $service->getLatestIssues(4);

        $iPadSection = $service->findIpadSection();
        krsort($mobileIssuesArticles);
        $session = $request->getSession();
        $session->set('pim_allow_unpublished', true);

        return array(
            'iPadAdName' => self::IPAD_AD,
            'typeIPadExists' => $typeIPadExists,
            'typeMobileExists' => $typeMobileExists,
            'issues' => $mobileIssuesArticles,
            'latestPrintIssues' => $latestPrintIssues,
            'iPadLinkParams' => $iPadSection[0],

        );
    }

    /**
     * @Route("/admin/print-issue-manager/load-articles", options={"expose"=true})
     */
    public function loadArticlesAction(Request $request)
    {
        $em = $this->container->get('em');
        $translator = $this->container->get('translator');
        $user = $this->container->get('user');
        $service =  $this->container->get('newscoop_print_issue_manager.service');
        $printdeskUser = $user->findOneBy(array('username' => 'printdesk'));

        $existingArticles = array_values($service->getContextBoxArticleList($request->get('context_box_id')));

        // get last or two issues numbers
        $latestIssues = $request->get('issues_range');
        foreach ($latestIssues as $issue) {
            list($issueId, $language) = explode('_', $issue);
            $allowedIssues[] = $issueId;
        }

        if (empty($printdeskUser) || (!$printdeskUser)) {
            $this->get('session')->getFlashBag()->add('error', $translator->trans('plugin.printissuemanager.msg.noprintdeskuser'));

            return $this->redirect($this->generateUrl('newscoop_printissuemanager_admin_index'));
        }

        // those are all articles of type news or pending with creator PrintDesk
        $printDescArticles = $service->getArticles($printdeskUser->getUserId());

        // switch print active within a fixed daterange
        $printArticles = $service->processPrintSwitchField(1);
        $printArticlesArray = array();
        foreach ($printArticles as $key => $field) {
            $printArticlesArray[$key] = $field;
        }

        // plus all articles of type iPad_Ad with switch active on.
        $ipadArticles = $service->processCustomField(array(
            'type' => 'iPad_Ad',
            'active' => true,
            'weekly_issue' => true
        ));

        $mergedArticles = array_merge($printDescArticles, $printArticlesArray, $ipadArticles);
        foreach ($mergedArticles as $article) {
            if (in_array($article->getIssueId(), $allowedIssues) || ($article->getType() == 'iPad_Ad')) {
                $existingArticles[] = $article->getNumber();
            }
        }

        $service->saveList($request->get('context_box_id'), array_unique($existingArticles));

        return $this->getIssueArticlesAction($request, $request->get('article_number'), $request->get('article_language'));
    }

    /**
     * @Route("/admin/print-issue-manager/get-issue-articles", options={"expose"=true})
     */
    public function getIssueArticlesAction(Request $request, $articleNumber = null, $articleLanguage = null)
    {
        try {if (!$articleNumber) {
            $articleNumber = $request->get('article_number');
        }

        if (!$articleLanguage) {
            $articleLanguage = $request->get('article_language');
        }

        $service =  $this->container->get('newscoop_print_issue_manager.service');
        $em =  $this->container->get('em');
        //article number in this case is issue id
        $issue = $em->getRepository('Newscoop\Entity\Article')
            ->createQueryBuilder('a')
            ->where('a.type = :type')
            ->andWhere('a.number = :number')
            ->andWhere('a.language = :language')
            ->setParameters(array(
                'type' => self::MOBILE_ISSUE,
                'number'=> $articleNumber,
                'language' => $articleLanguage
            ))
            ->getQuery()
            ->getSingleResult();

        if (!$issue) {
            return $this->redirect($this->generateUrl('newscoop_printissuemanager_admin_index'));
        }

        $contextBox = $service->getContextBoxByIdOrIssueId(null, $issue->getId());
        $relatedArticles = $service->getRelatedArticles($issue);
        $items = $this->createList($relatedArticles);

        $temp = array(
            'issue' => array(
                'number' => $issue->getNumber(),
                'language' => $issue->getLanguageId(),
                'date' => $issue->getDate(),
                'title' => $issue->getTitle(),
                'status' => $issue->getWorkflowStatus(),
                'freeze' => $issue->getData('freeze'),
                'url' => '/admin/articles/edit.php' . '?f_publication_id=' . $issue->getPublicationId()
                        . '&amp;f_issue_number=' . $issue->getIssue()->getNumber() . '&amp;f_section_number=' . $issue->getSectionId()
                        . '&amp;f_article_number=' . $issue->getNumber() . '&amp;f_language_id=' . $issue->getLanguageId()
                        . '&amp;f_language_selected=' . $issue->getLanguageId()
                ),
            'contextBox' => $contextBox->getId(),
            'aaData' => array()
        );
} catch (\Exception $e) {
    ladybug_dump($e);die;
}
        foreach($items as $key => $item) {
            $temp['aaData'][] = array(
                null, //remove
                $item->Title,
                $item->Webcode,
                $item->ArticleType,
                $item->OnlineSections,
                $item->PrintSection,
                $item->PrintStory,
                $item->Prominent, //prominent
                $item->Preview,
                null,  //save
                $item->id,
                $item->LanguageId
            );
        }

        return new Response(json_encode($temp));
    }

    /**
     * @Route("/admin/print-issue-manager/remove-article/{id}", options={"expose"=true})
     */
    public function removeArticleAction(Request $request, $id)
    {
        $contextBoxId = $request->get('context_box_id');
        $service =  $this->container->get('newscoop_print_issue_manager.service');
        $contextBoxArticles = $service->getContextBoxArticleList($contextBoxId);

        foreach ($contextBoxArticles as $key => $article) {
            if ($article == $id) {
                unset($contextBoxArticles[$key]);
            }
        }

        //reorder articles
        $service->saveList($contextBoxId, $contextBoxArticles);

        return new Response(json_encode(array('code' => true)));
    }

    /**
     * @Route("/admin/print-issue-manager/save-articles", options={"expose"=true})
     */
    public function saveArticlesAction(Request $request)
    {
        $service =  $this->container->get('newscoop_print_issue_manager.service');
        $em =  $this->container->get('em');;
        if ($request->get('data')) {
            foreach ($request->get('data') as $article) {
                foreach ($article as $key => $value) {
                    $article[$article[$key]['name']] = $article[$key]['value'];
                    unset($article[$key]);
                }

                $issue = $em->getRepository('Newscoop\Entity\Article')
                    ->findOneBy(array(
                        'number'=> $article['id'],
                        'language' => $article['language']
                    ));

                if (isset($article['printsection'])) {
                    $issue->setFieldData('printsection', $article['printsection']);
                }

                if (isset($article['printstory'])) {
                    $issue->setFieldData('printstory', $article['printstory']);
                }

                if (isset($article['prominent'])) {
                    if ($article['prominent'] == '1') {
                        $issue->setFieldData('iPad_prominent', true);
                    } else {
                        $issue->setFieldData('iPad_prominent', false);
                    }
                }
            }
        } else {
            $articleNumber = $request->get('id');
            $articleLanguage = $request->get('language');
            $issue = $em->getRepository('Newscoop\Entity\Article')
                ->findOneBy(array(
                    'number'=> $articleNumber,
                    'language' => $articleLanguage
                ));

            if ($request->request->has('printsection')) {
                $issue->setFieldData('printsection', $request->get('printsection'));
            }

            if ($request->request->has('printstory')) {
                $issue->setFieldData('printstory', $request->get('printstory'));
            }

            if ($request->request->has('prominent')) {
                if ($request->get('prominent') == '1') {
                    $issue->setFieldData('iPad_prominent', true);
                } else {
                    $issue->setFieldData('iPad_prominent', false);
                }
            }
        }

        $em->flush();

        return new Response(json_encode(array('code' => true)));
    }

    /**
     * @Route("/admin/print-issue-manager/update-order", options={"expose"=true})
     */
    public function updateOrderAction(Request $request)
    {
        $service =  $this->container->get('newscoop_print_issue_manager.service');
        $contextBoxId = $request->get('content_box_id');
        $contextBoxArticles = $request->get('context_box_articles');

        //reorder articles
        if (count($contextBoxArticles) > 0) {
            $service->saveList($contextBoxId, $contextBoxArticles);
        }

        return $this->getIssueArticlesAction($request, $request->get('article_number'), $request->get('article_language'));
    }

    private function createList($relatedArticles)
    {
        $items = array();
        if (count($relatedArticles) > 0) {
            foreach ($relatedArticles as $article) {
                $relatedArticleInfo = array();
                $relatedArticle = new \stdClass();
                $relatedArticle->id = $article[0]->getNumber();
                $relatedArticle->Title = '<a href="/admin/articles/edit.php' . '?f_publication_id=' . $article[0]->getPublicationId()
                        . '&amp;f_issue_number=' . $article[0]->getIssueId() . '&amp;f_section_number=' . $article[0]->getSectionId()
                        . '&amp;f_article_number=' . $article[0]->getNumber() . '&amp;f_language_id=' . $article[0]->getLanguageId()
                        . '&amp;f_language_selected=' . $article[0]->getLanguageId().'">'.$article[0]->getTitle().'</a>';
                $relatedArticle->ArticleType = $article[0]->getType();

                try {
                    $relatedArticle->OnlineSections = $article[0]->getSection()->getName();
                    $publicationId = $article[0]->getPublicationId();
                } catch(\Doctrine\ORM\EntityNotFoundException $e) {
                    $relatedArticle->OnlineSections = null;
                    $publicationId = null;
                }

                try {
                    $relatedArticle->PrintSection = $article[0]->getData('printsection');
                } catch(\InvalidPropertyException $e) {
                    $relatedArticle->PrintSection = null;
                }

                try {
                    $relatedArticle->PrintStory = $article[0]->getData('printstory');
                } catch(\InvalidPropertyException $e) {
                    $relatedArticle->PrintStory = null;
                }

                try {
                    $relatedArticle->Prominent = $article[0]->getData('iPad_prominent');
                } catch(\InvalidPropertyException $e) {
                    $relatedArticle->Prominent = null;
                }

                $relatedArticle->LanguageId = $article[0]->getLanguageId();
                $webcodeService =  $this->container->get('webcode');
                $relatedArticle->Webcode = $webcodeService->getArticleWebcode($article[0]);

                $issueNumber = $article[0]->getIssueId();
                $sectionNumber = $article[0]->getSectionId();
                $languageId = $article[0]->getLanguageId();

                $relatedArticle->Preview = $this->generateUrl('newscoop_tageswochemobileplugin_articles_item', array(
                    'article_id' => $article[0]->getNumber(),
                    'side' => 'front',
                    'language_id' => $article[0]->getLanguageId(),
                    'allow_unpublished' => true
                ));

                $items[] = $relatedArticle;
            }
        }

        return $items;
    }
}