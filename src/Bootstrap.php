<?php

/**
 * What Behavioral Health adds to OpenEMR: a menu entry for Part 2 disclosures across patients, and a
 * card on the patient dashboard for the patient's Part 2 consents and disclosures.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth;

use OpenEMR\Events\Core\TwigEnvironmentEvent;
use OpenEMR\Events\Patient\Summary\Card\SectionEvent;
use OpenEMR\Menu\MenuEvent;
use OpenHealthcare\BehavioralHealth\Card\Part2Card;
use OpenHealthcare\Common\OpenEmr;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Loader\FilesystemLoader;

final class Bootstrap
{
    public const MODULE_INSTALLATION_PATH = '/interface/modules/custom_modules/oe-module-behavioral-health';
    /** The access a user needs for the menu entry and the pages: section and value, as AclMain checks them. */
    public const ACL = ['patients', 'disclosure'];
    public const TWIG_NAMESPACE = 'behavioralhealth';

    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function subscribeToEvents(): void
    {
        $this->eventDispatcher->addListener(MenuEvent::MENU_UPDATE, $this->addMenuItem(...));
        $this->eventDispatcher->addListener(TwigEnvironmentEvent::EVENT_CREATED, $this->addTemplates(...));
        $this->eventDispatcher->addListener(SectionEvent::EVENT_HANDLE, $this->addCard(...));
    }

    /**
     * A page of the module's, with its query.
     *
     * @param array<string, int|string> $query
     */
    public static function url(string $page, array $query = []): string
    {
        return OpenEmr::webRoot() . self::MODULE_INSTALLATION_PATH . '/public/' . $page . ($query === [] ? '' : '?' . http_build_query($query));
    }

    public function addMenuItem(MenuEvent $event): MenuEvent
    {
        $item = new \stdClass();
        $item->requirement = 0;
        $item->target = 'behavi';
        $item->menu_id = 'behavi0';
        $item->label = xlt('Part 2 Disclosures');
        $item->url = self::MODULE_INSTALLATION_PATH . '/public/index.php';
        $item->children = [];
        $item->acl_req = self::ACL;
        $item->global_req = [];
        // After Messages, or at the end.
        $menu = $event->getMenu();
        $position = count($menu);
        foreach ($menu as $i => $entry) {
            if (($entry->menu_id ?? '') === 'msg0') {
                $position = (int)$i + 1;
                break;
            }
        }
        array_splice($menu, $position, 0, [$item]);
        $event->setMenu($menu);
        return $event;
    }

    public function addTemplates(TwigEnvironmentEvent $event): void
    {
        $loader = $event->getTwigEnvironment()->getLoader();
        if ($loader instanceof FilesystemLoader && !in_array(self::TWIG_NAMESPACE, $loader->getNamespaces(), true)) {
            $loader->addPath(dirname(__DIR__) . '/templates', self::TWIG_NAMESPACE);
        }
    }

    public function addCard(SectionEvent $event): void
    {
        if ($event->getSection() !== 'primary') {
            return;
        }
        $pid = (int)($GLOBALS['pid'] ?? 0);
        if ($pid > 0) {
            $event->addCard(new Part2Card($pid));
        }
    }
}
