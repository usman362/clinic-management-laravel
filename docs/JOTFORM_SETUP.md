# JotForm Consent Form Setup Guide

This guide explains how to configure JotForm so that consent forms work correctly within the booking flow.

---

## Step 1: Create Your JotForm Form

1. Go to [JotForm.com](https://www.jotform.com) and create your consent form
2. Add all required fields (name, signature, agreement checkboxes, etc.)
3. Save the form

## Step 2: Configure the Thank You Page (CRITICAL)

This is the most important step. **Do NOT use "Redirect to external link".**

1. In JotForm, go to **SETTINGS** tab (top bar)
2. Click **THANK YOU PAGE** in the left sidebar
3. Select: **"Show a Thank You Page after submission"**
4. **DO NOT** select "Redirect to an external link after submission"
5. Write a message like:

   > Thank you! Your consent form has been signed and submitted successfully.
   > You may close this window and continue with your booking.

6. Save settings

### Why This Matters

The booking page has built-in JavaScript that automatically detects when you complete the JotForm. It:
- Shows a green "Form Signed" badge next to the doctor's consent form
- Records the consent in the system automatically via AJAX
- Creates a document record in the patient's file

If you use "Redirect to external link" instead, the iframe navigates away from JotForm, the JavaScript detection breaks, and you see a raw JSON error page.

## Step 3: Get the Form URL

1. In JotForm, go to the **PUBLISH** tab
2. Copy the **Direct Link** URL (e.g., `https://form.jotform.com/260685612688065`)
3. This is the URL you'll paste into the doctor's profile in the clinic system

## Step 4: Add the JotForm Link to a Doctor's Profile

1. Log in as **Admin** (Super Admin)
2. Go to **Doctors** in the sidebar
3. Click **Edit** on the doctor
4. Find the **JotForm Link** field
5. Paste the JotForm URL (the direct link from Step 3)
   - You can paste either:
     - The direct URL: `https://form.jotform.com/260685612688065`
     - Or the full embed code: `<iframe src="https://form.jotform.com/260685612688065" ...></iframe>`
   - The system automatically extracts the URL from iframe embed codes
6. Click **Save**

## Step 5: Test the Flow

1. Log in as a **Patient**
2. Go to **Packages** and open a booking
3. Navigate to **Step 3: Consent**
4. You should see one consent form per doctor in the package
5. Fill in the form and click **Submit**
6. You should see:
   - JotForm's built-in "Thank You" message inside the iframe
   - A green "Form Signed" badge appears next to the doctor's name
7. Once all consent forms are signed, the "I confirm..." checkbox becomes clickable
8. Continue with the booking

## Troubleshooting

### "I see a JSON error page after submitting the form"
- You have "Redirect to external link" enabled in JotForm
- Fix: Go to JotForm SETTINGS > THANK YOU PAGE > Select "Show a Thank You Page"

### "The consent form doesn't appear"
- The doctor doesn't have a JotForm link set
- Fix: Admin > Doctors > Edit > Add JotForm Link

### "The green badge doesn't appear after signing"
- Check browser console (F12) for JavaScript errors
- The JotForm may be blocking cross-origin messages
- Try: Reload the page and sign again

### "I want the signed PDF stored in the patient's documents"

The system records a consent document entry when the form is signed (visible in the patient's Documents tab as "Consent Form - Dr. Name (signed) [JotForm #12345]"). The JotForm submission ID is stored for reference.

**To get the actual signed PDF, configure JotForm's built-in PDF feature:**

1. In JotForm Form Builder, click **SETTINGS** (top bar)
2. Click **DOCUMENTS** in the left sidebar  
3. Click **"+ Create Document"**
4. Choose a template or use "Submission PDF"
5. This auto-generates a PDF of each submission

**To email the PDF to the patient automatically:**

1. In JotForm **SETTINGS** > **EMAILS**
2. Click **"Autoresponder Email"** (sends to the submitter)
3. Enable **"Attach submission PDF"** toggle
4. The patient receives the signed form PDF by email automatically

**To download PDFs manually (admin):**

1. Go to JotForm.com > My Forms > Select the form
2. Click **Submissions** tab
3. Find the submission by the ID shown in the patient's Documents tab
4. Click the submission > **Download PDF**

> **Note:** Automatic PDF download into the clinic system requires JotForm API integration (API key in `.env`). This is not currently configured. The submission reference ID allows manual lookup until API integration is implemented.

---

## Architecture Note

The consent flow works like this:

```
Patient opens booking page
  -> Iframe loads JotForm with ?appointment_id=X&doctor_id=Y
  -> Patient fills and submits form
  -> JotForm shows "Thank You" page (stays in iframe)
  -> JotForm fires postMessage event to parent window
  -> Our booking.js detects the event
  -> JS calls POST /api/consent-webhook with appointment_id + doctor_id
  -> Server creates Document record in patient's file
  -> JS shows green "Form Signed" badge
```

The webhook endpoint requires authentication (the patient must be logged in) and verifies that the appointment belongs to the logged-in patient.
