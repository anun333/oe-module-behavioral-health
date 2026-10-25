# Changelog

## 0.2.0 (2026-10-24)

**Treatment plan reviews.**

- **Treatment plans on OpenEMR's own Care Plan form.** The goals, interventions and health concerns stay in core's form at a visit. The module records the plan's making and each review against it:
  - the person's endorsement (CCBHC criterion 4.e.2), who took part, and the signed plan in Documents;
  - each goal's wording as it read, with its progress;
  - what changed;
  - the next review date, which is never later than the clinic's interval allows.

  Core rewrites a care plan's rows on each save, so goals are kept by their wording.
- **The interval** is 180 days by default, within the CCBHC "no less frequently than every 6 months" (2.b.2). Clinics set their own, and a shorter interval can be applied to plans in progress. Rules read for this: Medicare community mental health centers every 30 days (42 CFR 485.916(d)), and state programs from monthly to yearly. The rules are listed in the openhealthcare workspace.
- **Treatment plan reviews** in the Staff Worklist (a contributed tab), for plans due within 14 days or overdue. Each due date is its own row, so a dismissal covers one review.
- **Treatment Plans** in the menu, and a dashboard card with the next review and the goals.
- Care plans in a visit marked with a sensitivity show only to users allowed it. This holds on the plan page, the card and the Worklist row.
- Works on OpenEMR 7.0 and later. The care plan's status and target date, which came in 7.0.4, are read only where they exist.
- **Checked:** unit tests for the schedule (5 new). End to end on 8.4.1 and master, 55 checks each (23 new): the tables, access, the plan and its refusals, reviews and their progress, the Worklist row when overdue, the overdue list, the card, the settings, ending a plan, and the audit log.

## 0.1.1 (2026-10-23)

- **Checked:** on fresh official 7.0.3, 8.1.0, 8.3.0 and 8.5.0 images it installs through Module Manager and its menu page loads, with every module enabled and with it alone.
- A page opened for a patient or disclosure that doesn't exist ends as a whole page with its language and title, not bare text (WCAG 2.1 AA, found by the accessibility scan).
- **README:** run OpenEMR on a PHP with PHP's php2shell fix (2026-10-21: 8.2.35, 8.3.36, 8.4.27, 8.5.12 or later). The official OpenEMR 7.0.2 and 7.0.3 Docker images can't get it.
- **FOOTPRINT.md** now comes from openhealthcare-common's footprint tool (1.1), the one every module shares; `tools/footprint.php` only loads it. The sheet's closing notes, what it can't show, are this module's own (in composer.json): the old ones were the Staff Worklist's and claimed checks of its queues.
- **Checked:** unit tests; the nightly matrix on 8.4.1 and master (32 checks each).

## 0.1.0 (2026-10-23)

**Part 2 consents and disclosures.**

- Part 2 consents recorded from the signed form and linked to it in Documents, with revocations and the end of an expiration event.
- Disclosures recorded under a consent in force, in OpenEMR's own Disclosures record with the type "Part 2: with written consent" (42 CFR 2.31(c) checked on the disclosure date).
- A cover sheet with the 42 CFR 2.32(a) statement in the regulation's words, and a copy of the consent or an explanation of its scope.
- The accounting of disclosures made with consent (2.25(a)), for 3 years or a shorter period. Its compliance date is put off (89 FR 12476).
- A patient dashboard card, the patient's page, and a menu page for recent disclosures and consents ending soon.
- **Checked:** end to end on OpenEMR 8.4.1 and master (`dev/behavioral_health_check.py` in the openhealthcare workspace, 32 checks: access, consents, disclosures in core's record and on core's Disclosures page, both cover sheets, the refusals, the accounting, the card, the audit log). On a fresh official 7.0.2 image it installs, OpenEMR's login still works, and its menu page loads.
