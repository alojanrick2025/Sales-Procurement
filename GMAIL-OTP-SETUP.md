# Email OTP (2FA) Setup with Gmail

Two-factor authentication emails a 6-digit code (valid for 2 minutes) to the
user's account email. The system needs a way to send email from your Gmail.

Choose **one** option:

| Option | Works on Render free plan | Works on local PC |
| :--- | :--- | :--- |
| A. Gmail relay (Google Apps Script) | Yes | Yes |
| B. Gmail SMTP + App Password | No (Render free blocks SMTP) | Yes |

---

## Option A: Gmail relay (recommended)

A tiny script runs in your Google account and sends the email from your Gmail.

1. Go to <https://script.google.com> and sign in with the Gmail account that should send the codes.
2. Click **New project**. Rename it to `OTP Mailer`.
3. Delete everything in the editor and paste:

   ```javascript
   function doPost(e) {
     var data = JSON.parse(e.postData.contents);
     var secret = PropertiesService.getScriptProperties().getProperty('SECRET');
     if (!secret || data.secret !== secret) {
       return reply({ ok: false, error: 'unauthorized' });
     }
     MailApp.sendEmail({
       to: data.to,
       subject: data.subject,
       body: data.text,
       htmlBody: data.html,
       name: data.fromName
     });
     return reply({ ok: true });
   }

   function reply(obj) {
     return ContentService.createTextOutput(JSON.stringify(obj))
       .setMimeType(ContentService.MimeType.JSON);
   }
   ```

4. Click **Save** (disk icon).
5. Set a secret password for the script:
   - Click **Project Settings** (gear icon, left side).
   - Scroll to **Script Properties** → **Add script property**.
   - Property: `SECRET`  Value: a long random password (e.g. 40 random letters/numbers). Keep it — you need it in step 8.
   - Click **Save script properties**.
6. Click **Deploy** → **New deployment** → gear icon → **Web app**.
   - Execute as: **Me**
   - Who has access: **Anyone**
   - Click **Deploy**, then **Authorize access** and allow the permissions
     (if you see "Google hasn't verified this app", click **Advanced** → **Go to OTP Mailer**).
7. Copy the **Web app URL** (ends with `/exec`).
8. In **Render** → your service → **Environment**, add:

   ```
   GMAIL_SCRIPT_URL    = https://script.google.com/macros/s/xxxx/exec
   GMAIL_SCRIPT_SECRET = (the SECRET value from step 5)
   ```

   Save — Render redeploys automatically.

Gmail allows about 100 emails per day this way on a free account.

---

## Option B: Gmail SMTP with an App Password (local PC)

1. Turn on **2-Step Verification** for your Google account: <https://myaccount.google.com/security>
2. Create an App Password: <https://myaccount.google.com/apppasswords> (name it `Sales System`). Copy the 16-character password.
3. Add to `.env.local` (local) or your server's environment:

   ```
   SMTP_USER = yourname@gmail.com
   SMTP_PASS = abcd efgh ijkl mnop
   ```

   Optional: `SMTP_HOST` (default `smtp.gmail.com`), `SMTP_PORT` (default `587`), `MAIL_FROM_NAME`.

---

## Turning on 2FA

1. Make sure every user has their correct Gmail address in **User Management**.
2. Go to **System Information** → **Two-Factor Authentication (2FA)** → click the switch.
3. Click **Send Code**, enter the code from your email, click **Turn On**.

## Locked out?

If email stops working while 2FA is on, turn it off directly in the database (TiDB SQL Editor):

```sql
UPDATE test.system_info SET meta_value = '0' WHERE meta_field = 'two_factor_enabled';
```
