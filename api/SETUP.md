Kontaktformular Setup

1. Öffne `api/contact-config.php`.
2. Trage diese Werte aus Hostinger ein:
   `recipient_email`
   `smtp.host`
   `smtp.port`
   `smtp.security`
   `smtp.username`
   `smtp.password`
   `smtp.from_email`
   `smtp.reply_to_email`
3. Lade die Seite auf ein Hosting mit PHP hoch.
4. Teste das Formular einmal mit deiner eigenen E-Mail-Adresse.

Das Formular macht danach automatisch:
- hCaptcha-Verifizierung im Backend
- eine Benachrichtigung an die Feuerwehr
- eine Bestätigungs-Mail an den Absender

Frontend:
- `pages/kontakt.html`

Backend:
- `api/contact.php`
- `api/SmtpMailer.php`
- `api/contact-config.php`
