# Changelog

## 0.1.1 (unreleased)

- A page opened for a patient or disclosure that doesn't exist ends as a whole page with its language and title, not bare text (WCAG 2.1 AA, found by the accessibility scan).

## 0.1.0 (2026-10-23)

**Part 2 consents and disclosures.**

- Part 2 consents recorded from the signed form and linked to it in Documents, with revocations and the end of an expiration event.
- Disclosures recorded under a consent in force, in OpenEMR's own Disclosures record with the type "Part 2: with written consent" (42 CFR 2.31(c) checked on the disclosure date).
- A cover sheet with the 42 CFR 2.32(a) statement in the regulation's words, and a copy of the consent or an explanation of its scope.
- The accounting of disclosures made with consent (2.25(a)), for 3 years or a shorter period. Its compliance date is put off (89 FR 12476).
- A patient dashboard card, the patient's page, and a menu page for recent disclosures and consents ending soon.
- **Checked:** end to end on OpenEMR 8.4.1 and master (`dev/behavioral_health_check.py` in the openhealthcare workspace, 32 checks: access, consents, disclosures in core's record and on core's Disclosures page, both cover sheets, the refusals, the accounting, the card, the audit log). On a fresh official 7.0.2 image it installs, OpenEMR's login still works, and its menu page loads.
