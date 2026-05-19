# Filament ActiveCampaign

Advanced ActiveCampaign integration for **Laravel** and **FilamentPHP 3**, focused on developer productivity.

This package allows you to:

- Sync **Lists**, **Tags** and **Custom Fields** from ActiveCampaign  
- Create **Automations** based on **custom events** (`user.registered`, `wallet.first_deposit`, etc.)  
- Subscribe contacts to Lists  
- Add Tags  
- Update Custom Fields  
- Update System Fields (`firstName`, `lastName`, `phone`, etc.)  
- Use dynamic templating (`{user.*}`, `{ctx.*}`, `{now}`, etc.)  
- Log every automation execution (success, payload, warnings, errors)  
- Preview the execution plan before running an automation  
- Manage everything visually from FilamentPHP  

---

## 🔧 Installation

Install via Composer:

```bash
composer require xavicabot/filament-activecampaign
```

Publish config and migrations:

```bash
php artisan vendor:publish --provider="XaviCabot\FilamentActiveCampaign\ActiveCampaignServiceProvider" --tag=activecampaign-config
php artisan vendor:publish --provider="XaviCabot\FilamentActiveCampaign\ActiveCampaignServiceProvider" --tag=activecampaign-migrations

php artisan migrate
```

---

## ⚙️ Configuration

Add your ActiveCampaign credentials to `.env`:

```env
ACTIVECAMPAIGN_BASE_URL="https://YOUR_ACCOUNT.api-us1.com"
ACTIVECAMPAIGN_API_KEY="your-api-key"
```

Published config file:

```
config/activecampaign.php
```

---

## 🔄 Syncing Metadata (Lists, Tags, Fields)

This package stores ActiveCampaign metadata locally:

- `activecampaign_lists`
- `activecampaign_tags`
- `activecampaign_fields`

### Sync everything

```bash
php artisan activecampaign:sync-metadata
```

Or individually:

```bash
php artisan activecampaign:sync-metadata --lists
php artisan activecampaign:sync-metadata --tags
php artisan activecampaign:sync-metadata --fields
```

### From Filament

The Automations resource includes a **"Sync metadata"** button to refresh lists/tags/fields without CLI access.

---

## 🏷️ Creating Tags

You can create new tags either from the Filament UI or programmatically from your code.

### From Filament UI

1. Navigate to **ActiveCampaign Tags** in Filament
2. Click the **"Create Tag"** button in the header
3. Fill in the form:
   - **Tag Name** (required) - The name as it will appear in ActiveCampaign
   - **Description** (optional) - Internal reference note (only used when creating new tags)
4. Submit

**Smart behavior:** If the tag name already exists (locally or in ActiveCampaign), it will be retrieved instead of creating a duplicate. No errors, just works!

### From Code

Use the `ActiveCampaign` facade to create or retrieve tags programmatically:

#### Recommended: `getOrCreateTag()` (Safe, no duplicates)

```php
use XaviCabot\FilamentActiveCampaign\Facades\ActiveCampaign;

// Get existing tag or create if it doesn't exist
$tag = ActiveCampaign::getOrCreateTag('VIP Customer');

// With description (only used if creating)
$tag = ActiveCampaign::getOrCreateTag('Premium Member', 'Users with premium subscription');

// The returned $tag is an ActiveCampaignTag model instance
echo $tag->ac_id;       // ActiveCampaign ID
echo $tag->name;        // Tag name
echo $tag->description; // Optional description
```

**What happens with `getOrCreateTag()`:**
1. Searches local database first (case-insensitive)
2. If not found locally, searches ActiveCampaign API
3. If found in AC but not locally, syncs it to local database
4. If not found anywhere, creates it in ActiveCampaign and stores locally
5. Cache automatically updated for instant use

**Benefits:**
- ✅ No duplicate errors
- ✅ Idempotent (safe to call multiple times)
- ✅ Handles sync automatically

#### Alternative: `createTag()` (Always creates new)

```php
// Only use if you're sure the tag doesn't exist
$tag = ActiveCampaign::createTag('New Tag', 'Description');
```

**Warning:** This method will fail if the tag already exists in ActiveCampaign.

**Note:** All tags are created with type `contact` (the standard type for 99% of use cases).

---

## 🧷 Managing Contact Tags & Lookup

Use the `ActiveCampaign` facade to manage tags on a contact and to look up existing contacts.

```php
use XaviCabot\FilamentActiveCampaign\Facades\ActiveCampaign;

// Find a contact by email (returns null if it does not exist)
$contact = ActiveCampaign::getContactByEmail('jane@example.com');

// Attach one tag
ActiveCampaign::addTagToContact($contact['id'], 'vip');

// Attach several tags in one call
ActiveCampaign::addTagToContact($contact['id'], ['vip', 'beta-tester']);

// List the tag associations for a contact
$associations = ActiveCampaign::getContactTags($contact['id']);

// Detach a tag by name (no-op if it is not attached)
ActiveCampaign::removeTagFromContact($contact['id'], 'beta-tester');
```

Tag names are resolved to ActiveCampaign IDs using the same cached lookup as `addTagToContact` — make sure the tag has been synced (`php artisan activecampaign:sync-metadata --tags`) or created beforehand.

---

## ⚡ Single-Call Field Sync

When an automation has multiple `fields` and/or `system_fields`, the package now sends them in a **single** `POST /contact/sync` request (plus the initial contact resolution call) instead of one HTTP call per field. No code change is required on the consumer side — existing automations benefit automatically.

> **Behavioural note:** a custom field whose template references `{user.*}` or `{ctx.*}` and resolves to an empty value is now omitted from the payload (it used to be sent as an empty string). This mirrors how `system_fields` already behaved and prevents accidental overwrites with blank values.

---

## 📦 Filament Resources Included

| Resource | Purpose |
|---------|---------|
| **Automations** | Create automations triggered by custom events |
| **Automation Logs** | Inspect execution results, payloads, warnings, errors |
| **Lists** | Read-only view of AC lists |
| **Tags** | View and create AC tags |
| **Fields** | Read-only view of AC custom fields |

---

## ⚡ Creating Automations

An Automation links:

- a **custom event name**  
- to a set of **ActiveCampaign actions**

Actions available:

- Subscribe to a list
- Add tags
- Update custom fields
- Update system fields

Example automation:

| Setting | Value |
|--------|--------|
| Event | `user.registered` |
| List | “Main List” |
| Tags | `Customer` |
| Custom Fields | `LANGUAGE = {user.profile.language}` |
| System Fields | `firstName = {user.name}` |

---

## 🧠 Triggering Automations from Your Laravel App

Use the facade:

```php
use XaviCabot\FilamentActiveCampaign\Facades\ActiveCampaignAutomations;

ActiveCampaignAutomations::trigger('user.registered', $user);
```

With context:

```php
ActiveCampaignAutomations::trigger('wallet.first_deposit', $user, [
    'amount'   => 150,
    'currency' => 'EUR',
]);
```

With another model:

```php
ActiveCampaignAutomations::trigger('post.published', $user, [
    'post' => $post,
]);
```

### Trigger without a registered user (by email)

You can also trigger automations for contacts that are not users in your system by providing an email and optional contact data. The contact will be created/synced in ActiveCampaign if it does not exist.

Basic usage:

```php
use XaviCabot\FilamentActiveCampaign\Facades\ActiveCampaignAutomations;

ActiveCampaignAutomations::triggerWithEmail('newsletter.signup', 'john@example.com');
```

With optional contact data and context:

```php
ActiveCampaignAutomations::triggerWithEmail(
    'lead.captured',
    'jane@example.com',
    [
        'firstName' => 'Jane',
        'lastName'  => 'Doe',
        'phone'     => '+1 555 123 4567',
    ],
    [
        'source' => 'landing-123',
        'utm'    => [
            'campaign' => 'winter-sale',
        ],
    ]
);
```

Notes:
- email is required; other contact fields are optional.
- System fields defined in the automation will be synced using the provided email.
- Template placeholders {ctx.*} work as usual; {user.*} placeholders will be left as-is if no user is provided.

---

## 🔀 Async (Queue) Support

By default, automations run synchronously. You can enable async execution to dispatch triggers to a Laravel queue.

### Configuration

In your published `config/activecampaign.php`:

```php
'async'         => false,        // Set to true to dispatch all triggers to queue
'queue'         => 'default',    // Queue name
'async_tries'   => 3,            // Max retry attempts
'async_backoff' => [10, 60],     // Backoff in seconds between retries
```

Or use environment variables:

```env
ACTIVECAMPAIGN_ASYNC=true
ACTIVECAMPAIGN_QUEUE=activecampaign
```

### Usage

#### Option 1: Global async via config

Set `async => true` in config. All `trigger()` and `triggerWithEmail()` calls will automatically dispatch to the queue:

```php
// These will be queued automatically when async is enabled
ActiveCampaignAutomations::trigger('user.registered', $user);
ActiveCampaignAutomations::triggerWithEmail('newsletter.signup', 'jane@example.com');
```

#### Option 2: Explicit async methods

Use `triggerAsync()` or `triggerWithEmailAsync()` to always dispatch to the queue, regardless of the `async` config value:

```php
// Always queued, even if async config is false
ActiveCampaignAutomations::triggerAsync('user.registered', $user, ['plan' => 'pro']);

ActiveCampaignAutomations::triggerWithEmailAsync('lead.captured', 'jane@example.com', [
    'firstName' => 'Jane',
], [
    'source' => 'landing-page',
]);
```

### How it works

- The job serializes only primitive data (event name, user ID, email, contact data, context) — no Eloquent models are serialized.
- When a `userId` is provided, the job resolves the user from the database at execution time. If the user no longer exists, the job exits gracefully without errors.
- Retries and backoff are configurable via `async_tries` and `async_backoff`.

### Retrocompatibility

With `async => false` (default), the package behaves exactly as before — all triggers execute synchronously. No changes needed in existing code.

---

## 🧩 Template Engine

Templates support:

### Global

- `{now}` — current datetime  
- `{now_date}` — current date  

### User data (supports relations)

```
{user.email}
{user.name}
{user.profile.phone}
{user.profile.language.name}
```

### Context data

```
{ctx.amount}
{ctx.currency}
{ctx.post.title}
{ctx.invoice.total}
```

If a value is missing → placeholder is preserved.  
Objects/arrays → JSON encoded.

---

## 🔍 Preview Mode (For Testing Automations)

From Filament, you can **preview** an automation:

- Select a User  
- Provide JSON context  
- Preview the execution plan **without** hitting ActiveCampaign  
- See:
  - list subscription  
  - tags  
  - field updates  
  - system fields  
  - rendered templates  
  - warnings  
  - payload  

Perfect for debugging before going live.

---

## 📝 Execution Logs

Every execution is stored in:

```
activecampaign_automation_logs
```

Includes:

- automation_id  
- event  
- user_id  
- success  
- error_message  
- context (JSON)  
- payload (JSON)  
- warnings (missing tags/fields/etc.)

Filament UI offers:

- warning icons  
- JSON pretty blocks  
- full detail view  

---

## 🚀 Examples — Automation on User Registration

### 1. Create automation in Filament:

- Event: `user.registered`
- List: “Main List”
- Tags: “New User”
- Custom fields:
  - `LANGUAGE = {user.profile.language}`
- System fields:
  - `firstName = {user.name}`

### 2. Trigger from your application:

```php
use XaviCabot\FilamentActiveCampaign\Facades\ActiveCampaignAutomations;

public function register(Request $request)
{
    $user = User::create([...]);

    ActiveCampaignAutomations::trigger('user.registered', $user);

    return $user;
}
```

---

## ❤️ Contributing

PRs are welcome, especially for:

- new actions  
- templates  
- automation presets  
- improvements to the runner or logging  

---

## 📄 License

MIT License.
