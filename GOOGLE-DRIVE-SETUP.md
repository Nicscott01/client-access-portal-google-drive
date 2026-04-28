# Google Drive Setup

This addon uses a **Google Cloud service account JSON key**.

Do **not** paste any of these into the plugin:

- an API key
- an OAuth client ID
- an OAuth client secret

The plugin needs the path to a downloaded **service account JSON credentials file** and the ID of a **Google Drive folder** that the service account can access.

## What you need

1. A Google Cloud project
2. The Google Drive API enabled for that project
3. A service account created in that project
4. A JSON key downloaded for that service account
5. A Google Drive folder that will act as the master folder for client folders
6. The service account email added to that Drive folder with write access

## Step-by-step

### 1. Create a Google Cloud project

Create or choose a Google Cloud project that will own the Drive integration.

### 2. Enable the Google Drive API

In Google Cloud:

- go to `APIs & Services`
- enable `Google Drive API`

Without that, the service account will not be able to call Drive.

### 3. Create a service account

In Google Cloud:

- go to `IAM & Admin > Service Accounts`
- click `Create service account`
- give it a name like `client-access-portal`

Make note of the service account email. It will look something like:

`client-access-portal@your-project-id.iam.gserviceaccount.com`

### 4. Create a JSON key

Inside that service account:

- open the `Keys` tab
- click `Add key > Create new key`
- choose `JSON` 
- download the file

This downloaded JSON file is the credential the plugin needs.

Store it **outside the web root** if possible. Example:

```text
/home/your-user/secure/google/client-access-portal-service-account.json
```

### 5. Create or choose the master Drive folder

Best option: create or choose a folder inside a **Shared Drive** that will contain all client folders.

If you use a regular folder in **My Drive**, the service account may be able to read it and even create folders, but Google can block uploads/file creation with this error:

`Service Accounts do not have storage quota`

Google's Drive docs say service accounts don't have storage quota and can't own files. Their guidance is to use **shared drives** or OAuth 2.0 on behalf of a human user.

In Google Drive, create a folder that will contain all client folders, for example:

`Client Access Portal`

### 6. Share that folder with the service account

Open the folder in Google Drive and share it with the service account email from step 3.

Recommended permission:

- `Editor`

If the folder is inside a Shared Drive, use a role that allows file creation there, such as:

- `Content manager`

That gives the service account enough access to create client subfolders and upload files.

### 7. Copy the master folder ID

Open the master folder in Google Drive. The folder ID is the long string in the URL:

```text
https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUvWxYz
```

In that example, the folder ID is:

```text
1AbCdEfGhIjKlMnOpQrStUvWxYz
```

That is what goes into the plugin's `Master folder ID` setting.

### 8. Enter the settings in WordPress

In the addon settings:

- `Credentials path`: absolute server path to the downloaded JSON file
- `Master folder ID`: the Drive folder ID from step 7
- `Review folder name`: usually `Uploads for Review`

Optional:

- set `CLIENT_ACCESS_PORTAL_GOOGLE_DRIVE_CREDENTIALS_PATH` in environment-specific config to override the saved credentials path

Example:

```php
define( 'CLIENT_ACCESS_PORTAL_GOOGLE_DRIVE_CREDENTIALS_PATH', '/home/your-user/secure/google/client-access-portal-service-account.json' );
```

## Common mistakes

### “I have an API key”

That is not the right credential for this plugin.

### “I created an OAuth web app”

That is also not the right credential for this plugin.

### “The JSON key works, but the plugin still can’t see the folder”

Usually that means the Drive folder was **not shared with the service account email**.

### “My org won’t let me create a JSON key”

Some Google organizations block service account key creation by policy. If that happens, your Google Workspace / Cloud admin will need to allow key creation for this project or provide an approved alternative deployment model.

## What the plugin is expecting

The addon is built around server-to-server authentication. Concretely, it expects:

- service account email exists
- JSON private key exists on disk
- Drive API is enabled
- master folder is shared to the service account

If all four are true, then the addon can provision client folders and sync against Drive.

For **uploads and new file creation**, also make sure one of these is true:

- the master folder is in a Shared Drive
- or you use OAuth delegation instead of a plain service account upload model

## References

- Google Workspace: Create access credentials
- Google Drive: Enable the Drive API
- Google Drive: Share files, folders, and drives
- Google Cloud IAM: Service account credentials
