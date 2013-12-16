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

    /**
     * @Route("/admin/print-issue-manager")
     * @Template()
     */
    public function indexAction(Request $request)
    {   
        $service = $this->container->get('newscoop_print_issue_manager.service');
        $em = $this->container->get('em');

        $mobileIssuesArticles = $service->getMobileIssues(self::MOBILE_ISSUE);
        $latestPrintIssues = $service->getLatestIssues(4);

        $iPadSection = $service->findIpadSection();

        krsort($mobileIssuesArticles);
        $_SESSION['pim_allow_unpublished'] = true;

        $iPadLinkParams = array();
        foreach ($iPadSection as $value) {
            $iPadLinkParams['publicationId'] = $value->getIssue()->getPublicationId();
            $iPadLinkParams['issueId'] = $value->getIssue()->getNumber();
            $iPadLinkParams['number'] = $value->getNumber();
            $iPadLinkParams['languageId'] = $value->getLanguageId();
        }

        return array(
            'issues' => $mobileIssuesArticles,
            'latestPrintIssues' => $latestPrintIssues,
            'iPadLinkParams' => $iPadLinkParams,
        );
    }

    /**
     * @Route("/admin/print-issue-manager/load-articles")
     */
    public function loadArticlesAction(Request $request)
    {
        $em = $this->container->get('em');
        $user = $this->container->get('user');
        $service =  $this->container->get('newscoop_print_issue_manager.service');
        $printdeskUser = $user->findOneBy(array('username' => 'printdesk'));
        var_dump($request->get('context_box_id'));die;
        try {
            $existingArticles = array_values($service->getContextBoxArticleList($request->get('context_box_id')));
        } catch (\Exception $e) {
            return new \Exception('Error occured, "id" value is empty.');
        }
die;
        // get last or two issues numbers
        $latestIssues = $_POST['issues_range'];
        foreach ($latestIssues as $issue) {
            list($issueId, $language) = explode('_', $issue);
            $allowedIssues[] = $issueId;
        }

        if (empty($printdeskUser) || (!$printdeskUser)) {
            new Response(json_encode(array()));
        }

        // those are all articles of type news or pending with creator PrintDesk
        $printDescArticles = Article::GetArticles(null, null, null, null, null, false, array(
            '(type = "news" OR type = "pending")',
            'IdUser = '.$printdeskUser->getUserId()
        ));

        // switch print active within a fixed daterange
        $printArticles = Article::GetList(array(
            new ComparisonOperation('print', new Operator('is'), true)
        ), null, null, 0, $count);

        // plus all articles of type iPad_Ad with switch active on.
        $ipadArticles = Article::GetList(array(
            new ComparisonOperation('type', new Operator('is', 'string'), 'iPad_Ad'),
            new ComparisonOperation('active', new Operator('is'), true),
            new ComparisonOperation('weekly_issue', new Operator('is'), true),
        ), null, null, 0, $count);


        $mergedArticles = array_merge($printDescArticles, $printArticles, $ipadArticles);
        foreach($mergedArticles as $article) {
            if (in_array($article->getIssueNumber(), $allowedIssues) || ($article->getType() == 'iPad_Ad')) {
                $existingArticles[] = $article->getNumber();
            }
        }

        $existingArticles = array_unique($existingArticles);
        ContextBoxArticle::saveList($_POST['context_box_id'], $existingArticles);
        $this->getissuearticlesAction($_POST['article_number'], $_POST['article_language']);
    }

    /**
     * @Route("/admin/print-issue-manager/get-issue-articles")
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

        $contextBox = $service->getContextBoxByIssueId($issue->getId());
        $relatedArticles = $service->getRelatedArticles($issue);
        $items = $service->createList($relatedArticles);

        $temp = array(
            'issue' => array(
                'number' => $issue->getNumber(),
                'language' => $issue->getLanguageId(),
                'date' => $issue->getDate(),
                'title' => $issue->getTitle(),
                'status' => $issue->getWorkflowStatus(),
                //'freeze' => $issue->getData('freeze'),
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
     * @Route("/admin/print-issue-manager/remove-article")
     */
    public function removeArticleAction(Request $request)
    {

    }

    /**
     * @Route("/admin/print-issue-manager/save-articles")
     */
    public function saveArticlesAction(Request $request)
    {

    }

    /**
     * @Route("/admin/print-issue-manager/update-order")
     */
    public function updateOrderAction(Request $request)
    {

    }
}