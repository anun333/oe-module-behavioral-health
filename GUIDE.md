# Behavioral Health: staff guide

For the staff who use the module day to day, task by task. What the rules are and why, and how to install it, are in the [README](README.md).

The module records Part 2 consents and disclosures, and the review schedule of each treatment plan. It doesn't read signed forms or send records: staff check the form and send the records themselves. It has been tested only on made-up patients.

## Medical records staff: Part 2 consents and disclosures

### Record a consent

Scan the signed consent into Documents first: Clinic Forms' Part 2 consent, or your own form.

*Where:* the patient's dashboard, the Part 2 card › **Consents and disclosures**, then **Record a consent**.

1. Enter **Who may receive the records (name or general designation)**, and **Their address, if known**.
2. Enter the **Purpose**, and **How much and what kind of information** the consent covers, as the form says.
3. Set **Signed on**.
4. Set when it ends: either **Expires on**, or **Or the event that ends it (for example, end of treatment)**.
5. Choose the scanned form under **Signed form in Documents**, add a **Note** if useful, and choose **Record the consent**.

### Record a disclosure

*Where:* the patient's Part 2 page, under **Record a disclosure**.

1. Choose the consent it relies on, **Under the consent**. A consent that wasn't yet signed on that date, has expired or ended, or was revoked can't be used.
2. Set **Date disclosed** and describe **What was disclosed**.
3. Choose the **Statement sent with the records (42 CFR 2.32(a))**, and what else went with them under **Also sent with the records (42 CFR 2.32(b))**.
4. Check the records and recipient against the signed form, tick **The recipient and the information are the ones the consent names**, and choose **Record the disclosure**.
5. Choose **Print the cover sheet to send with the records**, and send it with them.

The disclosure is also written to OpenEMR's own Disclosures record. The **Part 2 disclosures** list on the page keeps each one, with **Print** for its cover sheet again.

### Record a revocation, or the end of a consent

*Where:* the patient's Part 2 page, the consent's row › **Record a change**.

Set the date. Choose **Revoked** if the patient revoked it, or **Event happened** when the event that ends it has happened. Disclosures made before a revocation still stand.

### Give a patient their accounting of disclosures

*Where:* the patient's Part 2 page › **Accounting of disclosures**.

Choose **From** (3 years back by default, or a shorter period the patient asks for) and **Show**, then **Print**. If the page says some disclosures were entered on OpenEMR's Disclosures page with no consent linked, add their purpose there before giving it to the patient.

### See recent disclosures and consents ending soon

*Where:* the main menu › **Part 2 Disclosures**.

**Disclosures in the last 30 days** across patients, and **Consents ending in the next 30 days**.

## Clinicians: treatment plans

### Record a treatment plan

Write the goals, interventions and health concerns in the Care Plan form of a visit first. The paper plan for the person to sign is the Behavioral Health Treatment Plan in Clinic Forms; scan the signed copy into Documents.

*Where:* the patient's dashboard, the treatment plan card › **Treatment plan**, then **Record the plan**.

1. Under **Care plan it reads**, choose the visit's care plan and **Show**.
2. Set the **Date the plan was made**.
3. Choose how the plan was **Endorsed by the person**: **Signed by the person**, **Agreed by the person, and noted**, **The person did not agree**, or **The person could not take part**.
4. Enter **Who took part (the person, family or guardian as they wish, the team)**.
5. Leave **Next review by** blank for the latest the clinic allows, or set an earlier date.
6. Choose the **Signed plan in Documents**, add **Notes** if useful, and choose **Record the plan**.

### Record a review

*Where:* the patient's treatment plan page, under **Record a review**.

1. Update the visit's Care Plan form first if the plan changed, then choose it under **Care plan it reads** and **Show**.
2. Set the **Date of the review**, and the **Kind of review**: **Review**, or **Update after a change**.
3. Under **Progress toward each goal**, mark each one **Met**, **Partly met**, **Not met** or **Changed**.
4. Fill in **Endorsed by the person**, who took part, and **What changes in the plan**.
5. Choose **Record the review**. The next review is set no later than the clinic's interval allows; the page says when.

### End a plan

*Where:* the patient's treatment plan page › **End the plan**.

Set **Ended on** and **Why** (discharge, transfer, moved), and choose **End the plan**.

### Find reviews due

*Where:* the main menu › **Treatment Plans**: plans in progress, soonest review first. Filter by **All**, overdue, due soon or later.

If the Staff Worklist module is installed, its **Treatment plan reviews** tab under **Clinician** lists each plan whose review is due soon or overdue. Recording the review clears it.

## The administrator

### Set the review interval

*Where:* **Treatment Plans** › **Settings** (administrators only).

1. Set **The longest a plan may go between reviews, in days**. 180 days keeps within the CCBHC criteria's 6 months; your state, payer or accreditor may ask for less. Use the shortest rule that applies to you.
2. Tick **Apply this to plans already in progress (a shorter interval brings their next review forward)** if the change should reach current plans.
3. Set **Show a review in the Staff Worklist this many days before it is due**, and choose **Save**.
