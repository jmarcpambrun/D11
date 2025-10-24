# Mail Box Management Module

**Mail Box Management** is a Drupal module designed to provide robust email
management functionalities. It enables interaction with IMAP servers,
allowing users to efficiently handle their mailboxes directly from the
Drupal interface.

## Features

- **Connecting to IMAP Servers**: Seamlessly connect to your email
    accounts using the IMAP protocol.
- **Listing Emails**: View emails in your mailboxes with ease.
- **Creating Mailboxes**: Create new mailboxes directly from your Drupal site.
- **Deleting Emails**: Remove unwanted emails with a simple interface.
- **Flagging Emails**: Mark important emails for better organization.
- **Reading Email Content**: View email content within the Drupal environment.
- **Loading Attachments**: Access and download attachments from emails.
- **Sending Emails**: Compose and send emails directly from your site.
- **Replying to Emails**: Respond to emails without leaving your site.

## Requirements

The module requires the following:

- **PHP**: Version 8.2 or higher.

### Drupal Dependencies

The following Drupal modules are required for this module to function:

- [**Mailsystem**](https://www.drupal.org/project/mailsystem): Provides integration with different email sending
  libraries.
- [**SMTP Authentication Support**](https://www.drupal.org/project/smtp): Allows sending emails through SMTP
  servers.
- [**PHPMailer SMTP**](https://www.drupal.org/project/phpmailer_smtp): A library for sending emails using PHPMailer.

## Installation

1. Ensure your site meets the PHP and Drupal dependency requirements.
2. Download and enable the required dependencies:
   ```bash
   composer require drupal/mailsystem drupal/smtp drupal/phpmailer_smtp
   drush en mailsystem smtp phpmailer_smtp
   ```
3. Download the **Mail Box Management** module:
   ```bash
   composer require drupal/mail_box_management
   ```
4. Enable the module:
   ```bash
   drush en mail_box_management
   ```

## Configuration

1. Navigate to the module's configuration page: `Admin > Configuration >
   Mail Box Management Configurations`.
2. Set up your IMAP server details, including:
  - Hostname
  - Port
  - Encryption type (SSL/TLS)
  - Login credentials
3. Configure SMTP settings if not already set up via the SMTP module.
4. Save the configuration and test the connection.

## Usage

### Connecting to an IMAP Server
1. Go to the Mail Box Management dashboard.
2. Enter your IMAP server credentials and connect.

### Managing Emails
- **List Emails**: View your emails categorized by mailbox.
- **Create Mailbox**: Use the "Create Mailbox" button to add a new mailbox.
- **Delete Emails**: Select unwanted emails and click "Delete".
- **Flag Emails**: Use the "Flag" option to mark emails as important.
- **Read Emails**: Click on an email to view its content.
- **Download Attachments**: Open an email and click on the attachment
    link to download it.

### Sending and Replying to Emails
1. Navigate to the compose email interface.
2. Fill out the recipient, subject, and message body.
3. Add attachments if needed.
4. Click "Send" to deliver your message.
5. For replies, open the email and click "Reply."

## Support

For issues or feature requests, please visit the [Mail Box Management issue queue](https://www.drupal.org/project/mail_box_management/issues).

## Maintainers

This module is actively maintained by
- **[Chance Nyasulu](https://www.drupal.org/u/chancenyasulu)**
- **[Jasjeet Brar](https://www.drupal.org/u/jasjeet-kaur-brar)**
- **[Shekhar Verma](https://www.drupal.org/u/d-xpert)**
- **[Anjali Mehta](https://www.drupal.org/u/anjali-mehta)**

Contributions, bug reports, and feature requests are welcome!

---

**Note**: This module relies on external email services. Ensure
your server and hosting environment support IMAP and SMTP protocols
to avoid configuration issues.
