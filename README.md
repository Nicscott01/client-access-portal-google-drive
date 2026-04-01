# Client Access Portal - Google Drive

Google Drive storage-provider addon for the Client Access Portal core plugin.

This addon registers a `google-drive` provider, provisions client folders in Drive, exposes approved files and pending uploads in the frontend portal, and persists file notes/captions in Google Drive metadata.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- The core `Client Access Portal` plugin must be active
- A Google Cloud service account with Drive API access

## What It Adds

- Registers the Google Drive storage provider with the core plugin
- Automatically provisions:
  - a root client folder
  - a review/uploads folder
- Lists approved files in the frontend portal
- Sends client uploads into the review folder
- Supports file-note persistence and editing
- Adds Google Drive-specific settings and connection testing to the core settings screen
- Adds client-level Drive provisioning/recovery tools
- Adds quick-access Google Drive folder links on the core client edit screen

## Settings

This addon stores its settings alongside the core plugin settings screen.

Current Google Drive settings include:

- credentials path
- master folder ID
- review folder name
- sync interval minutes
- alert email
- upload notification toggle
- notification recipient

## Authentication Model

This addon is built around a Google Cloud service account JSON key on disk.

It does not use:

- API keys
- OAuth web app credentials

## Setup

Start with the detailed setup guide:

- [GOOGLE-DRIVE-SETUP.md](/Users/nscott/web_repos/somuchtosay/web/app/plugins/client-access-portal-google-drive/GOOGLE-DRIVE-SETUP.md)

High-level flow:

1. Create a Google Cloud project.
2. Enable the Google Drive API.
3. Create a service account.
4. Download a JSON key.
5. Share the master Drive folder with the service account.
6. Save the credentials path and master folder ID in WordPress.
7. Assign `Google Drive` as the client’s file-storage provider.

## Notes

- Shared Drives are the safest default for uploads and folder ownership behavior.
- The addon detects the active core plugin by runtime constants, so either the release folder or a `-dev` folder can satisfy the dependency as long as only one core build is active.

## Related Docs

- [GOOGLE-DRIVE-SETUP.md](/Users/nscott/web_repos/somuchtosay/web/app/plugins/client-access-portal-google-drive/GOOGLE-DRIVE-SETUP.md)
- [BUILD-CHECKLIST.md](/Users/nscott/web_repos/somuchtosay/web/app/plugins/client-access-portal-google-drive/BUILD-CHECKLIST.md)
