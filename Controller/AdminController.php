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

        $iPadLinkParams = array();
        foreach ($iPadSection as $value) {
            $iPadLinkParams['publicationId'] = $value->getIssue()->getPublicationId();
            $iPadLinkParams['issueId'] = $value->getIssue()->getNumber();
            $iPadLinkParams['number'] = $value->getNumber();
            $iPadLinkParams['languageId'] = $value->getLanguageId();
        }

        return array(
            'iPadAdName' => self::IPAD_AD,
            'typeIPadExists' => $typeIPadExists,
            'typeMobileExists' => $typeMobileExists,
            'issues' => $mobileIssuesArticles,
            'latestPrintIssues' => $latestPrintIssues,
            'iPadLinkParams' => $iPadLinkParams,

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
        foreach($mergedArticles as $article) {
            if (in_array($article->getSection()->getIssue()->getNumber(), $allowedIssues) || ($article->getType() == 'iPad_Ad')) {
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
       if (!$articleNumber) {
            $articleNumber = $request->get('article_number');
        }

        if (!$articleLanguage) {
            $articleLanguage = $request->get('article_language');
        }
        
        $service =  $this->container->get('newscoop_print_issue_manager.service');
        $em =  $this->container->get('em');
        //article number in this case is issue id
        $issue = $em->getRepository('Newscoop\Entity\Article')
            ->findOneBy(array(
                'type' => self::MOBILE_ISSUE,
                'number'=> $articleNumber,
                'language' => $articleLanguage
            ));

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
                        . '&amp;f_issue_number=' . $issue->getIssue()->getNumber() . '&amp;f_section_number=' . $issue->getSection()->getNumber()
                        . '&amp;f_article_number=' . $issue->getNumber() . '&amp;f_language_id=' . $issue->getLanguageId()
                        . '&amp;f_language_selected=' . $issue->getLanguageId()
                ),
            'contextBox' => $contextBox->getId(),
            'aaData' => array()
        );

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
            foreach($request->get('data') as $article) {
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
                $relatedArticle->id = $article->getNumber();
                $relatedArticle->Title = '<a href="/admin/articles/edit.php' . '?f_publication_id=' . $article->getPublicationId()
                        . '&amp;f_issue_number=' . $article->getSection()->getIssue()->getNumber() . '&amp;f_section_number=' . $article->getSection()->getNumber()
                        . '&amp;f_article_number=' . $article->getNumber() . '&amp;f_language_id=' . $article->getLanguageId()
                        . '&amp;f_language_selected=' . $article->getLanguageId().'">'.$article->getTitle().'</a>';
                $relatedArticle->ArticleType = $article->getType();
                
                try {
                    $relatedArticle->OnlineSections = $article->getSection()->getName();
                    $publicationId = $article->getPublicationId();
                } catch(\Doctrine\ORM\EntityNotFoundException $e) {
                    $relatedArticle->OnlineSections = null;
                    $publicationId = null;
                }

                try {
                    $relatedArticle->PrintSection = $article->getData('printsection');
                } catch(\InvalidPropertyException $e) {
                    $relatedArticle->PrintSection = null;
                }

                try {
                    $relatedArticle->PrintStory = $article->getData('printstory');
                } catch(\InvalidPropertyException $e) {
                    $relatedArticle->PrintStory = null;
                }

                try {
                    $relatedArticle->Prominent = $article->getData('iPad_prominent');
                } catch(\InvalidPropertyException $e) {
                    $relatedArticle->Prominent = null;
                }

                $relatedArticle->LanguageId = $article->getLanguageId();
                $webcodeService =  $this->container->get('webcode');
                $relatedArticle->Webcode = $webcodeService->getArticleWebcode($article);

                $issueNumber = $article->getIssue()->getNumber();
                $sectionNumber = $article->getSection()->getNumber();
                $languageId = $article->getLanguageId();

                $relatedArticle->Preview = $this->generateUrl('newscoop_tageswochemobileplugin_articles_item', array(
                    'article_id' => $article->getNumber(),
                    'side' => 'front',
                    'language_id' => $article->getLanguageId(),
                    'allow_unpublished' => true
                ));

                $items[] = $relatedArticle;
            }
        }

        return $items;
    }
}