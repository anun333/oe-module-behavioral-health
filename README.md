# Behavioral Health

> **Status: 0.1.0, a pre-release.** Tested on OpenEMR with synthetic data only.

Part 2 consents and disclosures for OpenEMR, for programs whose substance use disorder records fall under 42 CFR Part 2: certified community behavioral health clinics, mental health and substance use centers, and offices that prescribe buprenorphine. Treatment plans come in 0.2.0.

What installing it changes in your database, what it reads and writes, and what it hooks into are listed in [FOOTPRINT.md](FOOTPRINT.md), generated from the source.

## What it does

- **Consents.** Staff record each Part 2 consent from the signed form (Clinic Forms' Part 2 consent, or the clinic's own): who may receive the records, the purpose, how much and what kind of information, and when it ends, by a date or an event. The signed form stays in OpenEMR's Documents, and the record links to it. A revocation, or the day an expiration event happened, is recorded with its date.
- **Disclosures, in OpenEMR's own record.** A disclosure is recorded under a consent and written to OpenEMR's Disclosures (`extended_log`, through the same method core's Disclosures page uses), with the type "Part 2: with written consent". So it appears there with the patient's other disclosures. The module keeps a row beside it naming the consent and what went with the records.
  - A disclosure can't rely on a consent that wasn't yet signed on its date, has expired or ended, or was revoked (42 CFR 2.31(c)). Disclosures dated before a revocation still stand (2.31(a)(6)).
  - Staff confirm that the recipient and the information are the ones the consent names. The module can't read the signed form.
- **The cover sheet.** Each disclosure prints a cover sheet with the 42 CFR 2.32(a) statement the clinic chose (Statement 1, the full notice, or Statement 2, the short one) in the regulation's words. It also says either that a copy of the consent is attached or explains the consent's scope (2.32(b)). A test holds Statement 1 to the text eCFR printed on 2026-10-21.
- **The accounting of disclosures** (42 CFR 2.25(a)): every Part 2 disclosure in OpenEMR's record for the 3 years before the request, or a shorter period the patient chooses, with the date, the recipient and address, what was disclosed and the purpose. It starts from core's record, so a Part 2 disclosure entered on core's own page is included too, and the page warns when one has no purpose recorded. HHS has put off the compliance date for 2.25 until it revises the HIPAA accounting rule (89 FR 12476), so giving the accounting now is the clinic's choice.
- **Where it shows:** a card on the patient dashboard (collapsed for patients with no Part 2 consent or disclosure), the patient's page, and **Part 2 Disclosures** in the menu: disclosures in the last 30 days across patients, and consents ending in the next 30 days.

## Who can open what

The same access core's Disclosures page asks for, in OpenEMR's access control:

- `patients/disclosure` to see consents, disclosures, cover sheets and the accounting;
- `write` or `addonly` on it to record a consent or a disclosure;
- `write` to record a revocation or the end of a consent.

Every page view and change is in the audit log.

## Not done yet

- Treatment plans with goals, objectives and review dates on core's `care_plan` form, and a Worklist tab for plans due for review (0.2.0).
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
