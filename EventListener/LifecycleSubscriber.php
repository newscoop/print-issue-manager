<?php
/**
 * @package Newscoop\PrintIssueManagerBundle
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 * @copyright 2013 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\PrintIssueManagerBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Newscoop\EventDispatcher\Events\GenericEvent;

/**
 * Event lifecycle management
 */
class LifecycleSubscriber implements EventSubscriberInterface
{
    private $em;

    private $artcileTypeService;

    public function __construct($container) {
        $this->em = $container->get('em');
        $this->artcileTypeService = $container->get('newscoop_print_issue_manager.article_type.service');
    }

    public function install(GenericEvent $event)
    {
        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->updateSchema($this->getClasses(), true);

        // Generate proxies for entities
        $this->em->getProxyFactory()->generateProxyClasses($this->getClasses(), __DIR__ . '/../../../../library/Proxy');

        // Create articletypes
        $this->artcileTypeService->create('mobile_issue');
        $this->artcileTypeService->create('iPad_Ad');
    }

    public function update(GenericEvent $event)
    {
        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->updateSchema($this->getClasses(), true);

        // Generate proxies for entities
        $this->em->getProxyFactory()->generateProxyClasses($this->getClasses(), __DIR__ . '/../../../../library/Proxy');

        // Create articletypes
        $this->artcileTypeService->create('mobile_issue');
        $this->artcileTypeService->create('iPad_Ad');
    }

    public function remove(GenericEvent $event)
    {
        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->dropSchema($this->getClasses(), true);

        // Remove articletypes
        $this->artcileTypeService->remove('iPad_Ad');
        $this->artcileTypeService->remove('mobile_issue');
    }

    public static function getSubscribedEvents()
    {
        return array(
            'plugin.install.newscoop_print_issue_manager_bundle' => array('install', 1),
            'plugin.update.newscoop_print_issue_manager_bundle' => array('update', 1),
            'plugin.remove.newscoop_print_issue_manager_bundle' => array('remove', 1),
        );
    }

    private function getClasses(){
        return array();
    }
}