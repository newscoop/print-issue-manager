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

    /**
     * @Route("/admin/print-issue-manager")
     * @Template()
     */
    public function indexAction(Request $request)
    {   
        $articleService = $this->container->get('newscoop_print_issue_manager.service');
        $em = $this->container->get('em');

        $mobileIssuesArticles = $articleService->getMobileIssues();
        $latestPrintIssues = $articleService->getLatestIssues(4);

        $iPadSection = $articleService->findIpadSection();

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
}