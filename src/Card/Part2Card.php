<?php

/**
 * The Part 2 card on the patient dashboard: the patient's consents in force and their latest Part 2
 * disclosure. Core shows a card only to users who pass its access check, here patients/disclosure,
 * the access core's own Disclosures page asks for. A patient with no Part 2 consent or disclosure
 * gets the card collapsed.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    anun333 <anun333@posteo.net>
 * @copyright Copyright (c) 2026 anun333 <anun333@posteo.net>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenHealthcare\BehavioralHealth\Card;

use OpenEMR\Events\Patient\Summary\Card\CardModel;
use OpenHealthcare\BehavioralHealth\Bootstrap;
use OpenHealthcare\BehavioralHealth\Labels;
use OpenHealthcare\BehavioralHealth\Repository\ConsentRepository;
use OpenHealthcare\BehavioralHealth\Repository\DisclosureRepository;

final class Part2Card extends CardModel
{
    public function __construct(int $pid)
    {
        $vars = self::variables($pid);
        parent::__construct([
            'acl' => Bootstrap::ACL,
            'initiallyCollapsed' => $vars['consents'] === 0 && $vars['last'] === '',
            'add' => false,
            'edit' => false,
            'collapse' => true,
            'templateFile' => '@' . Bootstrap::TWIG_NAMESPACE . '/card.html.twig',
            'identifier' => 'behavioral_health_part2',
            'title' => xl('Part 2 Consents and Disclosures'),
            'templateVariables' => $vars,
        ]);
    }

    /** @return array{consents: int, inForce: list<array{recipient: string, ends: string}>, last: string, pageUrl: string} */
    private static function variables(int $pid): array
    {
        $today = new \DateTimeImmutable('today');
        $consents = (new ConsentRepository())->forPatient($pid);
        $inForce = [];
        foreach ($consents as $c) {
            if ($c->inForceOn($today)) {
                $inForce[] = ['recipient' => $c->recipient, 'ends' => $c->expiresOn !== null ? Labels::date($c->expiresOn) : $c->expirationEvent];
            }
        }
        $last = (new DisclosureRepository())->forPatient($pid)[0] ?? null;
        return [
            'consents' => count($consents),
            'inForce' => $inForce,
            'last' => $last === null ? '' : Labels::date($last->date) . ' · ' . $last->recipient,
            'pageUrl' => Bootstrap::url('patient.php', ['pid' => $pid]),
        ];
    }
}
