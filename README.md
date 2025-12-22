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

## 📦 Filament Resources Included

| Resource | Purpose |
|---------|---------|
| **Automations** | Create automations triggered by custom events |
| **Automation Logs** | Inspect execution results, payloads, warnings, errors |
| **Lists** | Read-only view of AC lists |
| **Tags** | Read-only view of AC tags |
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
