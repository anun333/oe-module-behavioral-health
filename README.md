# Behavioral Health

> **Status: 0.2.3, a pre-release.** Tested on OpenEMR with synthetic data only.

Part 2 consents and disclosures, and treatment plan reviews, for OpenEMR: for certified community behavioral health clinics, mental health and substance use centers, and offices that prescribe buprenorphine.

What installing it changes in your database, what it reads and writes, and what it hooks into are listed in [FOOTPRINT.md](FOOTPRINT.md), generated from the source.

Using the module day to day, task by task for each role: see [GUIDE.md](GUIDE.md).

## What it does

- **Consents.** Staff record each Part 2 consent from the signed form (Clinic Forms' Part 2 consent, or the clinic's own): who may receive the records, the purpose, how much and what kind of information, and when it ends, by a date or an event. The signed form stays in OpenEMR's Documents, and the record links to it. A revocation, or the day an expiration event happened, is recorded with its date.
- **Disclosures, in OpenEMR's own record.** A disclosure is recorded under a consent and written to OpenEMR's Disclosures (`extended_log`, through the same method core's Disclosures page uses), with the type "Part 2: with written consent". So it appears there with the patient's other disclosures. The module keeps a row beside it naming the consent and what went with the records.
  - A disclosure can't rely on a consent that wasn't yet signed on its date, has expired or ended, or was revoked (42 CFR 2.31(c)). Disclosures dated before a revocation still stand (2.31(a)(6)).
  - Staff confirm that the recipient and the information are the ones the consent names. The module can't read the signed form.
- **The cover sheet.** Each disclosure prints a cover sheet with the 42 CFR 2.32(a) statement the clinic chose (Statement 1, the full notice, or Statement 2, the short one) in the regulation's words. It also says either that a copy of the consent is attached or explains the consent's scope (2.32(b)). A test holds Statement 1 to the text eCFR printed on 2026-10-21.
- **The accounting of disclosures** (42 CFR 2.25(a)): every Part 2 disclosure in OpenEMR's record for the 3 years before the request, or a shorter period the patient chooses, with the date, the recipient and address, what was disclosed and the purpose. It starts from core's record, so a Part 2 disclosure entered on core's own page is included too, and the page warns when one has no purpose recorded. HHS has put off the compliance date for 2.25 until it revises the HIPAA accounting rule (89 FR 12476), so giving the accounting now is the clinic's choice.
- **Treatment plans.** The plan itself (goals, interventions, health concerns) is written in OpenEMR's own Care Plan form at a visit; the module doesn't copy it. What the module adds is the review schedule SAMHSA's CCBHC criteria ask for:
  - Staff record the plan from that care plan form: the date, whether the person endorsed it (signed, agreed and noted, did not agree, or could not take part), who took part, and the signed plan in Documents. The paper plan to sign is Clinic Forms' Behavioral Health Treatment Plan.
  - Each review, or update after a change, keeps each goal's wording as it read that day, with its progress (met, partly met, not met, changed), what changed, and the next review date.
  - The next review is never later than the clinic's interval allows. That interval is 180 days by default: the criteria say "no less frequently than every 6 months, unless the state, federal, or applicable accreditation standards are more stringent" (2.b.2). Clinics set their own in the settings, for example 30 days for a Medicare community mental health center (42 CFR 485.916(d)). A shorter interval can be applied to plans already in progress.
  - Core's Care Plan form rewrites its rows on each save, so the module keeps the goals' wording, not row numbers.
  - **Treatment plan reviews** in the Staff Worklist (when it is installed) lists each plan whose review is due within 14 days (a setting) or overdue. Recording the review clears it. **Treatment Plans** in the menu lists plans in progress, soonest review first, and a dashboard card shows the next review and the goals.
- **Where it shows:** a card on the patient dashboard (collapsed for patients with no Part 2 consent or disclosure), the patient's page, and **Part 2 Disclosures** in the menu: disclosures in the last 30 days across patients, and consents ending in the next 30 days.

## Who can open what

The same access core's Disclosures page asks for, in OpenEMR's access control:

- `patients/disclosure` to see consents, disclosures, cover sheets and the accounting;
- `write` or `addonly` on it to record a consent or a disclosure;
- `write` to record a revocation or the end of a consent.

For treatment plans, the access core uses for the medical record:

- `patients/med` to see plans and the list;
- `write` or `addonly` on it to record a plan or a review;
- `write` to end a plan.

Care plans in a visit marked with a sensitivity show only to users core allows that sensitivity. The settings are for administrators (`admin/super`).

Every page view and change is in the audit log.

## Not done yet

- State rules beyond the review interval: who must sign, and how soon the first plan is due after admission, differ by state and program (Texas 10 business days for community mental health, Medicare community mental health centers 7 working days). The module records who took part and the endorsement, and doesn't check either against a state's list.
- Segmenting Part 2 records from the rest of the chart. That is access control, and it isn't this module's.
- The accounting of treatment, payment and health care operations disclosures made through an EHR (2.25(b)), which waits on the same HIPAA rule.

## Install

From the OpenEMR directory:

```
composer require anun333/oe-module-behavioral-health
```

The installer plugin places it in `interface/modules/custom_modules/oe-module-behavioral-health/`, and Composer brings [openhealthcare-common](https://github.com/anun333/openhealthcare-common) with it. Without Composer, copy this directory there and run `composer install --no-dev` in it, which puts openhealthcare-common in its own `vendor/`. The module stays switched off until it finds that package one way or the other.

Then go to Modules › Manage Modules › Unregistered, and **Register**, **Install** and **Enable** it. Installing adds two tables of its own and the "Part 2: with written consent" disclosure type.

## Compatibility

**PHP security.** PHP's php2shell advisory (2026-10-21; CVE-2026-109618 and CVE-2026-109619) is remote code execution without logging in, and it is being exploited. Run OpenEMR on PHP 8.2.35, 8.3.36, 8.4.27, 8.5.12 or later, whatever the module needs. The official OpenEMR 7.0.2 and 7.0.3 Docker images can't get that fix: they carry PHP 8.2.16 and 8.3.15 on Alpine releases that no longer get updates. Run 7.0 only on a patched PHP, or upgrade OpenEMR. OpenEMR rebuilt its 8.4.1, 8.3.0, 8.2.0 and 8.0.0.3 images with the fix on 2026-10-22 (forum t/27157); a container started before then needs pulling again.

Written for OpenEMR 7.0 and later through [openhealthcare-common](https://github.com/anun333/openhealthcare-common)'s compatibility layer; its bootstrap never stops a page on any version. The releases it was checked on are in the [CHANGELOG](CHANGELOG.md).

## Development

```
composer install
vendor/bin/phpunit
php tools/footprint.php --check
```

## Sources

- 42 CFR Part 2 as amended by the 2024 final rule (89 FR 12472, February 16, 2024), read on eCFR as of 2026-10-21: §2.31 (consent requirements), §2.32 (notice and copy of consent to accompany disclosure) and §2.25 (accounting of disclosures).
- 45 CFR 164.528(b)(2), for what each accounting entry holds.

## Other modules for OpenEMR

See [openhealthcare-common](https://github.com/anun333/openhealthcare-common) for the modules that share it.

## License

GPL-3.0-or-later, as OpenEMR. See [LICENSE](LICENSE).
